import { vi } from 'vitest'

// Mock @nextcloud/axios — returns a minimal axios-like object
vi.mock('@nextcloud/axios', () => {
	const mockAxios = {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
		isAxiosError: (error: unknown): boolean => {
			return typeof error === 'object' && error !== null && 'isAxiosError' in error
		},
	}
	return { default: mockAxios }
})

// Mock @nextcloud/router
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path: string) => path,
}))

// Mock @nextcloud/l10n — partial mock preserving original exports used by @nextcloud/vue
vi.mock('@nextcloud/l10n', async (importOriginal) => {
	const actual = await importOriginal<typeof import('@nextcloud/l10n')>()
	return {
		...actual,
		translate: (_app: string, text: string, vars?: Record<string, string>) => {
			if (!vars) return text
			return Object.entries(vars).reduce(
				(result, [key, value]) => result.replace(`{${key}}`, value),
				text,
			)
		},
	}
})

// Mock @nextcloud/initial-state
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(() => null),
}))

// Mock @nextcloud/files — avoid loading real module which depends on cancelable-promise
vi.mock('@nextcloud/files', () => ({
	davGetClient: vi.fn(() => ({ stat: vi.fn().mockResolvedValue({ data: {} }) })),
	davGetDefaultPropfind: vi.fn(() => '<propfind-xml />'),
	davGetRootPath: vi.fn(() => '/files/admin'),
	davResultToNode: vi.fn((data: unknown) => ({ _nodeFromDav: true, data })),
}))

// Mock @nextcloud/event-bus
vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
	unsubscribe: vi.fn(),
}))
