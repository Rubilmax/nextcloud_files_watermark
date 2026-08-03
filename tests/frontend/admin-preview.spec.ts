/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const FIELD_VALUES = [
	'/opt/files-watermark-python/bin/python',
	'180',
	'28',
	'#333333',
	'30',
	'30',
	'145',
	'48',
	'78',
]

/** Create the relevant subset of Nextcloud's declarative settings markup. */
function renderAdminSettings(): void {
	document.body.innerHTML = `
		<div id="files_watermark_files_watermark_renderer">
			${FIELD_VALUES.map((value) => `<div class="declarative-form-field"><input value="${value}"></div>`).join('')}
		</div>
		<div
			id="files-watermark-admin-preview"
			data-preview-url="/apps/files_watermark/admin/preview"
			data-loading-text="Rendering"
			data-invalid-text="Invalid"
			data-error-text="Failed">
			<div class="files-watermark-admin-preview__document" aria-busy="true">
				<img class="files-watermark-admin-preview__image" hidden>
			</div>
			<span class="files-watermark-admin-preview__status"></span>
			<a class="files-watermark-admin-preview__open"></a>
		</div>`
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

	it('loads a rendered image while linking to the actual preview PDF', async () => {
		await import('../../src/admin-preview')
		document.dispatchEvent(new Event('DOMContentLoaded'))
		await vi.advanceTimersByTimeAsync(450)

		const image = document.querySelector<HTMLImageElement>('.files-watermark-admin-preview__image')
		const link = document.querySelector<HTMLAnchorElement>('.files-watermark-admin-preview__open')
		expect(image).not.toBeNull()
		expect(link).not.toBeNull()

		const imageUrl = new URL(image!.src)
		const pdfUrl = new URL(link!.href)
		expect(imageUrl.searchParams.get('format')).toBe('image')
		expect(imageUrl.searchParams.get('opacity')).toBe('30')
		expect(pdfUrl.searchParams.has('format')).toBe(false)

		image!.dispatchEvent(new Event('load'))
		expect(image!.hidden).toBe(false)
		expect(document.querySelector('.files-watermark-admin-preview__document')?.getAttribute('aria-busy')).toBe('false')
	})
})
