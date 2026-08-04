/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './admin-preview.scss'

const PREVIEW_ROOT_ID = 'files-watermark-admin-preview'
// Core declarative text fields save after a 1 second debounce. Refresh from
// the server after that save instead of duplicating core's form state here.
const SAVE_SETTLE_DELAY_MS = 1800

/** Refresh the preview after Nextcloud's declarative settings autosave. */
function initializePreview(): void {
	const root = document.getElementById(PREVIEW_ROOT_ID)
	if (!(root instanceof HTMLElement)) {
		return
	}

	const previewUrl = root.dataset.previewUrl
	const previewImage = root.querySelector<HTMLImageElement>('.files-watermark-admin-preview__image')
	const documentContainer = root.querySelector<HTMLElement>('.files-watermark-admin-preview__document')
	const status = root.querySelector<HTMLElement>('.files-watermark-admin-preview__status')
	const openLink = root.querySelector<HTMLAnchorElement>('.files-watermark-admin-preview__open')
	if (!previewUrl || !previewImage || !documentContainer || !status || !openLink) {
		return
	}

	let refreshTimer: ReturnType<typeof setTimeout> | undefined

	const refresh = (): void => {
		const url = new URL(previewUrl, window.location.href)
		url.searchParams.set('_', Date.now().toString())
		const imageUrl = new URL(url)
		imageUrl.searchParams.set('format', 'image')
		status.textContent = root.dataset.loadingText ?? ''
		documentContainer.setAttribute('aria-busy', 'true')
		openLink.href = url.toString()
		previewImage.src = imageUrl.toString()
	}

	const scheduleRefresh = (): void => {
		clearTimeout(refreshTimer)
		status.textContent = root.dataset.loadingText ?? ''
		documentContainer.setAttribute('aria-busy', 'true')
		refreshTimer = setTimeout(refresh, SAVE_SETTLE_DELAY_MS)
	}

	const showLoadedPreview = (): void => {
		documentContainer.setAttribute('aria-busy', 'false')
		previewImage.hidden = false
		status.textContent = ''
	}
	const showPreviewError = (): void => {
		documentContainer.setAttribute('aria-busy', 'false')
		previewImage.hidden = true
		status.textContent = root.dataset.errorText ?? ''
	}
	previewImage.addEventListener('load', showLoadedPreview)
	previewImage.addEventListener('error', showPreviewError)
	if (previewImage.complete) {
		if (previewImage.naturalWidth > 0) {
			showLoadedPreview()
		} else if (previewImage.src !== '') {
			showPreviewError()
		}
	}
	// Delegate from the shared settings container. Nextcloud replaces the
	// declarative form's mount element when Vue starts, so binding to that
	// temporary element loses the listeners and can read empty default fields.
	const settingsContainer = root.parentElement
	if (settingsContainer) {
		const handleSettingEvent = (event: Event): void => {
			if (event.target instanceof HTMLInputElement && !root.contains(event.target)) {
				scheduleRefresh()
			}
		}
		settingsContainer.addEventListener('input', handleSettingEvent)
		settingsContainer.addEventListener('change', handleSettingEvent)
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializePreview, { once: true })
} else {
	initializePreview()
}
