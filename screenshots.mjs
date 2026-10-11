// End-to-end walkthrough of the local UniHive demo (npm start must be running).
// Checks each role's view and saves screenshots to docs/img/ for the proposal PDF.
// Usage: npm run screenshots   (uses the installed Microsoft Edge or Chrome)

import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const BASE = process.env.UNIHIVE_URL || 'http://127.0.0.1:9400';
const OUT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'docs', 'img');
mkdirSync(OUT, { recursive: true });

const results = [];
const check = (name, ok, detail = '') => {
	results.push({ name, ok });
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  (' + detail + ')' : ''}`);
};

async function launch() {
	for (const channel of ['msedge', 'chrome']) {
		try {
			return await chromium.launch({ channel, headless: true });
		} catch {}
	}
	throw new Error('Install Microsoft Edge or Google Chrome to run this script.');
}

const browser = await launch();
const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1.5 });
const page = await context.newPage();
const shot = (name, opts = {}) => page.screenshot({ path: path.join(OUT, name + '.png'), ...opts });

// Switch persona via the demo bar's link (followed directly, so it works while the bar is hidden).
async function viewAs(label) {
	const href = await page.locator('.uh-demo-bar a', { hasText: new RegExp('^' + label + '$') }).first().getAttribute('href');
	await page.goto(new URL(href, page.url()).href, { waitUntil: 'networkidle' });
}

// Tidy screenshots: hide the demo bar and the admin bar.
async function hideChrome() {
	await page.addStyleTag({ content: '.uh-demo-bar{display:none!important} #wpadminbar{display:none!important} html{margin-top:0!important}' });
}

// Crop to the page title + UniHive block so the figures stay legible in the A4 PDF.
async function shotColumn(name, maxHeight = 1000, to = '.unihive') {
	await page.evaluate(() => { document.activeElement?.blur(); window.scrollTo(0, 0); });
	const top = await page.locator('h1').first().boundingBox();
	const body = await page.locator(to).last().boundingBox();
	const x = Math.max(0, Math.min(top.x, body.x) - 28);
	const right = Math.min(1280, Math.max(top.x + top.width, body.x + body.width) + 28);
	const y = Math.max(0, top.y - 20);
	const height = Math.min(maxHeight, body.y + body.height + 24 - y);
	await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: true, clip: { x, y, width: right - x, height } });
}

await page.goto(BASE + '/unihive/', { waitUntil: 'networkidle' });

// 1. Visitor
await viewAs('Visitor');
await page.goto(BASE + '/unihive/', { waitUntil: 'networkidle' });
check('Visitor sees teaser, not posts', (await page.locator('.uh-cat').count()) === 4 && (await page.locator('.uh-card').count()) === 0);
check('Visitor sees no contact details', !(await page.content()).includes('+447700900123'));
await hideChrome();
await shotColumn('01-visitor-teaser');

const firstPost = BASE + '/hive/room-in-3-bed-flat-near-university-of-manchester-january-move-in/';
await page.goto(firstPost, { waitUntil: 'networkidle' });
check('Visitor opening a post URL is redirected to the board', page.url().includes('hive_gate=1'), page.url());

// 2. Free member
await page.goto(BASE + '/unihive/', { waitUntil: 'networkidle' });
await viewAs('Free member');
check('Free member sees upgrade prompt', (await page.locator('.uh-plan').innerText()).includes('Free'));
check('Free member sees no posts', (await page.locator('.uh-card').count()) === 0);

// 3. Pro member board
await viewAs('Pro member');
check('Pro member sees the board', (await page.locator('.uh-card').count()) > 0 && (await page.locator('.uh-tabs').count()) === 1);
await hideChrome();
await shotColumn('02-pro-board', 760);

// Category filter
await page.locator('.uh-tab', { hasText: 'Accommodation' }).click();
await page.waitForLoadState('networkidle');
const chips = await page.locator('.uh-card .uh-chip').allInnerTexts();
check('Accommodation filter shows only Accommodation posts', chips.length > 0 && chips.every((c) => c.trim() === 'Accommodation'), chips.join(', '));
await hideChrome();
await shot('03-filter-accommodation', { clip: { x: 0, y: 0, width: 1280, height: 900 } });

// Search
await page.goto(BASE + '/unihive/?hive_q=Toronto', { waitUntil: 'networkidle' });
const titles = await page.locator('.uh-card__title').allInnerTexts();
check('Search "Toronto" returns only matching posts', titles.length >= 2 && titles.length < 6, titles.length + ' results');

// Single post with contact details (someone else's post, so the Report option shows)
await page.goto(firstPost, { waitUntil: 'networkidle' });
check('Pro member sees contact details on a post', (await page.locator('.uh-contact').innerText()).includes('+447700900123'));
await page.goto(BASE + '/hive/uk-graduate-route-my-exact-timeline-and-documents/', { waitUntil: 'networkidle' });
await page.locator('.uh-report > summary').click();
await hideChrome();
await shotColumn('04-single-post', 1400, '.uh-single');

// My posts (with an expired post to renew)
await page.goto(BASE + '/unihive/?hive_view=mine', { waitUntil: 'networkidle' });
check('My posts shows expired post with Renew', (await page.locator('.uh-status--expired').count()) === 1 && (await page.getByRole('button', { name: 'Renew' }).count()) === 1);
await hideChrome();
await shotColumn('05-my-posts', 760);

// 4. New Pro member: validation, then a post that goes to the approval queue
await page.goto(BASE + '/unihive/', { waitUntil: 'networkidle' });
await viewAs('New Pro member');
await page.locator('.uh-create > summary').click();
await page.getByRole('button', { name: 'Publish post' }).click();
await page.waitForLoadState('networkidle');
check('Empty form shows field errors', (await page.locator('.uh-field-error').count()) >= 3);
await page.locator('.uh-radio', { hasText: 'Part-time Jobs' }).click();
await page.fill('#uh-title', 'Library assistant shifts on campus, 10 hours a week');
await page.fill('#uh-description', 'The university library is hiring student assistants for evening shifts. 10 hours a week, £11.50/hour, apply through the careers portal before Friday.');
await page.fill('#uh-location', 'Leeds, UK');
await page.selectOption('#uh-contact-method', 'email');
await page.fill('#uh-contact-value', 'not-an-email');
await page.getByRole('button', { name: 'Publish post' }).click();
await page.waitForLoadState('networkidle');
check('Invalid contact is rejected', (await page.locator('.uh-field-error').allInnerTexts()).some((t) => t.includes('valid email')));
// Fresh form for a clean screenshot, then a valid submission.
await page.goto(BASE + '/unihive/?hive_new=1', { waitUntil: 'networkidle' });
await page.locator('.uh-radio', { hasText: 'Part-time Jobs' }).click();
await page.fill('#uh-title', 'Library assistant shifts on campus, 10 hours a week');
await page.fill('#uh-description', 'The university library is hiring student assistants for evening shifts. 10 hours a week, £11.50/hour, apply through the careers portal before Friday.');
await page.fill('#uh-location', 'Leeds, UK');
await page.selectOption('#uh-contact-method', 'email');
await page.fill('#uh-contact-value', 'rahul_pro@example.com');
await page.locator('input[name="unihive_rules"]').check();
await hideChrome();
await page.locator('.uh-create').screenshot({ path: path.join(OUT, '06-create-form.png') });
await page.getByRole('button', { name: 'Publish post' }).click();
await page.waitForLoadState('networkidle');
check('First post from a new member goes to approval', page.url().includes('hive_msg=pending'));

// 5. Premium member reports a post
await viewAs('Premium member');
await page.goto(firstPost, { waitUntil: 'networkidle' });
check("Other members see the author as 'Priya S.', not the full name", !(await page.content()).includes('Priya Sharma'));
await page.locator('.uh-report > summary').click();
await page.locator('.uh-report label', { hasText: 'Scam' }).click();
await page.getByRole('button', { name: 'Send report' }).click();
await page.waitForLoadState('networkidle');
check('Report is accepted', page.url().includes('hive_msg=reported'));

// 6. Moderator in wp-admin
await viewAs('Moderator');
await page.goto(BASE + '/wp-admin/edit.php?post_type=unihive_post', { waitUntil: 'networkidle' });
check('Moderator sees pending count on the UniHive menu', /\d/.test(await page.locator('#menu-posts-unihive_post .wp-menu-name').innerText()));
check('Moderator sees only Dashboard, UniHive and Profile', (await page.locator('#adminmenu li.menu-top').count()) <= 3);
await hideChrome();
await page.locator('#the-list tr.status-pending').first().hover();
await shot('07-admin-list', { clip: { x: 0, y: 0, width: 1280, height: 760 } });

await page.goto(BASE + '/wp-admin/edit.php?post_type=unihive_post&post_status=all&unihive_reported=1', { waitUntil: 'networkidle' });
check('Reported view lists reported posts', (await page.locator('#the-list tr').count()) >= 2);
await hideChrome();
await shot('08-admin-reported', { clip: { x: 0, y: 0, width: 1280, height: 620 } });

await page.goto(BASE + '/wp-admin/edit.php?post_type=unihive_post&unihive_category=part-time-jobs', { waitUntil: 'networkidle' });
const adminCats = await page.locator('#the-list td.taxonomy-unihive_category').allInnerTexts();
check('Admin category filter works', adminCats.length > 0 && adminCats.every((c) => c.includes('Part-time')), adminCats.length + ' rows');

await page.goto(BASE + '/wp-admin/edit.php?post_type=unihive_post&post_status=pending', { waitUntil: 'networkidle' });
const row = page.locator('#the-list tr', { hasText: 'Library assistant shifts' });
await row.hover();
await row.getByRole('link', { name: 'Approve' }).click();
await page.waitForLoadState('networkidle');
check('Moderator approves a post in one click', (await page.locator('.notice-success').innerText()).includes('approved'));

// 7. Admin settings
await viewAs('Admin');
await page.goto(BASE + '/wp-admin/edit.php?post_type=unihive_post&page=unihive-settings', { waitUntil: 'networkidle' });
await hideChrome();
await shot('09-settings', { fullPage: true });

// 8. Mobile board
await viewAs('Visitor');
await page.goto(BASE + '/unihive/', { waitUntil: 'networkidle' });
await viewAs('Pro member');
await page.setViewportSize({ width: 390, height: 844 });
await page.reload({ waitUntil: 'networkidle' });
await hideChrome();
await shot('10-mobile-board');

// Leave the demo logged in as Admin, the default.
await page.setViewportSize({ width: 1280, height: 900 });
await viewAs('Admin');

await browser.close();
const failed = results.filter((r) => !r.ok).length;
console.log(`\n${results.length - failed}/${results.length} checks passed. Screenshots in docs/img/`);
process.exit(failed ? 1 : 0);
