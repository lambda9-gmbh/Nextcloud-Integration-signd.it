import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ApiKeyForm from '@/settings/ApiKeyForm.vue'

vi.mock('@/services/api', () => ({
	settingsApi: {
		saveApiKey: vi.fn(),
	},
}))

import { settingsApi } from '@/services/api'

const mockedSettingsApi = vi.mocked(settingsApi)

const stubs = {
	NcButton: {
		name: 'NcButton',
		inheritAttrs: false,
		template: '<button @click="$emit(\'click\')" :disabled="disabled"><slot /><slot name="icon" /></button>',
		props: ['variant', 'disabled'],
	},
	NcPasswordField: {
		name: 'NcPasswordField',
		template: '<input class="nc-password-field" type="password" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" />',
		props: ['modelValue', 'label', 'placeholder', 'disabled'],
		emits: ['update:modelValue'],
	},
	NcNoteCard: {
		name: 'NcNoteCard',
		template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
		props: ['type'],
	},
	NcLoadingIcon: {
		name: 'NcLoadingIcon',
		template: '<span class="nc-loading-icon" />',
		props: ['size'],
	},
}

function mountForm() {
	return mount(ApiKeyForm, {
		global: { stubs },
	})
}

describe('ApiKeyForm', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('disables save button when API key is empty', () => {
		const wrapper = mountForm()

		const saveBtn = wrapper.findAll('button').find(b => b.text().includes('Save'))!
		expect(saveBtn.attributes('disabled')).toBeDefined()
	})

	it('calls saveApiKey with entered key', async () => {
		mockedSettingsApi.saveApiKey.mockResolvedValue({ userInfo: { email: 'a@b.com' } })

		const wrapper = mountForm()

		const input = wrapper.find('.nc-password-field')
		await input.setValue('my-api-key-123')

		const saveBtn = wrapper.findAll('button').find(b => b.text().includes('Save'))!
		await saveBtn.trigger('click')
		await flushPromises()

		expect(mockedSettingsApi.saveApiKey).toHaveBeenCalledWith('my-api-key-123')
	})

	it('clears input and emits saved on success', async () => {
		const result = { userInfo: { email: 'a@b.com' } }
		mockedSettingsApi.saveApiKey.mockResolvedValue(result)

		const wrapper = mountForm()

		const input = wrapper.find('.nc-password-field')
		await input.setValue('my-key')

		const saveBtn = wrapper.findAll('button').find(b => b.text().includes('Save'))!
		await saveBtn.trigger('click')
		await flushPromises()

		expect(wrapper.emitted('saved')?.[0]).toEqual([result])
		// Input should be cleared
		expect((wrapper.find('.nc-password-field').element as HTMLInputElement).value).toBe('')
	})

	it('shows error on failure', async () => {
		mockedSettingsApi.saveApiKey.mockRejectedValue({
			response: { data: { error: 'Key invalid' } },
		})

		const wrapper = mountForm()

		await wrapper.find('.nc-password-field').setValue('bad-key')
		await wrapper.findAll('button').find(b => b.text().includes('Save'))!.trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
	})

	it('shows specific error for SIGND_UNREACHABLE', async () => {
		mockedSettingsApi.saveApiKey.mockRejectedValue({
			response: { data: { errorCode: 'SIGND_UNREACHABLE' } },
		})

		const wrapper = mountForm()

		await wrapper.find('.nc-password-field').setValue('key')
		await wrapper.findAll('button').find(b => b.text().includes('Save'))!.trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.text()).toContain('Cannot reach')
	})

	it('shows specific error for SIGND_UNAUTHORIZED', async () => {
		mockedSettingsApi.saveApiKey.mockRejectedValue({
			response: { data: { errorCode: 'SIGND_UNAUTHORIZED' } },
		})

		const wrapper = mountForm()

		await wrapper.find('.nc-password-field').setValue('key')
		await wrapper.findAll('button').find(b => b.text().includes('Save'))!.trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.text()).toContain('Invalid API key')
	})

	it('re-enables button after error', async () => {
		mockedSettingsApi.saveApiKey.mockRejectedValue(new Error('fail'))

		const wrapper = mountForm()

		await wrapper.find('.nc-password-field').setValue('key')
		const saveBtn = wrapper.findAll('button').find(b => b.text().includes('Save'))!
		await saveBtn.trigger('click')
		await flushPromises()

		// Button should not be disabled after error (isSaving = false, apiKey still set)
		expect(saveBtn.attributes('disabled')).toBeUndefined()
	})
})
