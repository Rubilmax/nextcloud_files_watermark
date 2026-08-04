/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
	plugins: [vue()],
	test: {
		environment: 'jsdom',
		include: ['tests/frontend/**/*.spec.ts'],
		setupFiles: ['tests/frontend/setup.ts'],
		server: {
			deps: {
				inline: ['@nextcloud/vue'],
			},
		},
	},
})
