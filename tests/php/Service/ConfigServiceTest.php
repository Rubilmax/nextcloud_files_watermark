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
				ConfigService::KEY_WATERMARK_FONT_SIZE => '7',
				ConfigService::KEY_WATERMARK_COLOR => 'gray',
				ConfigService::KEY_WATERMARK_OPACITY => '0',
				ConfigService::KEY_WATERMARK_ANGLE => '181',
				ConfigService::KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL => '19',
				ConfigService::KEY_WATERMARK_HORIZONTAL_GAP => '-1',
				ConfigService::KEY_WATERMARK_VERTICAL_INTERVAL => '2001',
				default => $default,
			},
		);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::exactly(12))->method('warning');
		$service = new ConfigService($config, $logger);

		self::assertSame(ConfigService::DEFAULT_PYTHON, $service->getPythonExecutable());
		self::assertSame(ConfigService::DEFAULT_DPI, $service->getRasterDpi());
		self::assertSame(ConfigService::DEFAULT_MAX_SOURCE_MIB, $service->getMaximumSourceSizeMiB());
		self::assertSame(ConfigService::DEFAULT_MAX_PAGES, $service->getMaximumPages());
		self::assertSame(ConfigService::DEFAULT_TIMEOUT, $service->getTimeoutSeconds());
		self::assertSame(ConfigService::DEFAULT_WATERMARK_FONT_SIZE, $service->getWatermarkFontSize());
		self::assertSame(ConfigService::DEFAULT_WATERMARK_COLOR, $service->getWatermarkColor());
		self::assertSame(ConfigService::DEFAULT_WATERMARK_OPACITY, $service->getWatermarkOpacityPercent());
		self::assertSame(ConfigService::DEFAULT_WATERMARK_ANGLE, $service->getWatermarkAngleDegrees());
		self::assertSame(
			ConfigService::DEFAULT_WATERMARK_MIN_HORIZONTAL_INTERVAL,
			$service->getWatermarkMinimumHorizontalInterval(),
		);
		self::assertSame(ConfigService::DEFAULT_WATERMARK_HORIZONTAL_GAP, $service->getWatermarkHorizontalGap());
		self::assertSame(ConfigService::DEFAULT_WATERMARK_VERTICAL_INTERVAL, $service->getWatermarkVerticalInterval());
		// A repeated read does not repeat a warning in the same request.
		self::assertSame(ConfigService::DEFAULT_DPI, $service->getRasterDpi());
		self::assertCount(12, $service->getValidationErrors());
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
				ConfigService::KEY_WATERMARK_FONT_SIZE => '144',
				ConfigService::KEY_WATERMARK_COLOR => '#A1b2C3',
				ConfigService::KEY_WATERMARK_OPACITY => '100',
				ConfigService::KEY_WATERMARK_ANGLE => '-180',
				ConfigService::KEY_WATERMARK_MIN_HORIZONTAL_INTERVAL => '20',
				ConfigService::KEY_WATERMARK_HORIZONTAL_GAP => '0',
				ConfigService::KEY_WATERMARK_VERTICAL_INTERVAL => '2000',
				default => $default,
			},
		);
		$service = new ConfigService($config, $this->createStub(LoggerInterface::class));

		self::assertSame('/opt/python/bin/python3', $service->getPythonExecutable());
		self::assertSame(96, $service->getRasterDpi());
		self::assertSame(1024 * 1024 * 1024, $service->getMaximumSourceSizeBytes());
		self::assertSame(5000, $service->getMaximumPages());
		self::assertSame(3600, $service->getTimeoutSeconds());
		self::assertSame(144, $service->getWatermarkFontSize());
		self::assertSame('#a1b2c3', $service->getWatermarkColor());
		self::assertSame(100, $service->getWatermarkOpacityPercent());
		self::assertSame(-180, $service->getWatermarkAngleDegrees());
		self::assertSame(20, $service->getWatermarkMinimumHorizontalInterval());
		self::assertSame(0, $service->getWatermarkHorizontalGap());
		self::assertSame(2000, $service->getWatermarkVerticalInterval());
		self::assertSame([], $service->getValidationErrors());
	}
}
