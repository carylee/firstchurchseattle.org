import { expect, test } from '@playwright/test';

test('desktop dropdown opens on hover', async ({ page }) => {
	await page.setViewportSize({ width: 1440, height: 900 });
	await page.goto('/about/');
	const parent = page.locator('.fcs-nav__list > .menu-item-has-children').first();
	await parent.locator('> a').hover();
	await expect(parent.locator('.sub-menu')).toBeVisible();
});

test('mobile menu opens, locks scroll, and closes', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/');
	const toggle = page.locator('.fcs-nav-toggle');
	await toggle.click();
	await expect(page.locator('#fcs-mobile')).toBeVisible();
	await expect(page.locator('body')).toHaveClass(/fcs-nav-open/);
	await page.keyboard.press('Escape');
	await expect(page.locator('#fcs-mobile')).toBeHidden();
});

test('header search reveals a working search form', async ({ page }) => {
	await page.goto('/');
	await page.locator('.fcs-search-toggle').click();
	const input = page.locator('#fcs-search-input');
	await expect(input).toBeFocused();
	await input.fill('worship');
	await input.press('Enter');
	await expect(page).toHaveURL(/[?&]s=worship/);
});
