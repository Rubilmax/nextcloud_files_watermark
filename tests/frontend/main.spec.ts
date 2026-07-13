/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { INode } from '@nextcloud/files'

import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
	registerSidebarSection: vi.fn(),
}))

vi.mock('@nextcloud/sharing/ui', () => ({
	registerSidebarSection: mocks.registerSidebarSection,
}))
vi.mock('../../src/WatermarkShareSection.vue', () => ({
	default: { template: '<div />' },
}))

describe('sharing sidebar registration', () => {
	beforeEach(() => {
		mocks.registerSidebarSection.mockClear()
		vi.resetModules()
	})

	it('registers one section enabled only for PDF files', async () => {
		const { sidebarSection } = await import('../../src/main')

		expect(mocks.registerSidebarSection).toHaveBeenCalledWith(sidebarSection)
		expect(sidebarSection.enabled({ mime: 'application/pdf' } as INode)).toBe(true)
		expect(sidebarSection.enabled({ mime: 'image/png' } as INode)).toBe(false)
	})
})
