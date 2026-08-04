/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminSettings from '../../src/AdminSettings.vue'

const mocks = vi.hoisted(() => ({
	post: vi.fn(),
	showError: vi.fn(),
}))

const initialSettings = {
	python_executable: '/opt/files-watermark-python/bin/python',
	raster_dpi: '180',
	watermark_font_size_points: '28',
	watermark_color: '#333333',
	watermark_opacity_percent: '30',
	watermark_angle_degrees: '30',
	watermark_minimum_horizontal_interval_points: '145',
	watermark_horizontal_gap_points: '48',
	watermark_vertical_interval_points: '78',
	maximum_source_size_mib: '50',
	maximum_pages: '200',
	timeout_seconds: '120',
}

vi.mock('@nextcloud/axios', () => ({
	default: { post: mocks.post },
	isAxiosError: (error: { isAxiosError?: boolean }) => error.isAxiosError === true,
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: mocks.showError }))
vi.mock('@nextcloud/initial-state', () => ({
	loadState: () => ({
		settings: { ...initialSettings },
		previewUrl: '/apps/files_watermark/admin/preview',
		previewImageUrl: '/apps/files_watermark/admin/preview?format=image',
	}),
}))
vi.mock('@nextcloud/l10n', () => ({ t: (_app: string, message: string) => message }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (url: string) => url }))
vi.mock('@nextcloud/vue/components/NcInputField', () => ({
	default: {
		inheritAttrs: false,
		props: {
			modelValue: [String, Number],
			label: String,
			type: String,
			helperText: String,
			error: Boolean,
			success: Boolean,
		},
		emits: ['update:modelValue'],
		template: `
			<label>
				<span>{{ label }}</span>
				<input
					v-bind="$attrs"
					:type="type"
					:value="modelValue"
					@input="$emit('update:modelValue', $event.target.value)">
				<small :data-error="error" :data-success="success">{{ helperText }}</small>
			</label>`,
	},
}))
vi.mock('@nextcloud/vue/components/NcSettingsSection', () => ({
	default: {
		props: { name: String, description: String },
		template: '<section><h2>{{ name }}</h2><p>{{ description }}</p><slot /></section>',
	},
}))

function render() {
	return mount(AdminSettings)
}

describe('AdminSettings', () => {
	beforeEach(() => {
		vi.useFakeTimers()
		vi.setSystemTime(new Date('2026-08-04T12:00:00Z'))
		vi.clearAllMocks()
		mocks.post.mockImplementation(async (_url: string, payload: { key: string, value: string }) => ({
			data: payload,
		}))
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('autosaves through the protected app endpoint after the standard debounce', async () => {
		const wrapper = render()
		await wrapper.get('[data-testid="setting-watermark_opacity_percent"]').setValue('45')

		await vi.advanceTimersByTimeAsync(799)
		expect(mocks.post).not.toHaveBeenCalled()
		await vi.advanceTimersByTimeAsync(1)
		await flushPromises()

		expect(mocks.post).toHaveBeenCalledWith('/apps/files_watermark/admin/settings', {
			key: 'watermark_opacity_percent',
			value: '45',
		})
		expect(wrapper.get('[data-testid="setting-watermark_opacity_percent"] + small').text()).toBe('Saved')
		expect(wrapper.get('.files-watermark-admin-settings__image').attributes('src')).toContain('format=image')
		expect(wrapper.get('.files-watermark-admin-settings__image').attributes('src')).toContain('_=')
	})

	it('saves immediately on blur and cancels the pending debounce', async () => {
		const wrapper = render()
		const input = wrapper.get('[data-testid="setting-python_executable"]')
		await input.setValue('/usr/bin/python3')
		await input.trigger('blur')
		await flushPromises()

		expect(mocks.post).toHaveBeenCalledTimes(1)
		expect(mocks.post).toHaveBeenCalledWith('/apps/files_watermark/admin/settings', {
			key: 'python_executable',
			value: '/usr/bin/python3',
		})
		await vi.advanceTimersByTimeAsync(800)
		expect(mocks.post).toHaveBeenCalledTimes(1)
	})

	it('shows server validation errors on the corresponding input', async () => {
		mocks.post.mockRejectedValue({
			isAxiosError: true,
			response: { data: { message: 'Opacity must be from 1 to 100.' } },
		})
		const wrapper = render()
		const input = wrapper.get('[data-testid="setting-watermark_opacity_percent"]')
		await input.setValue('101')
		await input.trigger('blur')
		await flushPromises()

		expect(mocks.showError).toHaveBeenCalledWith('Opacity must be from 1 to 100.')
		expect(wrapper.get('[data-testid="setting-watermark_opacity_percent"] + small').text())
			.toBe('Opacity must be from 1 to 100.')
	})
})
