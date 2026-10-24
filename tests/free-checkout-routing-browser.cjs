'use strict';
// Focused local smoke check. No recordings, Stripe sessions, orders or payments.
const assert = require('node:assert/strict');
const { chromium } = require('../node-dev/node_modules/playwright');
const base = 'http://127.0.0.1:8888';

(async () => {
	const browser = await chromium.launch({ channel: 'msedge', headless: true });
	try {
		const context = await browser.newContext();
		let requests = 0;
		await context.route('**/*', async route => {
			const request = route.request();
			const url = new URL(request.url());
			if (url.origin !== base) return route.abort();
			if (url.pathname.endsWith('/admin-ajax.php')) {
				const body = new URLSearchParams(request.postData() || '');
				if (body.get('action') === 'sahcfwc_get_stripe_checkout_url') {
					assert.equal(request.method(), 'POST');
					assert.ok(body.get('security'));
					requests++;
					return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 'failed', message: 'Offline routing check. No payment was created.' }) });
				}
				// Never allow an unexpected checkout AJAX request to reach Stripe.
				return route.abort();
			}
			return route.continue();
		});
		const productId = process.argv[2];
		assert.match(productId || '', /^\d+$/);
		const added = await context.request.get(`${base}/cart/?add-to-cart=${productId}&quantity=1`);
		assert.equal(added.status(), 200);
		const page = await context.newPage();
		const response = await page.goto(`${base}/checkout/`, { waitUntil: 'domcontentloaded' });
		assert.equal(response.status(), 200);
		assert.match(response.headers()['cache-control'], /no-store|private/);
		await page.locator('.sahcfwc-launcher-status[role="alert"]').waitFor();
		assert.equal(await page.locator('form.checkout, #billing_first_name, .wc-block-checkout__billing-fields').count(), 0);
		assert.equal(requests, 1);
		await page.locator('.sahcfwc-launcher-retry').click();
		await page.waitForFunction(() => !document.querySelector('.sahcfwc-launcher-retry').disabled);
		assert.equal(requests, 2);
		const newTab = await context.newPage();
		await newTab.goto(`${base}/checkout/`, { waitUntil: 'domcontentloaded' });
		await newTab.locator('.sahcfwc-launcher-status[role="alert"]').waitFor();
		assert.equal(await newTab.locator('form.checkout, #billing_first_name, .wc-block-checkout__billing-fields').count(), 0);
		assert.equal(requests, 3);
		assert.equal((await context.request.get(`${base}/wp-admin/`)).status(), 200);
		await context.close();
		console.log('PASS: direct checkout, new tab, private cache headers, compiled JS POST, failure/retry, no Woo billing form, and admin reachable. No videos or payments.');
	} finally { await browser.close(); }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
