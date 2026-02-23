import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { loadState } from '@nextcloud/initial-state'
import OverviewApp from '@/views/OverviewApp.vue'
import type { FoundProcess, FoundProcessesResponse } from '@/services/api'

// Mock the API module
vi.mock('@/services/api', () => ({
	overviewApi: {
		list: vi.fn(),
		cancel: vi.fn(),
	},
	extractErrorMessage: vi.fn((_e: unknown, fallback: string) => fallback),
}))

import { overviewApi, extractErrorMessage } from '@/services/api'

const mockedOverviewApi = vi.mocked(overviewApi)
const mockedExtractError = vi.mocked(extractErrorMessage)
const mockedLoadState = vi.mocked(loadState)

// Named stub definitions for findComponent matching
const OverviewToolbarStub = {
	name: 'OverviewToolbar',
	template: '<div class="toolbar-stub" />',
	props: ['status', 'searchQuery', 'dateFrom', 'dateTo', 'onlyMine'],
	emits: ['update:status', 'update:search-query', 'update:date-from', 'update:date-to', 'update:only-mine'],
}

const OverviewTableStub = {
	name: 'OverviewTable',
	template: '<div class="table-stub" />',
	props: ['processes', 'sortCriteria', 'sortOrder', 'selectedId'],
	emits: ['select', 'update:sort-criteria', 'update:sort-order'],
}

const OverviewPaginationStub = {
	name: 'OverviewPagination',
	template: '<div class="pagination-stub" />',
	props: ['offset', 'limit', 'total'],
	emits: ['prev', 'next'],
}

const ProcessDetailStub = {
	name: 'ProcessDetail',
	template: '<div class="detail-stub" />',
	props: ['process'],
	emits: ['refresh', 'cancelled'],
}

// Stubs for child components
const stubs = {
	NcContent: {
		template: '<div class="nc-content"><slot /></div>',
		props: ['appName'],
	},
	NcAppContent: {
		template: '<div class="nc-app-content"><slot /></div>',
	},
	NcAppSidebar: {
		inheritAttrs: false,
		name: 'NcAppSidebar',
		template: '<div class="nc-app-sidebar"><slot /></div>',
		props: ['name'],
		emits: ['close'],
	},
	NcButton: {
		inheritAttrs: false,
		template: '<button @click="$emit(\'click\')" :disabled="disabled"><slot /></button>',
		props: ['variant', 'ariaLabel', 'disabled'],
	},
	NcNoteCard: {
		template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
		props: ['type'],
	},
	NcLoadingIcon: {
		template: '<div class="nc-loading-icon" />',
		props: ['size'],
	},
	NcIconSvgWrapper: {
		template: '<span class="nc-icon-stub" />',
		props: ['path', 'size'],
	},
	OverviewToolbar: OverviewToolbarStub,
	OverviewTable: OverviewTableStub,
	OverviewPagination: OverviewPaginationStub,
	ProcessDetail: ProcessDetailStub,
}

function createFoundProcess(overrides: Partial<FoundProcess> = {}): FoundProcess {
	return {
		processId: 'proc-1',
		name: 'Test Process',
		status: 'RUNNING',
		created: '2025-01-01T12:00:00Z',
		...overrides,
	}
}

function createListResponse(processes: FoundProcess[] = [], numHits?: number): FoundProcessesResponse {
	return {
		numHits: numHits ?? processes.length,
		processes,
	}
}

function mountOverview(opts: { apiKeySet?: boolean } = {}) {
	mockedLoadState.mockReturnValue(opts.apiKeySet ?? true)

	return mount(OverviewApp, {
		global: { stubs },
	})
}

describe('OverviewApp', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mockedOverviewApi.list.mockResolvedValue(createListResponse())
	})

	// ── API key warning ──

	it('shows warning when no API key is configured', () => {
		const wrapper = mountOverview({ apiKeySet: false })

		const warning = wrapper.find('.nc-note-card[data-type="warning"]')
		expect(warning.exists()).toBe(true)
		expect(warning.text()).toContain('not configured')
	})

	// ── Initial load ──

	it('loads processes on mount', async () => {
		const processes = [createFoundProcess()]
		mockedOverviewApi.list.mockResolvedValue(createListResponse(processes))

		mountOverview()
		await flushPromises()

		expect(mockedOverviewApi.list).toHaveBeenCalledTimes(1)
	})

	// ── Filter resets offset ──

	it('resets offset when status filter changes', async () => {
		mockedOverviewApi.list.mockResolvedValue(createListResponse())

		const wrapper = mountOverview()
		await flushPromises()

		// Simulate some pagination first
		;(wrapper.vm as any).offset = 50

		const toolbar = wrapper.findComponent(OverviewToolbarStub)
		await toolbar.vm.$emit('update:status', 'FINISHED')
		await flushPromises()

		expect((wrapper.vm as any).offset).toBe(0)
		expect((wrapper.vm as any).status).toBe('FINISHED')
	})

	it('resets offset when search query changes', async () => {
		mockedOverviewApi.list.mockResolvedValue(createListResponse())

		const wrapper = mountOverview()
		await flushPromises()

		;(wrapper.vm as any).offset = 50

		const toolbar = wrapper.findComponent(OverviewToolbarStub)
		await toolbar.vm.$emit('update:search-query', 'contract')
		await flushPromises()

		expect((wrapper.vm as any).offset).toBe(0)
		expect((wrapper.vm as any).searchQuery).toBe('contract')
	})

	// ── Pagination ──

	it('navigates to next page', async () => {
		mockedOverviewApi.list.mockResolvedValue(createListResponse([], 100))

		const wrapper = mountOverview()
		await flushPromises()

		const pagination = wrapper.findComponent(OverviewPaginationStub)
		await pagination.vm.$emit('next')
		await flushPromises()

		expect((wrapper.vm as any).offset).toBe(25) // default limit is 25
	})

	it('navigates to previous page without going below zero', async () => {
		mockedOverviewApi.list.mockResolvedValue(createListResponse([], 100))

		const wrapper = mountOverview()
		await flushPromises()

		// Go forward first, then back
		;(wrapper.vm as any).offset = 25
		const pagination = wrapper.findComponent(OverviewPaginationStub)
		await pagination.vm.$emit('prev')
		await flushPromises()

		expect((wrapper.vm as any).offset).toBe(0)
	})

	it('does not go below zero on prev from first page', async () => {
		mockedOverviewApi.list.mockResolvedValue(createListResponse())

		const wrapper = mountOverview()
		await flushPromises()

		const pagination = wrapper.findComponent(OverviewPaginationStub)
		await pagination.vm.$emit('prev')
		await flushPromises()

		expect((wrapper.vm as any).offset).toBe(0)
	})

	// ── Process selection ──

	it('opens detail sidebar when process is selected', async () => {
		const process = createFoundProcess()
		mockedOverviewApi.list.mockResolvedValue(createListResponse([process]))

		const wrapper = mountOverview()
		await flushPromises()

		const table = wrapper.findComponent(OverviewTableStub)
		await table.vm.$emit('select', process)

		expect((wrapper.vm as any).selectedProcess).toEqual(process)
		expect(wrapper.find('.nc-app-sidebar').exists()).toBe(true)
	})

	it('updates selected process after refresh if still in list', async () => {
		const process = createFoundProcess({ processId: 'proc-1', name: 'Original' })
		const updated = createFoundProcess({ processId: 'proc-1', name: 'Updated' })
		mockedOverviewApi.list
			.mockResolvedValueOnce(createListResponse([process]))
			.mockResolvedValueOnce(createListResponse([updated]))

		const wrapper = mountOverview()
		await flushPromises()

		// Select the process
		;(wrapper.vm as any).selectedProcess = process

		// Trigger refresh
		await (wrapper.vm as any).loadProcesses()
		await flushPromises()

		expect((wrapper.vm as any).selectedProcess.name).toBe('Updated')
	})

	it('clears selected process when it disappears from results', async () => {
		const process = createFoundProcess({ processId: 'proc-1' })
		mockedOverviewApi.list
			.mockResolvedValueOnce(createListResponse([process]))
			.mockResolvedValueOnce(createListResponse([])) // process gone

		const wrapper = mountOverview()
		await flushPromises()

		;(wrapper.vm as any).selectedProcess = process

		await (wrapper.vm as any).loadProcesses()
		await flushPromises()

		expect((wrapper.vm as any).selectedProcess).toBeNull()
	})

	it('clears selection and reloads after cancel from detail', async () => {
		const process = createFoundProcess()
		mockedOverviewApi.list.mockResolvedValue(createListResponse([process]))

		const wrapper = mountOverview()
		await flushPromises()

		// Select the process so the sidebar appears
		;(wrapper.vm as any).selectedProcess = process
		await wrapper.vm.$nextTick()

		const detail = wrapper.findComponent(ProcessDetailStub)
		const callCountBefore = mockedOverviewApi.list.mock.calls.length
		await detail.vm.$emit('cancelled')
		await flushPromises()

		expect((wrapper.vm as any).selectedProcess).toBeNull()
		expect(mockedOverviewApi.list.mock.calls.length).toBeGreaterThan(callCountBefore)
	})

	// ── Error handling ──

	it('shows error on API failure', async () => {
		mockedOverviewApi.list.mockRejectedValue(new Error('API down'))

		const wrapper = mountOverview()
		await flushPromises()

		const error = wrapper.find('.nc-note-card[data-type="error"]')
		expect(error.exists()).toBe(true)
		expect(mockedExtractError).toHaveBeenCalled()
	})
})
