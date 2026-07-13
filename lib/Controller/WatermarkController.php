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
					'message' => $exception->getMessage(),
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
					'message' => 'The watermarked PDF could not be generated.',
				],
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
