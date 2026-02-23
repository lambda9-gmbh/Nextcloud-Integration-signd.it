import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/integration_signd')

// ──────────────────────────────────────
// Error handling
// ──────────────────────────────────────

const errorCodeMessages: Record<string, () => string> = {
	SIGND_UNREACHABLE: () => t('integration_signd', 'Cannot reach the signd.it server. Please try again later.'),
	SIGND_UNAUTHORIZED: () => t('integration_signd', 'The signd.it API key is no longer valid. Please contact your administrator.'),
	STORAGE_ERROR: () => t('integration_signd', 'Not enough storage space or insufficient permissions. Please free up space and try again.'),
	FILE_NOT_FOUND: () => t('integration_signd', 'The original file no longer exists in Nextcloud.'),
}

export function extractErrorMessage(error: unknown, fallback: string): string {
	if (axios.isAxiosError(error)) {
		const data = error.response?.data
		if (data?.errorCode && errorCodeMessages[data.errorCode]) {
			return errorCodeMessages[data.errorCode]()
		}
		if (data?.error && typeof data.error === 'string') {
			return data.error
		}
	}
	return fallback
}

// ──────────────────────────────────────
// Settings API
// ──────────────────────────────────────

export interface UserInfo {
    email: string
    clearName?: string
    language?: string
}

export interface ConfigResponse {
    apiKeySet: boolean
    userInfo: UserInfo | null
}

export interface PriceInfo {
    perProcess: number
    perMonthAndUser: number
    includedProcessesPerMonth: number
    sms: number
    qes: number
}

export interface PricesResponse {
    premium: PriceInfo
    enterprise: PriceInfo
}

export interface RegisterData {
    productPlan: string
    organisation: string
    street: string
    houseNumber: string
    zipCode: string
    city: string
    country: string
    clearName: string
    email: string
    password: string
    agbAccepted: boolean
    dsbAccepted: boolean
    vatId?: string
    couponCode?: string
}

export const settingsApi = {
    async getConfig(): Promise<ConfigResponse> {
        const { data } = await axios.get(`${baseUrl}/settings/config`)
        return data
    },

    async saveApiKey(apiKey: string) {
        const { data } = await axios.post(`${baseUrl}/settings/api-key`, { apiKey })
        return data
    },

    async login(email: string, password: string) {
        const { data } = await axios.post(`${baseUrl}/settings/login`, { email, password })
        return data
    },

    async register(registerData: RegisterData) {
        const { data } = await axios.post(`${baseUrl}/settings/register`, registerData)
        return data
    },

    async getPrices(): Promise<PricesResponse> {
        const { data } = await axios.get(`${baseUrl}/settings/prices`)
        return data
    },

    async validate() {
        const { data } = await axios.get(`${baseUrl}/settings/validate`)
        return data
    },

    async deleteApiKey() {
        const { data } = await axios.delete(`${baseUrl}/settings/api-key`)
        return data
    },
}

// ──────────────────────────────────────
// Process API
// ──────────────────────────────────────

export interface SigndProcess {
    id: number
    fileId: number
    processId: string
    userId: string
    targetDir: string | null
    finishedPdfPath: string | null
    finishedPdfFileId?: number
    finishedPdfDeleted?: boolean
    isDraft?: boolean
    meta?: ProcessMeta
}

export interface SignerInfo {
    id: string
    clearName?: string
    email?: string
    mobile?: string
    signed?: string
    rejected?: string
    invited?: string
}

export interface ProcessMeta {
    documentId?: string
    name?: string
    created: string
    filename: string
    signersCompleted: SignerInfo[]
    signersRejected: SignerInfo[]
    signersPending: SignerInfo[]
    cancelled?: string
    interrupted?: string
    lastSignerAction?: string
}

// ──────────────────────────────────────
// Overview API (sign-API /api/list)
// ──────────────────────────────────────

export interface FoundSigner {
    id: string
    clearName?: string
    email?: string
    mobile?: string
    signed?: string
    rejected?: string
    invited?: string
}

export interface FoundProcessMetaData {
    applicationName?: string
    applicationMetaData?: {
        ncFileId?: string
        ncFilePath?: string
        ncFileName?: string
        ncUserId?: string
        ncInstanceId?: string
        _ncFileExists?: boolean
    }
}

export interface FoundProcess {
    processId: string
    name?: string
    filename?: string
    created?: string
    status?: string
    signersCompleted?: FoundSigner[]
    signersRejected?: FoundSigner[]
    signersPending?: FoundSigner[]
    cancelled?: string
    interrupted?: string
    lastSignerAction?: string
    apiClientMetaData?: FoundProcessMetaData
}

export interface FoundProcessesResponse {
    numHits: number
    processes: FoundProcess[]
}

export interface OverviewListParams {
    status?: string
    limit?: number
    offset?: number
    searchQuery?: string
    dateFrom?: string
    dateTo?: string
    sortCriteria?: string
    sortOrder?: string
    onlyMine?: boolean
}

export const overviewApi = {
    async list(params: OverviewListParams = {}): Promise<FoundProcessesResponse> {
        const query: Record<string, string> = {}

        if (params.status && params.status !== 'ALL') query.status = params.status
        if (params.limit) query.limit = String(params.limit)
        if (params.offset) query.offset = String(params.offset)
        if (params.searchQuery) query.searchQuery = params.searchQuery
        if (params.dateFrom) query.dateFrom = params.dateFrom
        if (params.dateTo) query.dateTo = params.dateTo
        if (params.sortCriteria) query.sortCriteria = params.sortCriteria
        if (params.sortOrder) query.sortOrder = params.sortOrder
        if (params.onlyMine) query.onlyMine = '1'

        const queryString = new URLSearchParams(query).toString()
        const url = `${baseUrl}/api/overview/list` + (queryString ? `?${queryString}` : '')
        const { data } = await axios.get(url)
        return data
    },

    async cancel(processId: string, reason = ''): Promise<void> {
        await axios.post(`${baseUrl}/api/overview/${processId}/cancel`, { reason })
    },
}

export const processApi = {
    async getByFileId(fileId: number): Promise<SigndProcess[]> {
        const { data } = await axios.get(`${baseUrl}/api/processes/${fileId}`)
        return data
    },

    async startWizard(fileId: number): Promise<{ wizardUrl: string; processId: string }> {
        const { data } = await axios.post(`${baseUrl}/api/processes/start-wizard`, { fileId })
        return data
    },

    async refresh(processId: string): Promise<SigndProcess> {
        const { data } = await axios.post(`${baseUrl}/api/processes/${processId}/refresh`)
        return data
    },

    async download(processId: string, filename?: string): Promise<{ path: string; name?: string; targetDirMissing?: boolean; fileId?: number; size?: number; mtime?: number; owner?: string }> {
        const params = filename ? `?filename=${encodeURIComponent(filename)}` : ''
        const { data } = await axios.get(`${baseUrl}/api/processes/${processId}/download${params}`)
        return data
    },

    async resumeWizard(processId: string): Promise<{ wizardUrl: string }> {
        const { data } = await axios.post(`${baseUrl}/api/processes/${processId}/resume-wizard`)
        return data
    },

    async cancelWizard(processId: string): Promise<void> {
        await axios.post(`${baseUrl}/api/processes/${processId}/cancel-wizard`)
    },
}
