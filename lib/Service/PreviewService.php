<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

use OCP\ITempManager;
use RuntimeException;

final class PreviewService {
	public function __construct(
		private readonly ITempManager $tempManager,
		private readonly DummyPdfService $dummyPdf,
		private readonly RendererService $renderer,
	) {
	}

	public function generate(
		?int $fontSize,
		?string $color,
		?int $opacity,
		?int $angle,
		?int $minimumHorizontalInterval,
		?int $horizontalGap,
		?int $verticalInterval,
	): string {
		$inputPath = $this->createTemporaryPath('.preview-source.pdf');
		$outputPath = null;

		try {
			if (file_put_contents($inputPath, $this->dummyPdf->create()) === false) {
				throw new RuntimeException('The preview source PDF could not be written.');
			}

			$outputPath = $this->createTemporaryPath('.preview-watermarked.pdf');
			$this->renderer->renderPreview(
				$inputPath,
				$outputPath,
				'PREVIEW - recipient@example.com',
				$fontSize,
				$color,
				$opacity,
				$angle,
				$minimumHorizontalInterval,
				$horizontalGap,
				$verticalInterval,
			);

			$pdf = file_get_contents($outputPath);
			if ($pdf === false || $pdf === '') {
				throw new RuntimeException('The watermarked preview PDF could not be read.');
			}

			return $pdf;
		} finally {
			$this->removeTemporaryFile($inputPath);
			if ($outputPath !== null) {
				$this->removeTemporaryFile($outputPath);
			}
		}
	}

	private function createTemporaryPath(string $postfix): string {
		$path = $this->tempManager->getTemporaryFile($postfix);
		if ($path === false) {
			throw new RuntimeException('Temporary storage is unavailable.');
		}

		return $path;
	}

	private function removeTemporaryFile(string $path): void {
		if (is_file($path)) {
			@unlink($path);
		}
	}
}
