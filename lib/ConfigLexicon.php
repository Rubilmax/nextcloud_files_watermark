<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark;

use OCA\FilesWatermark\Service\ConfigService;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

final class ConfigLexicon implements ILexicon {
	public function getStrictness(): Strictness {
		return Strictness::NOTICE;
	}

	/** @return list<Entry> */
	public function getAppConfigs(): array {
		return [
			new Entry(ConfigService::KEY_PYTHON, ValueType::STRING, ConfigService::DEFAULT_PYTHON, 'Python executable used by the PDF renderer'),
			new Entry(ConfigService::KEY_DPI, ValueType::STRING, (string)ConfigService::DEFAULT_DPI, 'Raster rendering resolution in DPI'),
			new Entry(ConfigService::KEY_MAX_SOURCE_MIB, ValueType::STRING, (string)ConfigService::DEFAULT_MAX_SOURCE_MIB, 'Maximum PDF source size in MiB'),
			new Entry(ConfigService::KEY_MAX_PAGES, ValueType::STRING, (string)ConfigService::DEFAULT_MAX_PAGES, 'Maximum source PDF page count'),
			new Entry(ConfigService::KEY_TIMEOUT, ValueType::STRING, (string)ConfigService::DEFAULT_TIMEOUT, 'PDF rendering timeout in seconds'),
			new Entry(ConfigService::KEY_WATERMARK_FONT_SIZE, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_FONT_SIZE, 'Watermark font size in points'),
			new Entry(ConfigService::KEY_WATERMARK_COLOR, ValueType::STRING, ConfigService::DEFAULT_WATERMARK_COLOR, 'Watermark hexadecimal color'),
			new Entry(ConfigService::KEY_WATERMARK_OPACITY, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_OPACITY, 'Watermark opacity in percent'),
			new Entry(ConfigService::KEY_WATERMARK_ANGLE, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_ANGLE, 'Watermark angle in degrees'),
			new Entry(ConfigService::KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_MIN_HORIZONTAL_INTERVAL, 'Minimum horizontal watermark interval in points'),
			new Entry(ConfigService::KEY_WATERMARK_HORIZONTAL_GAP, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_HORIZONTAL_GAP, 'Additional horizontal watermark gap in points'),
			new Entry(ConfigService::KEY_WATERMARK_VERTICAL_INTERVAL, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_VERTICAL_INTERVAL, 'Vertical watermark interval in points'),
			new Entry(ConfigService::KEY_WATERMARK_OPACITY_VARIATION, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_OPACITY_VARIATION, 'Maximum random opacity variation in percent'),
			new Entry(ConfigService::KEY_WATERMARK_SPACING_VARIATION, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_SPACING_VARIATION, 'Maximum random watermark spacing variation in percent'),
			new Entry(ConfigService::KEY_WATERMARK_POSITION_JITTER, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_POSITION_JITTER, 'Maximum random watermark position jitter in points'),
			new Entry(ConfigService::KEY_WATERMARK_BLUR_RADIUS, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_BLUR_RADIUS, 'Gaussian blur radius for the secondary watermark layer'),
			new Entry(ConfigService::KEY_WATERMARK_BLUR_OPACITY, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_BLUR_OPACITY, 'Opacity multiplier for the blurred watermark layer'),
			new Entry(ConfigService::KEY_WATERMARK_DISTORTION_ENABLED, ValueType::STRING, '0', 'Enable sinusoidal visible watermark distortion'),
			new Entry(ConfigService::KEY_WATERMARK_DISTORTION_STRENGTH, ValueType::STRING, (string)ConfigService::DEFAULT_WATERMARK_DISTORTION_STRENGTH, 'Sinusoidal visible watermark distortion strength in pixels'),
		];
	}

	/** @return list<Entry> */
	public function getUserConfigs(): array {
		return [];
	}
}
