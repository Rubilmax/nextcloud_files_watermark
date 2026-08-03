<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Settings;

use OCA\FilesWatermark\Settings\AdminPreview;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

final class AdminPreviewTest extends TestCase {
	public function testProvidesPreviewFormAfterAppearanceSettings(): void {
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->expects(self::once())
			->method('linkToRoute')
			->with('files_watermark.preview.show')
			->willReturn('/apps/files_watermark/admin/preview');

		$settings = new AdminPreview($urlGenerator);
		$form = $settings->getForm();

		self::assertSame('files_watermark', $settings->getSection());
		self::assertSame(20, $settings->getPriority());
		self::assertSame('files_watermark', $form->getApp());
		self::assertSame('admin-preview', $form->getTemplateName());
		self::assertSame(
			'/apps/files_watermark/admin/preview',
			$form->getParams()['previewUrl'],
		);
	}
}
