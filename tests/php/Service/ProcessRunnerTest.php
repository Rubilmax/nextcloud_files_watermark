<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Service;

use OCA\FilesWatermark\Exception\ProcessTimeoutException;
use OCA\FilesWatermark\Service\ProcessRunner;
use PHPUnit\Framework\TestCase;

final class ProcessRunnerTest extends TestCase {
	public function testUsesArgumentArrayWithoutShellInterpolation(): void {
		$result = (new ProcessRunner())->run(
			[PHP_BINARY, '-r', 'echo $argv[1];', '$(printf unsafe)'],
			'',
			5,
		);

		self::assertSame(0, $result->exitCode);
		self::assertSame('$(printf unsafe)', $result->stdout);
	}

	public function testBoundsStderrAndPreservesFailureCode(): void {
		$result = (new ProcessRunner())->run(
			[PHP_BINARY, '-r', 'fwrite(STDERR, str_repeat("x", 50000)); exit(7);'],
			'',
			5,
		);

		self::assertSame(7, $result->exitCode);
		self::assertSame(16384, strlen($result->stderr));
	}

	public function testTerminatesAtTimeout(): void {
		$this->expectException(ProcessTimeoutException::class);
		(new ProcessRunner())->run(
			[PHP_BINARY, '-r', 'usleep(3000000);'],
			'',
			1,
		);
	}
}
