/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { INode } from '@nextcloud/files'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WatermarkShareSection from '../../src/WatermarkShareSection.vue'

const mocks = vi.hoisted(() => ({
	copyText: vi.fn(),
	createPublicShare: vi.fn(),
	emit: vi.fn(),
	generateWatermarkedFile: vi.fn(),
	openSidebar: vi.fn(),
	resolveDavNode: vi.fn(),
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: () => ({
		files_sharing: {
			api_enabled: true,
			public: {
				enabled: true,
				custom_tokens: true,
				password: { enforced: false, askForOptionalPassword: false },
				expire_date: { enabled: true, enforced: false, days: 7 },
			},
		},
	}),
}))
vi.mock('@nextcloud/dialogs', () => ({
	showError: mocks.showError,
	showSuccess: mocks.showSuccess,
}))
vi.mock('@nextcloud/event-bus', () => ({ emit: mocks.emit }))
vi.mock('@nextcloud/files', () => ({
	getSidebar: () => ({ open: mocks.openSidebar }),
}))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, message: string, replacements?: Record<string, string>) => Object.entries(replacements ?? {})
		.reduce((result, [key, value]) => result.replace(`{${key}}`, value), message),
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		inheritAttrs: false,
		props: { disabled: Boolean, variant: String },
		template: '<button v-bind="$attrs" :disabled="disabled"><slot /></button>',
	},
}))
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
	default: {
		inheritAttrs: false,
		props: { modelValue: Boolean, disabled: Boolean },
		emits: ['update:modelValue'],
		template: '<label v-bind="$attrs"><input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)"><slot /></label>',
	},
}))
vi.mock('../../src/services/api', async (importOriginal) => {
	const original = await importOriginal<Record<string, unknown>>()
	return {
		...original,
		copyText: mocks.copyText,
		createPublicShare: mocks.createPublicShare,
		generateWatermarkedFile: mocks.generateWatermarkedFile,
		resolveDavNode: mocks.resolveDavNode,
	}
})

const sourceNode = {
	id: '42',
	fileid: 42,
	path: '/Reports/File.pdf',
	mime: 'application/pdf',
} as INode
const generated = {
	id: '84',
	path: '/Reports/File - Confidential.pdf',
	name: 'File - Confidential.pdf',
	mime: 'application/pdf',
	size: 1234,
	invisibleWatermarkId: 'a'.repeat(64),
}
const derivedNode = {
	id: '84',
	path: generated.path,
	mime: 'application/pdf',
} as INode

function render() {
	return mount(WatermarkShareSection, {
		props: { node: sourceNode },
	})
}

async function setCheckbox(wrapper: ReturnType<typeof render>, testId: string, value: boolean) {
	await wrapper.get(`[data-testid="${testId}"] input`).setValue(value)
}

describe('WatermarkShareSection', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mocks.createPublicShare.mockResolvedValue('https://cloud.example/s/public-link')
		mocks.generateWatermarkedFile.mockResolvedValue(generated)
		mocks.resolveDavNode.mockResolvedValue(derivedNode)
		mocks.copyText.mockResolvedValue(undefined)
	})

	it('shares the original PDF without generating a file when normalized text is empty', async () => {
		const wrapper = render()
		await wrapper.get('[data-testid="watermark-text"]').setValue('  \n\t ')
		await wrapper.get('[data-testid="create-link"]').trigger('click')
		await flushPromises()

		expect(mocks.generateWatermarkedFile).not.toHaveBeenCalled()
		expect(mocks.createPublicShare).toHaveBeenCalledWith(expect.objectContaining({
			path: sourceNode.path,
			permissions: 1,
		}))
	})

	it('generates the derivative and submits all supported public-link options', async () => {
		const wrapper = render()
		await wrapper.get('[data-testid="watermark-text"]').setValue('  Confidential   東京  ')
		await wrapper.get('[data-testid="permission-mode"]').setValue('custom')
		await setCheckbox(wrapper, 'custom-edit', true)
		await setCheckbox(wrapper, 'password-enabled', true)
		await wrapper.get('[data-testid="password"]').setValue('correct horse battery staple')
		await wrapper.get('#files-watermark-label').setValue('Board copy')
		await wrapper.get('#files-watermark-note').setValue('For the recipient')
		await setCheckbox(wrapper, 'hide-download', true)
		await setCheckbox(wrapper, 'talk-option', true)
		await wrapper.get('[data-testid="expiration-date"]').setValue('2030-06-30')
		await wrapper.get('[data-testid="create-link"]').trigger('click')
		await flushPromises()

		expect(mocks.generateWatermarkedFile).toHaveBeenCalledWith('42', sourceNode.path, 'Confidential 東京')
		expect(mocks.createPublicShare).toHaveBeenCalledWith({
			path: generated.path,
			permissions: 3,
			password: 'correct horse battery staple',
			expireDate: '2030-06-30',
			label: 'Board copy',
			note: 'For the recipient',
			hideDownload: true,
			sendPasswordByTalk: true,
		})
		expect(mocks.emit).toHaveBeenCalledWith('files:node:created', derivedNode)
		expect(mocks.emit).toHaveBeenCalledWith('files:node:updated', derivedNode)
		expect(wrapper.get('[data-testid="share-result"]').text()).toContain('Public link')
		expect(wrapper.get('[data-testid="invisible-watermark-id"] input').attributes('value')).toBe('a'.repeat(64))
	})

	it('explicitly disables default expiration and does not send a stale Talk option', async () => {
		const wrapper = render()
		await setCheckbox(wrapper, 'expiration-enabled', false)
		await setCheckbox(wrapper, 'password-enabled', true)
		await wrapper.get('[data-testid="password"]').setValue('temporary password')
		await setCheckbox(wrapper, 'talk-option', true)
		await setCheckbox(wrapper, 'password-enabled', false)
		await wrapper.get('[data-testid="create-link"]').trigger('click')
		await flushPromises()

		expect(mocks.createPublicShare).toHaveBeenCalledWith(expect.objectContaining({
			path: sourceNode.path,
			password: undefined,
			expireDate: '',
			sendPasswordByTalk: false,
		}))
	})

	it('keeps the derivative after share failure and retries without rendering again', async () => {
		mocks.createPublicShare
			.mockRejectedValueOnce(new Error('Policy rejected the share'))
			.mockResolvedValueOnce('https://cloud.example/s/retry')
		const wrapper = render()
		await wrapper.get('[data-testid="watermark-text"]').setValue('Confidential')

		await wrapper.get('[data-testid="create-link"]').trigger('click')
		await flushPromises()
		expect(wrapper.get('[data-testid="error"]').text()).toContain('Policy rejected')
		expect(wrapper.get('[data-testid="retained-path"]').text()).toContain(generated.path)

		await wrapper.get('[data-testid="create-link"]').trigger('click')
		await flushPromises()
		expect(mocks.generateWatermarkedFile).toHaveBeenCalledTimes(1)
		expect(mocks.createPublicShare).toHaveBeenCalledTimes(2)
		expect(wrapper.find('[data-testid="retained-path"]').exists()).toBe(false)
	})

	it('does not share a stale derivative when the active node changes during rendering', async () => {
		let finishRendering!: (file: typeof generated) => void
		mocks.generateWatermarkedFile.mockImplementation(() => new Promise((resolve) => {
			finishRendering = resolve
		}))
		const wrapper = render()
		await wrapper.get('[data-testid="watermark-text"]').setValue('Confidential')
		await wrapper.get('[data-testid="create-link"]').trigger('click')
		await flushPromises()

		await wrapper.setProps({
			node: { ...sourceNode, id: '43', fileid: 43, path: '/Reports/Other.pdf' } as INode,
		})
		finishRendering(generated)
		await flushPromises()

		expect(mocks.createPublicShare).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="share-result"]').exists()).toBe(false)
		expect(wrapper.get('[data-testid="create-link"]').attributes('disabled')).toBeUndefined()
	})

	it('copies the resulting URL and opens the generated node sharing tab', async () => {
		const wrapper = render()
		await wrapper.get('[data-testid="watermark-text"]').setValue('Confidential')
		await wrapper.get('[data-testid="create-link"]').trigger('click')
		await flushPromises()

		await wrapper.get('[data-testid="share-result"] button').trigger('click')
		await wrapper.get('[data-testid="invisible-watermark-id"] button').trigger('click')
		await wrapper.get('[data-testid="open-generated"]').trigger('click')
		await flushPromises()

		expect(mocks.copyText).toHaveBeenNthCalledWith(1, 'https://cloud.example/s/public-link')
		expect(mocks.copyText).toHaveBeenNthCalledWith(2, 'a'.repeat(64))
		expect(mocks.openSidebar).toHaveBeenCalledWith(derivedNode, 'sharing')
	})
})
