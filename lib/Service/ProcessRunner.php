<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

use OCA\FilesWatermark\Exception\ProcessTimeoutException;
use RuntimeException;

class ProcessRunner {
	private const OUTPUT_LIMIT = 65536;
	private const STDERR_LIMIT = 16384;

	/**
	 * @param non-empty-list<string> $command
	 */
	public function run(array $command, string $stdin, int $timeoutSeconds): ProcessResult {
		if (!function_exists('proc_open')) {
			throw new RuntimeException('The proc_open function is unavailable.');
		}

		$descriptorSpec = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$pipes = [];
		$process = @proc_open(
			$command,
			$descriptorSpec,
			$pipes,
			null,
			null,
			['bypass_shell' => true],
		);

		if (!is_resource($process)) {
			throw new RuntimeException('The renderer process could not be started.');
		}

		try {
			$this->writeInput($pipes[0], $stdin);
			fclose($pipes[0]);
			stream_set_blocking($pipes[1], false);
			stream_set_blocking($pipes[2], false);

			$stdout = '';
			$stderr = '';
			$startedAt = microtime(true);
			$exitCode = -1;

			while (true) {
				$status = proc_get_status($process);
				$this->drainPipes($pipes[1], $pipes[2], $stdout, $stderr);

				if (!$status['running']) {
					$exitCode = (int)$status['exitcode'];
					break;
				}

				if (microtime(true) - $startedAt >= $timeoutSeconds) {
					$this->terminate($process);
					throw new ProcessTimeoutException(sprintf(
						'Renderer exceeded the %d second timeout.',
						$timeoutSeconds,
					));
				}

				usleep(20000);
			}

			$this->drainPipes($pipes[1], $pipes[2], $stdout, $stderr, true);
			return new ProcessResult($exitCode, $stdout, $stderr);
		} finally {
			foreach ($pipes as $pipe) {
				if (is_resource($pipe)) {
					fclose($pipe);
				}
			}
			proc_close($process);
		}
	}

	/** @param resource $pipe */
	private function writeInput($pipe, string $input): void {
		$length = strlen($input);
		$written = 0;
		while ($written < $length) {
			$count = fwrite($pipe, substr($input, $written));
			if ($count === false || $count === 0) {
				throw new RuntimeException('Could not send configuration to the renderer.');
			}
			$written += $count;
		}
	}

	/**
	 * @param resource $stdoutPipe
	 * @param resource $stderrPipe
	 */
	private function drainPipes(
		$stdoutPipe,
		$stderrPipe,
		string &$stdout,
		string &$stderr,
		bool $untilEof = false,
	): void {
		do {
			$read = [];
			if (!feof($stdoutPipe)) {
				$read[] = $stdoutPipe;
			}
			if (!feof($stderrPipe)) {
				$read[] = $stderrPipe;
			}
			if ($read === []) {
				return;
			}

			$write = null;
			$except = null;
			$selected = @stream_select($read, $write, $except, 0, $untilEof ? 10000 : 0);
			if ($selected === false || $selected === 0) {
				return;
			}

			foreach ($read as $pipe) {
				$chunk = stream_get_contents($pipe, 8192);
				if ($chunk === false || $chunk === '') {
					continue;
				}
				if ($pipe === $stdoutPipe) {
					$stdout = substr($stdout . $chunk, 0, self::OUTPUT_LIMIT);
				} else {
					$stderr = substr($stderr . $chunk, -self::STDERR_LIMIT);
				}
			}
		} while ($untilEof);
	}

	/** @param resource $process */
	private function terminate($process): void {
		@proc_terminate($process, 15);
		$deadline = microtime(true) + 0.25;
		do {
			$status = proc_get_status($process);
			if (!$status['running']) {
				return;
			}
			usleep(10000);
		} while (microtime(true) < $deadline);

		@proc_terminate($process, 9);
	}
}
