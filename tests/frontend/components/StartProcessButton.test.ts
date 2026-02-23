import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import StartProcessButton from '@/components/StartProcessButton.vue'

vi.mock('@/services/api', () => ({
	processApi: {
		startWizard: vi.fn(),
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
}

function mountButton(props: { fileId: number; fileName?: string } = { fileId: 42 }) {
	return mount(StartProcessButton, {
		props,
		global: { stubs },
	})
}

describe('StartProcessButton', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('disables button during request', async () => {
		let resolvePromise: (v: any) => void
		mockedProcessApi.startWizard.mockReturnValue(
			new Promise((resolve) => { resolvePromise = resolve }),
		)

		const wrapper = mountButton()
		const btn = wrapper.find('button')

		expect(btn.attributes('disabled')).toBeUndefined()

		await btn.trigger('click')
		await wrapper.vm.$nextTick()

		expect(wrapper.find('button').attributes('disabled')).toBeDefined()

		resolvePromise!({ wizardUrl: 'https://signd.it/wizard/1', processId: 'p1' })
		await flushPromises()

		expect(wrapper.find('button').attributes('disabled')).toBeUndefined()
	})

	it('calls startWizard with fileId', async () => {
		mockedProcessApi.startWizard.mockResolvedValue({ wizardUrl: 'https://signd.it/wizard/1', processId: 'p1' })

		const wrapper = mountButton({ fileId: 99 })
		await wrapper.find('button').trigger('click')
		await flushPromises()

		expect(mockedProcessApi.startWizard).toHaveBeenCalledWith(99)
	})

	it('opens wizard URL in new tab', async () => {
		mockedProcessApi.startWizard.mockResolvedValue({ wizardUrl: 'https://signd.it/wizard/abc', processId: 'p1' })
		const windowOpenSpy = vi.spyOn(window, 'open').mockImplementation(() => null)

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
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

		expect(wrapper.emitted('started')).toHaveLength(1)
		expect(wrapper.emitted('started')![0]).toEqual([result])
	})

	it('shows error on failure', async () => {
		mockedProcessApi.startWizard.mockRejectedValue(new Error('API error'))

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
		expect(mockedExtractError).toHaveBeenCalled()
	})

	it('re-enables button after error', async () => {
		mockedProcessApi.startWizard.mockRejectedValue(new Error('API error'))

		const wrapper = mountButton()
		await wrapper.find('button').trigger('click')
		await flushPromises()

		expect(wrapper.find('button').attributes('disabled')).toBeUndefined()
	})
})
