<!--
  - SPDX-FileCopyrightText: 2026 Watermarked shares contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import axios, { isAxiosError } from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import './admin-settings.scss'

const APP_ID = 'files_watermark'
const SAVE_DELAY_MS = 800

interface AdminValues {
	python_executable: string
	raster_dpi: string
	watermark_font_size_points: string
	watermark_color: string
	watermark_opacity_percent: string
	watermark_angle_degrees: string
	watermark_minimum_horizontal_interval_points: string
	watermark_horizontal_gap_points: string
	watermark_vertical_interval_points: string
	watermark_opacity_variation_percent: string
	watermark_spacing_variation_percent: string
	watermark_position_jitter_points: string
	watermark_blur_radius_pixels: string
	watermark_blur_opacity_percent: string
	watermark_distortion_enabled: string
	watermark_distortion_strength_pixels: string
	maximum_source_size_mib: string
	maximum_pages: string
	timeout_seconds: string
}

type SettingKey = keyof AdminValues

interface SettingDefinition {
	key: SettingKey
	label: string
	description: string
	type: 'number' | 'text'
	minimum?: number
	maximum?: number
}

interface AdminState {
	settings: AdminValues
	previewUrl: string
	previewImageUrl: string
}

interface SaveResponse {
	key: SettingKey
	value: string
}

const initialState = loadState<AdminState>(APP_ID, 'admin-settings')
const visibleDefinitions: SettingDefinition[] = [
	{
		key: 'watermark_font_size_points',
		label: t(APP_ID, 'Watermark font size (points)'),
		description: t(APP_ID, 'Text size from 8 to 144 points.'),
		type: 'number',
		minimum: 8,
		maximum: 144,
	},
	{
		key: 'watermark_color',
		label: t(APP_ID, 'Watermark color'),
		description: t(APP_ID, 'Six-digit hexadecimal color, for example #333333.'),
		type: 'text',
	},
	{
		key: 'watermark_opacity_percent',
		label: t(APP_ID, 'Watermark opacity (percent)'),
		description: t(APP_ID, 'Opacity from 1 to 100 percent.'),
		type: 'number',
		minimum: 1,
		maximum: 100,
	},
	{
		key: 'watermark_angle_degrees',
		label: t(APP_ID, 'Watermark angle (degrees)'),
		description: t(APP_ID, 'Rotation from -180 to 180 degrees.'),
		type: 'number',
		minimum: -180,
		maximum: 180,
	},
	{
		key: 'watermark_minimum_horizontal_interval_points',
		label: t(APP_ID, 'Minimum horizontal interval (points)'),
		description: t(APP_ID, 'Minimum distance between repeated watermark origins, from 20 to 2000 points.'),
		type: 'number',
		minimum: 20,
		maximum: 2000,
	},
	{
		key: 'watermark_horizontal_gap_points',
		label: t(APP_ID, 'Horizontal gap (points)'),
		description: t(APP_ID, 'Extra horizontal space after each watermark, from 0 to 1000 points.'),
		type: 'number',
		minimum: 0,
		maximum: 1000,
	},
	{
		key: 'watermark_vertical_interval_points',
		label: t(APP_ID, 'Vertical interval (points)'),
		description: t(APP_ID, 'Distance between watermark rows, from 20 to 2000 points.'),
		type: 'number',
		minimum: 20,
		maximum: 2000,
	},
	{
		key: 'watermark_opacity_variation_percent',
		label: t(APP_ID, 'Opacity variation (percent)'),
		description: t(APP_ID, 'Randomly vary the page watermark opacity by up to 0 to 50 percentage points.'),
		type: 'number',
		minimum: 0,
		maximum: 50,
	},
	{
		key: 'watermark_spacing_variation_percent',
		label: t(APP_ID, 'Spacing variation (percent)'),
		description: t(APP_ID, 'Randomly vary horizontal and vertical spacing by up to 0 to 40 percent.'),
		type: 'number',
		minimum: 0,
		maximum: 40,
	},
	{
		key: 'watermark_position_jitter_points',
		label: t(APP_ID, 'Position jitter (points)'),
		description: t(APP_ID, 'Randomly offset each repeated watermark by up to 0 to 100 points.'),
		type: 'number',
		minimum: 0,
		maximum: 100,
	},
	{
		key: 'watermark_blur_radius_pixels',
		label: t(APP_ID, 'Blur radius (pixels)'),
		description: t(APP_ID, 'Gaussian blur radius for the secondary watermark layer, from 0 to 64 pixels.'),
		type: 'number',
		minimum: 0,
		maximum: 64,
	},
	{
		key: 'watermark_blur_opacity_percent',
		label: t(APP_ID, 'Blur opacity (percent)'),
		description: t(APP_ID, 'Opacity multiplier for the blurred layer, from 0 to 100 percent.'),
		type: 'number',
		minimum: 0,
		maximum: 100,
	},
	{
		key: 'watermark_distortion_strength_pixels',
		label: t(APP_ID, 'Distortion strength (pixels)'),
		description: t(APP_ID, 'Maximum nonlinear displacement when distortion is enabled, from 0 to 128 pixels.'),
		type: 'number',
		minimum: 0,
		maximum: 128,
	},
]

const rendererDefinitions: SettingDefinition[] = [
	{
		key: 'python_executable',
		label: t(APP_ID, 'Python executable'),
		description: t(APP_ID, 'Executable or absolute path used to run the local renderer.'),
		type: 'text',
	},
	{
		key: 'raster_dpi',
		label: t(APP_ID, 'Raster DPI'),
		description: t(APP_ID, 'Resolution from 96 to 300 DPI. Higher values use more memory and storage.'),
		type: 'number',
		minimum: 96,
		maximum: 300,
	},
	{
		key: 'maximum_source_size_mib',
		label: t(APP_ID, 'Maximum source size (MiB)'),
		description: t(APP_ID, 'Reject larger PDFs before rendering. Allowed range: 1 to 1024.'),
		type: 'number',
		minimum: 1,
		maximum: 1024,
	},
	{
		key: 'maximum_pages',
		label: t(APP_ID, 'Maximum pages'),
		description: t(APP_ID, 'Maximum pages per source PDF. Allowed range: 1 to 5000.'),
		type: 'number',
		minimum: 1,
		maximum: 5000,
	},
	{
		key: 'timeout_seconds',
		label: t(APP_ID, 'Timeout (seconds)'),
		description: t(APP_ID, 'Terminate rendering after this duration. Allowed range: 10 to 3600.'),
		type: 'number',
		minimum: 10,
		maximum: 3600,
	},
]

const values = reactive<AdminValues>({ ...initialState.settings })
const confirmedValues = reactive<AdminValues>({ ...initialState.settings })
const saving = reactive<Partial<Record<SettingKey, boolean>>>({})
const saved = reactive<Partial<Record<SettingKey, boolean>>>({})
const errors = reactive<Partial<Record<SettingKey, string>>>({})
const saveTimers: Partial<Record<SettingKey, ReturnType<typeof setTimeout>>> = {}
const savedTimers: Partial<Record<SettingKey, ReturnType<typeof setTimeout>>> = {}
const requestSequence: Partial<Record<SettingKey, number>> = {}

const previewPdfUrl = ref(initialState.previewUrl)
const previewImageUrl = ref(initialState.previewImageUrl)
const previewLoading = ref(true)
const previewFailed = ref(false)
const previewStatus = computed(() => {
	if (previewFailed.value) {
		return t(APP_ID, 'The watermarked PDF could not be generated.')
	}
	return previewLoading.value ? t(APP_ID, 'Rendering watermark preview…') : ''
})

/**
 * Queue the same debounced save behavior used by Nextcloud 34 theming inputs.
 *
 * @param key Setting to update
 * @param value New input value
 */
function updateValue(key: SettingKey, value: string | number): void {
	values[key] = String(value)
	errors[key] = ''
	saved[key] = false
	clearTimeout(saveTimers[key])
	saveTimers[key] = setTimeout(() => void saveSetting(key), SAVE_DELAY_MS)
}

/**
 * Save immediately when an input loses focus.
 *
 * @param key Setting to save
 */
function saveOnBlur(key: SettingKey): void {
	clearTimeout(saveTimers[key])
	if (values[key] === confirmedValues[key] && !errors[key]) {
		return
	}
	void saveSetting(key)
}

/**
 * Persist switches immediately, following Nextcloud administration controls.
 *
 * @param key Setting to update
 * @param value New switch value
 */
function updateBoolean(key: SettingKey, value: boolean): void {
	clearTimeout(saveTimers[key])
	values[key] = value ? '1' : '0'
	errors[key] = ''
	saved[key] = false
	void saveSetting(key)
}

/**
 * Persist one field through the app's CSRF-protected admin controller.
 *
 * @param key Setting to save
 */
async function saveSetting(key: SettingKey): Promise<void> {
	const submittedValue = values[key]
	const sequence = (requestSequence[key] ?? 0) + 1
	requestSequence[key] = sequence
	saving[key] = true
	errors[key] = ''
	saved[key] = false

	try {
		const { data } = await axios.post<SaveResponse>(generateUrl('/apps/files_watermark/admin/settings'), {
			key,
			value: submittedValue,
		})
		if (requestSequence[key] !== sequence || values[key] !== submittedValue) {
			return
		}

		values[key] = data.value
		confirmedValues[key] = data.value
		saved[key] = true
		refreshPreview()
		clearTimeout(savedTimers[key])
		savedTimers[key] = setTimeout(() => {
			saved[key] = false
		}, 2000)
	} catch (error) {
		if (requestSequence[key] !== sequence) {
			return
		}
		const message = isAxiosError<{ message?: string }>(error) && error.response?.data?.message
			? error.response.data.message
			: t(APP_ID, 'The setting could not be saved.')
		errors[key] = message
		showError(message)
	} finally {
		if (requestSequence[key] === sequence) {
			saving[key] = false
		}
	}
}

/**
 * Return field help text or its current save state.
 *
 * @param setting Field definition
 */
function getHelperText(setting: SettingDefinition): string {
	return getStatusText(setting.key, setting.description)
}

/**
 * Return help text or save state for a setting without an input definition.
 *
 * @param key Setting to describe
 * @param description Default help text
 */
function getStatusText(key: SettingKey, description: string): string {
	if (errors[key]) {
		return errors[key] ?? ''
	}
	if (saving[key]) {
		return t(APP_ID, 'Saving…')
	}
	if (saved[key]) {
		return t(APP_ID, 'Saved')
	}
	return description
}

/** Reload both preview targets after the server confirms persistence. */
function refreshPreview(): void {
	const pdfUrl = new URL(initialState.previewUrl, window.location.href)
	pdfUrl.searchParams.set('_', Date.now().toString())
	const imageUrl = new URL(pdfUrl)
	imageUrl.searchParams.set('format', 'image')
	previewPdfUrl.value = pdfUrl.toString()
	previewImageUrl.value = imageUrl.toString()
	previewLoading.value = true
	previewFailed.value = false
}

/** Mark the current preview image as loaded. */
function onPreviewLoad(): void {
	previewLoading.value = false
	previewFailed.value = false
}

/** Surface preview rendering failures without leaving a busy indicator. */
function onPreviewError(): void {
	previewLoading.value = false
	previewFailed.value = true
}

onBeforeUnmount(() => {
	for (const timer of [...Object.values(saveTimers), ...Object.values(savedTimers)]) {
		clearTimeout(timer)
	}
})
</script>

<template>
	<div class="files-watermark-admin-settings">
		<NcSettingsSection
			:name="t(APP_ID, 'Visible watermark')"
			:description="t(APP_ID, 'Configure the randomized FiligraneFacile-style watermark baked into every rendered page.')">
			<div class="files-watermark-admin-settings__fields">
				<NcInputField
					v-for="setting in visibleDefinitions"
					:id="`files-watermark-${setting.key}`"
					:key="setting.key"
					:data-testid="`setting-${setting.key}`"
					:modelValue="values[setting.key]"
					:label="setting.label"
					:type="setting.type"
					:min="setting.minimum"
					:max="setting.maximum"
					:step="setting.type === 'number' ? 1 : undefined"
					:error="Boolean(errors[setting.key])"
					:success="Boolean(saved[setting.key])"
					:helperText="getHelperText(setting)"
					autocomplete="off"
					@update:modelValue="updateValue(setting.key, $event)"
					@blur="saveOnBlur(setting.key)" />
				<div class="files-watermark-admin-settings__switch">
					<NcCheckboxRadioSwitch
						:modelValue="values.watermark_distortion_enabled === '1'"
						data-testid="setting-watermark_distortion_enabled"
						@update:modelValue="updateBoolean('watermark_distortion_enabled', $event)">
						{{ t(APP_ID, 'Enable nonlinear distortion') }}
					</NcCheckboxRadioSwitch>
					<small>{{ getStatusText('watermark_distortion_enabled', t(APP_ID, 'Warp the sharp watermark layer to make uniform automated cleanup more difficult.')) }}</small>
				</div>
			</div>
		</NcSettingsSection>

		<NcSettingsSection
			:name="t(APP_ID, 'Watermark preview')"
			:description="t(APP_ID, 'This sample PDF uses the saved visible settings and refreshes after each successful save.')">
			<div
				class="files-watermark-admin-settings__document"
				:aria-busy="previewLoading ? 'true' : 'false'">
				<img
					class="files-watermark-admin-settings__image"
					:src="previewImageUrl"
					:alt="t(APP_ID, 'Watermarked PDF preview')"
					@load="onPreviewLoad"
					@error="onPreviewError">
			</div>
			<div class="files-watermark-admin-settings__preview-footer">
				<span role="status" aria-live="polite">{{ previewStatus }}</span>
				<a :href="previewPdfUrl" target="_blank" rel="noopener noreferrer">
					{{ t(APP_ID, 'Open PDF preview') }}
				</a>
			</div>
		</NcSettingsSection>

		<NcSettingsSection
			:name="t(APP_ID, 'Renderer and safety limits')"
			:description="t(APP_ID, 'Configure the local renderer and synchronous resource limits.')">
			<div class="files-watermark-admin-settings__fields">
				<NcInputField
					v-for="setting in rendererDefinitions"
					:id="`files-watermark-${setting.key}`"
					:key="setting.key"
					:data-testid="`setting-${setting.key}`"
					:modelValue="values[setting.key]"
					:label="setting.label"
					:type="setting.type"
					:min="setting.minimum"
					:max="setting.maximum"
					:step="setting.type === 'number' ? 1 : undefined"
					:error="Boolean(errors[setting.key])"
					:success="Boolean(saved[setting.key])"
					:helperText="getHelperText(setting)"
					autocomplete="off"
					@update:modelValue="updateValue(setting.key, $event)"
					@blur="saveOnBlur(setting.key)" />
			</div>
		</NcSettingsSection>
	</div>
</template>
