<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		[
			'name' => 'preview#show',
			'url' => '/admin/preview',
			'verb' => 'GET',
		],
	],
	'ocs' => [
		[
			'name' => 'watermark#create',
			'url' => '/api/v1/watermarks',
			'verb' => 'POST',
		],
	],
];
