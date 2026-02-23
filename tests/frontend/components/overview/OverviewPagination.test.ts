import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import OverviewPagination from '@/components/overview/OverviewPagination.vue'

const stubs = {
	NcButton: {
		inheritAttrs: false,
		template: '<button @click="$emit(\'click\')" :disabled="disabled"><slot /></button>',
		props: ['variant', 'disabled'],
	},
}

function mountPagination(props: { offset: number; limit: number; total: number }) {
	return mount(OverviewPagination, {
		props,
		global: { stubs },
	})
}

describe('OverviewPagination', () => {
	it('is hidden when total is zero', () => {
		const wrapper = mountPagination({ offset: 0, limit: 25, total: 0 })
		expect(wrapper.find('.signd-pagination').exists()).toBe(false)
	})

	it('disables prev button at start', () => {
		const wrapper = mountPagination({ offset: 0, limit: 25, total: 100 })
		const buttons = wrapper.findAll('button')
		const prevBtn = buttons[0]

		expect(prevBtn.attributes('disabled')).toBeDefined()
	})

	it('disables next button at end', () => {
		const wrapper = mountPagination({ offset: 75, limit: 25, total: 100 })
		const buttons = wrapper.findAll('button')
		const nextBtn = buttons[1]

		expect(nextBtn.attributes('disabled')).toBeDefined()
	})

	it('shows range text', () => {
		const wrapper = mountPagination({ offset: 0, limit: 25, total: 100 })

		expect(wrapper.text()).toContain('1')
		expect(wrapper.text()).toContain('25')
		expect(wrapper.text()).toContain('100')
	})

	it('emits prev on click', async () => {
		const wrapper = mountPagination({ offset: 25, limit: 25, total: 100 })
		const prevBtn = wrapper.findAll('button')[0]

		await prevBtn.trigger('click')
		expect(wrapper.emitted('prev')).toHaveLength(1)
	})

	it('emits next on click', async () => {
		const wrapper = mountPagination({ offset: 0, limit: 25, total: 100 })
		const nextBtn = wrapper.findAll('button')[1]

		await nextBtn.trigger('click')
		expect(wrapper.emitted('next')).toHaveLength(1)
	})
})
