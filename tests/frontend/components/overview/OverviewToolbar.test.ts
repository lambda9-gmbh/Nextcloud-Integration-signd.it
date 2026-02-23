import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import OverviewToolbar from '@/components/overview/OverviewToolbar.vue'

const NcSelectStub = {
	name: 'NcSelect',
	template: '<select @change="$emit(\'update:modelValue\', $event)"><slot /></select>',
	props: ['modelValue', 'options', 'clearable', 'searchable', 'label'],
	emits: ['update:modelValue'],
}

const stubs = {
	NcTextField: {
		name: 'NcTextField',
		template: '<input class="nc-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
		props: ['modelValue', 'label', 'showTrailingButton', 'trailingButtonIcon'],
		emits: ['update:modelValue', 'trailing-button-click'],
	},
	NcSelect: NcSelectStub,
	NcDateTimePicker: {
		name: 'NcDateTimePicker',
		template: '<input class="nc-date-picker" />',
		props: ['modelValue', 'type', 'clearable', 'placeholder'],
		emits: ['update:modelValue'],
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		inheritAttrs: false,
		template: '<label class="nc-checkbox"><input type="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" /><slot /></label>',
		props: ['modelValue', 'type'],
		emits: ['update:modelValue'],
	},
}

function mountToolbar(props: Partial<{
	status: string,
	searchQuery: string,
	dateFrom: string,
	dateTo: string,
	onlyMine: boolean,
}> = {}) {
	return mount(OverviewToolbar, {
		props: {
			status: props.status ?? 'ALL',
			searchQuery: props.searchQuery ?? '',
			dateFrom: props.dateFrom ?? '',
			dateTo: props.dateTo ?? '',
			onlyMine: props.onlyMine ?? false,
		},
		global: { stubs },
	})
}

describe('OverviewToolbar', () => {
	beforeEach(() => {
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('emits status update when select changes', async () => {
		const wrapper = mountToolbar()

		// Directly call the computed setter
		;(wrapper.vm as any).selectedStatus = { value: 'FINISHED', label: 'Finished' }
		await wrapper.vm.$nextTick()

		expect(wrapper.emitted('update:status')?.[0]).toEqual(['FINISHED'])
	})

	it('debounces search input by 400ms', async () => {
		const wrapper = mountToolbar()

		;(wrapper.vm as any).onSearchInput('contract')

		// Not emitted immediately
		expect(wrapper.emitted('update:searchQuery')).toBeUndefined()

		// Advance 400ms
		vi.advanceTimersByTime(400)

		expect(wrapper.emitted('update:searchQuery')?.[0]).toEqual(['contract'])
	})

	it('clears search immediately without debounce', async () => {
		const wrapper = mountToolbar({ searchQuery: 'test' })

		;(wrapper.vm as any).clearSearch()

		expect(wrapper.emitted('update:searchQuery')?.[0]).toEqual([''])
	})

	it('formats date as YYYY-MM-DD', async () => {
		const wrapper = mountToolbar()

		// Simulate setting dateFromModel
		;(wrapper.vm as any).dateFromModel = new Date(2025, 5, 15) // June 15, 2025
		await wrapper.vm.$nextTick()

		expect(wrapper.emitted('update:dateFrom')?.[0]).toEqual(['2025-06-15'])
	})

	it('emits onlyMine as boolean', async () => {
		const wrapper = mountToolbar()

		const checkbox = wrapper.find('input[type="checkbox"]')
		await checkbox.setValue(true)

		expect(wrapper.emitted('update:onlyMine')?.[0]).toEqual([true])
	})
})
