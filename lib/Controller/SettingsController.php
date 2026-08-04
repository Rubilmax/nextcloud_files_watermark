<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Controller;

use InvalidArgumentException;
use OCA\FilesWatermark\AppInfo\Application;
use OCA\FilesWatermark\Service\ConfigService;
use OCA\FilesWatermark\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

final class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly ConfigService $config,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST, array{key?: string, value?: string, message?: string}, array{}>
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function set(string $key, mixed $value): DataResponse {
		try {
			return new DataResponse([
				'key' => $key,
				'value' => $this->config->setAdminSetting($key, $value),
			]);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse([
				'message' => $exception->getMessage(),
			], Http::STATUS_BAD_REQUEST);
		}
	}
}
