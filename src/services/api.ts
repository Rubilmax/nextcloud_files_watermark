/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { INode } from '@nextcloud/files'

import axios, { isAxiosError } from '@nextcloud/axios'
import {
	defaultRemoteURL,
	defaultRootPath,
	getClient,
	getDefaultPropfind,
	resultToNode,
} from '@nextcloud/files/dav'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

export type GeneratedFile = {
	id: string
	path: string
	name: string
	mime: string
	size: number
}

export type ShareOptions = {
	path: string
	permissions: number
	password?: string
	expireDate: string
	label?: string
	note?: string
	hideDownload: boolean
	sendPasswordByTalk: boolean
}

type OcsResponse<T> = {
	ocs: {
		data: T
		meta?: {
			message?: string
		}
	}
}

/**
 * Normalize user-entered watermark text exactly as the server does.
 *
 * @param text
 */
export function normalizeWatermark(text: string): string {
	const normalized = text.normalize('NFC').trim().replace(/\s+/gu, ' ')
	return [...normalized].slice(0, 128).join('')
}

/**
 * Ask the app endpoint to create a watermarked derivative.
 *
 * @param sourceId
 * @param sourcePath
 * @param text
 */
export async function generateWatermarkedFile(
	sourceId: string,
	sourcePath: string,
	text: string,
): Promise<GeneratedFile> {
	const response = await axios.post<OcsResponse<GeneratedFile>>(
		generateOcsUrl('apps/files_watermark/api/v1/watermarks'),
		{ sourceId, sourcePath, text },
	)
	return response.data.ocs.data
}

/**
 * Create an ordinary Nextcloud public-link share.
 *
 * @param options
 */
export async function createPublicShare(options: ShareOptions): Promise<string> {
	const attributes = options.hideDownload
		? JSON.stringify([{ scope: 'permissions', key: 'download', value: false }])
		: undefined
	const response = await axios.post<OcsResponse<{ url: string }>>(
		generateOcsUrl('apps/files_sharing/api/v1/shares'),
		{
			path: options.path,
			shareType: 3,
			permissions: options.permissions,
			password: options.password,
			expireDate: options.expireDate,
			label: options.label,
			note: options.note,
			// Nextcloud's controller accepts the literal string "true", not a JSON boolean.
			sendPasswordByTalk: options.sendPasswordByTalk ? 'true' : undefined,
			attributes,
		},
	)

	if (!response.data.ocs.data.url) {
		throw new Error(t('files_watermark', 'The share API did not return a public URL.'))
	}
	return response.data.ocs.data.url
}

/**
 * Resolve an app response path into the public Nextcloud files Node model.
 *
 * @param path
 */
export async function resolveDavNode(path: string): Promise<INode> {
	const client = getClient()
	const result = await client.stat(`${defaultRootPath}${path}`, {
		details: true,
		data: getDefaultPropfind(),
	})
	const stat = 'data' in result ? result.data : result
	return resultToNode(stat, defaultRootPath, defaultRemoteURL)
}

/**
 * Extract a useful, user-safe message from OCS or JavaScript errors.
 *
 * @param error
 */
export function errorMessage(error: unknown): string {
	if (isAxiosError(error)) {
		const data = error.response?.data as {
			ocs?: {
				data?: { error?: { message?: string } }
				meta?: { message?: string }
			}
		} | undefined
		return data?.ocs?.data?.error?.message
			?? data?.ocs?.meta?.message
			?? error.message
	}
	if (error instanceof Error) {
		return error.message
	}
	return t('files_watermark', 'An unexpected error occurred.')
}

/**
 * Copy text using the modern Clipboard API with a browser fallback.
 *
 * @param text
 */
export async function copyText(text: string): Promise<void> {
	if (navigator.clipboard?.writeText) {
		await navigator.clipboard.writeText(text)
		return
	}

	const textarea = document.createElement('textarea')
	textarea.value = text
	textarea.style.position = 'fixed'
	textarea.style.opacity = '0'
	document.body.appendChild(textarea)
	textarea.select()
	const copied = document.execCommand('copy')
	textarea.remove()
	if (!copied) {
		throw new Error(t('files_watermark', 'The URL could not be copied.'))
	}
}
