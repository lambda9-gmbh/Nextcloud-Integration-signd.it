import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ProcessDetail from '@/components/overview/ProcessDetail.vue'
import type { FoundProcess } from '@/services/api'

vi.mock('@/services/api', () => ({
	overviewApi: {
		cancel: vi.fn(),
	},
	processApi: {
		refresh: vi.fn(),
		download: vi.fn(),
	},
	extractErrorMessage: vi.fn((_e: unknown, fallback: string) => fallback),
}))

import { overviewApi, processApi, extractErrorMessage } from '@/services/api'

const mockedOverviewApi = vi.mocked(overviewApi)
const mockedProcessApi = vi.mocked(processApi)

const stubs = {
	NcButton: {
		inheritAttrs: false,
		template: '<button @click="$emit(\'click\')" :disabled="disabled"><slot /><slot name="icon" /></button>',
		props: ['variant', 'disabled'],
	},
	NcNoteCard: {
		template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
		props: ['type'],
	},
	NcLoadingIcon: {
		template: '<span class="nc-loading-icon" />',
		props: ['size'],
	},
	SignerList: {
		template: '<div class="signer-list-stub" />',
		props: ['label', 'signers', 'variant'],
	},
}

function createProcess(overrides: Partial<FoundProcess> = {}): FoundProcess {
	return {
		processId: 'proc-1',
		name: 'Test Process',
		created: '2025-01-15T10:00:00Z',
		signersCompleted: [],
		signersRejected: [],
		signersPending: [{ id: 's1', clearName: 'Bob' }],
		...overrides,
	}
}

function mountDetail(process?: FoundProcess) {
	return mount(ProcessDetail, {
		props: { process: process ?? createProcess() },
		global: { stubs },
	})
}

describe('ProcessDetail', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('shows download button when process is finished', () => {
		const wrapper = mountDetail(createProcess({
			signersCompleted: [{ id: 's1' }],
			signersPending: [],
			signersRejected: [],
		}))

		const downloadBtn = wrapper.findAll('button').find(b => b.text().includes('Download signed PDF'))
		expect(downloadBtn).toBeTruthy()
	})

	it('hides download button when process is cancelled', () => {
		const wrapper = mountDetail(createProcess({
			cancelled: '2025-01-02T00:00:00Z',
			signersCompleted: [{ id: 's1' }],
			signersPending: [],
		}))

		const downloadBtn = wrapper.findAll('button').find(b => b.text().includes('Download signed PDF'))
		expect(downloadBtn).toBeUndefined()
	})

	it('shows cancel button when signers are pending', () => {
		const wrapper = mountDetail(createProcess({
			signersPending: [{ id: 's1' }],
		}))

		const cancelBtn = wrapper.findAll('button').find(b => b.text().includes('Cancel process'))
		expect(cancelBtn).toBeTruthy()
	})

	it('hides cancel button when process is finished', () => {
		const wrapper = mountDetail(createProcess({
			signersCompleted: [{ id: 's1' }],
			signersPending: [],
			signersRejected: [],
		}))

		const cancelBtn = wrapper.findAll('button').find(b => b.text().includes('Cancel process'))
		expect(cancelBtn).toBeUndefined()
	})

	it('shows target dir warning on download', async () => {
		mockedProcessApi.download.mockResolvedValue({
			path: '/signed.pdf',
			targetDirMissing: true,
		})

		const wrapper = mountDetail(createProcess({
			signersCompleted: [{ id: 's1' }],
			signersPending: [],
			signersRejected: [],
		}))

		const downloadBtn = wrapper.findAll('button').find(b => b.text().includes('Download'))!
		await downloadBtn.trigger('click')
		await flushPromises()

		const success = wrapper.find('.nc-note-card[data-type="success"]')
		expect(success.exists()).toBe(true)
		expect(success.text()).toContain('home folder')
	})

	it('emits cancelled event after cancel', async () => {
		mockedOverviewApi.cancel.mockResolvedValue(undefined)

		const wrapper = mountDetail()

		const cancelBtn = wrapper.findAll('button').find(b => b.text().includes('Cancel process'))!
		await cancelBtn.trigger('click')
		await flushPromises()

		expect(wrapper.emitted('cancelled')).toHaveLength(1)
	})

	it('emits refresh event after refresh', async () => {
		mockedProcessApi.refresh.mockResolvedValue({ processId: 'proc-1' } as any)

		const wrapper = mountDetail()

		const refreshBtn = wrapper.findAll('button').find(b => b.text().includes('Refresh'))!
		await refreshBtn.trigger('click')
		await flushPromises()

		expect(wrapper.emitted('refresh')).toHaveLength(1)
	})

	it('shows file link using fileId', () => {
		const wrapper = mountDetail(createProcess({
			apiClientMetaData: {
				applicationMetaData: {
					ncFileId: '42',
					ncFileName: 'contract.pdf',
					_ncFileExists: true,
				},
			},
		}))

		const link = wrapper.find('.signd-file-link')
		expect(link.exists()).toBe(true)
		expect(link.attributes('href')).toContain('fileid')
	})
})
