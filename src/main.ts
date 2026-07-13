/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ISidebarSection } from '@nextcloud/sharing/ui'

import { registerSidebarSection } from '@nextcloud/sharing/ui'
import { defineCustomElement } from 'vue'
import WatermarkShareSection from './WatermarkShareSection.vue'

import '@nextcloud/dialogs/style.css'

export const ELEMENT_NAME = 'oca_files_watermark-sharing_section'

export const sidebarSection: ISidebarSection = {
	id: 'files_watermark',
	element: ELEMENT_NAME,
	order: 50,
	enabled(node) {
		return node.mime === 'application/pdf'
	},
}

/** Register the custom element in the files_sharing sidebar registry. */
export function registerWatermarkSection(): void {
	if (!customElements.get(ELEMENT_NAME)) {
		customElements.define(
			ELEMENT_NAME,
			defineCustomElement(WatermarkShareSection, { shadowRoot: false }),
		)
	}
	registerSidebarSection(sidebarSection)
}

registerWatermarkSection()
