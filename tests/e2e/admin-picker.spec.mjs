import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test, expect } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const AUTH_FILE = path.join(__dirname, '.auth', 'admin.json');

import { resetFixtures } from './fixture.mjs';

test.use({ storageState: AUTH_FILE });

const IDS = JSON.parse(fs.readFileSync(path.join(__dirname, '.auth', 'ids.json'), 'utf8'));

const editUrl = (id) => `/wp-admin/post.php?post=${id}&action=edit`;

test.describe('admin picker', () => {
	test.describe.configure({ mode: 'serial' });

	test.beforeEach(async () => {
		resetFixtures(IDS);
	});

	test('lazy catalog, search, click select, clear, save persists', async ({ page }) => {
		const catalogRequests = [];

		page.on('request', (req) => {
			if (req.url().includes('mudrava-lucide/v1/icons')) {
				catalogRequests.push(req.url());
			}
		});

		await page.goto(editUrl(IDS.rocket));

		const picker = page.locator('.mudrava-lucide-picker').first();

		await expect(picker).toBeVisible();
		await expect(picker.locator('.mudrava-lucide-preview-name')).toHaveText('Rocket');

		await picker.locator('.mudrava-lucide-selected').click();
		await expect(picker.locator('.mudrava-lucide-selected')).toHaveAttribute('aria-expanded', 'true');
		await expect(picker.locator('.mudrava-lucide-icon')).toHaveCount(100);
		await expect.poll(() => catalogRequests.length).toBe(1);

		const paint = await picker.evaluate((root) => {
			const tiles = Array.from(root.querySelectorAll('.mudrava-lucide-icon'));
			let brand = 0;
			let lucide = 0;
			let plain = 0;
			let brandStroke = null;
			let brandFill = null;
			let lucideFill = null;
			let lucideStroke = null;

			tiles.forEach((tile) => {
				const svg = tile.querySelector('svg');
				const cls = svg ? svg.getAttribute('class') || '' : '';

				if (cls.indexOf('--brand') !== -1) {
					brand += 1;

					if (brandStroke === null) {
						const cs = getComputedStyle(svg);

						brandStroke = cs.stroke;
						brandFill = cs.fill;
					}
				} else if (cls.indexOf('--lucide') !== -1) {
					lucide += 1;

					if (lucideFill === null) {
						const cs = getComputedStyle(svg);

						lucideFill = cs.fill;
						lucideStroke = cs.stroke;
					}
				} else {
					plain += 1;
				}
			});

			return { tiles: tiles.length, brand, lucide, plain, brandStroke, brandFill, lucideFill, lucideStroke };
		});

		expect(paint.tiles).toBe(100);
		expect(paint.plain).toBe(0);
		expect(paint.brand).toBeGreaterThan(0);
		expect(paint.lucide).toBeGreaterThan(0);
		expect(paint.brandStroke).toBe('none');
		expect(paint.brandFill).not.toBe('none');
		expect(paint.lucideFill).toBe('none');
		expect(paint.lucideStroke).not.toBe('none');

		await picker.locator('.mudrava-lucide-search').fill('arrow-left');

		const first = picker.locator('.mudrava-lucide-icon').first();

		await expect(first).toHaveAttribute('data-icon', 'arrow-left');
		await first.click();

		await expect(picker.locator('.mudrava-lucide-input')).toHaveValue('arrow-left');
		await expect(picker.locator('.mudrava-lucide-selected')).toHaveAttribute('aria-expanded', 'false');
		await expect(picker.locator('.mudrava-lucide-live')).toContainText('selected');

		await picker.locator('.mudrava-lucide-selected').click();
		await expect(picker.locator('.mudrava-lucide-search')).toBeFocused();
		await expect(picker.locator('.mudrava-lucide-icon')).not.toHaveCount(0);

		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('ArrowDown');
		await expect(picker.locator('.mudrava-lucide-search')).toHaveAttribute('aria-activedescendant', /.+/);
		await expect(picker.locator('.mudrava-lucide-icon.is-active')).toHaveCount(1);
		await page.keyboard.press('Enter');
		const selected = await picker.locator('.mudrava-lucide-input').inputValue();

		expect(selected).toBeTruthy();

		await picker.locator('.mudrava-lucide-selected').click();
		await expect(picker.locator('.mudrava-lucide-search')).toBeFocused();

		await expect(picker.locator('.mudrava-lucide-live')).toContainText('icons available');

		await page.keyboard.press('End');

		await expect(picker.locator('.mudrava-lucide-icon')).not.toHaveCount(100);
		await expect(picker.locator('.mudrava-lucide-icon.is-active')).toHaveCount(1);

		const activeId = await picker.locator('.mudrava-lucide-icon.is-active').getAttribute('id');
		const activedescendant = await picker.locator('.mudrava-lucide-search').getAttribute('aria-activedescendant');

		expect(activedescendant).toBe(activeId);

		await page.keyboard.press('ArrowDown');
		await expect(picker.locator('.mudrava-lucide-icon.is-active')).toHaveCount(1);

		await page.keyboard.press('Escape');
		await expect(picker.locator('.mudrava-lucide-selected')).toHaveAttribute('aria-expanded', 'false');
		await expect(picker.locator('.mudrava-lucide-selected')).toHaveAttribute('aria-expanded', 'false');

		await picker.locator('.mudrava-lucide-selected').click();
		await picker.locator('.mudrava-lucide-search').fill('arrow-left');
		await expect(picker.locator('.mudrava-lucide-icon').first()).toHaveAttribute('data-icon', 'arrow-left');
		await picker.locator('.mudrava-lucide-icon').first().click();
		await expect(picker.locator('.mudrava-lucide-input')).toHaveValue('arrow-left');

		await page.click('#publish');
		await expect(page.locator('.notice-success')).toContainText('Post updated', { timeout: 20000 });

		await page.goto(editUrl(IDS.rocket));
		await expect(page.locator('.mudrava-lucide-picker').first().locator('.mudrava-lucide-input')).toHaveValue('arrow-left');

		const picker2 = page.locator('.mudrava-lucide-picker').first();

		await expect(picker2.locator('.mudrava-lucide-clear')).toHaveCount(1);
		await picker2.locator('.mudrava-lucide-clear').click();
		await expect(picker2.locator('.mudrava-lucide-input')).toHaveValue('');
		await expect(picker2.locator('.mudrava-lucide-preview-empty')).toBeVisible();

		await picker2.locator('.mudrava-lucide-selected').click();
		await picker2.locator('.mudrava-lucide-search').fill('rocket');
		await expect(picker2.locator('.mudrava-lucide-icon').first()).toHaveAttribute('data-icon', 'simple:rocket');
		await picker2.locator('.mudrava-lucide-icon').first().click();
		await expect(picker2.locator('.mudrava-lucide-input')).toHaveValue('simple:rocket');
		await page.click('#publish');
		await expect(page.locator('.notice-success')).toContainText('Post updated', { timeout: 20000 });
	});

	test('unknown values: picker marks unknown, save warns without blocking', async ({ page }) => {
		await page.goto(editUrl(IDS.unknown));

		const picker = page.locator('.mudrava-lucide-picker').first();

		await expect(picker.locator('.mudrava-lucide-selected.is-unknown')).toBeVisible();

		await page.click('#publish');

		await expect(page.locator('.notice-warning').first()).toContainText('totally-removed-icon', { timeout: 20000 });
	});

	test('strict mode blocks save for unknown value', async ({ page }) => {
		await page.goto(editUrl(IDS.strict));

		await page.click('#publish');

		await expect(page.getByText('Please select a valid icon.')).toBeVisible();
		await expect(page.locator('.acf-field-strict')).toHaveClass(/acf-error/);

		await expect(page.locator('.mudrava-lucide-picker').nth(2).locator('.mudrava-lucide-selected')).toHaveClass(/is-unknown/);
	});

	test('required mode saves known value', async ({ page }) => {
		await page.goto(editUrl(IDS.rocket));

		await page.click('#publish');
		await expect(page.locator('.notice-success')).toContainText('Post updated', { timeout: 20000 });
	});

	test('grid tiles are CSS-sized squares and reflow on narrow viewports', async ({ page }) => {
		await page.goto(editUrl(IDS.rocket));

		const picker = page.locator('.mudrava-lucide-picker').first();

		await expect(picker).toBeVisible();
		await picker.locator('.mudrava-lucide-selected').click();

		const tile = picker.locator('.mudrava-lucide-icon').first();

		await expect(tile).toBeVisible();

		const wide = await tile.evaluate((el) => {
			const rect = el.getBoundingClientRect();

			return { w: rect.width, h: rect.height };
		});

		expect(wide.w).toBeGreaterThan(0);
		expect(Math.abs(wide.w - wide.h)).toBeLessThan(2);

		const wideCount = await picker.locator('.mudrava-lucide-icon').count();

		await page.setViewportSize({ width: 380, height: 800 });
		await page.waitForTimeout(300);

		const narrow = await tile.evaluate((el) => {
			const rect = el.getBoundingClientRect();

			return { w: rect.width, h: rect.height };
		});

		expect(Math.abs(narrow.w - narrow.h)).toBeLessThan(2);
		expect(narrow.w).toBeLessThan(480);

		await page.setViewportSize({ width: 1440, height: 900 });
		await page.waitForTimeout(300);

		expect(await picker.locator('.mudrava-lucide-icon').count()).toBe(wideCount);
	});
});
