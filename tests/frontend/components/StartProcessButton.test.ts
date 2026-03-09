import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import StartProcessButton from '@/components/StartProcessButton.vue'

vi.mock('@/services/api', () => ({
	processApi: {
		startWizard: vi.fn(),
		getCurrentUser: vi.fn(),
	},
	extractErrorMessage: vi.fn((_e: unknown, fallback: string) => fallback),
}))

import { processApi, extractErrorMessage } from '@/services/api'

const mockedProcessApi = vi.mocked(processApi)
const mockedExtractError = vi.mocked(extractErrorMessage)

const stubs = {
	NcButton: {
		inheritAttrs: false,
		template: '<button @click="$emit(\'click\')" :disabled="disabled"><slot /><slot name="icon" /></button>',
		props: ['variant', 'wide', 'disabled'],
	},
	NcNoteCard: {
		template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
		props: ['type'],
	},
	NcLoadingIcon: {
		template: '<span class="nc-loading-icon" />',
		props: ['size'],
	},
	NcDialog: {
		inheritAttrs: false,
		template: '<div class="nc-dialog"><slot /><slot name="actions" /></div>',
		props: ['name'],
		emits: ['closing'],
	},
	NcCheckboxRadioSwitch: {
		inheritAttrs: false,
		template: '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', !modelValue)" /><slot /></label>',
		props: ['modelValue'],
		emits: ['update:modelValue'],
	},
	NcTextField: {
		inheritAttrs: false,
		template: '<div class="nc-text-field-wrapper"><input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><span v-if="helperText" class="nc-text-field-helper" :class="{ \'nc-text-field-error\': error }">{{ helperText }}</span></div>',
		props: ['modelValue', 'label', 'type', 'error', 'helperText'],
		emits: ['update:modelValue'],
	},
}

function mountButton(props: { fileId: number; fileName?: string } = { fileId: 42, fileName: 'test.pdf' }) {
	return mount(StartProcessButton, {
		props,
		global: { stubs },
	})
}

describe('StartProcessButton', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mockedProcessApi.getCurrentUser.mockResolvedValue({ displayName: 'Admin', email: 'admin@example.com' })
	})

	it('opens dialog on button click and loads user', async () => {
		const wrapper = mountButton()

		expect(wrapper.find('.nc-dialog').exists()).toBe(false)

		await wrapper.find('button').trigger('click')
		await flushPromises()

		expect(wrapper.find('.nc-dialog').exists()).toBe(true)
		expect(mockedProcessApi.getCurrentUser).toHaveBeenCalled()
	})

	it('prefills email from current user', async () => {
		mockedProcessApi.getCurrentUser.mockResolvedValue({ displayName: 'Test', email: 'test@mail.com' })

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const emailInput = wrapper.find('.nc-text-field')
		expect(emailInput.exists()).toBe(true)
		expect((emailInput.element as HTMLInputElement).value).toBe('test@mail.com')
	})

	it('calls startWizard with notification options', async () => {
		mockedProcessApi.startWizard.mockResolvedValue({ wizardUrl: 'https://signd.it/wizard/1', processId: 'p1' })

		const wrapper = mountButton({ fileId: 99, fileName: 'contract.pdf' })
		await wrapper.find('button').trigger('click')
		await flushPromises()

		// Click the start button (second button in dialog actions)
		const buttons = wrapper.findAll('button')
		const startBtn = buttons[buttons.length - 1]
		await startBtn.trigger('click')
		await flushPromises()

		expect(mockedProcessApi.startWizard).toHaveBeenCalledWith({
			fileId: 99,
			notifyInitiator: true,
			initiatorEmail: 'admin@example.com',
		})
	})

	it('sends notifyInitiator=false when checkbox unchecked', async () => {
		mockedProcessApi.startWizard.mockResolvedValue({ wizardUrl: 'https://signd.it/wizard/1', processId: 'p1' })

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		// Uncheck the notification checkbox
		const checkbox = wrapper.find('.nc-checkbox input')
		await checkbox.trigger('change')
		await wrapper.vm.$nextTick()

		// Click start
		const buttons = wrapper.findAll('button')
		const startBtn = buttons[buttons.length - 1]
		await startBtn.trigger('click')
		await flushPromises()

		expect(mockedProcessApi.startWizard).toHaveBeenCalledWith({
			fileId: 42,
			notifyInitiator: false,
			initiatorEmail: undefined,
		})
	})

	it('opens wizard URL in new tab after start', async () => {
		mockedProcessApi.startWizard.mockResolvedValue({ wizardUrl: 'https://signd.it/wizard/abc', processId: 'p1' })
		const windowOpenSpy = vi.spyOn(window, 'open').mockImplementation(() => null)

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const buttons = wrapper.findAll('button')
		await buttons[buttons.length - 1].trigger('click')
		await flushPromises()

		expect(windowOpenSpy).toHaveBeenCalledWith('https://signd.it/wizard/abc', '_blank')
		windowOpenSpy.mockRestore()
	})

	it('emits started event with result', async () => {
		const result = { wizardUrl: 'https://signd.it/wizard/1', processId: 'p1' }
		mockedProcessApi.startWizard.mockResolvedValue(result)

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const buttons = wrapper.findAll('button')
		await buttons[buttons.length - 1].trigger('click')
		await flushPromises()

		expect(wrapper.emitted('started')).toHaveLength(1)
		expect(wrapper.emitted('started')![0]).toEqual([result])
	})

	it('closes dialog after successful start', async () => {
		mockedProcessApi.startWizard.mockResolvedValue({ wizardUrl: 'https://signd.it/wizard/1', processId: 'p1' })

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		expect(wrapper.find('.nc-dialog').exists()).toBe(true)

		const buttons = wrapper.findAll('button')
		await buttons[buttons.length - 1].trigger('click')
		await flushPromises()

		expect(wrapper.find('.nc-dialog').exists()).toBe(false)
	})

	it('shows error in dialog on failure', async () => {
		mockedProcessApi.startWizard.mockRejectedValue(new Error('API error'))

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const buttons = wrapper.findAll('button')
		await buttons[buttons.length - 1].trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-dialog .nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
		expect(mockedExtractError).toHaveBeenCalled()
	})

	it('hides email field when notifications disabled', async () => {
		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		expect(wrapper.find('.nc-text-field').exists()).toBe(true)

		// Uncheck notification checkbox
		const checkbox = wrapper.find('.nc-checkbox input')
		await checkbox.trigger('change')
		await wrapper.vm.$nextTick()

		expect(wrapper.find('.nc-text-field').exists()).toBe(false)
	})

	it('disables start button when email is empty and notifications enabled', async () => {
		mockedProcessApi.getCurrentUser.mockResolvedValue({ displayName: 'Admin', email: '' })

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const buttons = wrapper.findAll('button')
		const startBtn = buttons[buttons.length - 1]
		expect(startBtn.attributes('disabled')).toBeDefined()
	})

	it('disables start button when email is invalid', async () => {
		mockedProcessApi.getCurrentUser.mockResolvedValue({ displayName: 'Admin', email: 'not-an-email' })

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const buttons = wrapper.findAll('button')
		const startBtn = buttons[buttons.length - 1]
		expect(startBtn.attributes('disabled')).toBeDefined()
	})

	it('shows validation error for invalid email', async () => {
		mockedProcessApi.getCurrentUser.mockResolvedValue({ displayName: 'Admin', email: 'invalid' })

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const helper = wrapper.find('.nc-text-field-error')
		expect(helper.exists()).toBe(true)
		expect(helper.text()).toBe('Please enter a valid email address.')
	})

	it('enables start button when notifications disabled regardless of email', async () => {
		mockedProcessApi.getCurrentUser.mockResolvedValue({ displayName: 'Admin', email: '' })

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		// Start button should be disabled (empty email with notifications on)
		let buttons = wrapper.findAll('button')
		expect(buttons[buttons.length - 1].attributes('disabled')).toBeDefined()

		// Uncheck notifications
		const checkbox = wrapper.find('.nc-checkbox input')
		await checkbox.trigger('change')
		await wrapper.vm.$nextTick()

		// Start button should now be enabled
		buttons = wrapper.findAll('button')
		expect(buttons[buttons.length - 1].attributes('disabled')).toBeUndefined()
	})

	it('shows error when getCurrentUser fails', async () => {
		mockedProcessApi.getCurrentUser.mockRejectedValue(new Error('Network error'))

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-dialog .nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
	})
})
