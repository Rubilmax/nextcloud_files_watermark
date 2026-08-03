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
				$this->translateRendererStatus($status),
			));
		}

		return SetupResult::success($this->translateRendererStatus($status));
	}

	/** @param array{available: bool, message: string, version?: string} $status */
	private function translateRendererStatus(array $status): string {
		if (isset($status['version'])) {
			return $status['available']
				? $this->l10n->t('PyMuPDF %s is available.', $status['version'])
				: $this->l10n->t(
					'PyMuPDF %s is outside the supported >=1.28.0,<1.29.0 range.',
					$status['version'],
				);
		}

		return match ($status['message']) {
			'PHP proc_open is disabled.' => $this->l10n->t('PHP proc_open is disabled.'),
			'Configured Python cannot import PyMuPDF.' => $this->l10n->t('Configured Python cannot import PyMuPDF.'),
			default => $status['message'],
		};
	}
}
