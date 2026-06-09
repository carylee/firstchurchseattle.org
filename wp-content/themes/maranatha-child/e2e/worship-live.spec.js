import { expect, test } from '@playwright/test';

// Local-only (DDEV). Unit tests cover the schedule math; this confirms the
// island actually mounts on the real page: the hidden slot gets revealed and
// shows either the live indicator or the next-service line.

test('worship-live island reveals a status line on /worship/live/', async ({ page }) => {
	await page.goto('/worship/live/');

	const slot = page.locator('[data-island="worship-live"]');
	await expect(slot).toBeVisible(); // started hidden; island unhid it
	await expect(slot).toHaveText(/live now|next service/i);
});
