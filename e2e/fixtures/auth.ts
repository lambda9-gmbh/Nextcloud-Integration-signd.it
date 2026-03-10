import { test as base, type Page } from '@playwright/test'

/**
 * Fixture that provides a page already logged in as admin.
 */
export const test = base.extend<{ adminPage: Page }>({
	adminPage: async ({ page }, use) => {
		await page.goto('/login')
		await page.getByLabel('Account name or email').fill('admin')
		await page.locator('#password').fill('admin')
		await page.getByRole('button', { name: 'Log in', exact: true }).click()

		// Wait for dashboard or files to load
		await page.waitForURL(/\/(apps|index)/, { timeout: 15_000 })

		await use(page)
	},
})

export { expect } from '@playwright/test'
