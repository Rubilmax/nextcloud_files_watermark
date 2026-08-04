<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Settings;

use OCA\FilesWatermark\Service\ConfigService;
use OCA\FilesWatermark\Settings\WatermarkSettings;
use OCP\IL10N;
use OCP\Settings\DeclarativeSettingsTypes;
use PHPUnit\Framework\TestCase;

final class WatermarkSettingsTest extends TestCase {
	public function testExposesWatermarkAppearanceDefaults(): void {
		$l10n = $this->createStub(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$schema = (new WatermarkSettings($l10n))->getSchema();
		self::assertSame('renderer', $schema['id']);
		$fields = [];
		foreach ($schema['fields'] as $field) {
			$fields[$field['id']] = $field;
		}

		$expectedNumbers = [
			ConfigService::KEY_WATERMARK_FONT_SIZE => ConfigService::DEFAULT_WATERMARK_FONT_SIZE,
			ConfigService::KEY_WATERMARK_OPACITY => ConfigService::DEFAULT_WATERMARK_OPACITY,
			ConfigService::KEY_WATERMARK_ANGLE => ConfigService::DEFAULT_WATERMARK_ANGLE,
			ConfigService::KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL => ConfigService::DEFAULT_WATERMARK_MIN_HORIZONTAL_INTERVAL,
			ConfigService::KEY_WATERMARK_HORIZONTAL_GAP => ConfigService::DEFAULT_WATERMARK_HORIZONTAL_GAP,
			ConfigService::KEY_WATERMARK_VERTICAL_INTERVAL => ConfigService::DEFAULT_WATERMARK_VERTICAL_INTERVAL,
		];
		foreach ($expectedNumbers as $key => $default) {
			self::assertArrayHasKey($key, $fields);
			self::assertSame(DeclarativeSettingsTypes::NUMBER, $fields[$key]['type']);
			self::assertSame($default, $fields[$key]['default']);
		}

		self::assertArrayHasKey(ConfigService::KEY_WATERMARK_COLOR, $fields);
		self::assertSame(DeclarativeSettingsTypes::TEXT, $fields[ConfigService::KEY_WATERMARK_COLOR]['type']);
		self::assertSame(
			ConfigService::DEFAULT_WATERMARK_COLOR,
			$fields[ConfigService::KEY_WATERMARK_COLOR]['default'],
		);
	}
}
