<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Controller;

use OCA\FilesWatermark\AppInfo\Application;
use OCA\FilesWatermark\Exception\WatermarkException;
use OCA\FilesWatermark\ResponseDefinitions;
use OCA\FilesWatermark\Service\WatermarkService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type FilesWatermarkGeneratedFile from ResponseDefinitions
 * @psalm-import-type FilesWatermarkError from ResponseDefinitions
 */
final class WatermarkController extends OCSController {
	public function __construct(
		IRequest $request,
		private readonly WatermarkService $watermarks,
		private readonly LoggerInterface $logger,
		private readonly IL10N $l10n,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Rasterize a PDF with a visible watermark and save the derivative beside it.
	 *
	 * @param string $sourceId Current Nextcloud file ID
	 * @param string $sourcePath Current user-relative path
	 * @param string $text Watermark text, normalized to at most 128 characters
	 * @return DataResponse<Http::STATUS_*, FilesWatermarkGeneratedFile|FilesWatermarkError, array{}>
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 5, period: 60)]
	public function create(string $sourceId, string $sourcePath, string $text): DataResponse {
		try {
			return new DataResponse($this->watermarks->generate($sourceId, $sourcePath, $text));
		} catch (WatermarkException $exception) {
			$this->logger->warning('Watermark generation request failed.', [
				'app' => Application::APP_ID,
				'code' => $exception->getErrorCode(),
				'exception' => $exception,
			]);
			return new DataResponse([
				'error' => [
					'code' => $exception->getErrorCode(),
					'message' => $this->translateError($exception),
				],
			], $exception->getHttpStatus());
		} catch (\Throwable $exception) {
			$this->logger->error('Unexpected watermark generation failure.', [
				'app' => Application::APP_ID,
				'exception' => $exception,
			]);
			return new DataResponse([
				'error' => [
					'code' => 'internal_error',
					'message' => $this->l10n->t('The watermarked PDF could not be generated.'),
				],
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function translateError(WatermarkException $exception): string {
		return match ($exception->getErrorCode()) {
			'authentication_required' => $this->l10n->t('Authentication is required.'),
			'empty_watermark' => $this->l10n->t('Watermark text must not be empty.'),
			'parent_not_creatable' => $this->l10n->t('You cannot create a watermarked copy in the source folder.'),
			'generated_path_unavailable' => $this->l10n->t('The generated file path could not be resolved.'),
			'source_not_found' => $this->l10n->t('The source file no longer exists at that path.'),
			'source_access_denied' => $this->l10n->t('You do not have permission to access the source PDF.'),
			'source_not_file' => $this->l10n->t('The source must be a PDF file.'),
			'source_path_mismatch' => $this->l10n->t('The source path is stale.'),
			'source_id_mismatch' => $this->l10n->t('The source ID and path no longer identify the same file.'),
			'source_not_readable' => $this->l10n->t('You do not have permission to read the source PDF.'),
			'unsupported_media_type' => $this->l10n->t('Only PDF files are supported.'),
			'source_too_large' => $this->l10n->t('The PDF exceeds the configured source-size limit.'),
			'invalid_source_path' => $this->l10n->t('Invalid source path.'),
			'generated_filename_invalid' => $this->l10n->t('The generated PDF name is not allowed by the server or storage configuration.'),
			'temporary_storage_unavailable' => $this->l10n->t('Temporary storage is unavailable.'),
			'source_locked' => $this->l10n->t('The source PDF is currently locked.'),
			'source_stream_unavailable' => $this->l10n->t('The source stream could not be opened.'),
			'source_stream_failed' => $this->l10n->t('The source PDF could not be copied to temporary storage.'),
			'render_timeout' => $this->l10n->t('Rendering timed out. The source may be too complex for the configured limit.'),
			'renderer_unavailable' => $this->l10n->t('The local PDF renderer is unavailable.'),
			'encrypted_pdf' => $this->l10n->t('Password-encrypted PDFs are not supported.'),
			'malformed_pdf' => $this->l10n->t('The PDF is malformed or cannot be rendered.'),
			'page_limit_exceeded' => $this->l10n->t('The PDF exceeds the configured page limit.'),
			'invalid_render_request' => $this->l10n->t('The renderer rejected the request.'),
			'page_too_large' => $this->l10n->t('A PDF page is too large to rasterize safely.'),
			'render_failed' => $this->l10n->t('The PDF renderer failed.'),
			'render_output_unavailable' => $this->l10n->t('The rendered PDF could not be opened.'),
			'target_locked' => $this->l10n->t('The target folder is currently locked.'),
			'generated_file_write_failed' => $this->l10n->t('The watermarked PDF could not be saved beside the source.'),
			default => $this->l10n->t('The watermarked PDF could not be generated.'),
		};
	}
}
