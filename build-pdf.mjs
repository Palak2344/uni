// Renders docs/UniHive-Proposal.html to docs/UniHive-Proposal.pdf (A4) with Edge/Chrome.
// Usage: npm run pdf                      → byline shows today's date
//        npm run pdf -- "Your Name"       → "Prepared by Your Name · <date>"

import { chromium } from 'playwright-core';
import { readFileSync, writeFileSync, unlinkSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';

const docs = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'docs');
const name = process.argv.slice(2).join(' ').trim();
const date = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
const byline = (name ? `Prepared by ${name} · ` : '') + date;
const escapeHtml = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

const source = readFileSync(path.join(docs, 'UniHive-Proposal.html'), 'utf8');
const tmp = path.join(docs, '.build.html');
writeFileSync(tmp, source.replace('{{BYLINE}}', escapeHtml(byline)));

let browser;
for (const channel of ['msedge', 'chrome']) {
	try {
		browser = await chromium.launch({ channel, headless: true });
		break;
	} catch {}
}
if (!browser) throw new Error('Install Microsoft Edge or Google Chrome to build the PDF.');

try {
	const page = await browser.newPage();
	await page.goto(pathToFileURL(tmp).href, { waitUntil: 'networkidle' });
	await page.emulateMedia({ media: 'print' });
	const out = path.join(docs, 'UniHive-Proposal.pdf');
	await page.pdf({
		path: out,
		format: 'A4',
		printBackground: true,
		displayHeaderFooter: true,
		headerTemplate: '<div></div>',
		footerTemplate:
			'<div style="width:100%;font:8px Segoe UI,sans-serif;color:#8a9491;text-align:center">UniHive on WordPress · <span class="pageNumber"></span> / <span class="totalPages"></span></div>',
		margin: { top: '15mm', bottom: '16mm', left: '15mm', right: '15mm' },
	});
	console.log('Wrote ' + out);
} finally {
	await browser.close();
	unlinkSync(tmp);
}
