<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Settings;

use OCA\FilesWatermark\Service\ConfigService;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsForm;

final class WatermarkSettings implements IDeclarativeSettingsForm {
	/** @return array<string, mixed> */
	public function getSchema(): array {
		return [
			'id' => 'files_watermark_renderer',
			'priority' => 10,
			'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
			'section_id' => 'files_watermark',
			'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_INTERNAL,
			'title' => 'Watermark settings',
			'description' => 'Configure the local PyMuPDF renderer and synchronous safety limits.',
			'doc_url' => '',
			'fields' => [
				[
					'id' => ConfigService::KEY_PYTHON,
					'title' => 'Python executable',
					'description' => 'Executable or absolute path used to run the local renderer.',
					'type' => DeclarativeSettingsTypes::TEXT,
					'placeholder' => ConfigService::DEFAULT_PYTHON,
					'default' => ConfigService::DEFAULT_PYTHON,
				],
				[
					'id' => ConfigService::KEY_DPI,
					'title' => 'Raster DPI',
					'description' => 'Resolution from 96 to 300 DPI. Higher values use more memory and storage.',
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => ConfigService::DEFAULT_DPI,
				],
				[
					'id' => ConfigService::KEY_MAX_SOURCE_MIB,
					'title' => 'Maximum source size (MiB)',
					'description' => 'Reject larger PDFs before rendering. Allowed range: 1 to 1024.',
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => ConfigService::DEFAULT_MAX_SOURCE_MIB,
				],
				[
					'id' => ConfigService::KEY_MAX_PAGES,
					'title' => 'Maximum pages',
					'description' => 'Maximum pages per source PDF. Allowed range: 1 to 5000.',
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => ConfigService::DEFAULT_MAX_PAGES,
				],
				[
					'id' => ConfigService::KEY_TIMEOUT,
					'title' => 'Timeout (seconds)',
					'description' => 'Terminate rendering after this duration. Allowed range: 10 to 3600.',
					'type' => DeclarativeSettingsTypes::NUMBER,
					'default' => ConfigService::DEFAULT_TIMEOUT,
				],
			],
		];
	}
}
