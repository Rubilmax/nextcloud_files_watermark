<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Service;

use OCA\FilesWatermark\Service\FilenameService;
use PHPUnit\Framework\TestCase;

final class FilenameServiceTest extends TestCase {
	private FilenameService $service;

	protected function setUp(): void {
		$this->service = new FilenameService();
	}

	public function testBuildsExpectedPdfNameAndSanitizesInvalidCharacters(): void {
		self::assertSame(
			'Quarterly report - Board-copy- 2026.pdf',
			$this->service->build('Quarterly report.PDF', 'Board/copy: 2026'),
		);
	}

	public function testTruncatesToUtf8ByteLimitWhilePreservingWatermarkSuffix(): void {
		$name = $this->service->build(str_repeat('資料', 100) . '.pdf', 'Très secret');

		self::assertLessThanOrEqual(FilenameService::MAX_BYTES, strlen($name));
		self::assertTrue(mb_check_encoding($name, 'UTF-8'));
		self::assertStringEndsWith(' - Très secret.pdf', $name);
	}

	public function testUsesSafeFallbacksForEmptySanitizedParts(): void {
		self::assertSame('Document - Watermark.pdf', $this->service->build('.pdf', '...'));
	}
}
