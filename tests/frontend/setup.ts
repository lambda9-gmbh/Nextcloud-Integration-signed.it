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

// Mock @nextcloud/l10n
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app: string, text: string, vars?: Record<string, string>) => {
		if (!vars) return text
		return Object.entries(vars).reduce(
			(result, [key, value]) => result.replace(`{${key}}`, value),
			text,
		)
	},
}))

// Mock @nextcloud/initial-state
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(() => null),
}))
