import { describe, it, expect, vi, beforeEach } from 'vitest'

// Mock @nextcloud/files
const mockStat = vi.fn()
vi.mock('@nextcloud/files', () => ({
	davGetClient: vi.fn(() => ({ stat: mockStat })),
	davGetDefaultPropfind: vi.fn(() => '<propfind-xml />'),
	davGetRootPath: vi.fn(() => '/files/admin'),
	davResultToNode: vi.fn((data: unknown) => ({ _nodeFromDav: true, data })),
}))

// Mock @nextcloud/event-bus
vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
}))

import { notifyFileCreated } from '@/services/fileListNotify'
import { davGetClient, davGetRootPath, davResultToNode } from '@nextcloud/files'
import { emit } from '@nextcloud/event-bus'

const mockedEmit = vi.mocked(emit)
const mockedDavGetRootPath = vi.mocked(davGetRootPath)
const mockedDavResultToNode = vi.mocked(davResultToNode)

describe('notifyFileCreated', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mockedDavGetRootPath.mockReturnValue('/files/admin')
		mockStat.mockResolvedValue({ data: { filename: '/files/admin/Documents/contract_signed.pdf' } })
	})

	it('fetches file via WebDAV and emits files:node:created', async () => {
		await notifyFileCreated({
			path: '/admin/files/Documents/contract_signed.pdf',
			name: 'contract_signed.pdf',
			fileId: 123,
			size: 45678,
			mtime: 1700000000,
			owner: 'admin',
		})

		expect(mockStat).toHaveBeenCalledWith(
			'/files/admin/Documents/contract_signed.pdf',
			{ details: true, data: '<propfind-xml />' },
		)
		expect(mockedDavResultToNode).toHaveBeenCalled()
		expect(mockedEmit).toHaveBeenCalledWith('files:node:created', expect.objectContaining({ _nodeFromDav: true }))
	})

	it('does nothing when name is missing', async () => {
		await notifyFileCreated({
			path: '/admin/files/contract_signed.pdf',
			fileId: 123,
			owner: 'admin',
		})

		expect(mockStat).not.toHaveBeenCalled()
		expect(mockedEmit).not.toHaveBeenCalled()
	})

	it('does nothing when owner is missing', async () => {
		await notifyFileCreated({
			path: '/admin/files/contract_signed.pdf',
			name: 'contract_signed.pdf',
			fileId: 123,
		})

		expect(mockStat).not.toHaveBeenCalled()
		expect(mockedEmit).not.toHaveBeenCalled()
	})

	it('strips owner prefix from internal path for DAV stat', async () => {
		await notifyFileCreated({
			path: '/testuser/files/Projects/doc_signed.pdf',
			name: 'doc_signed.pdf',
			fileId: 456,
			owner: 'testuser',
		})

		expect(mockStat).toHaveBeenCalledWith(
			'/files/admin/Projects/doc_signed.pdf',
			expect.any(Object),
		)
	})

	it('uses path as-is when it does not match owner prefix', async () => {
		await notifyFileCreated({
			path: '/unexpected/path/file.pdf',
			name: 'file.pdf',
			fileId: 789,
			owner: 'admin',
		})

		expect(mockStat).toHaveBeenCalledWith(
			'/files/admin/unexpected/path/file.pdf',
			expect.any(Object),
		)
	})

	it('catches errors and logs warning without throwing', async () => {
		mockStat.mockRejectedValue(new Error('WebDAV error'))
		const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

		await notifyFileCreated({
			path: '/admin/files/file.pdf',
			name: 'file.pdf',
			fileId: 100,
			owner: 'admin',
		})

		expect(warnSpy).toHaveBeenCalledWith(
			'[signd] Failed to notify file list about new file',
			expect.any(Error),
		)
		expect(mockedEmit).not.toHaveBeenCalled()
		warnSpy.mockRestore()
	})
})