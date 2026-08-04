<!--
  - SPDX-FileCopyrightText: 2026 Watermarked shares contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="watermark-share" aria-labelledby="watermark-share-heading">
		<h3 id="watermark-share-heading">
			{{ t('files_watermark', 'Watermarked share') }}
		</h3>

		<p v-if="!publicLinksEnabled" class="watermark-share__notice">
			{{ t('files_watermark', 'Public links are disabled by the administrator.') }}
		</p>

		<template v-else>
			<label for="files-watermark-text">{{ t('files_watermark', 'Watermark text (optional)') }}</label>
			<textarea
				id="files-watermark-text"
				data-testid="watermark-text"
				:value="watermarkText"
				:disabled="loading"
				maxlength="128"
				rows="2"
				:placeholder="t('files_watermark', 'For example: Confidential – Jane Doe')"
				@input="onWatermarkInput" />
			<p class="watermark-share__hint">
				{{ t('files_watermark', 'Leave blank to share the original. Text is baked into rasterized page pixels when provided.') }}
			</p>

			<label for="files-watermark-permissions">{{ t('files_watermark', 'Permissions') }}</label>
			<select
				id="files-watermark-permissions"
				v-model="permissionMode"
				:disabled="loading"
				data-testid="permission-mode">
				<option value="view">
					{{ t('files_watermark', 'View only') }}
				</option>
				<option value="edit">
					{{ t('files_watermark', 'Allow editing') }}
				</option>
				<option value="custom">
					{{ t('files_watermark', 'Custom permissions') }}
				</option>
			</select>

			<div v-if="permissionMode === 'custom'" class="watermark-share__custom-permissions">
				<NcCheckboxRadioSwitch :modelValue="true" disabled>
					{{ t('files_watermark', 'View') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="customEdit" :disabled="loading" data-testid="custom-edit">
					{{ t('files_watermark', 'Edit') }}
				</NcCheckboxRadioSwitch>
			</div>

			<NcCheckboxRadioSwitch
				v-if="!passwordEnforced"
				v-model="passwordEnabled"
				:disabled="loading"
				data-testid="password-enabled">
				{{ t('files_watermark', 'Password protect') }}
			</NcCheckboxRadioSwitch>
			<label v-if="passwordEnabled || passwordEnforced" for="files-watermark-password">
				{{ t('files_watermark', 'Password') }}
			</label>
			<input
				v-if="passwordEnabled || passwordEnforced"
				id="files-watermark-password"
				v-model="password"
				:disabled="loading"
				type="password"
				autocomplete="new-password"
				data-testid="password">

			<details class="watermark-share__advanced">
				<summary>{{ t('files_watermark', 'Advanced settings') }}</summary>
				<div class="watermark-share__advanced-fields">
					<label for="files-watermark-label">{{ t('files_watermark', 'Link label') }}</label>
					<input
						id="files-watermark-label"
						v-model="label"
						:disabled="loading"
						maxlength="255"
						type="text">

					<NcCheckboxRadioSwitch
						v-model="expirationEnabled"
						:disabled="loading || expirationEnforced"
						data-testid="expiration-enabled">
						{{ t('files_watermark', 'Set expiration date') }}
					</NcCheckboxRadioSwitch>
					<label v-if="expirationEnabled" for="files-watermark-expiration">
						{{ t('files_watermark', 'Expiration date') }}
					</label>
					<input
						v-if="expirationEnabled"
						id="files-watermark-expiration"
						v-model="expirationDate"
						:disabled="loading"
						:min="minimumExpirationDate"
						type="date"
						data-testid="expiration-date">

					<NcCheckboxRadioSwitch v-model="hideDownload" :disabled="loading" data-testid="hide-download">
						{{ t('files_watermark', 'Hide download') }}
					</NcCheckboxRadioSwitch>

					<label for="files-watermark-note">{{ t('files_watermark', 'Note to recipient') }}</label>
					<textarea
						id="files-watermark-note"
						v-model="note"
						:disabled="loading"
						rows="2" />

					<NcCheckboxRadioSwitch
						v-if="talkAvailable && (passwordEnabled || passwordEnforced)"
						v-model="sendPasswordByTalk"
						:disabled="loading"
						data-testid="talk-option">
						{{ t('files_watermark', 'Send password through Talk') }}
					</NcCheckboxRadioSwitch>

					<p v-if="customTokensAvailable" class="watermark-share__hint">
						{{ t('files_watermark', 'A custom token can be set after creation in the generated file’s standard sharing settings.') }}
					</p>
				</div>
			</details>

			<p
				v-if="error"
				class="watermark-share__error"
				role="alert"
				data-testid="error">
				{{ error }}
			</p>
			<p v-if="retainedPath" class="watermark-share__notice" data-testid="retained-path">
				{{ t('files_watermark', 'The generated PDF was kept at {path}. You can retry link creation without rendering it again.', { path: retainedPath }) }}
			</p>

			<div v-if="publicUrl" class="watermark-share__result" data-testid="share-result">
				<label for="files-watermark-url">{{ t('files_watermark', 'Public link') }}</label>
				<div class="watermark-share__url-row">
					<input
						id="files-watermark-url"
						:value="publicUrl"
						readonly
						type="url">
					<NcButton variant="secondary" @click="copyUrl">
						{{ t('files_watermark', 'Copy') }}
					</NcButton>
				</div>
				<template v-if="generated?.invisibleWatermarkId">
					<label for="files-watermark-identifier">{{ t('files_watermark', 'Invisible watermark identifier') }}</label>
					<div class="watermark-share__url-row" data-testid="invisible-watermark-id">
						<input
							id="files-watermark-identifier"
							:value="generated.invisibleWatermarkId"
							readonly
							type="text">
						<NcButton variant="secondary" @click="copyInvisibleWatermarkId">
							{{ t('files_watermark', 'Copy') }}
						</NcButton>
					</div>
					<p class="watermark-share__hint">
						{{ t('files_watermark', 'Keep this identifier to compare with a PixelSeal extraction from a distributed copy.') }}
					</p>
				</template>
			</div>

			<div class="watermark-share__actions">
				<NcButton
					variant="primary"
					:disabled="loading || !canSubmit"
					data-testid="create-link"
					@click="createLink">
					{{ loading ? progressLabel : t('files_watermark', 'Create link') }}
				</NcButton>
				<NcButton
					v-if="generated"
					variant="secondary"
					:disabled="loading"
					data-testid="open-generated"
					@click="openGeneratedSettings">
					{{ t('files_watermark', 'Open generated file settings') }}
				</NcButton>
			</div>
		</template>
	</section>
</template>

<script setup lang="ts">
import type { INode } from '@nextcloud/files'
import type { GeneratedFile } from './services/api'

import { getCapabilities } from '@nextcloud/capabilities'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { getSidebar } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import {
	copyText,
	createPublicShare,
	errorMessage,
	generateWatermarkedFile,
	normalizeWatermark,
	resolveDavNode,
} from './services/api'

type FilesSharingCapabilities = {
	files_sharing?: {
		api_enabled?: boolean
		default_permissions?: number
		public?: {
			enabled?: boolean
			custom_tokens?: boolean
			password?: {
				enforced?: boolean
				askForOptionalPassword?: boolean
			}
			expire_date?: {
				enabled?: boolean
				enforced?: boolean
				days?: number
			}
		}
	}
}

const props = defineProps<{ node: INode }>()
const READ = 1
const UPDATE = 2

const capabilities = getCapabilities() as FilesSharingCapabilities
const sharing = capabilities.files_sharing
const publicCapabilities = sharing?.public

const publicLinksEnabled = sharing?.api_enabled === true && publicCapabilities?.enabled === true
const passwordEnforced = publicCapabilities?.password?.enforced === true
const expirationEnforced = publicCapabilities?.expire_date?.enforced === true
const customTokensAvailable = publicCapabilities?.custom_tokens === true
const talkAvailable = window.OC?.appswebroots?.spreed !== undefined
const defaultCanEdit = ((sharing?.default_permissions ?? READ) & UPDATE) === UPDATE

const watermarkText = ref('')
const permissionMode = ref<'view' | 'edit' | 'custom'>(defaultCanEdit ? 'edit' : 'view')
const customEdit = ref(defaultCanEdit)
const passwordEnabled = ref(passwordEnforced || publicCapabilities?.password?.askForOptionalPassword === true)
const password = ref('')
const expirationEnabled = ref(expirationEnforced || publicCapabilities?.expire_date?.enabled === true)
const expirationDate = ref(defaultExpirationDate(publicCapabilities?.expire_date?.days ?? 7))
const hideDownload = ref(false)
const label = ref('')
const note = ref('')
const sendPasswordByTalk = ref(false)
const loading = ref(false)
const progressLabel = ref(t('files_watermark', 'Creating…'))
const error = ref('')
const publicUrl = ref('')
const generated = ref<GeneratedFile | null>(null)
const generatedNode = ref<INode | null>(null)
const generatedForText = ref('')
const retainedPath = ref('')
let currentOperation = 0

const minimumExpirationDate = formatDate(addDays(new Date(), 1))
const normalizedText = computed(() => normalizeWatermark(watermarkText.value))
const permissions = computed(() => {
	if (permissionMode.value === 'edit') {
		return READ | UPDATE
	}
	if (permissionMode.value === 'custom' && customEdit.value) {
		return READ | UPDATE
	}
	return READ
})
const canSubmit = computed(() => {
	if ((passwordEnabled.value || passwordEnforced) && password.value === '') {
		return false
	}
	if (expirationEnabled.value && expirationDate.value === '') {
		return false
	}
	if (expirationEnabled.value && expirationDate.value < minimumExpirationDate) {
		return false
	}
	return Boolean(props.node.id ?? props.node.fileid) && props.node.path !== ''
})

watch(
	() => [String(props.node.id ?? props.node.fileid ?? ''), props.node.path] as const,
	resetForNode,
)

/**
 *
 * @param event
 */
function onWatermarkInput(event: Event) {
	watermarkText.value = (event.target as HTMLTextAreaElement).value
	if (normalizeWatermark(watermarkText.value) !== generatedForText.value) {
		generated.value = null
		generatedNode.value = null
		generatedForText.value = ''
		retainedPath.value = ''
		publicUrl.value = ''
	}
}

/**
 *
 */
function resetForNode() {
	currentOperation++
	watermarkText.value = ''
	generated.value = null
	generatedNode.value = null
	generatedForText.value = ''
	retainedPath.value = ''
	publicUrl.value = ''
	error.value = ''
	loading.value = false
	progressLabel.value = t('files_watermark', 'Creating…')
}

/**
 * Resolve the generated file without applying stale asynchronous results.
 *
 * @param operation Current operation identifier
 * @param event Files event to emit after resolving the node
 */
async function resolveGenerated(
	operation: number,
	event?: 'files:node:created' | 'files:node:updated',
): Promise<void> {
	const file = generated.value
	if (!file) {
		return
	}
	const node = await resolveDavNode(file.path)
	if (operation !== currentOperation || generated.value !== file) {
		return
	}
	generatedNode.value = node
	if (event) {
		emit(event, node)
	}
}

/**
 *
 */
async function createLink() {
	const operation = ++currentOperation
	const sourceId = String(props.node.id ?? props.node.fileid ?? '')
	const sourcePath = props.node.path
	loading.value = true
	error.value = ''
	publicUrl.value = ''
	retainedPath.value = ''
	let path = sourcePath
	try {
		if (normalizedText.value !== '') {
			if (!generated.value || generatedForText.value !== normalizedText.value) {
				progressLabel.value = t('files_watermark', 'Rendering PDF…')
				const file = await generateWatermarkedFile(sourceId, sourcePath, normalizedText.value)
				if (operation !== currentOperation) {
					return
				}
				generated.value = file
				generatedForText.value = normalizedText.value
				path = file.path
				try {
					await resolveGenerated(operation, 'files:node:created')
				} catch {
					// The exact generated path is still safe to share. DAV resolution is retried by the open action.
					if (operation === currentOperation) {
						generatedNode.value = null
					}
				}
				if (operation !== currentOperation) {
					return
				}
			} else {
				path = generated.value.path
			}
		}

		progressLabel.value = t('files_watermark', 'Creating link…')
		const hasPassword = passwordEnabled.value || passwordEnforced
		const url = await createPublicShare({
			path,
			permissions: permissions.value,
			password: hasPassword ? password.value : undefined,
			expireDate: expirationEnabled.value ? expirationDate.value : '',
			label: label.value.trim() || undefined,
			note: note.value.trim() || undefined,
			hideDownload: hideDownload.value,
			sendPasswordByTalk: talkAvailable && hasPassword && sendPasswordByTalk.value,
		})
		if (operation !== currentOperation) {
			return
		}
		publicUrl.value = url
		if (generated.value) {
			try {
				// Refresh after sharing so DAV share attributes and the Files row stay current.
				await resolveGenerated(operation, 'files:node:updated')
			} catch {
				// The public URL is already valid; opening settings will retry DAV resolution.
			}
		}
		showSuccess(t('files_watermark', 'Public link created'))
	} catch (caught) {
		if (operation !== currentOperation) {
			return
		}
		error.value = errorMessage(caught)
		if (generated.value) {
			retainedPath.value = generated.value.path
		}
	} finally {
		if (operation === currentOperation) {
			loading.value = false
			progressLabel.value = t('files_watermark', 'Creating…')
		}
	}
}

/**
 *
 */
async function copyUrl() {
	try {
		await copyText(publicUrl.value)
		showSuccess(t('files_watermark', 'Link copied'))
	} catch (caught) {
		showError(errorMessage(caught))
	}
}

/** Copy the identifier embedded into every page of the generated derivative. */
async function copyInvisibleWatermarkId() {
	const identifier = generated.value?.invisibleWatermarkId
	if (!identifier) {
		return
	}
	try {
		await copyText(identifier)
		showSuccess(t('files_watermark', 'Invisible watermark identifier copied'))
	} catch (caught) {
		showError(errorMessage(caught))
	}
}

/**
 *
 */
async function openGeneratedSettings() {
	if (!generated.value) {
		return
	}
	try {
		const operation = currentOperation
		if (!generatedNode.value) {
			await resolveGenerated(operation)
		}
		if (operation === currentOperation && generatedNode.value) {
			getSidebar().open(generatedNode.value, 'sharing')
		}
	} catch (caught) {
		showError(errorMessage(caught))
	}
}

/**
 *
 * @param date
 * @param days
 */
function addDays(date: Date, days: number): Date {
	const result = new Date(date)
	result.setDate(result.getDate() + days)
	return result
}

/**
 *
 * @param date
 */
function formatDate(date: Date): string {
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${year}-${month}-${day}`
}

/**
 *
 * @param days
 */
function defaultExpirationDate(days: number): string {
	return formatDate(addDays(new Date(), Math.max(1, days)))
}
</script>

<style scoped>
.watermark-share {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px 16px 16px;
}

.watermark-share h3 {
	margin: 0;
	font-size: 1rem;
}

.watermark-share input,
.watermark-share select,
.watermark-share textarea {
	box-sizing: border-box;
	width: 100%;
}

.watermark-share__hint,
.watermark-share__notice,
.watermark-share__error {
	margin: 0;
	font-size: 0.85rem;
}

.watermark-share__hint {
	color: var(--color-text-maxcontrast);
}

.watermark-share__notice {
	padding: 8px;
	border-radius: var(--border-radius);
	background: var(--color-background-dark);
}

.watermark-share__error {
	color: var(--color-text-error);
}

.watermark-share__advanced summary {
	cursor: pointer;
	font-weight: 600;
}

.watermark-share__advanced-fields,
.watermark-share__custom-permissions {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding-top: 8px;
}

.watermark-share__actions,
.watermark-share__url-row {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.watermark-share__url-row input {
	min-width: 0;
	flex: 1 1 220px;
}

.watermark-share__result {
	display: flex;
	flex-direction: column;
	gap: 4px;
}
</style>
