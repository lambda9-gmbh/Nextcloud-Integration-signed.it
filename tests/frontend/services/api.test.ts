import { describe, it, expect, vi, beforeEach } from 'vitest'
import axios from '@nextcloud/axios'
import { extractErrorMessage, settingsApi, overviewApi } from '@/services/api'

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
})
