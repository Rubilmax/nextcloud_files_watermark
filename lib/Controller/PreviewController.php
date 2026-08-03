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
	#[UserRateLimit(limit: 60, period: 60)]
	public function show(
		?int $fontSize = null,
		?string $color = null,
		?int $opacity = null,
		?int $angle = null,
		?int $minimumHorizontalInterval = null,
		?int $horizontalGap = null,
		?int $verticalInterval = null,
	): DataDisplayResponse {
		try {
			$pdf = $this->preview->generate(
				$fontSize,
				$color,
				$opacity,
				$angle,
				$minimumHorizontalInterval,
				$horizontalGap,
				$verticalInterval,
			);
			$response = new DataDisplayResponse($pdf, Http::STATUS_OK, [
				'Content-Type' => 'application/pdf',
				'Cache-Control' => 'no-store, no-cache, must-revalidate',
				'X-Content-Type-Options' => 'nosniff',
			]);
			$response->addHeader('Content-Disposition', 'inline; filename="watermark-preview.pdf"');
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
