<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Controller;

use OCA\FilesWatermark\Controller\SettingsController;
use OCA\FilesWatermark\Service\ConfigService;
use OCA\FilesWatermark\Settings\AdminSettings;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SettingsControllerTest extends TestCase {
	public function testPersistsValidatedSetting(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects(self::once())
			->method('setAppValueString')
			->with(ConfigService::KEY_WATERMARK_OPACITY, '45')
			->willReturn(true);
		$controller = $this->createController($appConfig);

		$response = $controller->set(ConfigService::KEY_WATERMARK_OPACITY, '45');

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame([
			'key' => ConfigService::KEY_WATERMARK_OPACITY,
			'value' => '45',
		], $response->getData());
	}

	public function testRejectsUnknownSetting(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects(self::never())->method('setAppValueString');
		$controller = $this->createController($appConfig);

		$response = $controller->set('not_a_setting', 'value');

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['message' => 'Unknown watermark setting.'], $response->getData());
	}

	public function testSaveRequiresAuthorizedAdminSetting(): void {
		$attributes = (new \ReflectionMethod(SettingsController::class, 'set'))
			->getAttributes(AuthorizedAdminSetting::class);

		self::assertCount(1, $attributes);
		self::assertSame(AdminSettings::class, $attributes[0]->newInstance()->getSettings());
		self::assertCount(0, (new \ReflectionMethod(SettingsController::class, 'set'))
			->getAttributes(NoCSRFRequired::class));
	}

	private function createController(IAppConfig $appConfig): SettingsController {
		return new SettingsController(
			$this->createStub(IRequest::class),
			new ConfigService($appConfig, $this->createStub(LoggerInterface::class)),
		);
	}
}
