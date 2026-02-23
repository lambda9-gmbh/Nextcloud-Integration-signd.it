import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import RegisterForm from '@/settings/RegisterForm.vue'

vi.mock('@/services/api', () => ({
	settingsApi: {
		getPrices: vi.fn(),
		register: vi.fn(),
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
		template: '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" :data-label="label" />',
		props: ['modelValue', 'label', 'type', 'disabled'],
		emits: ['update:modelValue'],
	},
	NcPasswordField: {
		name: 'NcPasswordField',
		template: '<input class="nc-password-field" type="password" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" :disabled="disabled" />',
		props: ['modelValue', 'label', 'disabled'],
		emits: ['update:modelValue'],
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		inheritAttrs: false,
		template: '<label class="nc-checkbox"><input type="checkbox" :checked="checked" @change="$emit(\'update:checked\', $event.target.checked)" /><slot /></label>',
		props: ['checked', 'value', 'name', 'type', 'disabled'],
		emits: ['update:checked'],
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

const mockPrices = {
	premium: { perProcess: 1.5, perMonthAndUser: 9.99, includedProcessesPerMonth: 10, sms: 0.15, qes: 2.5 },
	enterprise: { perProcess: 1.0, perMonthAndUser: 19.99, includedProcessesPerMonth: 50, sms: 0.10, qes: 2.0 },
}

function mountForm() {
	return mount(RegisterForm, {
		global: { stubs },
	})
}

async function fillRequiredFields(wrapper: ReturnType<typeof mountForm>) {
	const inputs = wrapper.findAll('.nc-text-field')

	// inputs[0] = organisation, [1] = street, [2] = houseNumber, [3] = zipCode,
	// [4] = city, [5] = country (pre-filled DE), [6] = clearName, [7] = email,
	// [8] = vatId (optional), [9] = couponCode (optional)
	await inputs[0].setValue('Test GmbH')
	await inputs[1].setValue('Teststraße')
	await inputs[2].setValue('42')
	await inputs[3].setValue('12345')
	await inputs[4].setValue('Berlin')
	// Country pre-filled with DE — skip
	await inputs[6].setValue('Max Mustermann')
	await inputs[7].setValue('max@example.com')
	// Password
	await wrapper.find('.nc-password-field').setValue('secret123')

	// Checkboxes: [0]+[1] = plan radio buttons (from plan options),
	// [2] = AGB, [3] = DSB
	const checkboxes = wrapper.findAll('input[type="checkbox"]')
	await checkboxes[2].setValue(true)
	await checkboxes[3].setValue(true)
}

describe('RegisterForm', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mockedSettingsApi.getPrices.mockResolvedValue(mockPrices)
	})

	it('loads prices on mount', async () => {
		mountForm()
		await flushPromises()

		expect(mockedSettingsApi.getPrices).toHaveBeenCalledOnce()
	})

	it('shows plan options with prices after load', async () => {
		const wrapper = mountForm()
		await flushPromises()

		expect(wrapper.text()).toContain('Premium')
		expect(wrapper.text()).toContain('Enterprise')
		expect(wrapper.text()).toContain('9.99')
	})

	it('disables register button until all required fields are filled', async () => {
		const wrapper = mountForm()
		await flushPromises()

		const registerBtn = wrapper.findAll('button').find(b => b.text().includes('Register'))!
		expect(registerBtn.attributes('disabled')).toBeDefined()
	})

	it('enables register button when all fields are filled', async () => {
		const wrapper = mountForm()
		await flushPromises()

		await fillRequiredFields(wrapper)

		const registerBtn = wrapper.findAll('button').find(b => b.text().includes('Register'))!
		expect(registerBtn.attributes('disabled')).toBeUndefined()
	})

	it('defaults country to DE', () => {
		const wrapper = mountForm()
		expect((wrapper.vm as any).form.country).toBe('DE')
	})

	it('defaults plan to premium', () => {
		const wrapper = mountForm()
		expect((wrapper.vm as any).form.productPlan).toBe('premium')
	})

	it('calls register API with form data', async () => {
		mockedSettingsApi.register.mockResolvedValue({ success: true })

		const wrapper = mountForm()
		await flushPromises()

		await fillRequiredFields(wrapper)

		await wrapper.findAll('button').find(b => b.text().includes('Register'))!.trigger('click')
		await flushPromises()

		expect(mockedSettingsApi.register).toHaveBeenCalledOnce()
		const calledWith = mockedSettingsApi.register.mock.calls[0][0]
		expect(calledWith.organisation).toBe('Test GmbH')
		expect(calledWith.email).toBe('max@example.com')
		expect(calledWith.agbAccepted).toBe(true)
		expect(calledWith.dsbAccepted).toBe(true)
	})

	it('emits registered on success', async () => {
		const result = { success: true }
		mockedSettingsApi.register.mockResolvedValue(result)

		const wrapper = mountForm()
		await flushPromises()

		await fillRequiredFields(wrapper)
		await wrapper.findAll('button').find(b => b.text().includes('Register'))!.trigger('click')
		await flushPromises()

		expect(wrapper.emitted('registered')?.[0]).toEqual([result])
	})

	it('shows error on failure', async () => {
		mockedSettingsApi.register.mockRejectedValue({
			response: { data: { error: 'Email already exists' } },
		})

		const wrapper = mountForm()
		await flushPromises()

		await fillRequiredFields(wrapper)
		await wrapper.findAll('button').find(b => b.text().includes('Register'))!.trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
		expect(error.text()).toContain('Email already exists')
	})

	it('has terms link pointing to correct URL', () => {
		const wrapper = mountForm()

		const links = wrapper.findAll('a')
		const termsLink = links.find(l => l.text().includes('Terms and Conditions'))
		expect(termsLink).toBeTruthy()
		expect(termsLink!.attributes('href')).toBe('https://signd.it/terms-and-conditions')
	})

	it('has privacy link pointing to correct URL', () => {
		const wrapper = mountForm()

		const links = wrapper.findAll('a')
		const privacyLink = links.find(l => l.text().includes('Privacy Policy'))
		expect(privacyLink).toBeTruthy()
		expect(privacyLink!.attributes('href')).toBe('https://signd.it/privacy-policy')
	})
})
