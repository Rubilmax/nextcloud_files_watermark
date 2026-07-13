<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\SetupChecks;

use OCA\FilesWatermark\Service\ConfigService;
use OCA\FilesWatermark\Service\RendererService;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

final class RendererSetupCheck implements ISetupCheck {
	public function __construct(
		private readonly IL10N $l10n,
		private readonly ConfigService $config,
		private readonly RendererService $renderer,
	) {
	}

	public function getCategory(): string {
		return 'system';
	}

	public function getName(): string {
		return $this->l10n->t('Watermarked shares PDF renderer');
	}

	public function run(): SetupResult {
		$validationErrors = $this->config->getValidationErrors();
		if ($validationErrors !== []) {
			return SetupResult::warning($this->l10n->t(
				'Invalid Watermarked shares settings: %s Safe defaults will be used.',
				implode(' ', $validationErrors),
			));
		}

		$status = $this->renderer->checkAvailability();
		if (!$status['available']) {
			return SetupResult::error($this->l10n->t(
				'The Watermarked shares renderer is unavailable: %s',
				$status['message'],
			));
		}

		return SetupResult::success($this->l10n->t($status['message']));
	}
}
