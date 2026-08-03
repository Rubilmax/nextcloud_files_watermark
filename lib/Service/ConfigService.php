<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

use OCA\FilesWatermark\AppInfo\Application;
use OCP\IConfig;
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

	/** @var array<string, true> */
	private array $warnedKeys = [];

	public function __construct(
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getPythonExecutable(): string {
		$value = trim($this->config->getAppValue(
			Application::APP_ID,
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
		$value = strtolower(trim($this->config->getAppValue(
			Application::APP_ID,
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

	/**
	 * Validate raw stored values without silently accepting clamped defaults.
	 *
	 * @return list<string>
	 */
	public function getValidationErrors(): array {
		$errors = [];
		$python = trim($this->config->getAppValue(
			Application::APP_ID,
			self::KEY_PYTHON,
			self::DEFAULT_PYTHON,
		));
		if ($python === '' || str_contains($python, "\0")) {
			$errors[] = 'Python executable must not be empty.';
		}
		$color = trim($this->config->getAppValue(
			Application::APP_ID,
			self::KEY_WATERMARK_COLOR,
			self::DEFAULT_WATERMARK_COLOR,
		));
		if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
			$errors[] = sprintf('%s must be a six-digit hexadecimal color such as #333333.', self::KEY_WATERMARK_COLOR);
		}

		foreach ([
			[self::KEY_DPI, self::DEFAULT_DPI, 96, 300],
			[self::KEY_MAX_SOURCE_MIB, self::DEFAULT_MAX_SOURCE_MIB, 1, 1024],
			[self::KEY_MAX_PAGES, self::DEFAULT_MAX_PAGES, 1, 5000],
			[self::KEY_TIMEOUT, self::DEFAULT_TIMEOUT, 10, 3600],
			[self::KEY_WATERMARK_FONT_SIZE, self::DEFAULT_WATERMARK_FONT_SIZE, 8, 144],
			[self::KEY_WATERMARK_OPACITY, self::DEFAULT_WATERMARK_OPACITY, 1, 100],
			[self::KEY_WATERMARK_ANGLE, self::DEFAULT_WATERMARK_ANGLE, -180, 180],
			[
				self::KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL,
				self::DEFAULT_WATERMARK_MIN_HORIZONTAL_INTERVAL,
				20,
				2000,
			],
			[self::KEY_WATERMARK_HORIZONTAL_GAP, self::DEFAULT_WATERMARK_HORIZONTAL_GAP, 0, 1000],
			[self::KEY_WATERMARK_VERTICAL_INTERVAL, self::DEFAULT_WATERMARK_VERTICAL_INTERVAL, 20, 2000],
		] as [$key, $default, $minimum, $maximum]) {
			$raw = $this->config->getAppValue(
				Application::APP_ID,
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
		$raw = $this->config->getAppValue(
			Application::APP_ID,
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
