// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AdminSettings from '@/settings/AdminSettings.vue'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, key: string, fallback: unknown) => {
		if (key === 'api_key_set') return false
		if (key === 'api_key_valid') return true
		if (key === 'user_info') return null
		return fallback
	}),
}))

vi.mock('@/services/api', () => ({
	settingsApi: {
		deleteApiKey: vi.fn(),
	},
}))

import { settingsApi } from '@/services/api'
import { loadState } from '@nextcloud/initial-state'

const mockedSettingsApi = vi.mocked(settingsApi)
const mockedLoadState = vi.mocked(loadState)

const NcSettingsSectionStub = {
	name: 'NcSettingsSection',
	template: '<div class="nc-settings-section"><slot /></div>',
	props: ['name'],
}

const NcButtonStub = {
	name: 'NcButton',
	inheritAttrs: false,
	template: '<button @click="$emit(\'click\')" :disabled="disabled"><slot /></button>',
	props: ['variant', 'disabled'],
}

const NcNoteCardStub = {
	name: 'NcNoteCard',
	template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
	props: ['type'],
}

const NcLoadingIconStub = {
	name: 'NcLoadingIcon',
	template: '<span class="nc-loading-icon" />',
	props: ['size'],
}

const ApiKeyFormStub = {
	name: 'ApiKeyForm',
	template: '<div class="apikey-form-stub" />',
	emits: ['saved'],
}

const LoginFormStub = {
	name: 'LoginForm',
	template: '<div class="login-form-stub" />',
	emits: ['logged-in'],
}

const RegisterFormStub = {
	name: 'RegisterForm',
	template: '<div class="register-form-stub" />',
	emits: ['registered'],
}

const stubs = {
	NcSettingsSection: NcSettingsSectionStub,
	NcButton: NcButtonStub,
	NcNoteCard: NcNoteCardStub,
	NcLoadingIcon: NcLoadingIconStub,
	ApiKeyForm: ApiKeyFormStub,
	LoginForm: LoginFormStub,
	RegisterForm: RegisterFormStub,
}

function mountSettings(initialStateOverrides: Record<string, unknown> = {}) {
	mockedLoadState.mockImplementation((_app: string, key: string, fallback: unknown) => {
		if (key in initialStateOverrides) return initialStateOverrides[key]
		if (key === 'api_key_set') return false
		if (key === 'api_key_valid') return true
		if (key === 'user_info') return null
		return fallback
	})

	return mount(AdminSettings, {
		global: { stubs },
	})
}

describe('AdminSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('shows warning when no API key is configured', () => {
		const wrapper = mountSettings()

		const warning = wrapper.find('.nc-note-card[data-type="warning"]')
		expect(warning.exists()).toBe(true)
		expect(warning.text()).toContain('No API key configured')
	})

	it('shows success when key is valid', () => {
		const wrapper = mountSettings({
			api_key_set: true,
			api_key_valid: true,
			user_info: { email: 'test@example.com', clearName: 'Test User' },
		})

		const success = wrapper.find('.nc-note-card[data-type="success"]')
		expect(success.exists()).toBe(true)
		expect(success.text()).toContain('API key is configured')
		expect(success.text()).toContain('Test User')
		expect(success.text()).toContain('test@example.com')
	})

	it('shows error when key is invalid', () => {
		const wrapper = mountSettings({
			api_key_set: true,
			api_key_valid: false,
		})

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
		expect(error.text()).toContain('no longer valid')
	})

	it('shows unreachable warning with disconnect button when key is set', () => {
		const wrapper = mountSettings({
			api_key_set: true,
			api_key_valid: true,
			signd_unreachable: true,
		})

		const warning = wrapper.find('.nc-note-card[data-type="warning"]')
		expect(warning.exists()).toBe(true)
		expect(warning.text()).toContain('Cannot reach')
		// Should show disconnect button
		const disconnectBtn = wrapper.findAll('button').find(b => b.text().includes('Disconnect'))
		expect(disconnectBtn).toBeTruthy()
		// Should not show invalid-key error or tabs
		expect(wrapper.find('.nc-note-card[data-type="error"]').exists()).toBe(false)
		expect(wrapper.findComponent(ApiKeyFormStub).exists()).toBe(false)
	})

	it('shows tabs when no key is set even if service is unreachable', () => {
		const wrapper = mountSettings({
			api_key_set: false,
			signd_unreachable: true,
		})

		// Tabs should be visible so the user can configure
		expect(wrapper.findComponent(ApiKeyFormStub).exists()).toBe(true)
	})

	it('shows disconnect button when key is set', () => {
		const wrapper = mountSettings({ api_key_set: true })

		const buttons = wrapper.findAll('button')
		const disconnectBtn = buttons.find(b => b.text().includes('Disconnect'))
		expect(disconnectBtn).toBeTruthy()
	})

	it('hides disconnect button when no key', () => {
		const wrapper = mountSettings({ api_key_set: false })

		const buttons = wrapper.findAll('button')
		const disconnectBtn = buttons.find(b => b.text().includes('Disconnect'))
		expect(disconnectBtn).toBeUndefined()
	})

	it('calls deleteApiKey and resets state on disconnect', async () => {
		mockedSettingsApi.deleteApiKey.mockResolvedValue(undefined)

		const wrapper = mountSettings({
			api_key_set: true,
			api_key_valid: true,
			user_info: { email: 'test@example.com' },
		})

		const disconnectBtn = wrapper.findAll('button').find(b => b.text().includes('Disconnect'))!
		await disconnectBtn.trigger('click')
		await flushPromises()

		expect(mockedSettingsApi.deleteApiKey).toHaveBeenCalledOnce()
		// After disconnect, warning card should appear
		const warning = wrapper.find('.nc-note-card[data-type="warning"]')
		expect(warning.exists()).toBe(true)
	})

	it('switches tabs', async () => {
		const wrapper = mountSettings()

		// Default: apikey tab visible
		expect(wrapper.findComponent(ApiKeyFormStub).exists()).toBe(true)
		expect(wrapper.findComponent(LoginFormStub).exists()).toBe(false)

		// Click Login tab
		const loginTab = wrapper.findAll('button').find(b => b.text().includes('Login'))!
		await loginTab.trigger('click')

		expect(wrapper.findComponent(ApiKeyFormStub).exists()).toBe(false)
		expect(wrapper.findComponent(LoginFormStub).exists()).toBe(true)

		// Click Register tab
		const registerTab = wrapper.findAll('button').find(b => b.text().includes('Register'))!
		await registerTab.trigger('click')

		expect(wrapper.findComponent(LoginFormStub).exists()).toBe(false)
		expect(wrapper.findComponent(RegisterFormStub).exists()).toBe(true)
	})

	it('updates state when ApiKeyForm emits saved', async () => {
		const wrapper = mountSettings()

		const form = wrapper.findComponent(ApiKeyFormStub)
		form.vm.$emit('saved', { userInfo: { email: 'new@example.com', clearName: 'New' } })
		await wrapper.vm.$nextTick()

		const success = wrapper.find('.nc-note-card[data-type="success"]')
		expect(success.exists()).toBe(true)
		expect(success.text()).toContain('new@example.com')
	})
})
