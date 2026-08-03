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
	public bool $timeout = false;

	public function __construct(private readonly ProcessResult $result) {
	}

	public function run(array $command, string $stdin, int $timeoutSeconds): ProcessResult {
		$this->lastCommand = $command;
		if ($this->timeout) {
			throw new ProcessTimeoutException('timeout');
		}
		return $this->result;
	}
}
