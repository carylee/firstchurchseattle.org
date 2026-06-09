import { defineConfig, devices } from '@playwright/test';

// End-to-end browser tests run LOCALLY against the running DDEV mirror — they
// are intentionally NOT part of CI (which stands up no WordPress/DB, by design).
// Start DDEV first (`ddev start`), then `npm run e2e`. Override the target with
// PLAYWRIGHT_BASE_URL (e.g. the Tailscale URL) when needed.
const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'https://firstchurchseattle.ddev.site:8843';

export default defineConfig({
	testDir: 'e2e',
	fullyParallel: true,
	reporter: 'list',
	use: {
		baseURL,
		ignoreHTTPSErrors: true, // DDEV uses a local mkcert CA.
		trace: 'on-first-retry',
	},
	projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
