import { test, expect } from './fixtures/auth'

test.describe('Admin Settings', () => {
	test('signd.it settings section is visible', async ({ adminPage }) => {
		await adminPage.goto('/settings/admin/integration_signd')

		// The admin settings page should contain the signd.it settings section
		const heading = adminPage.locator('text=signd.it')
		await expect(heading.first()).toBeVisible({ timeout: 10_000 })
	})
})
