<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesWatermark;

/**
 * @psalm-type FilesWatermarkGeneratedFile = array{
 *     id: string,
 *     path: string,
 *     name: string,
 *     mime: string,
 *     size: int|float,
 * }
 * @psalm-type FilesWatermarkError = array{
 *     error: array{code: string, message: string},
 * }
 */
final class ResponseDefinitions {
}
