<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

/**
 * Builds the small source PDF used by the administration preview.
 */
final class DummyPdfService {
	public function create(): string {
		$content = implode("\n", [
			'q',
			'0.95 0.97 1 rg',
			'0 650 612 142 re f',
			'0.16 0.36 0.66 rg',
			'48 705 516 4 re f',
			'0.84 0.88 0.94 RG',
			'1 w',
			'48 420 516 174 re S',
			'48 160 246 210 re S',
			'318 160 246 210 re S',
			'BT',
			'/F1 26 Tf',
			'0.10 0.18 0.30 rg',
			'48 736 Td',
			'(Watermarked PDF preview) Tj',
			'ET',
			'BT',
			'/F1 12 Tf',
			'0.28 0.34 0.42 rg',
			'48 674 Td',
			'(This dummy document shows how generated files will look.) Tj',
			'0 -22 Td',
			'(Change the appearance settings to update the watermark.) Tj',
			'ET',
			'BT',
			'/F1 18 Tf',
			'0.10 0.18 0.30 rg',
			'72 548 Td',
			'(Sample document) Tj',
			'/F1 11 Tf',
			'0.28 0.34 0.42 rg',
			'0 -30 Td',
			'(Watermarks are rasterized into every page.) Tj',
			'0 -20 Td',
			'(Recipients cannot remove the overlay as PDF text.) Tj',
			'ET',
			'BT',
			'/F1 15 Tf',
			'0.10 0.18 0.30 rg',
			'72 326 Td',
			'(Project brief) Tj',
			'/F1 10 Tf',
			'0.28 0.34 0.42 rg',
			'0 -28 Td',
			'(Status: Approved) Tj',
			'0 -18 Td',
			'(Owner: Example team) Tj',
			'ET',
			'BT',
			'/F1 15 Tf',
			'0.10 0.18 0.30 rg',
			'342 326 Td',
			'(Distribution) Tj',
			'/F1 10 Tf',
			'0.28 0.34 0.42 rg',
			'0 -28 Td',
			'(Internal recipients) Tj',
			'0 -18 Td',
			'(Public link protected) Tj',
			'ET',
			'Q',
			'',
		]);

		$objects = [
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
			sprintf("<< /Length %d >>\nstream\n%sendstream", strlen($content), $content),
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
		];

		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = [0];
		foreach ($objects as $index => $object) {
			$offsets[] = strlen($pdf);
			$pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
		}

		$xrefOffset = strlen($pdf);
		$pdf .= sprintf("xref\n0 %d\n", count($objects) + 1);
		$pdf .= "0000000000 65535 f \n";
		foreach (array_slice($offsets, 1) as $offset) {
			$pdf .= sprintf("%010d 00000 n \n", $offset);
		}
		$pdf .= sprintf(
			"trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n",
			count($objects) + 1,
			$xrefOffset,
		);

		return $pdf;
	}
}
