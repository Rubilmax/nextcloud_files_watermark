<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Service;

use OCA\FilesWatermark\Service\ConfigService;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class ConfigServiceTest extends TestCase {
	public function testClampsInvalidValuesToSafeDefaultsAndLogsWarningsOnce(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default): string => match ($key) {
				ConfigService::KEY_PYTHON => '',
				ConfigService::KEY_DPI => '301',
				ConfigService::KEY_MAX_SOURCE_MIB => 'zero',
				ConfigService::KEY_MAX_PAGES => '0',
				ConfigService::KEY_TIMEOUT => '9',
				default => $default,
			},
		);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(5))->method('warning');
		$service = new ConfigService($config, $logger);

		self::assertSame(ConfigService::DEFAULT_PYTHON, $service->getPythonExecutable());
		self::assertSame(ConfigService::DEFAULT_DPI, $service->getRasterDpi());
		self::assertSame(ConfigService::DEFAULT_MAX_SOURCE_MIB, $service->getMaximumSourceSizeMiB());
		self::assertSame(ConfigService::DEFAULT_MAX_PAGES, $service->getMaximumPages());
		self::assertSame(ConfigService::DEFAULT_TIMEOUT, $service->getTimeoutSeconds());
		// A repeated read does not repeat a warning in the same request.
		self::assertSame(ConfigService::DEFAULT_DPI, $service->getRasterDpi());
		self::assertCount(5, $service->getValidationErrors());
	}

	public function testAcceptsInclusiveConfigurationBounds(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default): string => match ($key) {
				ConfigService::KEY_PYTHON => '/opt/python/bin/python3',
				ConfigService::KEY_DPI => '96',
				ConfigService::KEY_MAX_SOURCE_MIB => '1024',
				ConfigService::KEY_MAX_PAGES => '5000',
				ConfigService::KEY_TIMEOUT => '3600',
				default => $default,
			},
		);
		$service = new ConfigService($config, $this->createStub(LoggerInterface::class));

		self::assertSame('/opt/python/bin/python3', $service->getPythonExecutable());
		self::assertSame(96, $service->getRasterDpi());
		self::assertSame(1024 * 1024 * 1024, $service->getMaximumSourceSizeBytes());
		self::assertSame(5000, $service->getMaximumPages());
		self::assertSame(3600, $service->getTimeoutSeconds());
		self::assertSame([], $service->getValidationErrors());
	}
}
