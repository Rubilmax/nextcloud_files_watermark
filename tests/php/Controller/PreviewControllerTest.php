<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Controller;

use OCA\FilesWatermark\Controller\PreviewController;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use PHPUnit\Framework\TestCase;

final class PreviewControllerTest extends TestCase {
	public function testPreviewGetDoesNotRequireACsrfToken(): void {
		$method = new \ReflectionMethod(PreviewController::class, 'show');

		self::assertCount(1, $method->getAttributes(NoCSRFRequired::class));
	}
}
