import { __ } from '@wordpress/i18n';
import './style.css';

const genericError = () => __( 'Unable to load secure checkout. Please try again.', 'sa-hosted-checkout-for-woocommerce' );
const config = window.sahcfwc_frontend_localized_data || {};
const checkoutSelectors = 'a.checkout-button, a.checkout, .wp-block-woocommerce-mini-cart-checkout-button-block, .wc-block-cart__submit-button';
let cartRequest = null;
let cartRevision = 0;
let popup = null;

function safeUrl( value, sameOrigin = true ) {
	try {
		const url = new URL( value, window.location.href );
		if ( ! value || url.username || url.password ||
			( sameOrigin ? url.origin !== window.location.origin :
				url.origin !== window.location.origin && url.protocol !== 'https:' ) ||
			! [ 'https:', 'http:' ].includes( url.protocol ) ) {
			return null;
		}
		return url.href;
	} catch ( error ) {
		return null;
	}
}

function safeMessage( value ) {
	return typeof value === 'string' && value.length < 500 &&
		! /(?:[sr]k_(?:test|live)_|whsec_|_secret_)/i.test( value ) ? value : genericError();
}

function makeElement( tag, className, text ) {
	const element = document.createElement( tag );
	element.className = className || '';
	if ( text ) {
		element.textContent = text;
	}
	return element;
}

async function post( url, values ) {
	const destination = safeUrl( url );
	if ( ! destination ) {
		throw new Error( genericError() );
	}
	const controller = new AbortController();
	const timer = window.setTimeout( () => controller.abort(), 45000 );
	try {
		const response = await fetch( destination, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: new URLSearchParams( values ),
			signal: controller.signal,
		} );
		const data = await response.json();
		if ( ! response.ok || ! data || data.status !== 'success' ) {
			throw new Error( safeMessage( data && data.message ) );
		}
		return data;
	} catch ( error ) {
		throw new Error( safeMessage( error && error.message ) );
	} finally {
		window.clearTimeout( timer );
	}
}

function setCartBusy( busy ) {
	document.querySelectorAll( checkoutSelectors ).forEach( ( button ) => {
		button.classList.toggle( 'sahcfwc-checkout-busy', busy );
		button.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
	} );
}

function showCartError( message ) {
	document.querySelectorAll( '.sahcfwc-checkout-error' ).forEach( ( notice ) => notice.remove() );
	const notice = makeElement( 'div', 'woocommerce-error sahcfwc-checkout-error', safeMessage( message ) );
	notice.setAttribute( 'role', 'alert' );
	notice.tabIndex = -1;
	const target = document.querySelector( '.woocommerce-notices-wrapper, main, .content-area, .site-main' ) || document.body;
	target.prepend( notice );
	notice.focus();
}

function createCheckoutView( mount, status ) {
	let instance = null;
	let pending = null;
	let currentSecret = '';
	let visible = true;
	let mounted = false;
	let generation = 0;

	function message( text, error = false ) {
		status.textContent = text;
		status.setAttribute( 'role', error ? 'alert' : 'status' );
		status.hidden = ! text;
		mount.setAttribute( 'aria-busy', text && ! error ? 'true' : 'false' );
	}

	function hide() {
		visible = false;
		if ( instance && mounted ) {
			instance.unmount();
			mounted = false;
		}
	}

	function dispose() {
		generation++;
		hide();
		if ( instance ) {
			instance.destroy();
		}
		instance = null;
		pending = null;
		currentSecret = '';
	}

	async function show( data ) {
		visible = true;
		const key = data && data.publishable_key;
		const secret = data && data.client_secret;
		const returnUrl = safeUrl( data && data.return_url );
		if ( typeof key !== 'string' || ! /^pk_(?:test|live)_[a-zA-Z0-9]+$/.test( key ) ||
			typeof secret !== 'string' || ! /^cs_(?:test|live)_.+_secret_.+$/.test( secret ) ||
			key.split( '_' )[ 1 ] !== secret.split( '_' )[ 1 ] ||
			! returnUrl || typeof window.Stripe !== 'function' ) {
			message( genericError(), true );
			throw new Error( genericError() );
		}
		if ( currentSecret && currentSecret !== secret ) {
			dispose();
			visible = true;
		}
		currentSecret = secret;
		message( __( 'Loading secure checkout…', 'sa-hosted-checkout-for-woocommerce' ) );
		if ( ! instance && ! pending ) {
			const ownGeneration = generation;
			const stripe = window.Stripe( key );
			if ( typeof stripe.initEmbeddedCheckout !== 'function' ) {
				message( genericError(), true );
				throw new Error( genericError() );
			}
			pending = stripe.initEmbeddedCheckout( {
				clientSecret: secret,
				onComplete: () => {
					// Only the server-verified return handler can update the order.
					window.location.assign( returnUrl );
				},
			} ).then( ( checkout ) => {
				if ( ownGeneration !== generation ) {
					checkout.destroy();
					return;
				}
				instance = checkout;
			} ).finally( () => {
				if ( ownGeneration === generation ) {
					pending = null;
				}
			} );
		}
		try {
			if ( pending ) {
				await pending;
			}
			if ( visible && instance && ! mounted ) {
				instance.mount( mount );
				mounted = true;
				message( '' );
			}
		} catch ( error ) {
			message( genericError(), true );
			throw new Error( genericError() );
		}
	}

	return { show, hide, dispose, message };
}

function isStripePortal( element ) {
	if ( ! ( element instanceof Element ) ) {
		return false;
	}
	const frames = element.tagName === 'IFRAME' ? [ element ] : Array.from( element.querySelectorAll( 'iframe' ) );
	return frames.some( ( frame ) => {
		try {
			return [ 'https://js.stripe.com', 'https://hooks.stripe.com', 'https://checkout.stripe.com' ].includes( new URL( frame.src ).origin );
		} catch ( error ) {
			return false;
		}
	} );
}

function createPopup() {
	const backdrop = makeElement( 'div', 'sahcfwc-checkout-backdrop' );
	backdrop.hidden = true;
	backdrop.setAttribute( 'aria-hidden', 'true' );
	const dialog = makeElement( 'dialog', 'sahcfwc-checkout-dialog' );
	dialog.setAttribute( 'aria-labelledby', 'sahcfwc-checkout-title' );
	dialog.setAttribute( 'role', 'dialog' );
	dialog.setAttribute( 'aria-modal', 'true' );
	const header = makeElement( 'div', 'sahcfwc-checkout-dialog-header' );
	const title = makeElement( 'h2', '', __( 'Secure checkout', 'sa-hosted-checkout-for-woocommerce' ) );
	title.id = 'sahcfwc-checkout-title';
	const closeButton = makeElement( 'button', 'sahcfwc-checkout-close', __( 'Close', 'sa-hosted-checkout-for-woocommerce' ) );
	closeButton.type = 'button';
	closeButton.setAttribute( 'aria-label', __( 'Close checkout and return to your cart', 'sa-hosted-checkout-for-woocommerce' ) );
	header.append( title, closeButton );
	const content = makeElement( 'div', 'sahcfwc-checkout-dialog-content' );
	const status = makeElement( 'div', 'sahcfwc-embedded-status' );
	status.setAttribute( 'aria-live', 'polite' );
	const mount = makeElement( 'div', 'sahcfwc-embedded-mount' );
	const recovery = makeElement( 'a', 'sahcfwc-checkout-recovery', __( 'Continue on the payment page', 'sa-hosted-checkout-for-woocommerce' ) );
	recovery.hidden = true;
	content.append( status, mount, recovery );
	dialog.append( header, content );
	document.body.append( backdrop, dialog );
	const view = createCheckoutView( mount, status );
	let lastFocus = null;
	let opened = false;
	let fallbackSiblings = [];
	let backgroundObserver = null;

	function stripeOverlayOpen() {
		return Array.from( document.querySelectorAll( 'iframe' ) ).some( ( frame ) => {
			const bounds = frame.getBoundingClientRect();
			return ! dialog.contains( frame ) && isStripePortal( frame ) && bounds.width > 50 && bounds.height > 50 &&
				window.getComputedStyle( frame ).visibility !== 'hidden';
		} );
	}

	function protectBackground() {
		if ( ! opened || typeof document === 'undefined' || ! document || ! document.body ) {
			return;
		}
		function protectBranch( sibling ) {
			if ( sibling === dialog || sibling === backdrop ) {
				return;
			}
			let previous = fallbackSiblings.find( ( entry ) => entry[ 0 ] === sibling );
			if ( ! previous ) {
				previous = [ sibling, sibling.inert, sibling.getAttribute( 'aria-hidden' ), false ];
				fallbackSiblings.push( previous );
			}
			// Stripe mounts quantity/authentication dialogs outside its main iframe.
			// They must remain interactive above our non-top-layer modal.
			if ( isStripePortal( sibling ) ) {
				if ( previous[ 3 ] ) {
					sibling.inert = previous[ 1 ];
					if ( previous[ 2 ] === null ) {
						sibling.removeAttribute( 'aria-hidden' );
					} else {
						sibling.setAttribute( 'aria-hidden', previous[ 2 ] );
					}
					previous[ 3 ] = false;
				}
				// An existing Stripe iframe can share a broad page container. Keep
				// only its ancestry usable; unrelated sibling controls stay inert.
				Array.from( sibling.children ).forEach( protectBranch );
			} else {
				sibling.inert = true;
				sibling.setAttribute( 'aria-hidden', 'true' );
				previous[ 3 ] = true;
			}
		}
		Array.from( document.body.children ).forEach( protectBranch );
	}

	function close() {
		opened = false;
		view.hide();
		if ( backgroundObserver ) {
			backgroundObserver.disconnect();
			backgroundObserver = null;
		}
		dialog.removeAttribute( 'open' );
		backdrop.hidden = true;
		document.body.classList.remove( 'sahcfwc-checkout-modal-open' );
		fallbackSiblings.forEach( ( [ sibling, inert, hidden, owned ] ) => {
			if ( ! owned ) {
				return;
			}
			sibling.inert = inert;
			if ( hidden === null ) {
				sibling.removeAttribute( 'aria-hidden' );
			} else {
				sibling.setAttribute( 'aria-hidden', hidden );
			}
		} );
		fallbackSiblings = [];
		if ( lastFocus && lastFocus.isConnected ) {
			lastFocus.focus();
		}
	}
	closeButton.addEventListener( 'click', close );
	dialog.addEventListener( 'cancel', ( event ) => {
		event.preventDefault();
		close();
	} );
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( stripeOverlayOpen() ) {
			return;
		}
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			close();
		}
		if ( event.key === 'Tab' ) {
			const focusable = Array.from( dialog.querySelectorAll( 'button, a[href], iframe, [tabindex="0"]' ) ).filter( ( element ) => ! element.hidden );
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			if ( ( event.shiftKey && document.activeElement === first ) || ( ! event.shiftKey && document.activeElement === last ) ) {
				event.preventDefault();
				( event.shiftKey ? last : first ).focus();
			}
		}
	} );
	document.addEventListener( 'focusin', ( event ) => {
		if ( opened && ! dialog.contains( event.target ) && ! isStripePortal( event.target ) && ! stripeOverlayOpen() ) {
			closeButton.focus();
		}
	} );

	return {
		async open( data, trigger ) {
			lastFocus = trigger || document.activeElement;
			opened = true;
			if ( ! dialog.open ) {
				// Do not use showModal(): its top layer blocks Stripe-owned overlays.
				dialog.setAttribute( 'open', '' );
				backdrop.hidden = false;
				protectBackground();
				backgroundObserver = new MutationObserver( protectBackground );
				backgroundObserver.observe( document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [ 'src' ] } );
			}
			document.body.classList.add( 'sahcfwc-checkout-modal-open' );
			closeButton.focus();
			recovery.hidden = true;
			const recoveryUrl = safeUrl( data.embedded_url );
			try {
				await view.show( data );
			} catch ( error ) {
				view.message( genericError(), true );
				if ( recoveryUrl ) {
					recovery.href = recoveryUrl;
					recovery.hidden = false;
				}
			}
			if ( ! opened ) {
				view.hide();
			}
		},
		close,
		dispose: view.dispose,
	};
}

async function handleCheckout( data, trigger ) {
	const mode = data.checkout_mode || 'hosted';
	if ( mode === 'embedded_popup' && data.client_secret && config.checkout_mode === 'embedded_popup' ) {
		popup = popup || createPopup();
		await popup.open( data, trigger );
		return;
	}
	const destination = mode === 'hosted' ?
		safeUrl( data.stripe_checkout_session_url, false ) :
		safeUrl( data.embedded_url );
	if ( ! destination ) {
		throw new Error( genericError() );
	}
	window.location.assign( destination );
}

function isCheckoutLink( target ) {
	const button = target.closest( checkoutSelectors + ', a[href]' );
	if ( ! button ) {
		return null;
	}
	if ( button.matches( checkoutSelectors ) ) {
		return button;
	}
	return config.wc_checkout_url && safeUrl( button.href ) === safeUrl( config.wc_checkout_url ) ? button : null;
}

function initCart() {
	if ( ! config.ajax || ! config.wc_checkout_url ) {
		return;
	}
	const popupCheckout = config.checkout_mode === 'embedded_popup';
	document.addEventListener( 'click', async ( event ) => {
		if ( event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ||
			! ( event.target instanceof Element ) ) {
			return;
		}
		const trigger = isCheckoutLink( event.target );
		if ( ! trigger ) {
			return;
		}
		if ( trigger.getAttribute( 'aria-disabled' ) === 'true' || trigger.hasAttribute( 'disabled' ) || trigger.classList.contains( 'disabled' ) ) {
			return;
		}
		event.preventDefault();
		if ( popupCheckout ) {
			// A popup does not navigate or fire pageshow. Intercept owned links
			// before Woo Blocks starts its navigation-only loading state.
			event.stopPropagation();
		}
		if ( cartRequest ) {
			return;
		}
		const revision = cartRevision;
		setCartBusy( true );
		document.querySelectorAll( '.sahcfwc-checkout-error' ).forEach( ( notice ) => notice.remove() );
		try {
			const before = blockCartState( trigger );
			if ( before && before.pending ) {
				throw new Error( __( 'Your cart is updating. Please wait and try again.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			cartRequest = post( config.ajax.url, {
				action: config.ajax.action,
				security: config.ajax.security,
			} );
			const data = await cartRequest;
			const after = blockCartState( trigger );
			if ( revision !== cartRevision || ( before && after && ( after.pending || before.fingerprint !== after.fingerprint ) ) ) {
				throw new Error( __( 'Your cart changed. Please review it and start checkout again.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			await handleCheckout( data, trigger );
		} catch ( error ) {
			showCartError( error.message );
		} finally {
			cartRequest = null;
			setCartBusy( false );
		}
	}, popupCheckout );
	// Cart refreshes must never create payment sessions automatically.
	function cartChanged() {
		cartRevision++;
		if ( popup ) {
			popup.close();
			popup.dispose();
		}
	}
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'updated_cart_totals.sahcfwcCheckout removed_from_cart.sahcfwcCheckout wc_fragment_refresh.sahcfwcCheckout', cartChanged );
	}
	document.body.addEventListener( 'wc-blocks_added_to_cart', cartChanged );
	document.body.addEventListener( 'wc-blocks_removed_from_cart', cartChanged );
}

function blockCartState( trigger ) {
	if ( ! trigger.closest( '.wc-block-cart__submit-button, .wp-block-woocommerce-mini-cart-checkout-button-block, .wp-block-woocommerce-cart, .wp-block-woocommerce-mini-cart' ) || ! window.wp || ! window.wp.data ) {
		return null;
	}
	try {
		const store = window.wp.data.select( 'wc/store/cart' );
		if ( ! store || typeof store.getCartData !== 'function' ) {
			return null;
		}
		const pending = [ 'hasPendingItemsOperations', 'isApplyingCoupon', 'isRemovingCoupon', 'isShippingRateBeingSelected', 'isCustomerDataUpdating', 'isAddressFieldsForShippingRatesUpdating', 'isCartDataStale' ]
			.some( ( name ) => typeof store[ name ] === 'function' && store[ name ]() );
		const data = store.getCartData() || {};
		const fingerprint = JSON.stringify( {
			items: ( data.items || [] ).map( ( item ) => ( { key: item.key, id: item.id, quantity: item.quantity, prices: item.prices, totals: item.totals } ) ),
			coupons: data.coupons,
			totals: typeof store.getCartTotals === 'function' ? store.getCartTotals() : data.totals,
			shipping: typeof store.getShippingRates === 'function' ? store.getShippingRates() : data.shippingRates,
			customer: typeof store.getCustomerData === 'function' ? store.getCustomerData() : undefined,
		} );
		return { pending, fingerprint };
	} catch ( error ) {
		// Classic carts and themes without the Blocks store use event invalidation.
		return null;
	}
}

function initPage( root ) {
	const mount = root.querySelector( '.sahcfwc-embedded-mount' );
	const status = root.querySelector( '.sahcfwc-embedded-status' );
	if ( ! mount || ! status ) {
		return;
	}
	status.setAttribute( 'aria-live', 'polite' );
	let isPopup = root.dataset.checkoutMode === 'embedded_popup';
	const view = createCheckoutView( mount, status );
	const retry = makeElement( 'button', 'button sahcfwc-checkout-retry', isPopup ?
		__( 'Open secure checkout', 'sa-hosted-checkout-for-woocommerce' ) : __( 'Try again', 'sa-hosted-checkout-for-woocommerce' ) );
	retry.type = 'button';
	retry.hidden = true;
	root.append( retry );
	let pending = false;
	async function load() {
		if ( pending ) {
			return;
		}
		pending = true;
		retry.disabled = true;
		view.message( __( 'Loading secure checkout…', 'sa-hosted-checkout-for-woocommerce' ) );
		try {
			const data = await post( root.dataset.ajaxUrl, {
				action: 'sahcfwc_get_embedded_session',
				security: root.dataset.security,
				order_id: root.dataset.orderId,
				order_key: root.dataset.orderKey,
			} );
			if ( data.checkout_mode === 'hosted' ) {
				await handleCheckout( data, retry );
				return;
			}
			isPopup = data.checkout_mode === 'embedded_popup';
			retry.textContent = isPopup ?
				__( 'Open secure checkout', 'sa-hosted-checkout-for-woocommerce' ) :
				__( 'Try again', 'sa-hosted-checkout-for-woocommerce' );
			if ( isPopup ) {
				view.message( __( 'Your secure checkout opens in a popup. You can reopen it below.', 'sa-hosted-checkout-for-woocommerce' ) );
				popup = popup || createPopup();
				await popup.open( data, retry );
			} else {
				await view.show( data );
			}
			retry.hidden = ! isPopup;
		} catch ( error ) {
			view.message( safeMessage( error.message ), true );
			retry.hidden = false;
		} finally {
			retry.disabled = false;
			pending = false;
		}
	}
	retry.addEventListener( 'click', load );
	load();
}

function initCheckoutLauncher( root ) {
	const status = root.querySelector( '.sahcfwc-launcher-status' );
	const retry = root.querySelector( '.sahcfwc-launcher-retry' );
	if ( ! status || ! retry || ! config.ajax ) {
		return;
	}
	let pending = false;
	async function start() {
		if ( pending || cartRequest ) {
			return;
		}
		pending = true;
		retry.disabled = true;
		retry.hidden = true;
		status.setAttribute( 'role', 'status' );
		status.textContent = __( 'Loading secure checkout…', 'sa-hosted-checkout-for-woocommerce' );
		try {
			cartRequest = post( config.ajax.url, { action: config.ajax.action, security: config.ajax.security } );
			const data = await cartRequest;
			await handleCheckout( data, retry );
			if ( data.checkout_mode === 'embedded_popup' ) {
				status.textContent = __( 'Your secure checkout opens in a popup. You can reopen it below.', 'sa-hosted-checkout-for-woocommerce' );
				retry.textContent = __( 'Open secure checkout', 'sa-hosted-checkout-for-woocommerce' );
				retry.hidden = false;
			}
		} catch ( error ) {
			status.setAttribute( 'role', 'alert' );
			status.textContent = safeMessage( error.message );
			retry.textContent = __( 'Try again', 'sa-hosted-checkout-for-woocommerce' );
			retry.hidden = false;
		} finally {
			cartRequest = null;
			pending = false;
			retry.disabled = false;
		}
	}
	retry.addEventListener( 'click', start );
	let started = false;
	function startWhenActive() {
		if ( started || document.prerendering || document.visibilityState === 'hidden' ) {
			return;
		}
		started = true;
		document.removeEventListener( 'visibilitychange', startWhenActive );
		document.removeEventListener( 'prerenderingchange', startWhenActive );
		start();
	}
	document.addEventListener( 'visibilitychange', startWhenActive );
	document.addEventListener( 'prerenderingchange', startWhenActive );
	startWhenActive();
}

function init() {
	if ( window.sahcfwcCheckoutInitialized ) {
		return;
	}
	window.sahcfwcCheckoutInitialized = true;
	initCart();
	document.querySelectorAll( '.sahcfwc-checkout-launcher' ).forEach( initCheckoutLauncher );
	document.querySelectorAll( '.sahcfwc-embedded-checkout' ).forEach( initPage );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init, { once: true } );
} else {
	init();
}

