<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

use Normalizer;

final class TextNormalizer {
	public const MAX_CHARACTERS = 128;

	public function normalize(string $text): string {
		$normalized = Normalizer::normalize($text, Normalizer::FORM_C);
		if ($normalized !== false) {
			$text = $normalized;
		}

		$text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
		if (mb_strlen($text, 'UTF-8') > self::MAX_CHARACTERS) {
			$text = mb_substr($text, 0, self::MAX_CHARACTERS, 'UTF-8');
		}

		return $text;
	}
}
