<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark\Exception;

use OCP\AppFramework\Http;
use RuntimeException;

class WatermarkException extends RuntimeException {
	/**
	 * @param Http::STATUS_* $httpStatus
	 */
	public function __construct(
		string $message,
		private readonly string $errorCode,
		private readonly int $httpStatus,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	public function getErrorCode(): string {
		return $this->errorCode;
	}

	/** @return Http::STATUS_* */
	public function getHttpStatus(): int {
		return $this->httpStatus;
	}
}
