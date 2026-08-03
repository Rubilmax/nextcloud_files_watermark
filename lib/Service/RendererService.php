<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

use JsonException;
use OCA\FilesWatermark\Exception\ProcessTimeoutException;
use OCA\FilesWatermark\Exception\WatermarkException;
use OCP\AppFramework\Http;
use RuntimeException;

final class RendererService {
	public const MINIMUM_PYMUPDF_VERSION = '1.28.0';
	public const MAXIMUM_PYMUPDF_VERSION = '1.29.0';

	private const EXIT_ENCRYPTED = 10;
	private const EXIT_MALFORMED = 11;
	private const EXIT_PAGE_LIMIT = 12;
	private const EXIT_BAD_REQUEST = 13;
	private const EXIT_PAGE_TOO_LARGE = 14;
	private const EXIT_DEPENDENCY = 20;

	public function __construct(
		private readonly ConfigService $config,
		private readonly ProcessRunner $processRunner,
	) {
	}

	public function render(string $inputPath, string $outputPath, string $text): void {
		try {
			$input = json_encode([
				'text' => $text,
				'dpi' => $this->config->getRasterDpi(),
				'maxPages' => $this->config->getMaximumPages(),
				'jpegQuality' => 88,
				'watermarkFontSize' => $this->config->getWatermarkFontSize(),
				'watermarkColor' => $this->config->getWatermarkColor(),
				'watermarkOpacityPercent' => $this->config->getWatermarkOpacityPercent(),
				'watermarkAngle' => $this->config->getWatermarkAngleDegrees(),
				'watermarkMinimumHorizontalInterval' => $this->config->getWatermarkMinimumHorizontalInterval(),
				'watermarkHorizontalGap' => $this->config->getWatermarkHorizontalGap(),
				'watermarkVerticalInterval' => $this->config->getWatermarkVerticalInterval(),
			], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
			$result = $this->processRunner->run(
				[
					$this->config->getPythonExecutable(),
					$this->getScriptPath(),
					$inputPath,
					$outputPath,
				],
				$input,
				$this->config->getTimeoutSeconds(),
			);
		} catch (ProcessTimeoutException $exception) {
			throw new WatermarkException(
				'Rendering timed out. The source may be too complex for the configured limit.',
				'render_timeout',
				Http::STATUS_GATEWAY_TIMEOUT,
				$exception,
			);
		} catch (JsonException|RuntimeException $exception) {
			throw new WatermarkException(
				'The local PDF renderer is unavailable.',
				'renderer_unavailable',
				Http::STATUS_SERVICE_UNAVAILABLE,
				$exception,
			);
		}

		if ($result->exitCode === 0 && is_file($outputPath) && filesize($outputPath) > 0) {
			return;
		}

		$message = $this->readRendererMessage($result->stderr);
		throw match ($result->exitCode) {
			self::EXIT_ENCRYPTED => new WatermarkException(
				$message ?? 'Password-encrypted PDFs are not supported.',
				'encrypted_pdf',
				Http::STATUS_UNPROCESSABLE_ENTITY,
			),
			self::EXIT_MALFORMED => new WatermarkException(
				$message ?? 'The PDF is malformed or cannot be rendered.',
				'malformed_pdf',
				Http::STATUS_UNPROCESSABLE_ENTITY,
			),
			self::EXIT_PAGE_LIMIT => new WatermarkException(
				$message ?? 'The PDF exceeds the configured page limit.',
				'page_limit_exceeded',
				Http::STATUS_UNPROCESSABLE_ENTITY,
			),
			self::EXIT_BAD_REQUEST => new WatermarkException(
				$message ?? 'The renderer rejected the request.',
				'invalid_render_request',
				Http::STATUS_BAD_REQUEST,
			),
			self::EXIT_PAGE_TOO_LARGE => new WatermarkException(
				$message ?? 'A PDF page is too large to rasterize safely.',
				'page_too_large',
				Http::STATUS_UNPROCESSABLE_ENTITY,
			),
			self::EXIT_DEPENDENCY => new WatermarkException(
				$message ?? 'PyMuPDF 1.28.x is unavailable.',
				'renderer_unavailable',
				Http::STATUS_SERVICE_UNAVAILABLE,
			),
			126, 127 => new WatermarkException(
				'The configured Python executable could not be started.',
				'renderer_unavailable',
				Http::STATUS_SERVICE_UNAVAILABLE,
			),
			default => new WatermarkException(
				$message ?? 'The PDF renderer failed.',
				'render_failed',
				Http::STATUS_INTERNAL_SERVER_ERROR,
			),
		};
	}

	/** @return array{available: bool, message: string, version?: string} */
	public function checkAvailability(): array {
		if (!function_exists('proc_open')) {
			return ['available' => false, 'message' => 'PHP proc_open is disabled.'];
		}

		try {
			$result = $this->processRunner->run([
				$this->config->getPythonExecutable(),
				'-c',
				'import pymupdf; print(pymupdf.__version__)',
			], '', 10);
		} catch (\Throwable $exception) {
			return ['available' => false, 'message' => $exception->getMessage()];
		}

		$version = trim($result->stdout);
		if ($result->exitCode !== 0 || $version === '') {
			return [
				'available' => false,
				'message' => $this->readRendererMessage($result->stderr) ?? 'Configured Python cannot import PyMuPDF.',
			];
		}

		if (version_compare($version, self::MINIMUM_PYMUPDF_VERSION, '<')
			|| version_compare($version, self::MAXIMUM_PYMUPDF_VERSION, '>=')) {
			return [
				'available' => false,
				'message' => sprintf('PyMuPDF %s is outside the supported >=1.28.0,<1.29.0 range.', $version),
				'version' => $version,
			];
		}

		return [
			'available' => true,
			'message' => sprintf('PyMuPDF %s is available.', $version),
			'version' => $version,
		];
	}

	private function getScriptPath(): string {
		return dirname(__DIR__, 2) . '/scripts/rasterize.py';
	}

	private function readRendererMessage(string $stderr): ?string {
		$lines = preg_split('/\R/', trim($stderr)) ?: [];
		foreach (array_reverse($lines) as $line) {
			try {
				$data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
				if (is_array($data) && isset($data['message']) && is_string($data['message'])) {
					return $data['message'];
				}
			} catch (JsonException) {
				continue;
			}
		}

		return null;
	}
}
