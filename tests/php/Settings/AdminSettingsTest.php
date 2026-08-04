<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Settings;

use OCA\FilesWatermark\Service\ConfigService;
use OCA\FilesWatermark\Settings\AdminSettings;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AdminSettingsTest extends TestCase {
	public function testProvidesInitialStateForTheVueAdminForm(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')->willReturnCallback(
			static fn (string $key, string $default): string => $default,
		);
		$config = new ConfigService($appConfig, $this->createStub(LoggerInterface::class));

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->expects(self::exactly(2))
			->method('linkToRoute')
			->willReturnCallback(static function (string $route, array $parameters = []): string {
				self::assertSame('files_watermark.preview.show', $route);
				return empty($parameters)
					? '/apps/files_watermark/admin/preview'
					: '/apps/files_watermark/admin/preview?format=image';
			});
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects(self::once())
			->method('provideInitialState')
			->with('admin-settings', self::callback(static function (array $state): bool {
				return $state['settings'][ConfigService::KEY_WATERMARK_OPACITY] === '30'
					&& $state['previewUrl'] === '/apps/files_watermark/admin/preview'
					&& $state['previewImageUrl'] === '/apps/files_watermark/admin/preview?format=image';
			}));
		$l10n = $this->createStub(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$settings = new AdminSettings($config, $initialState, $urlGenerator, $l10n);
		$form = $settings->getForm();

		self::assertSame('files_watermark', $settings->getSection());
		self::assertSame(10, $settings->getPriority());
		self::assertSame('Watermark settings', $settings->getName());
		self::assertSame([], $settings->getAuthorizedAppConfig());
		self::assertSame('files_watermark', $form->getApp());
		self::assertSame('admin-settings', $form->getTemplateName());
	}
}
