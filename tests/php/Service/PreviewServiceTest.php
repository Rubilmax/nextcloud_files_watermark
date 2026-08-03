<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Service;

use OCA\FilesWatermark\Service\ConfigService;
use OCA\FilesWatermark\Service\DummyPdfService;
use OCA\FilesWatermark\Service\PreviewService;
use OCA\FilesWatermark\Service\ProcessResult;
use OCA\FilesWatermark\Service\ProcessRunner;
use OCA\FilesWatermark\Service\RendererService;
use OCP\IConfig;
use OCP\ITempManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PreviewServiceTest extends TestCase {
	public function testReturnsRenderedPageImageAndRemovesTemporaryFiles(): void {
		$paths = [];
		foreach (['source', 'output', 'image'] as $name) {
			$path = tempnam(sys_get_temp_dir(), 'watermark-preview-' . $name . '-');
			self::assertNotFalse($path);
			$paths[] = $path;
		}
		$temporaryPaths = $paths;
		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->expects(self::exactly(3))
			->method('getTemporaryFile')
			->willReturnCallback(static function () use (&$temporaryPaths): string {
				$path = array_shift($temporaryPaths);
				self::assertIsString($path);
				return $path;
			});

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default): string => $default,
		);
		$runner = new PreviewProcessRunner();
		$renderer = new RendererService(
			new ConfigService($config, $this->createStub(LoggerInterface::class)),
			$runner,
		);
		$service = new PreviewService($tempManager, new DummyPdfService(), $renderer);

		$result = $service->generate(null, null, null, null, null, null, null, true);

		self::assertSame("\xff\xd8preview-image", $result);
		self::assertStringStartsWith('%PDF-1.4', $runner->sourcePdf);
		self::assertCount(5, $runner->lastCommand);
		foreach ($paths as $path) {
			self::assertFileDoesNotExist($path);
		}
	}
}

final class PreviewProcessRunner extends ProcessRunner {
	/** @var list<string> */
	public array $lastCommand = [];
	public string $sourcePdf = '';

	public function run(array $command, string $stdin, int $timeoutSeconds): ProcessResult {
		$this->lastCommand = $command;
		$source = file_get_contents($command[2]);
		if ($source === false) {
			throw new \RuntimeException('Preview source PDF could not be read.');
		}
		$this->sourcePdf = $source;
		file_put_contents($command[3], '%PDF-rendered-preview');
		file_put_contents($command[4], "\xff\xd8preview-image");
		return new ProcessResult(0, '', '');
	}
}
