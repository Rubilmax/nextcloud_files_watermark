<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Controller;

use OCA\FilesWatermark\AppInfo\Application;
use OCA\FilesWatermark\Exception\WatermarkException;
use OCA\FilesWatermark\Service\PreviewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class PreviewController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly PreviewService $preview,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/** @return DataDisplayResponse<Http::STATUS_*, array<string, string>> */
	#[NoCSRFRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	public function show(
		?int $fontSize = null,
		?string $color = null,
		?int $opacity = null,
		?int $angle = null,
		?int $minimumHorizontalInterval = null,
		?int $horizontalGap = null,
		?int $verticalInterval = null,
		string $format = 'pdf',
	): DataDisplayResponse {
		if (!in_array($format, ['pdf', 'image'], true)) {
			return $this->errorResponse('Invalid preview format.', Http::STATUS_BAD_REQUEST);
		}

		try {
			$data = $this->preview->generate(
				$fontSize,
				$color,
				$opacity,
				$angle,
				$minimumHorizontalInterval,
				$horizontalGap,
				$verticalInterval,
				$format === 'image',
			);
			$response = new DataDisplayResponse($data, Http::STATUS_OK, [
				'Content-Type' => $format === 'image' ? 'image/jpeg' : 'application/pdf',
				'Cache-Control' => 'no-store, no-cache, must-revalidate',
				'X-Content-Type-Options' => 'nosniff',
			]);
			$filename = $format === 'image' ? 'watermark-preview.jpg' : 'watermark-preview.pdf';
			$response->addHeader('Content-Disposition', sprintf('inline; filename="%s"', $filename));
			return $response;
		} catch (WatermarkException $exception) {
			$this->logger->warning('Watermark preview rendering failed.', [
				'app' => Application::APP_ID,
				'code' => $exception->getErrorCode(),
				'exception' => $exception,
			]);
			return $this->errorResponse($exception->getMessage(), $exception->getHttpStatus());
		} catch (\Throwable $exception) {
			$this->logger->error('Unexpected watermark preview failure.', [
				'app' => Application::APP_ID,
				'exception' => $exception,
			]);
			return $this->errorResponse(
				'The watermarked PDF preview could not be generated.',
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}
	}

	/**
	 * @param Http::STATUS_* $status
	 * @return DataDisplayResponse<Http::STATUS_*, array<string, string>>
	 */
	private function errorResponse(string $message, int $status): DataDisplayResponse {
		return new DataDisplayResponse($message, $status, [
			'Content-Type' => 'text/plain; charset=utf-8',
			'Cache-Control' => 'no-store, no-cache, must-revalidate',
			'X-Content-Type-Options' => 'nosniff',
		]);
	}
}
