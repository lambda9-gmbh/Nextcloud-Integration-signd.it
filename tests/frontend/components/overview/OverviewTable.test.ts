import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import OverviewTable from '@/components/overview/OverviewTable.vue'
import type { FoundProcess } from '@/services/api'

function createProcess(overrides: Partial<FoundProcess> = {}): FoundProcess {
	return {
		processId: 'proc-1',
		name: 'Test Process',
		status: 'RUNNING',
		created: '2025-01-15T10:00:00Z',
		signersCompleted: [{ id: 's1', clearName: 'Alice' }],
		signersRejected: [],
		signersPending: [{ id: 's2', clearName: 'Bob' }],
		...overrides,
	}
}

function mountTable(props: Partial<{
	processes: FoundProcess[],
	sortCriteria: string,
	sortOrder: string,
	selectedId: string,
}> = {}) {
	return mount(OverviewTable, {
		props: {
			processes: props.processes ?? [createProcess()],
			sortCriteria: props.sortCriteria ?? 'CREATED',
			sortOrder: props.sortOrder ?? 'DESC',
			selectedId: props.selectedId ?? '',
		},
	})
}

describe('OverviewTable', () => {
	it('toggles sort order for same column', async () => {
		const wrapper = mountTable({ sortCriteria: 'CREATED', sortOrder: 'DESC' })

		// Click the CREATED column header (5th column, index 4)
		const headers = wrapper.findAll('th')
		const createdHeader = headers.find(h => h.text().includes('Created'))!
		await createdHeader.trigger('click')

		// Should toggle to ASC
		expect(wrapper.emitted('update:sortOrder')?.[0]).toEqual(['ASC'])
	})

	it('resets sort to DESC for new column', async () => {
		const wrapper = mountTable({ sortCriteria: 'CREATED', sortOrder: 'ASC' })

		// Click the NAME column header
		const headers = wrapper.findAll('th')
		const nameHeader = headers.find(h => h.text().includes('Process name'))!
		await nameHeader.trigger('click')

		expect(wrapper.emitted('update:sortCriteria')?.[0]).toEqual(['NAME'])
		expect(wrapper.emitted('update:sortOrder')?.[0]).toEqual(['DESC'])
	})

	it('shows file link when file exists', () => {
		const wrapper = mountTable({
			processes: [createProcess({
				apiClientMetaData: {
					applicationMetaData: {
						ncFileId: '42',
						ncFileName: 'contract.pdf',
						_ncFileExists: true,
					},
				},
			})],
		})

		const link = wrapper.find('.signd-file-link')
		expect(link.exists()).toBe(true)
		expect(link.attributes('href')).toContain('fileid')
	})

	it('hides file link when file is deleted', () => {
		const wrapper = mountTable({
			processes: [createProcess({
				apiClientMetaData: {
					applicationMetaData: {
						ncFileId: '42',
						ncFileName: 'contract.pdf',
						_ncFileExists: false,
					},
				},
			})],
		})

		const link = wrapper.find('a.signd-file-link')
		expect(link.exists()).toBe(false)
		expect(wrapper.find('.signd-file-link--missing').exists()).toBe(true)
	})

	it('maps status classes correctly', () => {
		// Cancelled → error
		const cancelled = mountTable({ processes: [createProcess({ cancelled: '2025-01-01' })] })
		expect(cancelled.find('.signd-badge--error').exists()).toBe(true)

		// Finished → success
		const finished = mountTable({
			processes: [createProcess({
				signersCompleted: [{ id: 's1' }],
				signersPending: [],
				signersRejected: [],
			})],
		})
		expect(finished.find('.signd-badge--success').exists()).toBe(true)

		// Pending → pending
		const pending = mountTable({
			processes: [createProcess({
				signersCompleted: [],
				signersPending: [{ id: 's1' }],
			})],
		})
		expect(pending.find('.signd-badge--pending').exists()).toBe(true)
	})

	it('calculates progress correctly', () => {
		const wrapper = mountTable({
			processes: [createProcess({
				signersCompleted: [{ id: 's1' }, { id: 's2' }],
				signersRejected: [{ id: 's3' }],
				signersPending: [{ id: 's4' }],
			})],
		})

		// 2 completed out of 4 total
		expect(wrapper.text()).toContain('2/4')
	})

	it('shows empty state message', () => {
		const wrapper = mountTable({ processes: [] })
		expect(wrapper.text()).toContain('No processes found.')
	})

	it('emits select on row click', async () => {
		const process = createProcess()
		const wrapper = mountTable({ processes: [process] })

		await wrapper.find('.signd-overview-table__row').trigger('click')

		expect(wrapper.emitted('select')).toHaveLength(1)
		expect(wrapper.emitted('select')![0]).toEqual([process])
	})
})
