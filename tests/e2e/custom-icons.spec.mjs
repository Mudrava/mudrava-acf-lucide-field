import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const AUTH_FILE = path.join(__dirname, '.auth', 'admin.json');
const SUMMARY_FILE = path.join(__dirname, 'test-results', 'custom-icons-summary.md');

import { resetFixtures } from './fixture.mjs';

test.use({ storageState: AUTH_FILE });

const IDS = JSON.parse(fs.readFileSync(path.join(__dirname, '.auth', 'ids.json'), 'utf8'));

const SITE = process.env.WP_SITE_PATH || '/var/folders/8l/qy_hbfsx1lxc2_2_rkn33mzw0000gn/T/opencode/wp-site';
const WP_BIN = process.env.WP_BIN || 'wp';

const editUrl = (id) => `/wp-admin/post.php?post=${id}&action=edit`;
const ICONS_PAGE = '/wp-admin/options-general.php?page=mudrava-lucide-icons';

const GOOD_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><g transform="translate(2 2)"><path d="M4 4h8" fill="#123456"/></g><circle cx="24" cy="24" r="4" fill="#abcdef"/></svg>';
const EVIL_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><script>alert(1)</script><style>circle{fill:red}</style><image href="https://evil.test/x.png"/><circle cx="8" cy="8" r="4" onload="alert(2)"/><path d="M1 1h8" fill="#00ff00"/></svg>';
const NO_VIEWBOX_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect x="2" y="2" width="8" height="8"/></svg>';
const GARBAGE_SVG = 'this is not an svg';

const results = [];
let seq = 0;

const record = (testInfo) => {
	seq += 1;

	results.push(`| ${seq} | ${testInfo.title} | ${testInfo.status === 'passed' ? 'PASS' : 'FAIL'} |\n`);
	fs.mkdirSync(path.dirname(SUMMARY_FILE), { recursive: true });
	fs.writeFileSync(SUMMARY_FILE, `# Custom icons e2e summary\n\n| # | Check | Result |\n|---|---|---|\n${results.join('')}`);
};

const upload = async (page, svg, title) => {
	await page.goto(ICONS_PAGE);

	const nonce = await page.locator('input[name="mudrava_lucide_upload_nonce"]').first().inputValue();

	await page.setInputFiles('input[name="mudrava_icon"]', {
		name: 'icon.svg',
		mimeType: 'image/svg+xml',
		buffer: Buffer.from(svg),
	});
	await page.fill('input[name="icon_title"]', title);

	await Promise.all([
		page.waitForURL(/message=/, { timeout: 20000 }),
		page.click('input[name="mudrava_lucide_upload_submit"]'),
	]);

	return nonce;
};

const deleteIcon = (page, name, nonce) => page.evaluate(async ([iconName, uploadNonceValue]) => {
	const body = new URLSearchParams({
		action: 'mudrava_lucide_delete_icon',
		mudrava_lucide_upload_nonce: uploadNonceValue,
		icon_name: iconName,
	});

	const res = await fetch('/wp-admin/admin-post.php', {
		method: 'POST',
		credentials: 'same-origin',
		redirect: 'follow',
		body,
	});

	return res.url;
}, [name, nonce]);

const restIcons = (page, nonce) => page.evaluate(async (restNonce) => {
	const res = await fetch('/wp-json/mudrava-lucide/v1/icons', {
		credentials: 'same-origin',
		headers: { 'X-WP-Nonce': restNonce },
	});

	return res.json();
}, nonce);

const grabNonce = async (page) => {
	await page.goto(editUrl(IDS.rocket));
	await page.waitForFunction(() => window.mudravaLucideField && window.mudravaLucideField.nonce);

	return page.evaluate(() => window.mudravaLucideField.nonce);
};

test.describe('custom icons', () => {
	test.describe.configure({ mode: 'serial' });

	test.beforeAll(() => {
		execFileSync(WP_BIN, ['eval', 'delete_option("mudrava_lucide_field_custom_icons"); echo "cleared";', '--path=' + SITE], { stdio: 'pipe' });
		fs.rmSync(path.join(SITE, 'wp-content', 'uploads', 'mudrava-lucide-icons'), { recursive: true, force: true });
	});

	test.beforeEach(async () => {
		resetFixtures(IDS);
	});

	test.afterEach(async ({}, testInfo) => {
		record(testInfo);
	});

	test('management page is reachable', async ({ page }) => {
		await page.goto(ICONS_PAGE);

		await expect(page.locator('h1')).toContainText('Custom Icons');

	});

	test('upload stores sanitized markup only', async ({ page }) => {
		const nonce = await grabNonce(page);

		await upload(page, EVIL_SVG, 'evil test icon');

		await expect(page.locator('.notice-success, .updated')).toContainText('was added to the library', { timeout: 20000 });

		const payload = await restIcons(page, nonce);

		expect(payload.custom.icons['evil-test-icon']).toBeTruthy();

		const inner = payload.custom.symbols['evil-test-icon'].inner;

		expect(inner).toContain('fill="#00ff00"');
		expect(inner).toContain('<circle');
		expect(inner).not.toContain('script');
		expect(inner).not.toContain('alert');
		expect(inner).not.toContain('style');
		expect(inner).not.toContain('image');
		expect(inner).not.toContain('evil.test');
		expect(inner).not.toContain('onload');

	});

	test('upload with viewBox keeps geometry and powers picker, save and frontend', async ({ page }) => {
		await upload(page, GOOD_SVG, 'zeta-test');

		await expect(page.locator('.notice-success, .updated')).toContainText('was added to the library', { timeout: 20000 });

		await page.goto(editUrl(IDS.rocket));

		const picker = page.locator('.mudrava-lucide-picker').first();

		await expect(picker).toBeVisible();
		await picker.locator('.mudrava-lucide-selected').click();
		await picker.locator('.mudrava-lucide-search').fill('zeta');

		const tile = picker.locator('.mudrava-lucide-icon[data-icon="custom:zeta-test"]');

		await expect(tile).toHaveCount(1);

		const cls = await tile.locator('svg').getAttribute('class');

		expect(cls).toContain('--custom');

		await expect(tile.locator('svg')).toHaveCSS('stroke', /rgb|none/);
		await expect(page.locator('#mudrava-lucide-sprite-custom')).toHaveCount(1);
		await expect(page.locator('#mudrava-lucide-sprite-custom symbol#custom-zeta-test')).toHaveCount(1);

		await tile.click();
		await expect(picker.locator('.mudrava-lucide-input')).toHaveValue('custom:zeta-test');

		await page.click('#publish');
		await expect(page.locator('.notice-success')).toContainText('Post updated', { timeout: 20000 });

		await page.goto(editUrl(IDS.rocket));
		await expect(page.locator('.mudrava-lucide-picker').first().locator('.mudrava-lucide-input')).toHaveValue('custom:zeta-test');

		await expect(page.locator('#mudrava-lucide-sprite-custom symbol#custom-zeta-test')).toHaveAttribute('viewBox', '0 0 48 48');

		const marker = `zeta-front-${Date.now()}`;
		const editNonce = await page.evaluate(() => (window.mudravaLucideField && window.mudravaLucideField.nonce) || '');
		const slug = await page.evaluate(async ([name, content]) => {
			const res = await fetch('/wp-json/wp/v2/pages', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.mudravaLucideField.nonce,
				},
				body: JSON.stringify({ title: name, status: 'publish', content }),
			});

			const data = await res.json();

			return data.slug || '';
		}, [`zeta-${Date.now()}`, `<p>${marker}[lucide_icon name="custom:zeta-test" title="Zeta"]</p>`]);

		expect(slug).toBeTruthy();

		await page.goto(`/${slug}/`);

		const icon = page.locator(`article:has-text("${marker}") svg, .entry-content svg`).first();

		await expect(icon).toBeVisible();

		const info = await icon.evaluate((svg) => ({
			cls: svg.getAttribute('class') || '',
			vb: svg.getAttribute('viewBox') || '',
			fill: svg.getAttribute('fill'),
			stroke: svg.getAttribute('stroke'),
			innerHTML: svg.innerHTML,
		}));

		expect(info.cls).toContain('mudrava-lucide-icon-svg--custom');
		expect(info.vb).toBe('0 0 48 48');
		expect(info.fill).toBeNull();
		expect(info.stroke).toBeNull();
		expect(info.innerHTML).toContain('<g transform="translate(2 2)">');
		expect(info.innerHTML).toContain('fill="#123456"');
		expect(info.innerHTML).not.toContain('<script');

		await page.evaluate(async ([pageSlug, restNonce]) => {
			const found = await (await fetch(`/wp-json/wp/v2/pages?slug=${pageSlug}`, { credentials: 'same-origin', headers: { 'X-WP-Nonce': restNonce } })).json();

			if (found[0]) {
				await fetch(`/wp-json/wp/v2/pages/${found[0].id}?force=true`, {
					method: 'DELETE',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': restNonce },
				});
			}
		}, [slug, editNonce]);

		await page.goto(ICONS_PAGE);
	});

	test('rejected upload leaves no trace', async ({ page }) => {
		const nonce = await grabNonce(page);

		await upload(page, GARBAGE_SVG, 'garbage icon');

		await expect(page.locator('.notice-error')).toContainText('not a valid SVG document', { timeout: 20000 });

		const payload = await restIcons(page, nonce);

		expect(payload.custom.icons['garbage-icon']).toBeUndefined();
		expect(Object.keys(payload.custom.icons)).not.toContain('garbage-icon');
	});

	test('viewBox defaults from width and height', async ({ page }) => {
		const nonce = await grabNonce(page);

		await upload(page, NO_VIEWBOX_SVG, 'ghost test icon');

		await expect(page.locator('.notice-success, .updated')).toContainText('was added to the library', { timeout: 20000 });

		const payload = await restIcons(page, nonce);

		expect(payload.custom.icons['ghost-test-icon']).toBeTruthy();
		expect(payload.custom.symbols['ghost-test-icon'].viewBox).toBe('0 0 64 64');
		expect(payload.custom.symbols['ghost-test-icon'].inner).toContain('<rect');

	});

	test('delete removes icon from library and payload', async ({ page }) => {
		await page.goto(ICONS_PAGE);

		const adminNonce = await page.locator('input[name="mudrava_lucide_upload_nonce"]').first().inputValue();

		for (const name of ['zeta-test', 'ghost-test-icon', 'evil-test-icon']) {
			await deleteIcon(page, name, adminNonce);
		}

		const restNonce = await grabNonce(page);

		await page.goto(ICONS_PAGE);

		await expect(page.locator('body')).toContainText('No custom icons yet.');

		const payload = await restIcons(page, restNonce);

		expect(Object.keys(payload.custom.icons)).toHaveLength(0);
	});
});
