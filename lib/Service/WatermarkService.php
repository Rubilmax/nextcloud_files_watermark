<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Service;

use OCA\FilesWatermark\Exception\WatermarkException;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\ITempManager;
use OCP\IUserSession;
use OCP\Lock\LockedException;

final class WatermarkService {
	public function __construct(
		private readonly IUserSession $userSession,
		private readonly IRootFolder $rootFolder,
		private readonly ITempManager $tempManager,
		private readonly ConfigService $config,
		private readonly TextNormalizer $textNormalizer,
		private readonly FilenameService $filenameService,
		private readonly IFilenameValidator $filenameValidator,
		private readonly RendererService $renderer,
	) {
	}

	/**
	 * @return array{id: string, path: string, name: string, mime: string, size: int|float}
	 */
	public function generate(string $sourceId, string $sourcePath, string $text): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new WatermarkException(
				'Authentication is required.',
				'authentication_required',
				Http::STATUS_UNAUTHORIZED,
			);
		}

		$text = $this->textNormalizer->normalize($text);
		if ($text === '') {
			throw new WatermarkException(
				'Watermark text must not be empty.',
				'empty_watermark',
				Http::STATUS_BAD_REQUEST,
			);
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$node = $this->resolveSource($userFolder, $sourceId, $sourcePath);
		$this->validateSource($node);
		$parent = $node->getParent();
		if (!$parent->isCreatable()) {
			throw new WatermarkException(
				'You cannot create a watermarked copy in the source folder.',
				'parent_not_creatable',
				Http::STATUS_FORBIDDEN,
			);
		}
		$name = $this->buildGeneratedName($parent, $node->getName(), $text);

		$inputPath = $this->createTemporaryPath('.source.pdf');
		$outputPath = null;
		try {
			$this->copySourceToTemporaryFile($node, $inputPath);
			$outputPath = $this->createTemporaryPath('.watermarked.pdf');
			$this->renderer->render($inputPath, $outputPath, $text);
			$generated = $this->copyResultToFolder($parent, $name, $outputPath);
			$relativePath = $userFolder->getRelativePath($generated->getPath());

			if ($relativePath === null) {
				$generated->delete();
				throw new WatermarkException(
					'The generated file path could not be resolved.',
					'generated_path_unavailable',
					Http::STATUS_INTERNAL_SERVER_ERROR,
				);
			}

			return [
				'id' => (string)$generated->getId(),
				'path' => '/' . ltrim($relativePath, '/'),
				'name' => $generated->getName(),
				'mime' => $generated->getMimeType(),
				'size' => $generated->getSize(),
			];
		} finally {
			$this->removeTemporaryFile($inputPath);
			if ($outputPath !== null) {
				$this->removeTemporaryFile($outputPath);
			}
		}
	}

	private function resolveSource(Folder $userFolder, string $sourceId, string $sourcePath): File {
		$relativePath = $this->validateRequestPath($sourcePath);
		try {
			$node = $userFolder->get($relativePath);
		} catch (NotFoundException $exception) {
			throw new WatermarkException(
				'The source file no longer exists at that path.',
				'source_not_found',
				Http::STATUS_NOT_FOUND,
				$exception,
			);
		} catch (NotPermittedException $exception) {
			throw new WatermarkException(
				'You do not have permission to access the source path.',
				'source_access_denied',
				Http::STATUS_FORBIDDEN,
				$exception,
			);
		}

		if (!$node instanceof File) {
			throw new WatermarkException(
				'The source must be a PDF file.',
				'source_not_file',
				Http::STATUS_UNSUPPORTED_MEDIA_TYPE,
			);
		}

		$actualPath = $userFolder->getRelativePath($node->getPath());
		if ($actualPath === null || '/' . ltrim($actualPath, '/') !== '/' . $relativePath) {
			throw new WatermarkException(
				'The source path is stale.',
				'source_path_mismatch',
				Http::STATUS_CONFLICT,
			);
		}

		if ($sourceId === '' || !hash_equals((string)$node->getId(), $sourceId)) {
			throw new WatermarkException(
				'The source ID and path no longer identify the same file.',
				'source_id_mismatch',
				Http::STATUS_CONFLICT,
			);
		}

		return $node;
	}

	private function validateSource(File $source): void {
		if (!$source->isReadable()) {
			throw new WatermarkException(
				'You do not have permission to read the source PDF.',
				'source_not_readable',
				Http::STATUS_FORBIDDEN,
			);
		}

		if ($source->getMimeType() !== 'application/pdf') {
			throw new WatermarkException(
				'Only PDF files are supported.',
				'unsupported_media_type',
				Http::STATUS_UNSUPPORTED_MEDIA_TYPE,
			);
		}

		if ($source->getSize() > $this->config->getMaximumSourceSizeBytes()) {
			throw new WatermarkException(
				sprintf(
					'The PDF exceeds the configured %d MiB source-size limit.',
					$this->config->getMaximumSourceSizeMiB(),
				),
				'source_too_large',
				Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
			);
		}
	}

	private function validateRequestPath(string $sourcePath): string {
		if ($sourcePath === '' || str_contains($sourcePath, "\0") || str_contains($sourcePath, '\\')) {
			throw new WatermarkException('Invalid source path.', 'invalid_source_path', Http::STATUS_BAD_REQUEST);
		}

		$relativePath = ltrim($sourcePath, '/');
		$segments = explode('/', $relativePath);
		if ($relativePath === '' || in_array('', $segments, true)
			|| in_array('.', $segments, true) || in_array('..', $segments, true)) {
			throw new WatermarkException('Invalid source path.', 'invalid_source_path', Http::STATUS_BAD_REQUEST);
		}

		return $relativePath;
	}

	private function buildGeneratedName(Folder $parent, string $sourceName, string $text): string {
		try {
			$name = $this->filenameValidator->sanitizeFilename(
				$this->filenameService->build($sourceName, $text),
			);
			if (!str_ends_with(mb_strtolower($name, 'UTF-8'), '.pdf')) {
				throw new InvalidPathException('The PDF extension is not allowed by the server configuration.');
			}
			$this->filenameValidator->validateFilename($name);
			$parent->verifyPath($name);

			$name = $parent->getNonExistingName($name);
			$this->filenameValidator->validateFilename($name);
			$parent->verifyPath($name);
			return $name;
		} catch (NotPermittedException $exception) {
			throw new WatermarkException(
				'You cannot create a watermarked copy in the source folder.',
				'parent_not_creatable',
				Http::STATUS_FORBIDDEN,
				$exception,
			);
		} catch (InvalidPathException|\InvalidArgumentException $exception) {
			throw new WatermarkException(
				'The generated PDF name is not allowed by the server or storage configuration.',
				'generated_filename_invalid',
				Http::STATUS_UNPROCESSABLE_ENTITY,
				$exception,
			);
		}
	}

	private function createTemporaryPath(string $postfix): string {
		$path = $this->tempManager->getTemporaryFile($postfix);
		if ($path === false) {
			throw new WatermarkException(
				'Temporary storage is unavailable.',
				'temporary_storage_unavailable',
				Http::STATUS_SERVICE_UNAVAILABLE,
			);
		}

		return $path;
	}

	private function copySourceToTemporaryFile(File $source, string $temporaryPath): void {
		try {
			$sourceStream = $source->fopen('r');
		} catch (NotPermittedException $exception) {
			throw new WatermarkException(
				'You do not have permission to read the source PDF.',
				'source_access_denied',
				Http::STATUS_FORBIDDEN,
				$exception,
			);
		} catch (LockedException $exception) {
			throw new WatermarkException(
				'The source PDF is currently locked.',
				'source_locked',
				Http::STATUS_LOCKED,
				$exception,
			);
		}
		$temporaryStream = fopen($temporaryPath, 'wb');
		if (!is_resource($sourceStream) || !is_resource($temporaryStream)) {
			if (is_resource($sourceStream)) {
				fclose($sourceStream);
			}
			if (is_resource($temporaryStream)) {
				fclose($temporaryStream);
			}
			throw new WatermarkException(
				'The source stream could not be opened.',
				'source_stream_unavailable',
				Http::STATUS_UNPROCESSABLE_ENTITY,
			);
		}

		try {
			$maximumBytes = $this->config->getMaximumSourceSizeBytes();
			$copiedBytes = stream_copy_to_stream($sourceStream, $temporaryStream, $maximumBytes + 1);
			if ($copiedBytes === false) {
				throw new WatermarkException(
					'The source PDF could not be copied to temporary storage.',
					'source_stream_failed',
					Http::STATUS_UNPROCESSABLE_ENTITY,
				);
			}
			if ($copiedBytes > $maximumBytes) {
				throw new WatermarkException(
					sprintf(
						'The PDF exceeds the configured %d MiB source-size limit.',
						$this->config->getMaximumSourceSizeMiB(),
					),
					'source_too_large',
					Http::STATUS_REQUEST_ENTITY_TOO_LARGE,
				);
			}
		} finally {
			fclose($sourceStream);
			fclose($temporaryStream);
		}
	}

	private function copyResultToFolder(Folder $parent, string $name, string $outputPath): File {
		$outputStream = fopen($outputPath, 'rb');
		if (!is_resource($outputStream)) {
			throw new WatermarkException(
				'The rendered PDF could not be opened.',
				'render_output_unavailable',
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}

		try {
			return $parent->newFile($name, $outputStream);
		} catch (NotPermittedException $exception) {
			throw new WatermarkException(
				'You cannot create a watermarked copy in the source folder.',
				'parent_not_creatable',
				Http::STATUS_FORBIDDEN,
				$exception,
			);
		} catch (\Throwable $exception) {
			if ($exception instanceof InvalidPathException) {
				throw new WatermarkException(
					'The generated PDF name is not allowed by the target storage.',
					'generated_filename_invalid',
					Http::STATUS_UNPROCESSABLE_ENTITY,
					$exception,
				);
			}
			if ($exception instanceof LockedException) {
				throw new WatermarkException(
					'The target folder is currently locked.',
					'target_locked',
					Http::STATUS_LOCKED,
					$exception,
				);
			}
			throw new WatermarkException(
				'The watermarked PDF could not be saved beside the source.',
				'generated_file_write_failed',
				Http::STATUS_INSUFFICIENT_STORAGE,
				$exception,
			);
		} finally {
			$this->closeStreamIfOpen($outputStream);
		}
	}

	/**
	 * Storage backends are allowed to consume and close streams passed to newFile().
	 */
	private function closeStreamIfOpen(mixed $stream): void {
		if (is_resource($stream)) {
			fclose($stream);
		}
	}

	private function removeTemporaryFile(string $path): void {
		if (is_file($path)) {
			@unlink($path);
		}
	}
}
