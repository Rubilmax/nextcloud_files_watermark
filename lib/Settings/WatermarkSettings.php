<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Settings;

use OCA\FilesWatermark\Service\ConfigService;
use OCP\IL10N;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsForm;

final class WatermarkSettings implements IDeclarativeSettingsForm {
	public function __construct(
		private readonly IL10N $l10n,
	) {
	}

	/** @return array<string, mixed> */
	public function getSchema(): array {
		return [
			'id' => 'files_watermark_renderer',
			'priority' => 10,
			'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
			'section_id' => 'files_watermark',
			'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_INTERNAL,
			'title' => $this->l10n->t('Watermark settings'),
			'description' => $this->l10n->t('Configure the local PyMuPDF renderer and synchronous safety limits.'),
			'doc_url' => '',
			'fields' => [
				[
					'id' => ConfigService::KEY_PYTHON,
					'title' => $this->l10n->t('Python executable'),
					'description' => $this->l10n->t('Executable or absolute path used to run the local renderer.'),
					'type' => DeclarativeSettingsTypes::TEXT,
					'placeholder' => ConfigService::DEFAULT_PYTHON,
					'default' => ConfigService::DEFAULT_PYTHON,
				],
				[
					'id' => ConfigService::KEY_DPI,
					'title' => $this->l10n->t('Raster DPI'),
					'description' => $this->l10n->t('Resolution from 96 to 300 DPI. Higher values use more memory and storage.'),
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => ConfigService::DEFAULT_DPI,
				],
				[
					'id' => ConfigService::KEY_MAX_SOURCE_MIB,
					'title' => $this->l10n->t('Maximum source size (MiB)'),
					'description' => $this->l10n->t('Reject larger PDFs before rendering. Allowed range: 1 to 1024.'),
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => ConfigService::DEFAULT_MAX_SOURCE_MIB,
				],
				[
					'id' => ConfigService::KEY_MAX_PAGES,
					'title' => $this->l10n->t('Maximum pages'),
					'description' => $this->l10n->t('Maximum pages per source PDF. Allowed range: 1 to 5000.'),
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => ConfigService::DEFAULT_MAX_PAGES,
				],
				[
					'id' => ConfigService::KEY_TIMEOUT,
					'title' => $this->l10n->t('Timeout (seconds)'),
					'description' => $this->l10n->t('Terminate rendering after this duration. Allowed range: 10 to 3600.'),
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => ConfigService::DEFAULT_TIMEOUT,
				],
			],
		];
	}
}
