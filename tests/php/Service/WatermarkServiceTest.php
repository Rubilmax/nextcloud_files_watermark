<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Tests\Service;

use OCA\FilesWatermark\Exception\WatermarkException;
use OCA\FilesWatermark\Service\ConfigService;
use OCA\FilesWatermark\Service\FilenameService;
use OCA\FilesWatermark\Service\ProcessResult;
use OCA\FilesWatermark\Service\ProcessRunner;
use OCA\FilesWatermark\Service\RendererService;
use OCA\FilesWatermark\Service\TextNormalizer;
use OCA\FilesWatermark\Service\WatermarkService;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\AppFramework\Services\IAppConfig;
use OCP\ITempManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class WatermarkServiceTest extends TestCase {
	public function testRequiresAuthentication(): void {
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);
		$service = $this->createService($userSession);

		$this->assertWatermarkError(
			static fn () => $service->generate('42', '/Reports/File.pdf', 'Confidential'),
			'authentication_required',
			Http::STATUS_UNAUTHORIZED,
		);
	}

	public function testRejectsStaleSourceId(): void {
		[$service] = $this->createPopulatedService(sourceId: 41);

		$this->assertWatermarkError(
			static fn () => $service->generate('42', '/Reports/File.pdf', 'Confidential'),
			'source_id_mismatch',
			Http::STATUS_CONFLICT,
		);
	}

	public function testRejectsNonPdfBeforeCreatingTemporaryFiles(): void {
		[$service, $tempManager] = $this->createPopulatedService(mime: 'text/plain');

		$this->assertWatermarkError(
			static fn () => $service->generate('42', '/Reports/File.pdf', 'Confidential'),
			'unsupported_media_type',
			Http::STATUS_UNSUPPORTED_MEDIA_TYPE,
		);
		self::assertSame([], $tempManager->createdPaths);
	}

	public function testRejectsOversizedSource(): void {
		[$service] = $this->createPopulatedService(size: 51 * 1024 * 1024);

		$this->assertWatermarkError(
			static fn () => $service->generate('42', '/Reports/File.pdf', 'Confidential'),
			'source_too_large',
			Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
		);
	}

	public function testEnforcesSourceLimitWhileCopyingAnUnderreportedStream(): void {
		[$service, $tempManager] = $this->createPopulatedService(
			size: 100,
			sourceContent: str_repeat('x', 1024 * 1024 + 1),
			maximumSourceSizeMiB: 1,
		);

		$this->assertWatermarkError(
			static fn () => $service->generate('42', '/Reports/File.pdf', 'Confidential'),
			'source_too_large',
			Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
		);
		foreach ($tempManager->createdPaths as $path) {
			self::assertFileDoesNotExist($path);
		}
	}

	public function testRejectsUnreadableSourceAndUncreatableParent(): void {
		[$unreadable] = $this->createPopulatedService(readable: false);
		try {
			$unreadable->generate('42', '/Reports/File.pdf', 'Confidential');
			self::fail('Expected unreadable failure');
		} catch (WatermarkException $exception) {
			self::assertSame('source_not_readable', $exception->getErrorCode());
		}

		[$uncreatable] = $this->createPopulatedService(creatable: false);
		$this->assertWatermarkError(
			static fn () => $uncreatable->generate('42', '/Reports/File.pdf', 'Confidential'),
			'parent_not_creatable',
			Http::STATUS_FORBIDDEN,
		);
	}

	public function testStreamsRemoteSourceAndCreatesCollisionSafeDerivative(): void {
		[$service, $tempManager] = $this->createPopulatedService(
			generatedName: 'File - Confidential (2).pdf',
		);

		$result = $service->generate('42', '/Reports/File.pdf', "  Confidential  ");

		self::assertSame([
			'id' => '84',
			'path' => '/Reports/File - Confidential (2).pdf',
			'name' => 'File - Confidential (2).pdf',
			'mime' => 'application/pdf',
			'size' => 16,
		], $result);
		self::assertCount(2, $tempManager->createdPaths);
		foreach ($tempManager->createdPaths as $path) {
			self::assertFileDoesNotExist($path);
		}
	}

	private function assertWatermarkError(callable $operation, string $code, int $status): void {
		try {
			$operation();
			self::fail('Expected WatermarkException with code ' . $code);
		} catch (WatermarkException $exception) {
			self::assertSame($code, $exception->getErrorCode());
			self::assertSame($status, $exception->getHttpStatus());
			self::assertNotSame('', $exception->getMessage());
		}
	}

	/**
	 * @return array{WatermarkService, TestTempManager, Folder}
	 */
	private function createPopulatedService(
		int $sourceId = 42,
		string $mime = 'application/pdf',
		int $size = 100,
		bool $readable = true,
		bool $creatable = true,
		string $sourceContent = '%PDF-remote-stream',
		int $maximumSourceSizeMiB = 50,
		?string $generatedName = null,
	): array {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$parent = $this->createMock(Folder::class);
		$parent->method('isCreatable')->willReturn($creatable);
		$parent->method('getNonExistingName')->willReturnCallback(
			static fn (string $name): string => $generatedName ?? $name,
		);

		$source = $this->createMock(File::class);
		$source->method('getId')->willReturn($sourceId);
		$source->method('getPath')->willReturn('/alice/files/Reports/File.pdf');
		$source->method('getName')->willReturn('File.pdf');
		$source->method('getMimeType')->willReturn($mime);
		$source->method('getSize')->willReturn($size);
		$source->method('isReadable')->willReturn($readable);
		$source->method('getParent')->willReturn($parent);
		$source->method('fopen')->willReturnCallback(static function () use ($sourceContent) {
			$stream = fopen('php://temp', 'w+b');
			fwrite($stream, $sourceContent);
			rewind($stream);
			return $stream;
		});

		$generated = $this->createMock(File::class);
		$generated->method('getId')->willReturn(84);
		$generated->method('getPath')->willReturn('/alice/files/Reports/File - Confidential (2).pdf');
		$generated->method('getName')->willReturn('File - Confidential (2).pdf');
		$generated->method('getMimeType')->willReturn('application/pdf');
		$generated->method('getSize')->willReturn(16);
		$parent->method('newFile')->willReturnCallback(static function (string $name, $content) use ($generated): File {
			self::assertSame('File - Confidential (2).pdf', $name);
			self::assertIsResource($content);
			self::assertSame('%PDF-rendered!!', stream_get_contents($content));
			fclose($content);
			return $generated;
		});

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->with('Reports/File.pdf')->willReturn($source);
		$userFolder->method('getRelativePath')->willReturnCallback(
			static fn (string $path): ?string => match ($path) {
				'/alice/files/Reports/File.pdf' => 'Reports/File.pdf',
				'/alice/files/Reports/File - Confidential (2).pdf' => 'Reports/File - Confidential (2).pdf',
				default => null,
			},
		);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->with('alice')->willReturn($userFolder);
		$tempManager = new TestTempManager();

		return [
			$this->createService($userSession, $root, $tempManager, $maximumSourceSizeMiB),
			$tempManager,
			$parent,
		];
	}

	private function createService(
		IUserSession $userSession,
		?IRootFolder $rootFolder = null,
		?ITempManager $tempManager = null,
		int $maximumSourceSizeMiB = 50,
	): WatermarkService {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getAppValueString')->willReturnCallback(
			static fn (string $key, string $default): string => $key === ConfigService::KEY_MAX_SOURCE_MIB
				? (string)$maximumSourceSizeMiB
				: $default,
		);
		$configService = new ConfigService($config, $this->createStub(LoggerInterface::class));
		$runner = new class extends ProcessRunner {
			public function run(array $command, string $stdin, int $timeoutSeconds): ProcessResult {
				file_put_contents($command[3], '%PDF-rendered!!');
				return new ProcessResult(0, '{"pages":1}', '');
			}
		};

		return new WatermarkService(
			$userSession,
			$rootFolder ?? $this->createStub(IRootFolder::class),
			$tempManager ?? new TestTempManager(),
			$configService,
			new TextNormalizer(),
			new FilenameService(),
			$this->createFilenameValidator(),
			new RendererService($configService, $runner),
		);
	}

	private function createFilenameValidator(): IFilenameValidator {
		$validator = $this->createMock(IFilenameValidator::class);
		$validator->method('sanitizeFilename')->willReturnCallback(
			static fn (string $name): string => $name,
		);
		return $validator;
	}
}

final class TestTempManager implements ITempManager {
	/** @var list<string> */
	public array $createdPaths = [];

	public function getTemporaryFile(string $postFix = ''): string|false {
		$path = tempnam(sys_get_temp_dir(), 'files-watermark-test-');
		if ($path !== false) {
			$this->createdPaths[] = $path;
		}
		return $path;
	}

	public function getTemporaryFolder(string $postFix = ''): string|false {
		return false;
	}

	public function clean(): void {
	}

	public function cleanOld(): void {
	}

	public function getTempBaseDir(): string {
		return sys_get_temp_dir();
	}
}
