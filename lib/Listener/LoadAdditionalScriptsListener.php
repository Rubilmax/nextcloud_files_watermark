<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\FilesWatermark\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<LoadAdditionalScriptsEvent> */
final class LoadAdditionalScriptsListener implements IEventListener {
	public function __construct(
		private readonly IAppManager $appManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$this->appManager->isEnabledForUser('files_sharing')) {
			return;
		}
		Util::addInitScript(Application::APP_ID, 'files_watermark-main');
	}
}
