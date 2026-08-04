/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import AdminSettings from './AdminSettings.vue'

import '@nextcloud/dialogs/style.css'

const mountElement = document.getElementById('files-watermark-admin-settings')
if (mountElement) {
	createApp(AdminSettings).mount(mountElement)
}
