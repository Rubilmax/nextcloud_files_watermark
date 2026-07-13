/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { INode } from '@nextcloud/files'

declare module '@nextcloud/event-bus' {
	interface NextcloudEvents {
		'files:node:created': INode
		'files:node:updated': INode
	}
}

export {}
