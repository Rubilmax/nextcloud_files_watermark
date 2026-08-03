/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPublicShare, normalizeWatermark } from '../../src/services/api'

vi.mock('@nextcloud/axios', () => ({
	default: { post: vi.fn() },
	isAxiosError: vi.fn(),
}))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path: string) => `/ocs/v2.php/${path}`,
}))

describe('sharing API helpers', () => {
	beforeEach(() => vi.clearAllMocks())

	it('normalizes NFC, whitespace, and the 128-code-point limit', () => {
		const value = `  A\u030A   ${'x'.repeat(140)}  `
		const normalized = normalizeWatermark(value)
		expect(normalized.startsWith('Å x')).toBe(true)
		expect([...normalized]).toHaveLength(128)
	})

	it('serializes share options using the types expected by Nextcloud', async () => {
		vi.mocked(axios.post).mockResolvedValue({
			data: { ocs: { data: { url: 'https://cloud.example/s/token' } } },
		})
		await createPublicShare({
			path: '/Report.pdf',
			permissions: 1,
			expireDate: '',
			hideDownload: true,
			sendPasswordByTalk: false,
		})

		expect(axios.post).toHaveBeenCalledWith(
			'/ocs/v2.php/apps/files_sharing/api/v1/shares',
			expect.objectContaining({
				shareType: 3,
				sendPasswordByTalk: undefined,
				attributes: JSON.stringify([{ scope: 'permissions', key: 'download', value: false }]),
			}),
		)
	})

	it('serializes the Talk password option as the literal string expected by Nextcloud', async () => {
		vi.mocked(axios.post).mockResolvedValue({
			data: { ocs: { data: { url: 'https://cloud.example/s/token' } } },
		})
		await createPublicShare({
			path: '/Report.pdf',
			permissions: 1,
			password: 'correct horse battery staple',
			expireDate: '',
			hideDownload: false,
			sendPasswordByTalk: true,
		})

		expect(axios.post).toHaveBeenCalledWith(
			'/ocs/v2.php/apps/files_sharing/api/v1/shares',
			expect.objectContaining({
				sendPasswordByTalk: 'true',
			}),
		)
	})
})
