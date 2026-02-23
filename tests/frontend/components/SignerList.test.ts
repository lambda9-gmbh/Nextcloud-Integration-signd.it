import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SignerList from '@/components/SignerList.vue'

function mountList(props: {
	label: string
	signers: Array<{ id: string; clearName?: string; email?: string; signed?: string; rejected?: string }>
	variant?: 'default' | 'rejected' | 'pending'
}) {
	return mount(SignerList, {
		props: {
			label: props.label,
			signers: props.signers,
			variant: props.variant ?? 'default',
		},
	})
}

describe('SignerList', () => {
	it('is hidden when no signers', () => {
		const wrapper = mountList({ label: 'Signed:', signers: [] })
		expect(wrapper.find('.signd-signers').exists()).toBe(false)
	})

	it('shows signer clearName', () => {
		const wrapper = mountList({
			label: 'Signed:',
			signers: [{ id: 's1', clearName: 'Alice Smith' }],
		})

		expect(wrapper.text()).toContain('Alice Smith')
	})

	it('falls back to email when no clearName', () => {
		const wrapper = mountList({
			label: 'Pending:',
			signers: [{ id: 's1', email: 'alice@example.com' }],
		})

		expect(wrapper.text()).toContain('alice@example.com')
	})

	it('falls back to Unknown when no clearName and no email', () => {
		const wrapper = mountList({
			label: 'Pending:',
			signers: [{ id: 's1' }],
		})

		expect(wrapper.text()).toContain('Unknown')
	})

	it('shows signed date', () => {
		const wrapper = mountList({
			label: 'Signed:',
			signers: [{ id: 's1', clearName: 'Alice', signed: '2025-06-15T10:30:00Z' }],
		})

		expect(wrapper.find('.signd-signer-date').exists()).toBe(true)
	})

	it('shows rejected date', () => {
		const wrapper = mountList({
			label: 'Rejected:',
			signers: [{ id: 's1', clearName: 'Bob', rejected: '2025-06-15T11:00:00Z' }],
			variant: 'rejected',
		})

		expect(wrapper.find('.signd-signer-date').exists()).toBe(true)
	})

	it('applies rejected variant class', () => {
		const wrapper = mountList({
			label: 'Rejected:',
			signers: [{ id: 's1', clearName: 'Bob' }],
			variant: 'rejected',
		})

		expect(wrapper.find('.signd-signer--rejected').exists()).toBe(true)
	})

	it('applies pending variant class', () => {
		const wrapper = mountList({
			label: 'Pending:',
			signers: [{ id: 's1', clearName: 'Charlie' }],
			variant: 'pending',
		})

		expect(wrapper.find('.signd-signer--pending').exists()).toBe(true)
	})
})
