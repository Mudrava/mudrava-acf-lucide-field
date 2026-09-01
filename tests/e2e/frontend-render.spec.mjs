import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const AUTH_FILE = path.join(__dirname, '.auth', 'admin.json');
const IDS = JSON.parse(fs.readFileSync(path.join(__dirname, '.auth', 'ids.json'), 'utf8'));

test.describe('frontend rendering', () => {
	test('shortcode page renders inline svgs, aliases, brand fills and sprite sheet', async ({ page }) => {
		const external = [];

		page.on('request', (req) => {
			const url = req.url();

			if (url.startsWith('http') && !url.startsWith(process.env.WP_BASE_URL || 'http://127.0.0.1:8877')) {
				external.push(url);
			}
		});

		await page.goto('/e2e-shortcodes/');

		const svgs = page.locator('article svg, .entry-content svg, svg.mudrava-lucide-icon-svg, svg[viewBox="0 0 24 24"]');

		expect(await svgs.count()).toBeGreaterThanOrEqual(4);

		const html = await page.content();

		expect(html).toContain('mudrava-lucide-title-');
		expect(html).toContain('fill="#24292f"');
		expect(html).toContain('<use href="#rocket">');
		expect(html).toContain('<symbol id="rocket"');
		expect(external).toHaveLength(0);
	});

	test('legacy alias renders on single post', async ({ page }) => {
		await page.goto('/e2e-legacy-smile/');

		const html = await page.content();

		expect(html).toContain('<circle cx="12" cy="12" r="10"');
	});

	test('REST catalog requires auth', async ({ request }) => {
		const anon = await request.get('/wp-json/mudrava-lucide/v1/icons');

		expect(anon.status()).toBe(401);
	});
});

test.describe('admin catalog', () => {
	test.use({ storageState: AUTH_FILE });

	test('REST catalog returns payload with aliases and vocab', async ({ page, request }) => {
		await page.goto(`/wp-admin/post.php?post=${IDS.rocket}&action=edit`);
		await expect(page.locator('.mudrava-lucide-picker').first()).toBeVisible();

		const base = page.url().split('/wp-admin/')[0];
		const nonce = await page.evaluate(() => (window.wpApiSettings ? window.wpApiSettings.nonce : ''));

		expect(nonce).toBeTruthy();

		const resp = await request.get(`${base}/wp-json/mudrava-lucide/v1/icons`, {
			headers: { 'X-WP-Nonce': nonce },
		});

		expect(resp.status()).toBe(200);

		const body = await resp.json();

		expect(body.compatAliases.smile).toBeTruthy();
		expect(body.lucide.spriteUrl).toBeTruthy();
		expect(body.allowedElements.length).toBeGreaterThan(0);
	});

	test('sprite assets serve with correct headers', async ({ page }) => {
		const response = await page.request.get('/wp-content/plugins/mudrava-acf-lucide-field/assets/sprite.svg');

		expect(response.status()).toBe(200);
		expect(await response.text()).toMatch(/^<svg/);
	});
});
