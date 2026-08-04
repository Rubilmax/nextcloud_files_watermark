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

	public function render(string $inputPath, string $outputPath, string $text, string $watermarkId): void {
		if (preg_match('/^[0-9a-f]{64}$/', $watermarkId) !== 1) {
			throw new WatermarkException(
				'Watermark identifier must contain exactly 256 bits.',
				'invalid_render_request',
				Http::STATUS_BAD_REQUEST,
			);
		}
		$this->renderConfigured(
			$inputPath,
			$outputPath,
			$text,
			$this->config->getWatermarkFontSize(),
			$this->config->getWatermarkColor(),
			$this->config->getWatermarkOpacityPercent(),
			$this->config->getWatermarkAngleDegrees(),
			$this->config->getWatermarkMinimumHorizontalInterval(),
			$this->config->getWatermarkHorizontalGap(),
			$this->config->getWatermarkVerticalInterval(),
			$watermarkId,
			$this->config->isPixelSealEnabled(),
		);
	}

	public function renderPreview(
		string $inputPath,
		string $outputPath,
		string $text,
		?int $fontSize,
		?string $color,
		?int $opacity,
		?int $angle,
		?int $minimumHorizontalInterval,
		?int $horizontalGap,
		?int $verticalInterval,
		?string $previewImagePath = null,
	): void {
		$fontSize ??= $this->config->getWatermarkFontSize();
		$color = $color === null ? $this->config->getWatermarkColor() : strtolower(trim($color));
		$opacity ??= $this->config->getWatermarkOpacityPercent();
		$angle ??= $this->config->getWatermarkAngleDegrees();
		$minimumHorizontalInterval ??= $this->config->getWatermarkMinimumHorizontalInterval();
		$horizontalGap ??= $this->config->getWatermarkHorizontalGap();
		$verticalInterval ??= $this->config->getWatermarkVerticalInterval();

		if ($fontSize < 8 || $fontSize > 144
			|| preg_match('/^#[0-9a-f]{6}$/', $color) !== 1
			|| $opacity < 1 || $opacity > 100
			|| $angle < -180 || $angle > 180
			|| $minimumHorizontalInterval < 20 || $minimumHorizontalInterval > 2000
			|| $horizontalGap < 0 || $horizontalGap > 1000
			|| $verticalInterval < 20 || $verticalInterval > 2000) {
			throw new WatermarkException(
				'Invalid watermark preview appearance settings.',
				'invalid_render_request',
				Http::STATUS_BAD_REQUEST,
			);
		}

		$this->renderConfigured(
			$inputPath,
			$outputPath,
			$text,
			$fontSize,
			$color,
			$opacity,
			$angle,
			$minimumHorizontalInterval,
			$horizontalGap,
			$verticalInterval,
			hash('sha256', 'files-watermark-admin-preview'),
			false,
			$previewImagePath,
		);
	}

	private function renderConfigured(
		string $inputPath,
		string $outputPath,
		string $text,
		int $fontSize,
		string $color,
		int $opacity,
		int $angle,
		int $minimumHorizontalInterval,
		int $horizontalGap,
		int $verticalInterval,
		string $watermarkId,
		bool $pixelSealEnabled,
		?string $previewImagePath = null,
	): void {
		try {
			$input = json_encode([
				'text' => $text,
				'dpi' => $this->config->getRasterDpi(),
				'maxPages' => $this->config->getMaximumPages(),
				'jpegQuality' => 88,
				'watermarkFontSize' => $fontSize,
				'watermarkColor' => $color,
				'watermarkOpacityPercent' => $opacity,
				'watermarkAngle' => $angle,
				'watermarkMinimumHorizontalInterval' => $minimumHorizontalInterval,
				'watermarkHorizontalGap' => $horizontalGap,
				'watermarkVerticalInterval' => $verticalInterval,
				'watermarkOpacityVariationPercent' => $this->config->getWatermarkOpacityVariationPercent(),
				'watermarkSpacingVariationPercent' => $this->config->getWatermarkSpacingVariationPercent(),
				'watermarkPositionJitterPoints' => $this->config->getWatermarkPositionJitterPoints(),
				'watermarkBlurRadiusPixels' => $this->config->getWatermarkBlurRadiusPixels(),
				'watermarkBlurOpacityPercent' => $this->config->getWatermarkBlurOpacityPercent(),
				'watermarkDistortionEnabled' => $this->config->isWatermarkDistortionEnabled(),
				'watermarkDistortionStrengthPixels' => $this->config->getWatermarkDistortionStrengthPixels(),
				'randomSeed' => $watermarkId,
				'pixelSealEnabled' => $pixelSealEnabled,
				'pixelSealMessage' => $pixelSealEnabled ? $watermarkId : null,
				'pixelSealModelPath' => $this->config->getPixelSealModelPath(),
				'pixelSealStrengthPercent' => $this->config->getPixelSealStrengthPercent(),
				'pixelSealDevice' => $this->config->getPixelSealDevice(),
			], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
			$command = [
				$this->config->getPythonExecutable(),
				$this->getScriptPath(),
				$inputPath,
				$outputPath,
			];
			if ($previewImagePath !== null) {
				$command[] = $previewImagePath;
			}
			$result = $this->processRunner->run(
				$command,
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

		$hasPreviewImage = $previewImagePath === null
			|| (is_file($previewImagePath) && filesize($previewImagePath) > 0);
		if ($result->exitCode === 0 && is_file($outputPath) && filesize($outputPath) > 0 && $hasPreviewImage) {
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
			$probe = <<<'PYTHON'
import json
import os
import sys

import numpy
import pymupdf
from PIL import __version__ as pillow_version

status = {
    "pymupdf": pymupdf.__version__,
    "numpy": numpy.__version__,
    "pillow": pillow_version,
    "pixelSeal": False,
}
if sys.argv[2] == "1":
    import torch
    import videoseal
    from omegaconf import OmegaConf
    from videoseal.utils.cfg import setup_model

    if not os.path.isfile(sys.argv[1]) or not os.access(sys.argv[1], os.R_OK):
        raise FileNotFoundError(f"PixelSeal checkpoint is unavailable: {sys.argv[1]}")
    if sys.argv[3] == "cuda" and not torch.cuda.is_available():
        raise RuntimeError("PixelSeal CUDA device is unavailable.")
    if sys.argv[3] == "mps" and (
        not hasattr(torch.backends, "mps") or not torch.backends.mps.is_available()
    ):
        raise RuntimeError("PixelSeal MPS device is unavailable.")
    status["pixelSeal"] = True
    status["torch"] = torch.__version__
    status["videoseal"] = getattr(videoseal, "__version__", "unknown")
print(json.dumps(status, separators=(",", ":")))
PYTHON;
			$result = $this->processRunner->run([
				$this->config->getPythonExecutable(),
				'-c',
				$probe,
				$this->config->getPixelSealModelPath(),
				$this->config->isPixelSealEnabled() ? '1' : '0',
				$this->config->getPixelSealDevice(),
			], '', 10);
		} catch (\Throwable $exception) {
			return ['available' => false, 'message' => $exception->getMessage()];
		}

		try {
			$status = json_decode(trim($result->stdout), true, flags: JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$status = null;
		}
		$version = is_array($status) && isset($status['pymupdf']) && is_string($status['pymupdf'])
			? $status['pymupdf']
			: '';
		$pixelSealAvailable = is_array($status) && ($status['pixelSeal'] ?? false) === true;
		if ($result->exitCode !== 0 || $version === ''
			|| ($this->config->isPixelSealEnabled() && !$pixelSealAvailable)) {
			return [
				'available' => false,
				'message' => $this->config->isPixelSealEnabled()
					? sprintf(
						'Configured Python cannot load PixelSeal, read its checkpoint at %s, or use device %s.',
						$this->config->getPixelSealModelPath(),
						$this->config->getPixelSealDevice(),
					)
					: 'Configured Python cannot import the renderer dependencies.',
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
			'message' => $this->config->isPixelSealEnabled()
				? sprintf('PyMuPDF %s and PixelSeal are available.', $version)
				: sprintf('PyMuPDF %s is available; PixelSeal is disabled.', $version),
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
