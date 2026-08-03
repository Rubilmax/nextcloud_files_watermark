<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Service;

use OCA\FilesWatermark\Service\DummyPdfService;
use PHPUnit\Framework\TestCase;

final class DummyPdfServiceTest extends TestCase {
	public function testCreatesACompleteSinglePagePdf(): void {
		$pdf = (new DummyPdfService())->create();

		self::assertStringStartsWith('%PDF-1.4', $pdf);
		self::assertStringContainsString('/Type /Page ', $pdf);
		self::assertStringContainsString('(Watermarked PDF preview) Tj', $pdf);
		self::assertStringEndsWith("%%EOF\n", $pdf);

		self::assertMatchesRegularExpression('/startxref\n(\d+)\n%%EOF\n$/', $pdf);
		preg_match('/startxref\n(\d+)\n%%EOF\n$/', $pdf, $matches);
		self::assertSame('xref', substr($pdf, (int)$matches[1], 4));
	}
}
