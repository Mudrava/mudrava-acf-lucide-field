import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { resetFixtures } from './fixture.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.WP_BASE_URL || 'http://127.0.0.1:8877';
const AUTH_DIR = path.join(__dirname, '.auth');
const AUTH_FILE = path.join(AUTH_DIR, 'admin.json');
const IDS_FILE = path.join(AUTH_DIR, 'ids.json');

async function resolveIds(page) {
	const slugs = ['e2e-rocket', 'e2e-unknown', 'e2e-strict-unknown'];
	const ids = {};

	for (const slug of slugs) {
		const rows = await page.evaluate(async (s) => {
			const res = await fetch(`/wp-json/wp/v2/posts?slug=${s}&_fields=id`, {
				headers: { 'X-WP-Nonce': window.wpApiSettings.nonce },
				credentials: 'same-origin',
			});

			return res.json();
		}, slug);

		if (!rows.length) {
			throw new Error(`Fixture post "${slug}" not found; run the site seeding.`);
		}

		ids[slug] = rows[0].id;
	}

	return {
		rocket: ids['e2e-rocket'],
		unknown: ids['e2e-unknown'],
		strict: ids['e2e-strict-unknown'],
	};
}

export default async () => {
	const browser = await chromium.launch({ channel: 'chrome' });
	const context = await browser.newContext({ baseURL: BASE });
	const page = await context.newPage();

	await page.goto('/wp-login.php');
	await page.fill('#user_login', process.env.WP_ADMIN_USER || 'admin');
	await page.fill('#user_pass', process.env.WP_ADMIN_PASS || 'adminpass123');
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**');

	await page.goto('/wp-admin/post.php?post=1&action=edit');
	await page.waitForFunction(() => Boolean(window.wpApiSettings));

	const ids = await resolveIds(page);

	fs.mkdirSync(AUTH_DIR, { recursive: true });
	fs.writeFileSync(IDS_FILE, JSON.stringify(ids));
	await context.storageState({ path: AUTH_FILE });

	resetFixtures(ids);

	await browser.close();
};
