import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { loadState } from '@nextcloud/initial-state'
import SigndSidebarTab from '@/views/SigndSidebarTab.vue'
import type { SigndProcess } from '@/services/api'

// Mock the API module
vi.mock('@/services/api', () => ({
	processApi: {
		getByFileId: vi.fn(),
		download: vi.fn(),
		resumeWizard: vi.fn(),
		cancelWizard: vi.fn(),
	},
	extractErrorMessage: vi.fn((_e: unknown, fallback: string) => fallback),
}))

// Mock fileListNotify
vi.mock('@/services/fileListNotify', () => ({
	notifyFileCreated: vi.fn(),
}))

import { processApi, extractErrorMessage } from '@/services/api'
import { notifyFileCreated } from '@/services/fileListNotify'

const mockedProcessApi = vi.mocked(processApi)
const mockedExtractError = vi.mocked(extractErrorMessage)
const mockedLoadState = vi.mocked(loadState)
const mockedNotifyFileCreated = vi.mocked(notifyFileCreated)

// Stub definitions with name property for findComponent matching
const ProcessListStub = {
	name: 'ProcessList',
	template: '<div class="process-list-stub" />',
	props: ['processes'],
	emits: ['download', 'resume-wizard', 'cancel-wizard'],
}

const stubs = {
	NcButton: {
		inheritAttrs: false,
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
	NcNoteCard: {
		template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
		props: ['type'],
	},
	NcLoadingIcon: {
		template: '<div class="nc-loading-icon" />',
		props: ['size'],
	},
	ProcessList: ProcessListStub,
	StartProcessButton: {
		name: 'StartProcessButton',
		template: '<div class="start-button-stub" />',
		props: ['fileId', 'fileName'],
		emits: ['started'],
	},
}

function createProcess(overrides: Partial<SigndProcess> = {}): SigndProcess {
	return {
		id: 1,
		fileId: 42,
		processId: 'proc-123',
		userId: 'admin',
		targetDir: null,
		finishedPdfPath: null,
		...overrides,
	}
}

function mountTab(opts: { apiKeySet?: boolean; fileInfo?: object } = {}) {
	mockedLoadState.mockReturnValue(opts.apiKeySet ?? true)

	return mount(SigndSidebarTab, {
		props: {
			fileInfo: opts.fileInfo ?? { id: 42, name: 'test.pdf' },
		},
		global: { stubs },
	})
}

describe('SigndSidebarTab', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mockedProcessApi.getByFileId.mockResolvedValue([])
	})

	// ── API key warning ──

	it('shows warning when no API key is configured', () => {
		const wrapper = mountTab({ apiKeySet: false })

		const warning = wrapper.find('.nc-note-card[data-type="warning"]')
		expect(warning.exists()).toBe(true)
		expect(warning.text()).toContain('not configured')
	})

	it('does not load processes when no API key is set', async () => {
		mountTab({ apiKeySet: false })
		await flushPromises()

		expect(mockedProcessApi.getByFileId).not.toHaveBeenCalled()
	})

	// ── Loading processes ──

	it('loads processes when fileId changes', async () => {
		const processes = [createProcess()]
		mockedProcessApi.getByFileId.mockResolvedValue(processes)

		mountTab({ fileInfo: { id: 42, name: 'test.pdf' } })
		await flushPromises()

		expect(mockedProcessApi.getByFileId).toHaveBeenCalledWith(42)
	})

	it('shows loading spinner during API call', async () => {
		let resolvePromise: (v: SigndProcess[]) => void
		mockedProcessApi.getByFileId.mockReturnValue(
			new Promise((resolve) => {
				resolvePromise = resolve
			}),
		)

		const wrapper = mountTab()
		await vi.waitFor(() => {
			expect(wrapper.find('.nc-loading-icon').exists()).toBe(true)
		})

		resolvePromise!([])
		await flushPromises()
		expect(wrapper.find('.nc-loading-icon').exists()).toBe(false)
	})

	it('shows process list after loading', async () => {
		const processes = [createProcess(), createProcess({ processId: 'proc-456', id: 2 })]
		mockedProcessApi.getByFileId.mockResolvedValue(processes)

		const wrapper = mountTab()
		await flushPromises()

		expect(wrapper.find('.process-list-stub').exists()).toBe(true)
		const list = wrapper.findComponent(ProcessListStub)
		expect(list.props('processes')).toEqual(processes)
	})

	it('shows empty message when no processes exist', async () => {
		mockedProcessApi.getByFileId.mockResolvedValue([])

		const wrapper = mountTab()
		await flushPromises()

		expect(wrapper.find('.signd-empty').exists()).toBe(true)
		expect(wrapper.text()).toContain('No signing processes')
	})

	// ── Error handling ──

	it('shows error on API failure', async () => {
		mockedProcessApi.getByFileId.mockRejectedValue(new Error('Network error'))

		const wrapper = mountTab()
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
		expect(mockedExtractError).toHaveBeenCalled()
	})

	// ── Process actions ──

	it('shows warning when download reports targetDirMissing', async () => {
		const process = createProcess({
			processId: 'proc-123',
			meta: {
				created: '2025-01-01T00:00:00Z',
				filename: 'contract.pdf',
				signersCompleted: [{ id: 's1' }],
				signersRejected: [],
				signersPending: [],
			},
		})
		mockedProcessApi.getByFileId.mockResolvedValue([process])
		mockedProcessApi.download.mockResolvedValue({
			path: '/contract_signed.pdf',
			targetDirMissing: true,
		})

		const wrapper = mountTab()
		await flushPromises()

		const list = wrapper.findComponent(ProcessListStub)
		await list.vm.$emit('download', 'proc-123')
		await flushPromises()

		const warning = wrapper.find('.nc-note-card[data-type="warning"]')
		expect(warning.exists()).toBe(true)
		expect(warning.text()).toContain('home folder')
	})

	it('calls notifyFileCreated after successful download', async () => {
		const process = createProcess({
			processId: 'proc-123',
			meta: {
				created: '2025-01-01T00:00:00Z',
				filename: 'contract.pdf',
				signersCompleted: [{ id: 's1' }],
				signersRejected: [],
				signersPending: [],
			},
		})
		mockedProcessApi.getByFileId.mockResolvedValue([process])

		const downloadResult = {
			path: '/admin/files/Documents/contract_signed.pdf',
			name: 'contract_signed.pdf',
			fileId: 99,
			size: 12345,
			mtime: 1700000000,
			owner: 'admin',
		}
		mockedProcessApi.download.mockResolvedValue(downloadResult)

		const wrapper = mountTab()
		await flushPromises()

		const list = wrapper.findComponent(ProcessListStub)
		await list.vm.$emit('download', 'proc-123')
		await flushPromises()

		expect(mockedNotifyFileCreated).toHaveBeenCalledWith(downloadResult)
	})

	it('does not call notifyFileCreated when download fails', async () => {
		const process = createProcess({ processId: 'proc-123' })
		mockedProcessApi.getByFileId.mockResolvedValue([process])
		mockedProcessApi.download.mockRejectedValue(new Error('Download failed'))

		const wrapper = mountTab()
		await flushPromises()

		const list = wrapper.findComponent(ProcessListStub)
		await list.vm.$emit('download', 'proc-123')
		await flushPromises()

		expect(mockedNotifyFileCreated).not.toHaveBeenCalled()
	})

	it('opens new tab when resuming wizard', async () => {
		const process = createProcess({ isDraft: true })
		mockedProcessApi.getByFileId.mockResolvedValue([process])
		mockedProcessApi.resumeWizard.mockResolvedValue({ wizardUrl: 'https://signd.it/wizard/abc' })

		const windowOpenSpy = vi.spyOn(window, 'open').mockImplementation(() => null)

		const wrapper = mountTab()
		await flushPromises()

		const list = wrapper.findComponent(ProcessListStub)
		await list.vm.$emit('resume-wizard', 'proc-123')
		await flushPromises()

		expect(windowOpenSpy).toHaveBeenCalledWith('https://signd.it/wizard/abc', '_blank')
		windowOpenSpy.mockRestore()
	})

	it('reloads processes after cancelling wizard', async () => {
		const process = createProcess({ isDraft: true })
		mockedProcessApi.getByFileId.mockResolvedValue([process])
		mockedProcessApi.cancelWizard.mockResolvedValue(undefined)

		const wrapper = mountTab()
		await flushPromises()
		expect(mockedProcessApi.getByFileId).toHaveBeenCalledTimes(1)

		const list = wrapper.findComponent(ProcessListStub)
		await list.vm.$emit('cancel-wizard', 'proc-123')
		await flushPromises()

		expect(mockedProcessApi.cancelWizard).toHaveBeenCalledWith('proc-123')
		expect(mockedProcessApi.getByFileId).toHaveBeenCalledTimes(2)
	})
})
