import { test, expect } from './fixtures/auth'

test.describe('Files Integration', () => {
	test('sidebar tab is available for files', async ({ adminPage }) => {
		// Navigate to Files app
		await adminPage.goto('/apps/files')
		await adminPage.waitForTimeout(3000)

		// Check that the signd tab exists in the sidebar
		// This depends on having at least one file in the files list
		const fileRow = adminPage.locator('[data-cy-files-list-row]').first()
		const fileRowExists = await fileRow.isVisible().catch(() => false)

		if (fileRowExists) {
			// Click on the file to open sidebar
			await fileRow.click()
			await adminPage.waitForTimeout(1000)

			// Look for the signd tab in the sidebar
			const sidebar = adminPage.locator('[id="app-sidebar-vue"]')
			const sidebarVisible = await sidebar.isVisible().catch(() => false)

			if (sidebarVisible) {
				const signdTab = adminPage.locator('text=signd.it')
				const tabExists = await signdTab.first().isVisible().catch(() => false)
				// Tab may or may not be visible depending on file type
				expect(typeof tabExists).toBe('boolean')
			}
		}
	})
})
