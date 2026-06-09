import { defineConfig } from 'vitest/config';

// Unit tests for first-party JS. happy-dom gives a fast DOM for the
// island modules that shape the page (e.g. the skip-link wiring) without a
// real browser. Browser-level behavior lives in Playwright specs under e2e/.
export default defineConfig({
	test: {
		environment: 'happy-dom',
		include: ['tests/**/*.test.js'],
	},
});
