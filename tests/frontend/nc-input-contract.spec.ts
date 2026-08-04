/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import NcInputField from '@nextcloud/vue/components/NcInputField'

describe('Nextcloud 34 input contract', () => {
	it('emits update:modelValue and forwards blur to the native input', async () => {
		const onBlur = vi.fn()
		const wrapper = mount(NcInputField, {
			props: {
				id: 'watermark-opacity',
				label: 'Watermark opacity',
				modelValue: '30',
				type: 'number',
			},
			attrs: { onBlur },
		})

		const input = wrapper.get('input')
		await input.setValue('45')
		await input.trigger('blur')

		expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['45'])
		expect(onBlur).toHaveBeenCalledOnce()
	})
})
