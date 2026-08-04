<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

use InvalidArgumentException;
use OCA\FilesWatermark\AppInfo\Application;
use OCP\AppFramework\Services\IAppConfig;
use Psr\Log\LoggerInterface;

final class ConfigService {
	public const KEY_PYTHON = 'python_executable';
	public const KEY_DPI = 'raster_dpi';
	public const KEY_MAX_SOURCE_MIB = 'maximum_source_size_mib';
	public const KEY_MAX_PAGES = 'maximum_pages';
	public const KEY_TIMEOUT = 'timeout_seconds';
	public const KEY_WATERMARK_FONT_SIZE = 'watermark_font_size_points';
	public const KEY_WATERMARK_COLOR = 'watermark_color';
	public const KEY_WATERMARK_OPACITY = 'watermark_opacity_percent';
	public const KEY_WATERMARK_ANGLE = 'watermark_angle_degrees';
	public const KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL = 'watermark_minimum_horizontal_interval_points';
	public const KEY_WATERMARK_HORIZONTAL_GAP = 'watermark_horizontal_gap_points';
	public const KEY_WATERMARK_VERTICAL_INTERVAL = 'watermark_vertical_interval_points';
	public const KEY_WATERMARK_OPACITY_VARIATION = 'watermark_opacity_variation_percent';
	public const KEY_WATERMARK_SPACING_VARIATION = 'watermark_spacing_variation_percent';
	public const KEY_WATERMARK_POSITION_JITTER = 'watermark_position_jitter_points';
	public const KEY_WATERMARK_BLUR_RADIUS = 'watermark_blur_radius_pixels';
	public const KEY_WATERMARK_BLUR_OPACITY = 'watermark_blur_opacity_percent';
	public const KEY_WATERMARK_DISTORTION_ENABLED = 'watermark_distortion_enabled';
	public const KEY_WATERMARK_DISTORTION_STRENGTH = 'watermark_distortion_strength_pixels';
	public const KEY_PIXEL_SEAL_ENABLED = 'pixel_seal_enabled';
	public const KEY_PIXEL_SEAL_MODEL_PATH = 'pixel_seal_model_path';
	public const KEY_PIXEL_SEAL_STRENGTH = 'pixel_seal_strength_percent';
	public const KEY_PIXEL_SEAL_DEVICE = 'pixel_seal_device';

	public const DEFAULT_PYTHON = '/opt/files-watermark-python/bin/python';
	public const DEFAULT_DPI = 180;
	public const DEFAULT_MAX_SOURCE_MIB = 50;
	public const DEFAULT_MAX_PAGES = 200;
	public const DEFAULT_TIMEOUT = 120;
	public const DEFAULT_WATERMARK_FONT_SIZE = 28;
	public const DEFAULT_WATERMARK_COLOR = '#333333';
	public const DEFAULT_WATERMARK_OPACITY = 30;
	public const DEFAULT_WATERMARK_ANGLE = 30;
	public const DEFAULT_WATERMARK_MIN_HORIZONTAL_INTERVAL = 145;
	public const DEFAULT_WATERMARK_HORIZONTAL_GAP = 48;
	public const DEFAULT_WATERMARK_VERTICAL_INTERVAL = 78;
	public const DEFAULT_WATERMARK_OPACITY_VARIATION = 5;
	public const DEFAULT_WATERMARK_SPACING_VARIATION = 10;
	public const DEFAULT_WATERMARK_POSITION_JITTER = 8;
	public const DEFAULT_WATERMARK_BLUR_RADIUS = 6;
	public const DEFAULT_WATERMARK_BLUR_OPACITY = 80;
	public const DEFAULT_WATERMARK_DISTORTION_ENABLED = false;
	public const DEFAULT_WATERMARK_DISTORTION_STRENGTH = 12;
	public const DEFAULT_PIXEL_SEAL_ENABLED = true;
	public const DEFAULT_PIXEL_SEAL_MODEL_PATH = '/opt/files-watermark-python/models/pixelseal.pth';
	public const DEFAULT_PIXEL_SEAL_STRENGTH = 20;
	public const DEFAULT_PIXEL_SEAL_DEVICE = 'auto';

	/** @var array<string, array{int, int, int}> */
	private const INTEGER_SETTINGS = [
		self::KEY_DPI => [self::DEFAULT_DPI, 96, 300],
		self::KEY_MAX_SOURCE_MIB => [self::DEFAULT_MAX_SOURCE_MIB, 1, 1024],
		self::KEY_MAX_PAGES => [self::DEFAULT_MAX_PAGES, 1, 5000],
		self::KEY_TIMEOUT => [self::DEFAULT_TIMEOUT, 10, 3600],
		self::KEY_WATERMARK_FONT_SIZE => [self::DEFAULT_WATERMARK_FONT_SIZE, 8, 144],
		self::KEY_WATERMARK_OPACITY => [self::DEFAULT_WATERMARK_OPACITY, 1, 100],
		self::KEY_WATERMARK_ANGLE => [self::DEFAULT_WATERMARK_ANGLE, -180, 180],
		self::KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL => [self::DEFAULT_WATERMARK_MIN_HORIZONTAL_INTERVAL, 20, 2000],
		self::KEY_WATERMARK_HORIZONTAL_GAP => [self::DEFAULT_WATERMARK_HORIZONTAL_GAP, 0, 1000],
		self::KEY_WATERMARK_VERTICAL_INTERVAL => [self::DEFAULT_WATERMARK_VERTICAL_INTERVAL, 20, 2000],
		self::KEY_WATERMARK_OPACITY_VARIATION => [self::DEFAULT_WATERMARK_OPACITY_VARIATION, 0, 50],
		self::KEY_WATERMARK_SPACING_VARIATION => [self::DEFAULT_WATERMARK_SPACING_VARIATION, 0, 40],
		self::KEY_WATERMARK_POSITION_JITTER => [self::DEFAULT_WATERMARK_POSITION_JITTER, 0, 100],
		self::KEY_WATERMARK_BLUR_RADIUS => [self::DEFAULT_WATERMARK_BLUR_RADIUS, 0, 64],
		self::KEY_WATERMARK_BLUR_OPACITY => [self::DEFAULT_WATERMARK_BLUR_OPACITY, 0, 100],
		self::KEY_WATERMARK_DISTORTION_STRENGTH => [self::DEFAULT_WATERMARK_DISTORTION_STRENGTH, 0, 128],
		self::KEY_PIXEL_SEAL_STRENGTH => [self::DEFAULT_PIXEL_SEAL_STRENGTH, 1, 100],
	];

	/** @var array<string, bool> */
	private const BOOLEAN_SETTINGS = [
		self::KEY_WATERMARK_DISTORTION_ENABLED => self::DEFAULT_WATERMARK_DISTORTION_ENABLED,
		self::KEY_PIXEL_SEAL_ENABLED => self::DEFAULT_PIXEL_SEAL_ENABLED,
	];

	private const PIXEL_SEAL_DEVICES = ['auto', 'cpu', 'cuda', 'mps'];

	/** @var array<string, true> */
	private array $warnedKeys = [];

	public function __construct(
		private readonly IAppConfig $config,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getPythonExecutable(): string {
		$value = trim($this->config->getAppValueString(
			self::KEY_PYTHON,
			self::DEFAULT_PYTHON,
		));

		if ($value === '' || str_contains($value, "\0")) {
			$this->warnInvalid(self::KEY_PYTHON, $value, self::DEFAULT_PYTHON);
			return self::DEFAULT_PYTHON;
		}

		return $value;
	}

	public function getRasterDpi(): int {
		return $this->getBoundedInt(self::KEY_DPI, self::DEFAULT_DPI, 96, 300);
	}

	public function getMaximumSourceSizeMiB(): int {
		return $this->getBoundedInt(
			self::KEY_MAX_SOURCE_MIB,
			self::DEFAULT_MAX_SOURCE_MIB,
			1,
			1024,
		);
	}

	public function getMaximumSourceSizeBytes(): int {
		return $this->getMaximumSourceSizeMiB() * 1024 * 1024;
	}

	public function getMaximumPages(): int {
		return $this->getBoundedInt(
			self::KEY_MAX_PAGES,
			self::DEFAULT_MAX_PAGES,
			1,
			5000,
		);
	}

	public function getTimeoutSeconds(): int {
		return $this->getBoundedInt(
			self::KEY_TIMEOUT,
			self::DEFAULT_TIMEOUT,
			10,
			3600,
		);
	}

	public function getWatermarkFontSize(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_FONT_SIZE,
			self::DEFAULT_WATERMARK_FONT_SIZE,
			8,
			144,
		);
	}

	public function getWatermarkColor(): string {
		$value = strtolower(trim($this->config->getAppValueString(
			self::KEY_WATERMARK_COLOR,
			self::DEFAULT_WATERMARK_COLOR,
		)));

		if (preg_match('/^#[0-9a-f]{6}$/', $value) !== 1) {
			$this->warnInvalid(self::KEY_WATERMARK_COLOR, $value, self::DEFAULT_WATERMARK_COLOR);
			return self::DEFAULT_WATERMARK_COLOR;
		}

		return $value;
	}

	public function getWatermarkOpacityPercent(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_OPACITY,
			self::DEFAULT_WATERMARK_OPACITY,
			1,
			100,
		);
	}

	public function getWatermarkAngleDegrees(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_ANGLE,
			self::DEFAULT_WATERMARK_ANGLE,
			-180,
			180,
		);
	}

	public function getWatermarkMinimumHorizontalInterval(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL,
			self::DEFAULT_WATERMARK_MIN_HORIZONTAL_INTERVAL,
			20,
			2000,
		);
	}

	public function getWatermarkHorizontalGap(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_HORIZONTAL_GAP,
			self::DEFAULT_WATERMARK_HORIZONTAL_GAP,
			0,
			1000,
		);
	}

	public function getWatermarkVerticalInterval(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_VERTICAL_INTERVAL,
			self::DEFAULT_WATERMARK_VERTICAL_INTERVAL,
			20,
			2000,
		);
	}

	public function getWatermarkOpacityVariationPercent(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_OPACITY_VARIATION,
			self::DEFAULT_WATERMARK_OPACITY_VARIATION,
			0,
			50,
		);
	}

	public function getWatermarkSpacingVariationPercent(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_SPACING_VARIATION,
			self::DEFAULT_WATERMARK_SPACING_VARIATION,
			0,
			40,
		);
	}

	public function getWatermarkPositionJitterPoints(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_POSITION_JITTER,
			self::DEFAULT_WATERMARK_POSITION_JITTER,
			0,
			100,
		);
	}

	public function getWatermarkBlurRadiusPixels(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_BLUR_RADIUS,
			self::DEFAULT_WATERMARK_BLUR_RADIUS,
			0,
			64,
		);
	}

	public function getWatermarkBlurOpacityPercent(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_BLUR_OPACITY,
			self::DEFAULT_WATERMARK_BLUR_OPACITY,
			0,
			100,
		);
	}

	public function isWatermarkDistortionEnabled(): bool {
		return $this->getBoolean(self::KEY_WATERMARK_DISTORTION_ENABLED, self::DEFAULT_WATERMARK_DISTORTION_ENABLED);
	}

	public function getWatermarkDistortionStrengthPixels(): int {
		return $this->getBoundedInt(
			self::KEY_WATERMARK_DISTORTION_STRENGTH,
			self::DEFAULT_WATERMARK_DISTORTION_STRENGTH,
			0,
			128,
		);
	}

	public function isPixelSealEnabled(): bool {
		return $this->getBoolean(self::KEY_PIXEL_SEAL_ENABLED, self::DEFAULT_PIXEL_SEAL_ENABLED);
	}

	public function getPixelSealModelPath(): string {
		$value = trim($this->config->getAppValueString(
			self::KEY_PIXEL_SEAL_MODEL_PATH,
			self::DEFAULT_PIXEL_SEAL_MODEL_PATH,
		));
		if ($value === '' || str_contains($value, "\0") || !str_starts_with($value, '/')) {
			$this->warnInvalid(self::KEY_PIXEL_SEAL_MODEL_PATH, $value, self::DEFAULT_PIXEL_SEAL_MODEL_PATH);
			return self::DEFAULT_PIXEL_SEAL_MODEL_PATH;
		}
		return $value;
	}

	public function getPixelSealStrengthPercent(): int {
		return $this->getBoundedInt(
			self::KEY_PIXEL_SEAL_STRENGTH,
			self::DEFAULT_PIXEL_SEAL_STRENGTH,
			1,
			100,
		);
	}

	public function getPixelSealDevice(): string {
		$value = strtolower(trim($this->config->getAppValueString(
			self::KEY_PIXEL_SEAL_DEVICE,
			self::DEFAULT_PIXEL_SEAL_DEVICE,
		)));
		if (!in_array($value, self::PIXEL_SEAL_DEVICES, true)) {
			$this->warnInvalid(self::KEY_PIXEL_SEAL_DEVICE, $value, self::DEFAULT_PIXEL_SEAL_DEVICE);
			return self::DEFAULT_PIXEL_SEAL_DEVICE;
		}
		return $value;
	}

	/** @return array<string, string> */
	public function getAdminSettings(): array {
		return [
			self::KEY_PYTHON => $this->getPythonExecutable(),
			self::KEY_DPI => (string)$this->getRasterDpi(),
			self::KEY_WATERMARK_FONT_SIZE => (string)$this->getWatermarkFontSize(),
			self::KEY_WATERMARK_COLOR => $this->getWatermarkColor(),
			self::KEY_WATERMARK_OPACITY => (string)$this->getWatermarkOpacityPercent(),
			self::KEY_WATERMARK_ANGLE => (string)$this->getWatermarkAngleDegrees(),
			self::KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL => (string)$this->getWatermarkMinimumHorizontalInterval(),
			self::KEY_WATERMARK_HORIZONTAL_GAP => (string)$this->getWatermarkHorizontalGap(),
			self::KEY_WATERMARK_VERTICAL_INTERVAL => (string)$this->getWatermarkVerticalInterval(),
			self::KEY_WATERMARK_OPACITY_VARIATION => (string)$this->getWatermarkOpacityVariationPercent(),
			self::KEY_WATERMARK_SPACING_VARIATION => (string)$this->getWatermarkSpacingVariationPercent(),
			self::KEY_WATERMARK_POSITION_JITTER => (string)$this->getWatermarkPositionJitterPoints(),
			self::KEY_WATERMARK_BLUR_RADIUS => (string)$this->getWatermarkBlurRadiusPixels(),
			self::KEY_WATERMARK_BLUR_OPACITY => (string)$this->getWatermarkBlurOpacityPercent(),
			self::KEY_WATERMARK_DISTORTION_ENABLED => $this->isWatermarkDistortionEnabled() ? '1' : '0',
			self::KEY_WATERMARK_DISTORTION_STRENGTH => (string)$this->getWatermarkDistortionStrengthPixels(),
			self::KEY_PIXEL_SEAL_ENABLED => $this->isPixelSealEnabled() ? '1' : '0',
			self::KEY_PIXEL_SEAL_MODEL_PATH => $this->getPixelSealModelPath(),
			self::KEY_PIXEL_SEAL_STRENGTH => (string)$this->getPixelSealStrengthPercent(),
			self::KEY_PIXEL_SEAL_DEVICE => $this->getPixelSealDevice(),
			self::KEY_MAX_SOURCE_MIB => (string)$this->getMaximumSourceSizeMiB(),
			self::KEY_MAX_PAGES => (string)$this->getMaximumPages(),
			self::KEY_TIMEOUT => (string)$this->getTimeoutSeconds(),
		];
	}

	/** Persist and return one normalized administration setting. */
	public function setAdminSetting(string $key, mixed $value): string {
		if ($key === self::KEY_PYTHON || $key === self::KEY_PIXEL_SEAL_MODEL_PATH) {
			if (!is_string($value)) {
				throw new InvalidArgumentException('Executable and model paths must be text.');
			}
			$normalized = trim($value);
			if ($normalized === '' || str_contains($normalized, "\0")
				|| ($key === self::KEY_PIXEL_SEAL_MODEL_PATH && !str_starts_with($normalized, '/'))) {
				throw new InvalidArgumentException($key === self::KEY_PYTHON
					? 'Python executable must not be empty.'
					: 'PixelSeal model path must be an absolute path.');
			}
		} elseif ($key === self::KEY_WATERMARK_COLOR) {
			if (!is_string($value)) {
				throw new InvalidArgumentException('Watermark color must be text.');
			}
			$normalized = strtolower(trim($value));
			if (preg_match('/^#[0-9a-f]{6}$/', $normalized) !== 1) {
				throw new InvalidArgumentException('Watermark color must be a six-digit hexadecimal color such as #333333.');
			}
		} elseif (isset(self::BOOLEAN_SETTINGS[$key])) {
			$normalized = match (true) {
				$value === true, $value === 1, $value === '1', $value === 'true' => '1',
				$value === false, $value === 0, $value === '0', $value === 'false' => '0',
				default => throw new InvalidArgumentException(sprintf('%s must be enabled or disabled.', $key)),
			};
		} elseif ($key === self::KEY_PIXEL_SEAL_DEVICE) {
			$normalized = is_string($value) ? strtolower(trim($value)) : '';
			if (!in_array($normalized, self::PIXEL_SEAL_DEVICES, true)) {
				throw new InvalidArgumentException('PixelSeal device must be auto, cpu, cuda, or mps.');
			}
		} elseif (isset(self::INTEGER_SETTINGS[$key])) {
			[, $minimum, $maximum] = self::INTEGER_SETTINGS[$key];
			$raw = is_int($value) ? (string)$value : (is_string($value) ? trim($value) : '');
			$parsed = preg_match('/^-?\d+$/', $raw) === 1 ? (int)$raw : null;
			if ($parsed === null || $parsed < $minimum || $parsed > $maximum) {
				throw new InvalidArgumentException(sprintf('%s must be an integer from %d to %d.', $key, $minimum, $maximum));
			}
			$normalized = (string)$parsed;
		} else {
			throw new InvalidArgumentException('Unknown watermark setting.');
		}

		$this->config->setAppValueString($key, $normalized);
		return $normalized;
	}

	/**
	 * Validate raw stored values without silently accepting clamped defaults.
	 *
	 * @return list<string>
	 */
	public function getValidationErrors(): array {
		$errors = [];
		$python = trim($this->config->getAppValueString(
			self::KEY_PYTHON,
			self::DEFAULT_PYTHON,
		));
		if ($python === '' || str_contains($python, "\0")) {
			$errors[] = 'Python executable must not be empty.';
		}
		$color = trim($this->config->getAppValueString(
			self::KEY_WATERMARK_COLOR,
			self::DEFAULT_WATERMARK_COLOR,
		));
		if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
			$errors[] = sprintf('%s must be a six-digit hexadecimal color such as #333333.', self::KEY_WATERMARK_COLOR);
		}
		$modelPath = trim($this->config->getAppValueString(
			self::KEY_PIXEL_SEAL_MODEL_PATH,
			self::DEFAULT_PIXEL_SEAL_MODEL_PATH,
		));
		if ($modelPath === '' || str_contains($modelPath, "\0") || !str_starts_with($modelPath, '/')) {
			$errors[] = sprintf('%s must be an absolute path.', self::KEY_PIXEL_SEAL_MODEL_PATH);
		}
		$device = strtolower(trim($this->config->getAppValueString(
			self::KEY_PIXEL_SEAL_DEVICE,
			self::DEFAULT_PIXEL_SEAL_DEVICE,
		)));
		if (!in_array($device, self::PIXEL_SEAL_DEVICES, true)) {
			$errors[] = sprintf('%s must be auto, cpu, cuda, or mps.', self::KEY_PIXEL_SEAL_DEVICE);
		}
		foreach (self::BOOLEAN_SETTINGS as $key => $default) {
			$raw = $this->config->getAppValueString($key, $default ? '1' : '0');
			if ($raw !== '0' && $raw !== '1') {
				$errors[] = sprintf('%s must be 0 or 1.', $key);
			}
		}

		foreach (self::INTEGER_SETTINGS as $key => [$default, $minimum, $maximum]) {
			$raw = $this->config->getAppValueString(
				$key,
				(string)$default,
			);
			$value = filter_var($raw, FILTER_VALIDATE_INT);
			if ($value === false || $value < $minimum || $value > $maximum) {
				$errors[] = sprintf('%s must be an integer from %d to %d.', $key, $minimum, $maximum);
			}
		}

		return $errors;
	}

	private function getBoundedInt(string $key, int $default, int $minimum, int $maximum): int {
		$raw = $this->config->getAppValueString(
			$key,
			(string)$default,
		);
		$value = filter_var($raw, FILTER_VALIDATE_INT);

		if ($value === false || $value < $minimum || $value > $maximum) {
			$this->warnInvalid($key, $raw, $default);
			return $default;
		}

		return $value;
	}

	private function getBoolean(string $key, bool $default): bool {
		$raw = $this->config->getAppValueString($key, $default ? '1' : '0');
		if ($raw !== '0' && $raw !== '1') {
			$this->warnInvalid($key, $raw, $default ? '1' : '0');
			return $default;
		}
		return $raw === '1';
	}

	private function warnInvalid(string $key, string $value, int|string $default): void {
		if (isset($this->warnedKeys[$key])) {
			return;
		}
		$this->warnedKeys[$key] = true;
		$this->logger->warning(
			'Invalid Watermarked shares setting; using the safe default.',
			[
				'app' => Application::APP_ID,
				'key' => $key,
				'value' => $value,
				'default' => $default,
			],
		);
	}
}
