/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './admin-preview.scss'

interface PreviewField {
	index: number
	parameter: string
	isValid: (value: string) => boolean
}

const SETTINGS_FORM_ID = 'files_watermark_files_watermark_renderer'
const PREVIEW_ROOT_ID = 'files-watermark-admin-preview'
const REFRESH_DELAY_MS = 450

/**
 * Build a validator for integer-valued settings.
 *
 * @param minimum Inclusive lower bound
 * @param maximum Inclusive upper bound
 */
function integerInRange(minimum: number, maximum: number): (value: string) => boolean {
	return (value: string): boolean => {
		if (!/^-?\d+$/.test(value)) {
			return false
		}

		const parsed = Number(value)
		return Number.isSafeInteger(parsed) && parsed >= minimum && parsed <= maximum
	}
}

const PREVIEW_FIELDS: PreviewField[] = [
	{ index: 2, parameter: 'fontSize', isValid: integerInRange(8, 144) },
	{ index: 3, parameter: 'color', isValid: (value) => /^#[0-9a-f]{6}$/i.test(value) },
	{ index: 4, parameter: 'opacity', isValid: integerInRange(1, 100) },
	{ index: 5, parameter: 'angle', isValid: integerInRange(-180, 180) },
	{ index: 6, parameter: 'minimumHorizontalInterval', isValid: integerInRange(20, 2000) },
	{ index: 7, parameter: 'horizontalGap', isValid: integerInRange(0, 1000) },
	{ index: 8, parameter: 'verticalInterval', isValid: integerInRange(20, 2000) },
]

/** Connect the preview to the declarative appearance inputs. */
function initializePreview(): void {
	const root = document.getElementById(PREVIEW_ROOT_ID)
	const form = document.getElementById(SETTINGS_FORM_ID)
	if (!(root instanceof HTMLElement) || !(form instanceof HTMLElement)) {
		return
	}

	const previewUrl = root.dataset.previewUrl
	const iframe = root.querySelector<HTMLIFrameElement>('.files-watermark-admin-preview__frame')
	const documentContainer = root.querySelector<HTMLElement>('.files-watermark-admin-preview__document')
	const status = root.querySelector<HTMLElement>('.files-watermark-admin-preview__status')
	const openLink = root.querySelector<HTMLAnchorElement>('.files-watermark-admin-preview__open')
	if (!previewUrl || !iframe || !documentContainer || !status || !openLink) {
		return
	}

	let refreshTimer: ReturnType<typeof setTimeout> | undefined

	const refresh = (): void => {
		const fields = form.querySelectorAll<HTMLElement>('.declarative-form-field')
		const url = new URL(previewUrl, window.location.href)

		for (const field of PREVIEW_FIELDS) {
			const input = fields[field.index]?.querySelector<HTMLInputElement>('input')
			const value = input?.value.trim() ?? ''
			if (!field.isValid(value)) {
				status.textContent = root.dataset.invalidText ?? ''
				documentContainer.setAttribute('aria-busy', 'false')
				return
			}
			url.searchParams.set(field.parameter, value)
		}

		url.searchParams.set('_', Date.now().toString())
		status.textContent = root.dataset.loadingText ?? ''
		documentContainer.setAttribute('aria-busy', 'true')
		openLink.href = url.toString()
		iframe.src = url.toString()
	}

	const scheduleRefresh = (): void => {
		clearTimeout(refreshTimer)
		refreshTimer = setTimeout(refresh, REFRESH_DELAY_MS)
	}

	iframe.addEventListener('load', () => {
		documentContainer.setAttribute('aria-busy', 'false')
		status.textContent = ''
	})
	form.addEventListener('input', scheduleRefresh)
	form.addEventListener('change', scheduleRefresh)

	const observer = new MutationObserver(scheduleRefresh)
	observer.observe(form, { childList: true, subtree: true })
	scheduleRefresh()
}

/** Wait for Nextcloud's settings frame before initializing the preview. */
function initializeWhenReady(): void {
	if (document.getElementById(SETTINGS_FORM_ID)) {
		initializePreview()
		return
	}

	const observer = new MutationObserver(() => {
		if (document.getElementById(SETTINGS_FORM_ID)) {
			observer.disconnect()
			initializePreview()
		}
	})
	observer.observe(document.body, { childList: true, subtree: true })
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializeWhenReady, { once: true })
} else {
	initializeWhenReady()
}
