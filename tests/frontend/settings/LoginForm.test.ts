import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import LoginForm from '@/settings/LoginForm.vue'

vi.mock('@/services/api', () => ({
	settingsApi: {
		login: vi.fn(),
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
	NcTextField: {
		name: 'NcTextField',
		template: '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" />',
		props: ['modelValue', 'label', 'type', 'disabled'],
		emits: ['update:modelValue'],
	},
	NcPasswordField: {
		name: 'NcPasswordField',
		template: '<input class="nc-password-field" type="password" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" />',
		props: ['modelValue', 'label', 'disabled'],
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
	return mount(LoginForm, {
		global: { stubs },
	})
}

describe('LoginForm', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('disables login button when fields are empty', () => {
		const wrapper = mountForm()

		const loginBtn = wrapper.findAll('button').find(b => b.text().includes('Login'))!
		expect(loginBtn.attributes('disabled')).toBeDefined()
	})

	it('calls login API with credentials', async () => {
		mockedSettingsApi.login.mockResolvedValue({ userInfo: { email: 'test@test.com' } })

		const wrapper = mountForm()

		await wrapper.find('.nc-text-field').setValue('test@test.com')
		await wrapper.find('.nc-password-field').setValue('secret')

		const loginBtn = wrapper.findAll('button').find(b => b.text().includes('Login'))!
		await loginBtn.trigger('click')
		await flushPromises()

		expect(mockedSettingsApi.login).toHaveBeenCalledWith('test@test.com', 'secret')
	})

	it('clears fields and emits logged-in on success', async () => {
		const result = { userInfo: { email: 'test@test.com' } }
		mockedSettingsApi.login.mockResolvedValue(result)

		const wrapper = mountForm()

		await wrapper.find('.nc-text-field').setValue('test@test.com')
		await wrapper.find('.nc-password-field').setValue('secret')

		await wrapper.findAll('button').find(b => b.text().includes('Login'))!.trigger('click')
		await flushPromises()

		expect(wrapper.emitted('logged-in')?.[0]).toEqual([result])
		// Fields should be cleared
		expect((wrapper.find('.nc-text-field').element as HTMLInputElement).value).toBe('')
		expect((wrapper.find('.nc-password-field').element as HTMLInputElement).value).toBe('')
	})

	it('shows error on login failure', async () => {
		mockedSettingsApi.login.mockRejectedValue({
			response: { data: { error: 'Invalid credentials' } },
		})

		const wrapper = mountForm()

		await wrapper.find('.nc-text-field').setValue('bad@email.com')
		await wrapper.find('.nc-password-field').setValue('wrong')
		await wrapper.findAll('button').find(b => b.text().includes('Login'))!.trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
		expect(error.text()).toContain('Invalid credentials')
	})

	it('shows fallback error when no response message', async () => {
		mockedSettingsApi.login.mockRejectedValue(new Error('Network error'))

		const wrapper = mountForm()

		await wrapper.find('.nc-text-field').setValue('test@test.com')
		await wrapper.find('.nc-password-field').setValue('pass')
		await wrapper.findAll('button').find(b => b.text().includes('Login'))!.trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
		expect(error.text()).toContain('Login failed')
	})
})
