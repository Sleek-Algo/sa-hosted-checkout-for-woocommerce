'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('../node-dev/node_modules/jsdom');

const source = fs.readFileSync(path.join(__dirname, '../node-dev/frontend/checkout/index.js'), 'utf8')
	.replace(/^import .*;\r?$/gm, '')
	.replaceAll('window.location.assign( ', 'window.__navigate( ');
const tick = () => new Promise((resolve) => setTimeout(resolve, 20));
const fixture = {
	status: 'success',
	checkout_mode: 'embedded_popup',
	client_secret: ['cs', 'test', 'fixture', 'secret', 'example'].join('_'),
	publishable_key: ['pk', 'test', 'fixture'].join('_'),
	embedded_url: 'https://shop.example/payment/?order=1',
	return_url: 'https://shop.example/verified-return/?order=1',
};
let passed = 0;

function setup(html = '<main><a class="checkout-button" href="/checkout/">Checkout</a></main>', options = {}) {
	const dom = new JSDOM(html, { url: 'https://shop.example/cart/', runScripts: 'outside-only' });
	const { window } = dom;
	const calls = { requests: 0, init: 0, mount: 0, unmount: 0, destroy: 0, navigations: [] };
	const handlers = {};
	window.__ = (value) => value;
	window.__navigate = (value) => calls.navigations.push(value);
	window.sahcfwc_frontend_localized_data = {
		checkout_mode: options.mode || 'embedded_popup',
		wc_checkout_url: 'https://shop.example/checkout/',
		ajax: { url: '/wp-admin/admin-ajax.php', action: 'create_checkout', security: 'test-nonce' },
	};
	window.jQuery = () => ({ on: (events, handler) => {
		events.split(' ').forEach((event) => { handlers[event.split('.')[0]] = handler; });
	} });
	window.fetch = async () => {
		calls.requests++;
		return { ok: true, json: async () => ({ ...fixture, checkout_mode: window.document.querySelector('.sahcfwc-embedded-checkout')?.dataset.checkoutMode || fixture.checkout_mode }) };
	};
	window.Stripe = () => ({
		initEmbeddedCheckout: async (options) => {
			calls.init++;
			calls.options = options;
			return {
				mount: () => { calls.mount++; },
				unmount: () => { calls.unmount++; },
				destroy: () => { calls.destroy++; },
			};
		},
	});
	if (options.beforeInit) options.beforeInit(window);
	window.eval(source);
	window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
	return { dom, window, calls, handlers, click: () => window.document.querySelector('.checkout-button').click() };
}

async function test(name, action) {
	await action();
	passed++;
	process.stdout.write('PASS ' + name + '\n');
}

(async () => {
	await test('cart render and refresh never create a session', async () => {
		const env = setup();
		await tick();
		env.handlers.wc_fragment_refresh();
		env.handlers.updated_cart_totals();
		assert.equal(env.calls.requests, 0);
		env.dom.window.close();
	});
	await test('popup double click creates one request and one instance', async () => {
		const env = setup();
		env.click();
		env.click();
		await tick();
		assert.equal(env.calls.requests, 1);
		assert.equal(env.calls.init, 1);
		assert.equal(env.calls.mount, 1);
		assert.equal(env.window.document.querySelector('dialog').hasAttribute('open'), true);
		env.dom.window.close();
	});
	await test('popup close unmounts without cancellation; reopen reuses instance', async () => {
		const env = setup();
		env.click();
		await tick();
		env.window.document.querySelector('.sahcfwc-checkout-close').click();
		assert.equal(env.calls.unmount, 1);
		assert.equal(env.calls.requests, 1);
		assert.equal(env.calls.destroy, 0);
		env.click();
		await tick();
		assert.equal(env.calls.init, 1);
		assert.equal(env.calls.mount, 2);
		assert.equal(env.calls.requests, 2);
		env.dom.window.close();
	});
	await test('closing during initialization never remounts the hidden popup', async () => {
		const env = setup();
		let resolve;
		env.window.Stripe = () => ({ initEmbeddedCheckout: () => new Promise((done) => { resolve = done; }) });
		env.click();
		await tick();
		env.window.document.querySelector('.sahcfwc-checkout-close').click();
		resolve({ mount: () => { env.calls.mount++; }, unmount() {}, destroy() {} });
		await tick();
		assert.equal(env.calls.mount, 0);
		assert.equal(env.window.document.querySelector('dialog').hasAttribute('open'), false);
		env.dom.window.close();
	});
	await test('changed cart cannot open the stale response', async () => {
		const env = setup();
		env.click();
		env.handlers.updated_cart_totals();
		await tick();
		assert.equal(env.calls.init, 0);
		assert.match(env.window.document.querySelector('.sahcfwc-checkout-error').textContent, /cart changed/);
		env.dom.window.close();
	});
	await test('invalid publishable key is never passed to Stripe', async () => {
		const env = setup();
		env.window.fetch = async () => ({ ok: true, json: async () => ({
			...fixture, publishable_key: ['sk', 'test', 'notarealkey'].join('_'),
		}) });
		env.click();
		await tick();
		assert.equal(env.calls.init, 0);
		assert.match(env.window.document.querySelector('.sahcfwc-embedded-status').textContent, /Unable to load/);
		env.dom.window.close();
	});
	await test('mixed test and live configuration fails safely', async () => {
		const env = setup();
		env.window.fetch = async () => ({ ok: true, json: async () => ({
			...fixture, publishable_key: ['pk', 'live', 'fixture'].join('_'),
		}) });
		env.click();
		await tick();
		assert.equal(env.calls.init, 0);
		env.dom.window.close();
	});
	await test('external return URL fails safely', async () => {
		const env = setup();
		env.window.fetch = async () => ({ ok: true, json: async () => ({
			...fixture, return_url: 'https://attacker.example/return/',
		}) });
		env.click();
		await tick();
		assert.equal(env.calls.init, 0);
		env.dom.window.close();
	});
	await test('server error containing credentials is redacted', async () => {
		const env = setup();
		env.window.fetch = async () => ({ ok: true, json: async () => ({
			status: 'failed', message: 'Invalid API Key: ' + ['sk', 'test', 'notarealkey'].join('_'),
		}) });
		env.click();
		await tick();
		const notice = env.window.document.querySelector('.sahcfwc-checkout-error');
		assert.match(notice.textContent, /Unable to load/);
		assert.doesNotMatch(notice.textContent, /notarealkey/);
		env.dom.window.close();
	});
	await test('escaped server error is text, never HTML', async () => {
		const env = setup();
		env.window.fetch = async () => ({ ok: true, json: async () => ({
			status: 'failed', message: '<img src=x onerror=alert(1)>',
		}) });
		env.click();
		await tick();
		assert.equal(env.window.document.querySelector('.sahcfwc-checkout-error img'), null);
		env.dom.window.close();
	});
	await test('network failure releases buttons and permits retry', async () => {
		const env = setup();
		env.window.fetch = async () => { throw new Error('Network unavailable'); };
		env.click();
		await tick();
		assert.notEqual(env.window.document.querySelector('.checkout-button').getAttribute('aria-disabled'), 'true');
		env.window.fetch = async () => ({ ok: true, json: async () => ({ ...fixture }) });
		env.click();
		await tick();
		assert.equal(env.calls.mount, 1);
		env.dom.window.close();
	});
	await test('order page retrieves existing session and mounts inline', async () => {
		const env = setup('<div class="sahcfwc-embedded-checkout" data-checkout-mode="embedded_page" data-order-id="1" data-order-key="wc_order_fixture" data-security="nonce" data-ajax-url="/wp-admin/admin-ajax.php"><div class="sahcfwc-embedded-status"></div><div class="sahcfwc-embedded-mount"></div></div>');
		await tick();
		assert.equal(env.calls.requests, 1);
		assert.equal(env.calls.mount, 1);
		assert.equal(env.window.document.querySelector('dialog'), null);
		env.dom.window.close();
	});
	await test('popup order page can close and reopen via explicit button', async () => {
		const env = setup('<div class="sahcfwc-embedded-checkout" data-checkout-mode="embedded_popup" data-order-id="1" data-order-key="wc_order_fixture" data-security="nonce" data-ajax-url="/wp-admin/admin-ajax.php"><div class="sahcfwc-embedded-status"></div><div class="sahcfwc-embedded-mount"></div></div>');
		await tick();
		env.window.document.querySelector('.sahcfwc-checkout-close').click();
		env.window.document.querySelector('.sahcfwc-checkout-retry').click();
		await tick();
		assert.equal(env.calls.requests, 2);
		assert.equal(env.calls.init, 1);
		assert.equal(env.calls.mount, 2);
		env.dom.window.close();
	});
	for (const eventName of ['wc-blocks_added_to_cart', 'wc-blocks_removed_from_cart']) {
		await test(eventName + ' invalidates an in-flight cart request', async () => {
			const env = setup();
			env.click();
			env.window.document.body.dispatchEvent(new env.window.CustomEvent(eventName, { bubbles: true }));
			await tick();
			assert.equal(env.calls.requests, 1);
			assert.equal(env.calls.init, 0);
			assert.match(env.window.document.querySelector('.sahcfwc-checkout-error').textContent, /cart changed/);
			env.dom.window.close();
		});
	}
	await test('WooCommerce-disabled checkout is not intercepted or enabled', async () => {
		const env = setup('<main><a class="checkout-button wc-block-cart__submit-button" aria-disabled="true">Checkout</a></main>');
		env.click();
		await tick();
		assert.equal(env.calls.requests, 0);
		assert.equal(env.window.document.querySelector('.checkout-button').getAttribute('aria-disabled'), 'true');
		env.dom.window.close();
	});
	await test('already prevented clicks respect other checkout validation', async () => {
		const env = setup(undefined, { mode: 'hosted' });
		env.window.document.querySelector('.checkout-button').addEventListener('click', (event) => event.preventDefault());
		env.click();
		await tick();
		assert.equal(env.calls.requests, 0);
		env.dom.window.close();
	});
	await test('handled popup click suppresses native navigation loading handler', async () => {
		const env = setup('<main><a class="checkout-button wc-block-cart__submit-button" href="/checkout/"><span>Checkout</span></a></main>');
		let nativeCalls = 0;
		env.window.document.querySelector('.checkout-button').addEventListener('click', () => nativeCalls++);
		env.window.document.querySelector('span').click();
		await tick();
		assert.equal(nativeCalls, 0);
		assert.equal(env.calls.requests, 1);
		assert.equal(env.calls.mount, 1);
		env.window.document.querySelector('.sahcfwc-checkout-close').click();
		env.click();
		await tick();
		assert.equal(nativeCalls, 0);
		assert.equal(env.calls.init, 1);
		env.dom.window.close();
	});
	await test('popup capture leaves modified disabled and unrelated clicks alone', async () => {
		const env = setup('<main><a class="checkout-button wc-block-cart__submit-button" href="#">Checkout</a><button id="unrelated">Other</button></main>');
		const button = env.window.document.querySelector('.checkout-button');
		let nativeCalls = 0;
		button.addEventListener('click', (event) => { nativeCalls++; event.preventDefault(); });
		for (const modifier of ['ctrlKey', 'metaKey', 'shiftKey', 'altKey']) button.dispatchEvent(new env.window.MouseEvent('click', { bubbles: true, cancelable: true, button: 0, [modifier]: true }));
		button.dispatchEvent(new env.window.MouseEvent('click', { bubbles: true, cancelable: true, button: 1 }));
		button.setAttribute('aria-disabled', 'true');
		button.click();
		let unrelatedCalls = 0;
		env.window.document.querySelector('#unrelated').addEventListener('click', () => unrelatedCalls++);
		env.window.document.querySelector('#unrelated').click();
		await tick();
		assert.equal(nativeCalls, 6);
		assert.equal(unrelatedCalls, 1);
		assert.equal(env.calls.requests, 0);
		env.dom.window.close();
	});
	await test('popup capture respects a previously cancelled event', async () => {
		const env = setup(undefined, { beforeInit: (window) => window.addEventListener('click', (event) => event.preventDefault(), true) });
		env.click();
		await tick();
		assert.equal(env.calls.requests, 0);
		env.dom.window.close();
	});
	for (const mode of ['hosted', 'embedded_page']) {
		await test(mode + ' retains native bubbling and reuses popup session through its existing page', async () => {
			const env = setup(undefined, { mode });
			let nativeCalls = 0;
			env.window.document.querySelector('.checkout-button').addEventListener('click', () => nativeCalls++);
			env.click();
			await tick();
			assert.equal(nativeCalls, 1);
			assert.equal(env.calls.requests, 1);
			assert.equal(env.calls.init, 0);
			assert.deepEqual(env.calls.navigations, [fixture.embedded_url]);
			env.dom.window.close();
		});
	}
	await test('reused popup page fallback rejects an external URL', async () => {
		const env = setup(undefined, { mode: 'hosted' });
		env.window.fetch = async () => { env.calls.requests++; return { ok: true, json: async () => ({ ...fixture, embedded_url: 'https://attacker.example/payment/' }) }; };
		env.click();
		await tick();
		assert.equal(env.calls.requests, 1);
		assert.equal(env.calls.init, 0);
		assert.equal(env.calls.navigations.length, 0);
		assert.match(env.window.document.querySelector('.sahcfwc-checkout-error').textContent, /Unable to load/);
		env.dom.window.close();
	});
	await test('Blocks quantity change without native events invalidates the pending response', async () => {
		const env = setup('<main><a class="checkout-button wc-block-cart__submit-button">Checkout</a></main>');
		let quantity = 1;
		env.window.wp = { data: { select: () => ({ getCartData: () => ({ items: [{ key: 'item', id: 1, quantity }] }) }) } };
		env.click();
		quantity = 2;
		await tick();
		assert.equal(env.calls.init, 0);
		assert.match(env.window.document.querySelector('.sahcfwc-checkout-error').textContent, /cart changed/);
		env.dom.window.close();
	});
	await test('Blocks pending coupon operation prevents starting checkout', async () => {
		const env = setup('<main><a class="checkout-button wc-block-cart__submit-button">Checkout</a></main>');
		env.window.wp = { data: { select: () => ({ getCartData: () => ({ items: [] }), isApplyingCoupon: () => true }) } };
		env.click();
		await tick();
		assert.equal(env.calls.requests, 0);
		assert.match(env.window.document.querySelector('.sahcfwc-checkout-error').textContent, /cart is updating/);
		env.dom.window.close();
	});
	await test('popup avoids the native top layer and restores background accessibility', async () => {
		const env = setup();
		let nativeModalCalls = 0;
		env.window.HTMLDialogElement.prototype.showModal = () => { nativeModalCalls++; };
		env.click(); await tick();
		assert.equal(nativeModalCalls, 0);
		assert.equal(env.window.document.querySelector('main').inert, true);
		assert.equal(env.window.document.querySelector('.sahcfwc-checkout-backdrop').hidden, false);
		env.window.document.querySelector('.sahcfwc-checkout-close').click();
		assert.notEqual(env.window.document.querySelector('main').inert, true);
		assert.equal(env.window.document.querySelector('main').getAttribute('aria-hidden'), null);
		assert.equal(env.window.document.querySelector('.sahcfwc-checkout-backdrop').hidden, true);
		env.dom.window.close();
	});
	await test('late Stripe portal is released from inert background and keeps focus', async () => {
		const env = setup();
		const portal = env.window.document.createElement('div');
		env.window.document.body.append(portal);
		env.click(); await tick();
		assert.equal(portal.inert, true);
		const frame = env.window.document.createElement('iframe');
		frame.src = 'https://js.stripe.com/test-quantity-dialog';
		portal.append(frame); await tick();
		assert.notEqual(portal.inert, true);
		assert.equal(portal.getAttribute('aria-hidden'), null);
		frame.focus();
		assert.equal(env.window.document.activeElement, frame);
		env.window.document.querySelector('.sahcfwc-checkout-close').click();
		env.dom.window.close();
	});
	await test('late Stripe iframe src assignment releases only its portal branch', async () => {
		const env = setup();
		env.click(); await tick();
		const portal = env.window.document.createElement('div');
		const frame = env.window.document.createElement('iframe');
		portal.append(frame); env.window.document.body.append(portal); await tick();
		assert.equal(portal.inert, true);
		frame.src = 'https://js.stripe.com/test-quantity-dialog'; await tick();
		assert.notEqual(portal.inert, true);
		assert.equal(portal.getAttribute('aria-hidden'), null);
		assert.notEqual(frame.inert, true);
		env.window.document.querySelector('.sahcfwc-checkout-close').click();
		env.dom.window.close();
	});
	await test('Stripe iframe nested in main does not expose unrelated page controls', async () => {
		const env = setup('<main><section><a class="checkout-button">Checkout</a><button>Unrelated action</button></section><div><iframe src="https://js.stripe.com/test-existing"></iframe></div></main>');
		env.click(); await tick();
		const section = env.window.document.querySelector('section');
		assert.notEqual(env.window.document.querySelector('main').inert, true);
		assert.equal(section.inert, true);
		assert.equal(section.getAttribute('aria-hidden'), 'true');
		assert.notEqual(env.window.document.querySelector('main iframe').inert, true);
		env.window.document.querySelector('.sahcfwc-checkout-close').click();
		assert.notEqual(section.inert, true);
		assert.equal(section.getAttribute('aria-hidden'), null);
		env.dom.window.close();
	});
	await test('visible Stripe overlay owns Escape instead of closing the checkout underneath', async () => {
		const env = setup();
		env.click(); await tick();
		const frame = env.window.document.createElement('iframe');
		frame.src = 'https://hooks.stripe.com/test-authentication-dialog';
		frame.getBoundingClientRect = () => ({ width: 400, height: 400 });
		env.window.document.body.append(frame); await tick();
		const dialog = env.window.document.querySelector('dialog');
		const escape = new env.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
		dialog.dispatchEvent(escape);
		assert.equal(escape.defaultPrevented, false);
		assert.equal(dialog.hasAttribute('open'), true);
		frame.remove(); await tick();
		dialog.dispatchEvent(new env.window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
		assert.equal(dialog.hasAttribute('open'), false);
		env.dom.window.close();
	});
	const launcherHtml = '<main><div class="sahcfwc-checkout-launcher"><p class="sahcfwc-launcher-status"></p><button class="sahcfwc-launcher-retry" hidden>Retry</button><a href="/cart/">Return to cart</a></div></main>';
	for (const mode of ['hosted', 'embedded_page', 'embedded_popup']) {
		await test('direct checkout launcher starts ' + mode + ' once without a Woo form', async () => {
			const env = setup(launcherHtml, { mode, beforeInit: (window) => {
				Object.defineProperty(window.document, 'visibilityState', { value: 'visible', configurable: true });
				window.launcherRequests = 0;
				window.fetch = async (url, args) => { window.launcherRequests++; assert.equal(args.method, 'POST'); assert.equal(new URLSearchParams(args.body).get('security'), 'test-nonce'); return { ok: true, json: async () => ({ ...fixture, checkout_mode: mode, stripe_checkout_session_url: 'https://checkout.stripe.com/pay/test' }) }; };
			} });
			await tick();
			env.window.document.dispatchEvent(new env.window.Event('DOMContentLoaded'));
			assert.equal(env.window.launcherRequests, 1);
			assert.equal(env.window.document.querySelector('form.checkout,#place_order'), null);
			if (mode === 'embedded_popup') {
				assert.equal(env.calls.mount, 1);
				env.window.document.querySelector('.sahcfwc-checkout-close').click();
				env.window.document.querySelector('.sahcfwc-launcher-retry').click(); await tick();
				assert.equal(env.calls.init, 1); assert.equal(env.calls.mount, 2);
			} else assert.deepEqual(env.calls.navigations, [mode === 'hosted' ? 'https://checkout.stripe.com/pay/test' : fixture.embedded_url]);
			env.dom.window.close();
		});
	}
	await test('launcher error offers retry without a Woo form or unsafe message', async () => {
		const env = setup(launcherHtml, { beforeInit: (window) => {
			Object.defineProperty(window.document, 'visibilityState', { value: 'visible' });
			window.fetch = async () => { throw Error('sk_test_dummy must stay private'); };
		} });
		await tick();
		assert.equal(env.window.document.querySelector('.sahcfwc-launcher-status').getAttribute('role'), 'alert');
		assert.equal(env.window.document.body.textContent.includes('sk_test_dummy'), false);
		assert.equal(env.window.document.querySelector('.sahcfwc-launcher-retry').hidden, false);
		env.window.fetch = async () => ({ ok: true, json: async () => fixture });
		env.window.document.querySelector('.sahcfwc-launcher-retry').click(); await tick();
		assert.equal(env.calls.mount, 1); env.dom.window.close();
	});
	await test('hidden or prerendered checkout does not create a session', async () => {
		let visible = 'hidden', prerender = true;
		const env = setup(launcherHtml, { beforeInit: (window) => {
			Object.defineProperty(window.document, 'visibilityState', { get: () => visible });
			Object.defineProperty(window.document, 'prerendering', { get: () => prerender });
		} });
		await tick(); assert.equal(env.calls.requests, 0);
		visible = 'visible'; env.window.document.dispatchEvent(new env.window.Event('visibilitychange')); await tick(); assert.equal(env.calls.requests, 0);
		prerender = false; env.window.document.dispatchEvent(new env.window.Event('prerenderingchange')); await tick(); assert.equal(env.calls.requests, 1);
		env.window.document.dispatchEvent(new env.window.Event('visibilitychange')); await tick(); assert.equal(env.calls.requests, 1);
		env.dom.window.close();
	});
	await test('launcher blocks repeated clicks while its POST is pending', async () => {
		let finish;
		const env = setup(launcherHtml, { beforeInit: (window) => {
			Object.defineProperty(window.document, 'visibilityState', { value: 'visible' });
			window.launcherRequests = 0;
			window.fetch = () => { window.launcherRequests++; return new Promise(resolve => { finish = resolve; }); };
		} });
		const button = env.window.document.querySelector('.sahcfwc-launcher-retry');
		button.dispatchEvent(new env.window.Event('click')); button.dispatchEvent(new env.window.Event('click'));
		assert.equal(env.window.launcherRequests, 1);
		finish({ ok: true, json: async () => fixture }); await tick(); assert.equal(env.calls.mount, 1);
		env.dom.window.close();
	});
	process.stdout.write('All ' + passed + ' Embedded Checkout DOM checks passed.\n');
})().catch((error) => { console.error(error.stack); process.exitCode = 1; });

