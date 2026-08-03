<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Settings;

use OCA\FilesWatermark\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

final class AdminPreview implements ISettings {
	public function __construct(
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	/** @return TemplateResponse<\OCP\AppFramework\Http::STATUS_OK, array{}> */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'admin-preview', [
			'previewUrl' => $this->urlGenerator->linkToRoute(Application::APP_ID . '.preview.show'),
		], '');
	}

	public function getSection(): string {
		return 'files_watermark';
	}

	public function getPriority(): int {
		return 20;
	}
}
