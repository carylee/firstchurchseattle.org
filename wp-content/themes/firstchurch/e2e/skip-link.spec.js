import { expect, test } from '@playwright/test';

// Local-only: runs against the DDEV mirror (see playwright.config.js). Verifies
// the skip-link island actually wires the page in a real browser — the unit test
// covers the DOM-shaping logic; this covers the module loading + keyboard flow.

test('skip link is the first tab stop and targets the injected main anchor', async ({ page }) => {
	await page.goto('/');

	// The module injects a stable focus target regardless of the template's main id.
	await expect(page.locator('#main-content')).toHaveCount(1);

	// First Tab from the top of the page lands on the skip link.
	await page.keyboard.press('Tab');
	const skip = page.locator('a.fcs-skip-link');
	await expect(skip).toBeFocused();
	await expect(skip).toHaveAttribute('href', '#main-content');

	// Activating it jumps to the injected target.
	await page.keyboard.press('Enter');
	await expect(page).toHaveURL(/#main-content$/);
});
