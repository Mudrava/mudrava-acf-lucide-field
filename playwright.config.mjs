import { defineConfig } from '@playwright/test';

const BASE = process.env.WP_BASE_URL || 'http://127.0.0.1:8877';

export default defineConfig({
	testDir: 'tests/e2e',
	timeout: 60000,
	expect: { timeout: 10000 },
	reporter: [['list']],
	fullyParallel: false,
	workers: 1,
	use: {
		baseURL: BASE,
		browserName: 'chromium',
		channel: 'chrome',
		viewport: { width: 1440, height: 900 },
	},
	globalSetup: './tests/e2e/global-setup.mjs',
});
