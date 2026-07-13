<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Service;

use OCA\FilesWatermark\Service\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class TextNormalizerTest extends TestCase {
	public function testNormalizesUnicodeWhitespaceAndLength(): void {
		$normalizer = new TextNormalizer();
		$result = $normalizer->normalize("  A\u{030A}\n\t" . str_repeat('é', 140) . '  ');

		self::assertStringStartsWith("Å é", $result);
		self::assertSame(TextNormalizer::MAX_CHARACTERS, mb_strlen($result, 'UTF-8'));
		self::assertStringNotContainsString("\n", $result);
	}

	public function testEmptyWhitespaceStaysEmpty(): void {
		self::assertSame('', (new TextNormalizer())->normalize(" \n\t "));
	}
}
