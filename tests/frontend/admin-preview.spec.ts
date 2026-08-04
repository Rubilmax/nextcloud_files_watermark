/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/** Create the relevant subset of Nextcloud's declarative settings markup. */
function renderAdminSettings(): void {
	document.body.innerHTML = `
		<div class="settings-container">
			<div class="declarative-settings-section">
				<div class="declarative-form-field"><input value="30"></div>
			</div>
			<div
				id="files-watermark-admin-preview"
				data-preview-url="/apps/files_watermark/admin/preview"
				data-loading-text="Rendering"
				data-error-text="Failed">
				<div class="files-watermark-admin-preview__document" aria-busy="true">
					<img class="files-watermark-admin-preview__image" src="/apps/files_watermark/admin/preview?format=image">
				</div>
				<span class="files-watermark-admin-preview__status">Rendering</span>
				<a class="files-watermark-admin-preview__open" href="/apps/files_watermark/admin/preview"></a>
			</div>
		</div>
	`
}

describe('admin watermark preview', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.resetModules()
		renderAdminSettings()
	})

	afterEach(() => {
		vi.useRealTimers()
		document.body.replaceChildren()
	})

	it('loads the initial server-rendered preview without inspecting form values', async () => {
		await import('../../src/admin-preview')
		document.dispatchEvent(new Event('DOMContentLoaded'))

		const image = document.querySelector<HTMLImageElement>('.files-watermark-admin-preview__image')
		const link = document.querySelector<HTMLAnchorElement>('.files-watermark-admin-preview__open')
		expect(image).not.toBeNull()
		expect(link).not.toBeNull()

		const imageUrl = new URL(image!.src)
		const pdfUrl = new URL(link!.href)
		expect(imageUrl.searchParams.get('format')).toBe('image')
		expect(imageUrl.searchParams.has('opacity')).toBe(false)
		expect(pdfUrl.searchParams.has('format')).toBe(false)

		image!.dispatchEvent(new Event('load'))
		expect(image!.hidden).toBe(false)
		expect(document.querySelector('.files-watermark-admin-preview__document')?.getAttribute('aria-busy')).toBe('false')
	})

	it('refreshes from saved server settings after core autosave settles', async () => {
		vi.setSystemTime(new Date('2026-08-03T12:00:00Z'))
		await import('../../src/admin-preview')
		document.dispatchEvent(new Event('DOMContentLoaded'))

		const input = document.querySelector<HTMLInputElement>('input')!
		input.value = '45'
		input.dispatchEvent(new Event('input', { bubbles: true }))

		expect(document.querySelector('.files-watermark-admin-preview__status')?.textContent).toBe('Rendering')
		expect(document.querySelector('.files-watermark-admin-preview__document')?.getAttribute('aria-busy')).toBe('true')
		await vi.advanceTimersByTimeAsync(1800)

		const image = document.querySelector<HTMLImageElement>('.files-watermark-admin-preview__image')!
		const link = document.querySelector<HTMLAnchorElement>('.files-watermark-admin-preview__open')!
		const imageUrl = new URL(image.src)
		const pdfUrl = new URL(link.href)
		expect(imageUrl.searchParams.get('format')).toBe('image')
		expect(imageUrl.searchParams.has('opacity')).toBe(false)
		expect(imageUrl.searchParams.get('_')).toBe('1785758401800')
		expect(pdfUrl.searchParams.get('_')).toBe('1785758401800')
	})
})
