import { describe, it, expect, vi, beforeEach } from 'vitest'
import axios from '@nextcloud/axios'
import { extractErrorMessage, settingsApi, overviewApi, processApi } from '@/services/api'

// Type the mocked axios
const mockedAxios = vi.mocked(axios)

describe('extractErrorMessage', () => {
	it('returns translated message for known errorCode', () => {
		const error = {
			isAxiosError: true,
			response: {
				data: {
					errorCode: 'SIGND_UNREACHABLE',
					error: 'some raw message',
				},
			},
		}

		const result = extractErrorMessage(error, 'Fallback')
		expect(result).toBe('Cannot reach the signd.it server. Please try again later.')
	})

	it('returns error string from response data when no known errorCode', () => {
		const error = {
			isAxiosError: true,
			response: {
				data: {
					error: 'Custom API error message',
				},
			},
		}

		const result = extractErrorMessage(error, 'Fallback')
		expect(result).toBe('Custom API error message')
	})

	it('returns fallback for non-axios errors', () => {
		const error = new Error('Network failure')
		const result = extractErrorMessage(error, 'Something went wrong')
		expect(result).toBe('Something went wrong')
	})

	it('returns fallback when response has no useful data', () => {
		const error = {
			isAxiosError: true,
			response: {
				data: {},
			},
		}

		const result = extractErrorMessage(error, 'Default error')
		expect(result).toBe('Default error')
	})
})

describe('settingsApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('getConfig calls correct endpoint', async () => {
		const mockData = { apiKeySet: true, userInfo: { email: 'test@example.com' } }
		mockedAxios.get.mockResolvedValueOnce({ data: mockData })

		const result = await settingsApi.getConfig()

		expect(mockedAxios.get).toHaveBeenCalledWith('/apps/integration_signd/settings/config')
		expect(result).toEqual(mockData)
	})

	it('deleteApiKey calls DELETE endpoint', async () => {
		mockedAxios.delete.mockResolvedValueOnce({ data: { apiKeySet: false } })

		const result = await settingsApi.deleteApiKey()

		expect(mockedAxios.delete).toHaveBeenCalledWith('/apps/integration_signd/settings/api-key')
		expect(result).toEqual({ apiKeySet: false })
	})
})

describe('overviewApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('list sends no query params for default call', async () => {
		const mockData = { numHits: 0, processes: [] }
		mockedAxios.get.mockResolvedValueOnce({ data: mockData })

		await overviewApi.list()

		expect(mockedAxios.get).toHaveBeenCalledWith('/apps/integration_signd/api/overview/list')
	})

	it('list builds query string from params', async () => {
		const mockData = { numHits: 5, processes: [] }
		mockedAxios.get.mockResolvedValueOnce({ data: mockData })

		await overviewApi.list({
			status: 'RUNNING',
			limit: 10,
			offset: 20,
			searchQuery: 'contract',
			onlyMine: true,
		})

		const calledUrl = mockedAxios.get.mock.calls[0][0] as string
		expect(calledUrl).toContain('status=RUNNING')
		expect(calledUrl).toContain('limit=10')
		expect(calledUrl).toContain('offset=20')
		expect(calledUrl).toContain('searchQuery=contract')
		expect(calledUrl).toContain('onlyMine=1')
	})

	it('list omits status=ALL from query', async () => {
		const mockData = { numHits: 0, processes: [] }
		mockedAxios.get.mockResolvedValueOnce({ data: mockData })

		await overviewApi.list({ status: 'ALL' })

		const calledUrl = mockedAxios.get.mock.calls[0][0] as string
		expect(calledUrl).not.toContain('status=')
	})

	it('cancel sends processId in URL and reason in body', async () => {
		mockedAxios.post.mockResolvedValueOnce({ data: {} })

		await overviewApi.cancel('proc-42', 'No longer needed')

		expect(mockedAxios.post).toHaveBeenCalledWith(
			'/apps/integration_signd/api/overview/proc-42/cancel',
			{ reason: 'No longer needed' },
		)
	})

	it('list encodes special chars in searchQuery', async () => {
		mockedAxios.get.mockResolvedValueOnce({ data: { numHits: 0, processes: [] } })

		await overviewApi.list({ searchQuery: 'a&b=c' })

		const calledUrl = mockedAxios.get.mock.calls[0][0] as string
		expect(calledUrl).toContain('searchQuery=a%26b%3Dc')
	})

	it('list sends date params', async () => {
		mockedAxios.get.mockResolvedValueOnce({ data: { numHits: 0, processes: [] } })

		await overviewApi.list({ dateFrom: '2025-01-01', dateTo: '2025-12-31' })

		const calledUrl = mockedAxios.get.mock.calls[0][0] as string
		expect(calledUrl).toContain('dateFrom=2025-01-01')
		expect(calledUrl).toContain('dateTo=2025-12-31')
	})

	it('list sends sort params', async () => {
		mockedAxios.get.mockResolvedValueOnce({ data: { numHits: 0, processes: [] } })

		await overviewApi.list({ sortCriteria: 'CREATED', sortOrder: 'ASC' })

		const calledUrl = mockedAxios.get.mock.calls[0][0] as string
		expect(calledUrl).toContain('sortCriteria=CREATED')
		expect(calledUrl).toContain('sortOrder=ASC')
	})
})

// ── extractErrorMessage extensions ──

describe('extractErrorMessage — additional error codes', () => {
	it('returns storage error message for STORAGE_ERROR code', () => {
		const error = {
			isAxiosError: true,
			response: { data: { errorCode: 'STORAGE_ERROR' } },
		}

		const result = extractErrorMessage(error, 'Fallback')
		expect(result).toBe('Not enough storage space or insufficient permissions. Please free up space and try again.')
	})

	it('returns file not found message for FILE_NOT_FOUND code', () => {
		const error = {
			isAxiosError: true,
			response: { data: { errorCode: 'FILE_NOT_FOUND' } },
		}

		const result = extractErrorMessage(error, 'Fallback')
		expect(result).toBe('The original file no longer exists in Nextcloud.')
	})
})

// ── processApi ──

describe('processApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('getByFileId calls correct endpoint', async () => {
		mockedAxios.get.mockResolvedValueOnce({ data: [] })

		await processApi.getByFileId(42)

		expect(mockedAxios.get).toHaveBeenCalledWith('/apps/integration_signd/api/processes/42')
	})

	it('startWizard sends fileId in body', async () => {
		const mockResult = { wizardUrl: 'https://signd.it/wizard/p1', processId: 'p1' }
		mockedAxios.post.mockResolvedValueOnce({ data: mockResult })

		const result = await processApi.startWizard(42)

		expect(mockedAxios.post).toHaveBeenCalledWith(
			'/apps/integration_signd/api/processes/start-wizard',
			{ fileId: 42 },
		)
		expect(result).toEqual(mockResult)
	})

	it('refresh calls correct endpoint', async () => {
		mockedAxios.post.mockResolvedValueOnce({ data: { processId: 'proc-1' } })

		await processApi.refresh('proc-1')

		expect(mockedAxios.post).toHaveBeenCalledWith('/apps/integration_signd/api/processes/proc-1/refresh')
	})

	it('download builds URL with filename param', async () => {
		mockedAxios.get.mockResolvedValueOnce({ data: { path: '/signed.pdf' } })

		await processApi.download('proc-1', 'contract.pdf')

		const calledUrl = mockedAxios.get.mock.calls[0][0] as string
		expect(calledUrl).toContain('/api/processes/proc-1/download')
		expect(calledUrl).toContain('filename=contract.pdf')
	})

	it('download builds URL without filename when undefined', async () => {
		mockedAxios.get.mockResolvedValueOnce({ data: { path: '/signed.pdf' } })

		await processApi.download('proc-1')

		const calledUrl = mockedAxios.get.mock.calls[0][0] as string
		expect(calledUrl).toBe('/apps/integration_signd/api/processes/proc-1/download')
	})

	it('resumeWizard calls correct endpoint', async () => {
		mockedAxios.post.mockResolvedValueOnce({ data: { wizardUrl: 'https://signd.it/wizard/p1' } })

		await processApi.resumeWizard('proc-1')

		expect(mockedAxios.post).toHaveBeenCalledWith('/apps/integration_signd/api/processes/proc-1/resume-wizard')
	})

	it('cancelWizard calls correct endpoint', async () => {
		mockedAxios.post.mockResolvedValueOnce({ data: {} })

		await processApi.cancelWizard('proc-1')

		expect(mockedAxios.post).toHaveBeenCalledWith('/apps/integration_signd/api/processes/proc-1/cancel-wizard')
	})
})

// ── settingsApi extensions ──

describe('settingsApi — additional endpoints', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('saveApiKey sends apiKey in body', async () => {
		mockedAxios.post.mockResolvedValueOnce({ data: { success: true } })

		await settingsApi.saveApiKey('my-key')

		expect(mockedAxios.post).toHaveBeenCalledWith(
			'/apps/integration_signd/settings/api-key',
			{ apiKey: 'my-key' },
		)
	})

	it('login sends email and password', async () => {
		mockedAxios.post.mockResolvedValueOnce({ data: { success: true } })

		await settingsApi.login('test@example.com', 'secret')

		expect(mockedAxios.post).toHaveBeenCalledWith(
			'/apps/integration_signd/settings/login',
			{ email: 'test@example.com', password: 'secret' },
		)
	})

	it('register sends full payload', async () => {
		mockedAxios.post.mockResolvedValueOnce({ data: { success: true } })

		const registerData = {
			productPlan: 'premium',
			organisation: 'TestOrg',
			street: 'Main St',
			houseNumber: '1',
			zipCode: '12345',
			city: 'Berlin',
			country: 'DE',
			clearName: 'Test User',
			email: 'test@example.com',
			password: 'pass123',
			agbAccepted: true,
			dsbAccepted: true,
		}

		await settingsApi.register(registerData)

		expect(mockedAxios.post).toHaveBeenCalledWith(
			'/apps/integration_signd/settings/register',
			registerData,
		)
	})

	it('getPrices calls correct endpoint', async () => {
		mockedAxios.get.mockResolvedValueOnce({ data: { premium: {}, enterprise: {} } })

		await settingsApi.getPrices()

		expect(mockedAxios.get).toHaveBeenCalledWith('/apps/integration_signd/settings/prices')
	})

	it('validate calls correct endpoint', async () => {
		mockedAxios.get.mockResolvedValueOnce({ data: { valid: true } })

		await settingsApi.validate()

		expect(mockedAxios.get).toHaveBeenCalledWith('/apps/integration_signd/settings/validate')
	})
})
