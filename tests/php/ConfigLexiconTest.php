<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests;

use OCA\FilesWatermark\ConfigLexicon;
use OCA\FilesWatermark\Service\ConfigService;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;
use PHPUnit\Framework\TestCase;

final class ConfigLexiconTest extends TestCase {
	public function testDeclaresEveryRendererSettingAsACompatibleString(): void {
		$lexicon = new ConfigLexicon();
		$entries = [];
		foreach ($lexicon->getAppConfigs() as $entry) {
			$entries[$entry->getKey()] = $entry;
		}

		self::assertSame(Strictness::NOTICE, $lexicon->getStrictness());
		self::assertSame([], $lexicon->getUserConfigs());
		self::assertCount(19, $entries);
		self::assertArrayHasKey(ConfigService::KEY_PYTHON, $entries);
		self::assertArrayHasKey(ConfigService::KEY_WATERMARK_OPACITY, $entries);
		self::assertArrayHasKey(ConfigService::KEY_WATERMARK_DISTORTION_ENABLED, $entries);
		foreach ($entries as $entry) {
			self::assertSame(ValueType::STRING, $entry->getValueType());
		}
	}
}
