<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

final class FilenameService {
	public const MAX_BYTES = 240;

	public function build(string $sourceName, string $watermark): string {
		$stem = preg_replace('/\.pdf$/iu', '', $sourceName) ?? $sourceName;
		$stem = $this->sanitizePart($stem, 'Document');
		$suffix = $this->sanitizePart($watermark, 'Watermark');
		$extension = '.pdf';
		$separator = ' - ';

		$maxSuffixBytes = self::MAX_BYTES - strlen($separator) - strlen($extension) - 1;
		$suffix = $this->truncateUtf8($suffix, $maxSuffixBytes);
		$remaining = self::MAX_BYTES - strlen($separator) - strlen($suffix) - strlen($extension);
		$stem = $this->truncateUtf8($stem, max(1, $remaining));

		return $stem . $separator . $suffix . $extension;
	}

	private function sanitizePart(string $value, string $fallback): string {
		$value = preg_replace('/[<>:"\\/\\\\|?*\x00-\x1F\x7F]/u', '-', $value) ?? '';
		$value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
		$value = rtrim($value, ". ");
		return $value === '' ? $fallback : $value;
	}

	private function truncateUtf8(string $value, int $maximumBytes): string {
		if (strlen($value) <= $maximumBytes) {
			return $value;
		}

		while ($maximumBytes > 0) {
			$candidate = substr($value, 0, $maximumBytes);
			if (mb_check_encoding($candidate, 'UTF-8')) {
				return rtrim($candidate, ". ");
			}
			$maximumBytes--;
		}

		return '';
	}
}
