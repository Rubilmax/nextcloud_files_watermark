<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\FilesWatermark\Listener\LoadAdditionalScriptsListener;
use OCA\FilesWatermark\Settings\WatermarkSettings;
use OCA\FilesWatermark\SetupChecks\RendererSetupCheck;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

final class Application extends App implements IBootstrap {
	public const APP_ID = 'files_watermark';

	/** @param array<string, mixed> $urlParams */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		require_once __DIR__ . '/../../vendor/autoload.php';

		$context->registerEventListener(
			LoadAdditionalScriptsEvent::class,
			LoadAdditionalScriptsListener::class,
		);
		$context->registerDeclarativeSettings(WatermarkSettings::class);
		$context->registerSetupCheck(RendererSetupCheck::class);
	}

	public function boot(IBootContext $context): void {
	}
}
