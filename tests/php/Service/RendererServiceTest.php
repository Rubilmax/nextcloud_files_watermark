<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Service;

use OCA\FilesWatermark\AppInfo\Application;
use OCA\FilesWatermark\Exception\ProcessTimeoutException;
use OCA\FilesWatermark\Exception\WatermarkException;
use OCA\FilesWatermark\Service\ConfigService;
use OCA\FilesWatermark\Service\ProcessResult;
use OCA\FilesWatermark\Service\ProcessRunner;
use OCA\FilesWatermark\Service\RendererService;
use OCP\AppFramework\Http;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class RendererServiceTest extends TestCase {
	/** @return iterable<string, array{int, string, int}> */
	public static function failureProvider(): iterable {
		yield 'encrypted' => [10, 'encrypted_pdf', Http::STATUS_UNPROCESSABLE_ENTITY];
		yield 'malformed' => [11, 'malformed_pdf', Http::STATUS_UNPROCESSABLE_ENTITY];
		yield 'page limit' => [12, 'page_limit_exceeded', Http::STATUS_UNPROCESSABLE_ENTITY];
		yield 'bad config' => [13, 'invalid_render_request', Http::STATUS_BAD_REQUEST];
		yield 'page dimensions' => [14, 'page_too_large', Http::STATUS_UNPROCESSABLE_ENTITY];
		yield 'dependency' => [20, 'renderer_unavailable', Http::STATUS_SERVICE_UNAVAILABLE];
		yield 'unknown' => [99, 'render_failed', Http::STATUS_INTERNAL_SERVER_ERROR];
	}

	#[DataProvider('failureProvider')]
	public function testMapsRendererExitCodes(int $exitCode, string $code, int $status): void {
		$runner = new FakeProcessRunner(new ProcessResult(
			$exitCode,
			'',
			json_encode(['message' => 'Renderer detail'], JSON_THROW_ON_ERROR),
		));
		$renderer = new RendererService($this->createConfig(), $runner);

		try {
			$renderer->render('/input.pdf', '/missing-output.pdf', 'Confidential');
			self::fail('Expected WatermarkException');
		} catch (WatermarkException $exception) {
			self::assertSame($code, $exception->getErrorCode());
			self::assertSame($status, $exception->getHttpStatus());
			self::assertSame('Renderer detail', $exception->getMessage());
		}
	}

	public function testMapsTimeoutToGatewayTimeout(): void {
		$runner = new FakeProcessRunner(new ProcessResult(0, '', ''));
		$runner->timeout = true;
		$renderer = new RendererService($this->createConfig(), $runner);

		try {
			$renderer->render('/input.pdf', '/output.pdf', 'Confidential');
			self::fail('Expected WatermarkException');
		} catch (WatermarkException $exception) {
			self::assertSame('render_timeout', $exception->getErrorCode());
			self::assertSame(Http::STATUS_GATEWAY_TIMEOUT, $exception->getHttpStatus());
		}
	}

	public function testMapsMissingExecutableToServiceUnavailable(): void {
		$runner = new FakeProcessRunner(new ProcessResult(127, '', 'not found'));
		$renderer = new RendererService($this->createConfig(), $runner);

		try {
			$renderer->render('/input.pdf', '/output.pdf', 'Confidential');
			self::fail('Expected WatermarkException');
		} catch (WatermarkException $exception) {
			self::assertSame('renderer_unavailable', $exception->getErrorCode());
			self::assertSame(Http::STATUS_SERVICE_UNAVAILABLE, $exception->getHttpStatus());
		}
	}

	public function testPassesWatermarkAppearanceConfigurationToRenderer(): void {
		$runner = new FakeProcessRunner(new ProcessResult(0, '', ''));
		$runner->createOutput = true;
		$output = tempnam(sys_get_temp_dir(), 'watermark-renderer-test-');
		self::assertNotFalse($output);

		try {
			$renderer = new RendererService($this->createConfig(), $runner);
			$renderer->render('/input.pdf', $output, 'Confidential');
			$input = json_decode($runner->lastInput, true, flags: JSON_THROW_ON_ERROR);

			self::assertSame(ConfigService::DEFAULT_WATERMARK_FONT_SIZE, $input['watermarkFontSize']);
			self::assertSame(ConfigService::DEFAULT_WATERMARK_COLOR, $input['watermarkColor']);
			self::assertSame(ConfigService::DEFAULT_WATERMARK_OPACITY, $input['watermarkOpacityPercent']);
			self::assertSame(ConfigService::DEFAULT_WATERMARK_ANGLE, $input['watermarkAngle']);
			self::assertSame(
				ConfigService::DEFAULT_WATERMARK_MIN_HORIZONTAL_INTERVAL,
				$input['watermarkMinimumHorizontalInterval'],
			);
			self::assertSame(
				ConfigService::DEFAULT_WATERMARK_HORIZONTAL_GAP,
				$input['watermarkHorizontalGap'],
			);
			self::assertSame(
				ConfigService::DEFAULT_WATERMARK_VERTICAL_INTERVAL,
				$input['watermarkVerticalInterval'],
			);
		} finally {
			@unlink($output);
		}
	}

	public function testPreviewUsesUnsavedAppearanceOverrides(): void {
		$runner = new FakeProcessRunner(new ProcessResult(0, '', ''));
		$runner->createOutput = true;
		$output = tempnam(sys_get_temp_dir(), 'watermark-preview-renderer-test-');
		$previewImage = tempnam(sys_get_temp_dir(), 'watermark-preview-image-test-');
		self::assertNotFalse($output);
		self::assertNotFalse($previewImage);

		try {
			$renderer = new RendererService($this->createConfig(), $runner);
			$renderer->renderPreview(
				'/input.pdf',
				$output,
				'Preview',
				42,
				'#abcdef',
				65,
				-15,
				210,
				70,
				120,
				$previewImage,
			);
			$input = json_decode($runner->lastInput, true, flags: JSON_THROW_ON_ERROR);

			self::assertSame(42, $input['watermarkFontSize']);
			self::assertSame('#abcdef', $input['watermarkColor']);
			self::assertSame(65, $input['watermarkOpacityPercent']);
			self::assertSame(-15, $input['watermarkAngle']);
			self::assertSame(210, $input['watermarkMinimumHorizontalInterval']);
			self::assertSame(70, $input['watermarkHorizontalGap']);
			self::assertSame(120, $input['watermarkVerticalInterval']);
			self::assertSame($previewImage, $runner->lastCommand[4]);
			self::assertSame('%JPEG-preview', file_get_contents($previewImage));
		} finally {
			@unlink($output);
			@unlink($previewImage);
		}
	}

	public function testPreviewRejectsInvalidAppearanceBeforeStartingRenderer(): void {
		$runner = new FakeProcessRunner(new ProcessResult(0, '', ''));
		$renderer = new RendererService($this->createConfig(), $runner);

		try {
			$renderer->renderPreview(
				'/input.pdf',
				'/output.pdf',
				'Preview',
				7,
				'#333333',
				30,
				30,
				145,
				48,
				78,
			);
			self::fail('Expected WatermarkException');
		} catch (WatermarkException $exception) {
			self::assertSame('invalid_render_request', $exception->getErrorCode());
			self::assertSame(Http::STATUS_BAD_REQUEST, $exception->getHttpStatus());
			self::assertSame([], $runner->lastCommand);
		}
	}

	public function testAvailabilityChecksPinnedVersionRange(): void {
		$runner = new FakeProcessRunner(new ProcessResult(0, "1.28.0\n", ''));
		$status = (new RendererService($this->createConfig(), $runner))->checkAvailability();

		self::assertTrue($status['available']);
		self::assertSame('1.28.0', $status['version']);
		self::assertSame([
			ConfigService::DEFAULT_PYTHON,
			'-c',
			'import pymupdf; print(pymupdf.__version__)',
		], $runner->lastCommand);
	}

	private function createConfig(): ConfigService {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default): string => $default,
		);
		return new ConfigService($config, $this->createStub(LoggerInterface::class));
	}
}

final class FakeProcessRunner extends ProcessRunner {
	/** @var list<string> */
	public array $lastCommand = [];
	public string $lastInput = '';
	public bool $timeout = false;
	public bool $createOutput = false;

	public function __construct(private readonly ProcessResult $result) {
	}

	public function run(array $command, string $stdin, int $timeoutSeconds): ProcessResult {
		$this->lastCommand = $command;
		$this->lastInput = $stdin;
		if ($this->timeout) {
			throw new ProcessTimeoutException('timeout');
		}
		if ($this->createOutput) {
			file_put_contents($command[3], '%PDF-rendered');
			if (isset($command[4])) {
				file_put_contents($command[4], '%JPEG-preview');
			}
		}
		return $this->result;
	}
}
