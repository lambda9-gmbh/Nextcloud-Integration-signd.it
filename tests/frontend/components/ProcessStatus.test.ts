import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ProcessStatus from '@/components/ProcessStatus.vue'
import type { SigndProcess } from '@/services/api'

// Stub child components to isolate ProcessStatus
const stubs = {
	NcButton: {
		inheritAttrs: false,
		template: '<button @click="$emit(\'click\')"><slot /></button>',
		props: ['variant'],
	},
	NcNoteCard: {
		template: '<div class="nc-note-card" :data-type="type"><slot /></div>',
		props: ['type'],
	},
	SignerList: {
		template: '<div class="signer-list-stub" />',
		props: ['label', 'signers', 'variant'],
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

describe('ProcessStatus', () => {
	// ── derivedStatus computed ──

	it('shows DRAFT status for draft processes', () => {
		const wrapper = mount(ProcessStatus, {
			props: { process: createProcess({ isDraft: true }) },
			global: { stubs },
		})

		expect(wrapper.find('.signd-badge').text()).toBe('Draft')
		expect(wrapper.find('.signd-badge').classes()).toContain('signd-badge--info')
	})

	it('shows FINISHED status when no pending signers', () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: 'contract.pdf',
						signersCompleted: [{ id: 's1', clearName: 'Alice' }],
						signersRejected: [],
						signersPending: [],
					},
				}),
			},
			global: { stubs },
		})

		expect(wrapper.find('.signd-badge').text()).toBe('Completed')
		expect(wrapper.find('.signd-badge').classes()).toContain('signd-badge--success')
	})

	it('shows CANCELLED status when cancelled timestamp present', () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: 'contract.pdf',
						signersCompleted: [],
						signersRejected: [],
						signersPending: [{ id: 's1' }],
						cancelled: '2025-01-02T12:00:00Z',
					},
				}),
			},
			global: { stubs },
		})

		expect(wrapper.find('.signd-badge').text()).toBe('Cancelled')
		expect(wrapper.find('.signd-badge').classes()).toContain('signd-badge--error')
	})

	it('shows PENDING status when signers are still pending', () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: 'contract.pdf',
						signersCompleted: [],
						signersRejected: [],
						signersPending: [{ id: 's1', clearName: 'Bob' }],
					},
				}),
			},
			global: { stubs },
		})

		expect(wrapper.find('.signd-badge').text()).toBe('Pending')
		expect(wrapper.find('.signd-badge').classes()).toContain('signd-badge--pending')
	})

	it('shows UNKNOWN status when no meta and not draft', () => {
		const wrapper = mount(ProcessStatus, {
			props: { process: createProcess() },
			global: { stubs },
		})

		expect(wrapper.find('.signd-badge').text()).toBe('Unknown')
		expect(wrapper.find('.signd-badge').classes()).toContain('signd-badge--unknown')
	})

	// ── Download button ──

	it('shows download button for FINISHED process without downloaded PDF', async () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: 'contract.pdf',
						signersCompleted: [{ id: 's1' }],
						signersRejected: [],
						signersPending: [],
					},
				}),
			},
			global: { stubs },
		})

		const downloadBtn = wrapper.findAll('button').find(btn => btn.text().includes('Download signed PDF'))
		expect(downloadBtn).toBeTruthy()

		await downloadBtn!.trigger('click')
		expect(wrapper.emitted('download')).toHaveLength(1)
	})

	it('shows "Open signed PDF" link when finishedPdfFileId is present', () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					finishedPdfPath: '/Documents/contract_signed.pdf',
					finishedPdfFileId: 99,
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: 'contract.pdf',
						signersCompleted: [{ id: 's1' }],
						signersRejected: [],
						signersPending: [],
					},
				}),
			},
			global: { stubs },
		})

		const link = wrapper.find('.signd-pdf-link')
		expect(link.exists()).toBe(true)
		expect(link.text()).toBe('Open signed PDF')
		expect(link.attributes('href')).toContain('fileid=99')

		const downloadBtn = wrapper.findAll('button').find(btn => btn.text().includes('Download'))
		expect(downloadBtn).toBeUndefined()
	})

	it('shows warning NcNoteCard and download button when finishedPdfDeleted is true', () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					finishedPdfPath: null,
					finishedPdfDeleted: true,
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: 'contract.pdf',
						signersCompleted: [{ id: 's1' }],
						signersRejected: [],
						signersPending: [],
					},
				}),
			},
			global: { stubs },
		})

		const warning = wrapper.find('.nc-note-card[data-type="warning"]')
		expect(warning.exists()).toBe(true)
		expect(warning.text()).toContain('Previously saved PDF was deleted.')

		// Download button should be visible since finishedPdfPath is null
		const downloadBtn = wrapper.findAll('button').find(btn => btn.text().includes('Download signed PDF'))
		expect(downloadBtn).toBeTruthy()
	})

	// ── Process name fallback ──

	it('falls back to filename when no name in meta', () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: 'vertrag.pdf',
						signersCompleted: [],
						signersRejected: [],
						signersPending: [{ id: 's1' }],
					},
				}),
			},
			global: { stubs },
		})

		expect(wrapper.find('.signd-process-name').text()).toBe('vertrag.pdf')
	})

	it('falls back to default text when no name and no filename', () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: '',
						signersCompleted: [],
						signersRejected: [],
						signersPending: [{ id: 's1' }],
					},
				}),
			},
			global: { stubs },
		})

		expect(wrapper.find('.signd-process-name').text()).toBe('Signing process')
	})

	// ── Status priority ──

	it('cancelled takes priority over pending signers', () => {
		const wrapper = mount(ProcessStatus, {
			props: {
				process: createProcess({
					meta: {
						created: '2025-01-01T12:00:00Z',
						filename: 'contract.pdf',
						signersCompleted: [{ id: 's1' }],
						signersRejected: [],
						signersPending: [{ id: 's2' }],
						cancelled: '2025-01-02T12:00:00Z',
					},
				}),
			},
			global: { stubs },
		})

		expect(wrapper.find('.signd-badge').text()).toBe('Cancelled')
		expect(wrapper.find('.signd-badge').classes()).toContain('signd-badge--error')
	})

	// ── Draft actions ──

	it('emits resume-wizard and cancel-wizard for draft processes', async () => {
		const wrapper = mount(ProcessStatus, {
			props: { process: createProcess({ isDraft: true }) },
			global: { stubs },
		})

		const buttons = wrapper.findAll('button')
		const resumeBtn = buttons.find(b => b.text().includes('Resume wizard'))
		const cancelBtn = buttons.find(b => b.text().includes('Cancel draft'))

		expect(resumeBtn).toBeTruthy()
		expect(cancelBtn).toBeTruthy()

		await resumeBtn!.trigger('click')
		await cancelBtn!.trigger('click')

		expect(wrapper.emitted('resume-wizard')).toHaveLength(1)
		expect(wrapper.emitted('cancel-wizard')).toHaveLength(1)
	})
})
