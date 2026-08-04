<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Settings;

use OCA\FilesWatermark\AppInfo\Application;
use OCA\FilesWatermark\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IDelegatedSettings;

final class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private readonly ConfigService $config,
		private readonly IInitialState $initialState,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l10n,
	) {
	}

	/** @return TemplateResponse<\OCP\AppFramework\Http::STATUS_OK, array{}> */
	public function getForm(): TemplateResponse {
		$route = Application::APP_ID . '.preview.show';
		$this->initialState->provideInitialState('admin-settings', [
			'settings' => $this->config->getAdminSettings(),
			'previewUrl' => $this->urlGenerator->linkToRoute($route),
			'previewImageUrl' => $this->urlGenerator->linkToRoute($route, ['format' => 'image']),
		]);

		return new TemplateResponse(Application::APP_ID, 'admin-settings', [], '');
	}

	public function getSection(): string {
		return 'files_watermark';
	}

	public function getPriority(): int {
		return 10;
	}

	public function getName(): string {
		return $this->l10n->t('Watermark settings');
	}

	/** @return array{} */
	public function getAuthorizedAppConfig(): array {
		// The controller validates and persists each supported key explicitly.
		return [];
	}
}
