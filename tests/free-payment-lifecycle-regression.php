<?php
/**
 * Offline FREE payment-lifecycle regression (WordPress CLI only).
 *
 * Run against the disposable local FREE site in Stripe test mode:
 *   wp --exec="if (!defined('WP_HTTP_BLOCK_EXTERNAL')) define('WP_HTTP_BLOCK_EXTERNAL', true);" eval-file wp-content/plugins/sa-hosted-checkout-for-woocommerce/tests/free-payment-lifecycle-regression.php
 * Optionally pass one case name after the filename. JSON is written to stdout.
 *
 * No Stripe credentials are needed: SDK transport, WordPress HTTP and mail are
 * intercepted. Fixtures are created in one transaction and always rolled back.
 * Run in a separate CLI process, not an interactive/shared PHP interpreter.
 * This tests installed WooCommerce; the legacy-hook case simulates absence of
 * Woo 11.0's failed-order stock hook, not an actual older Woo installation.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'WordPress CLI only.' );
}
$qa_host = wp_parse_url( home_url() );
if ( ! isset( $qa_host['host'], $qa_host['port'] ) || '127.0.0.1' !== $qa_host['host'] || 8888 !== (int) $qa_host['port']
	|| function_exists( 'sahcfwc_fs' ) || ! function_exists( 'WC' )
	|| 'test-mode' !== get_option( 'sahcfwc_stripe_integration_mode' )
	|| ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
	throw new RuntimeException( 'Requires the FREE local 127.0.0.1:8888 site in Stripe test mode with HTTP blocked at bootstrap.' );
}
require_once dirname( __DIR__ ) . '/includes/classes/class-sahcfwc-payment-completion.php';

use SAHCFWC\Classes\SAHCFWC_Payment_Completion as QA_Completion;
use SAHCFWC\Classes\SAHCFWC_Money as QA_Money;

class SAHCFWC_Test_Lifecycle_Redirect extends RuntimeException {}
class SAHCFWC_Test_Lifecycle_Session extends WC_Session {
	public function __construct() { $this->_customer_id = 'offline-lifecycle-' . wp_generate_uuid4(); }
	public function init() {}
	public function has_session() { return true; }
	public function set_customer_session_cookie( $set ) {}
	public function save_data( $old_session_key = 0 ) {}
	public function destroy_session() { $this->_data = array(); }
	public function get_session_cookie() { return false; }
}

class SAHCFWC_Test_Lifecycle_HTTP implements \SAHCFWC\Libraries\Stripe\HttpClient\ClientInterface {
	public $session = array();
	public $calls = 0;
	public $unavailable = false;
	public function request( $method, $url, $headers, $params, $has_file, $api_mode = 'v1' ) {
		++$this->calls;
		if ( $this->unavailable ) { throw new RuntimeException( 'Simulated offline transport failure.' ); }
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( 'get' !== strtolower( $method ) ) { throw new RuntimeException( 'Unexpected SDK write intercepted.' ); }
		if ( '/v1/checkout/sessions/' . $this->session['id'] === $path ) {
			$data = $this->session;
		} elseif ( '/v1/payment_intents/' . $this->session['payment_intent'] === $path ) {
			$data = array( 'id' => $this->session['payment_intent'], 'object' => 'payment_intent', 'metadata' => $this->session['metadata'], 'livemode' => false );
		} else { throw new RuntimeException( 'Unexpected SDK route intercepted.' ); }
		return array( wp_json_encode( $data ), 200, array() );
	}
}

class SAHCFWC_Test_Lifecycle {
	public static $transport;
	public static $client;
	public static $mail = 0;
	public static $payment_events = 0;
	public static $fixture_ids = array( 'products' => array(), 'coupons' => array(), 'orders' => array(), 'users' => array() );
	public static $checks = array();
	public static $options = array(
		'woocommerce_manage_stock' => 'yes', 'woocommerce_hold_stock_minutes' => '60',
		'woocommerce_calc_taxes' => 'no', 'woocommerce_currency' => 'USD',
		'sahcfwc_stripe_integration_mode' => 'test-mode', 'sahcfwc_api_key_type' => 'standard',
		'sahcfwc_stripe_test_secret_key' => 'sk_test_offline_fixture_not_a_real_key',
		'sahcfwc_stripe_live_secret_key' => '', 'sahcfwc_restricted_test_key' => '', 'sahcfwc_restricted_live_key' => '',
		'sahcfwc_stripe_webhook_key' => 'whsec_offline_fixture_not_a_real_secret',
	);
	public static function check( $name, $condition ) {
		self::$checks[ $name ] = (bool) $condition;
		if ( ! $condition ) { throw new RuntimeException( 'Assertion failed: ' . $name ); }
	}
	public static function fixture( $currency = 'USD', $downloadable = false, $customer_id = 0 ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Offline lifecycle fixture ' . wp_generate_uuid4() );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' ); $product->set_price( '10' );
		$product->set_virtual( $downloadable ); $product->set_downloadable( $downloadable );
		$product->set_tax_status( 'none' ); $product->set_manage_stock( true ); $product->set_stock_quantity( 10 );
		$product_id = $product->save(); self::$fixture_ids['products'][] = $product_id;
		$coupon = new WC_Coupon();
		$coupon->set_code( 'offline-lifecycle-' . wp_generate_uuid4() );
		$coupon->set_discount_type( 'fixed_cart' ); $coupon->set_amount( 2 ); $coupon->set_usage_limit( 10 );
		$coupon_id = $coupon->save(); self::$fixture_ids['coupons'][] = $coupon_id;
		$order = new WC_Order(); $order->set_currency( $currency ); $order->set_status( 'pending' );
		$order->set_customer_id( $customer_id );
		$order->set_payment_method( QA_Completion::GATEWAY ); $order->set_billing_email( 'offline-lifecycle@example.test' );
		$order->add_product( $product, 2 ); $order->calculate_totals( false );
		$order_id = $order->save(); self::$fixture_ids['orders'][] = $order_id;
		$applied = $order->apply_coupon( $coupon->get_code() );
		if ( is_wp_error( $applied ) ) { throw new RuntimeException( 'Could not apply the fixture coupon.' ); }
		$order->calculate_totals( false ); $order->save();
		$minor = QA_Money::to_minor( $order->get_total(), $currency );
		$session = array(
			'id' => 'cs_test_offline_' . $order_id, 'object' => 'checkout.session', 'mode' => 'payment',
			'livemode' => false, 'status' => 'complete', 'payment_status' => 'unpaid',
			'payment_intent' => 'pi_offline_' . $order_id, 'currency' => strtolower( $currency ), 'amount_total' => $minor,
			'metadata' => array( 'sahcfwc_order_id' => (string) $order_id, 'sahcfwc_order_key' => $order->get_order_key() ),
			'customer_details' => array( 'email' => 'offline-lifecycle@example.test', 'name' => 'Offline Fixture' ),
		);
		foreach ( array( 'sahcfwc_stripe_checkout_session_id' => $session['id'], 'sahcfwc_checkout_expected_amount' => $minor,
			'sahcfwc_checkout_currency' => strtolower( $currency ), 'sahcfwc_checkout_mode' => 'test' ) as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}
		$order->save(); wc_reserve_stock_for_order( $order );
		self::$transport->session = $session; self::$transport->unavailable = false;
		return array( 'order' => $order_id, 'product' => $product_id, 'coupon' => $coupon_id, 'total' => $order->get_total() );
	}
	public static function snapshot( $fixture ) {
		global $wpdb;
		$order = wc_get_order( $fixture['order'] );
		$coupon = new WC_Coupon( $fixture['coupon'] );
		return array(
			'status' => $order->get_status(), 'paid' => $order->is_paid(), 'total' => $order->get_total(),
			'stock' => (int) wc_get_product( $fixture['product'] )->get_stock_quantity(),
			'coupon_usage' => (int) $coupon->get_usage_count(), 'transaction' => '' !== $order->get_transaction_id(),
			'completed' => '' !== $order->get_meta( 'sahcfwc_completed_session_id', true ),
			'failed' => '' !== $order->get_meta( 'sahcfwc_failed_session_id', true ),
			'reserved' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(stock_quantity),0) FROM {$wpdb->prefix}wc_reserved_stock WHERE order_id=%d", $fixture['order'] ) ),
			'notes' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_type='order_note'", $fixture['order'] ) ),
			'mail' => self::$mail, 'payment_events' => self::$payment_events,
		);
	}
	public static function pending( $fixture ) {
		return QA_Completion::pending( $fixture['order'], self::$transport->session, self::$client );
	}
	public static function complete( $fixture ) {
		return QA_Completion::complete( $fixture['order'], self::$transport->session, self::$client );
	}
	public static function fail( $fixture ) {
		return QA_Completion::fail( $fixture['order'], self::$transport->session, self::$client );
	}
	public static function webhook( $type, $bad_signature = false, $event_overrides = array() ) {
		$event = array_merge( array( 'id' => 'evt_offline_' . wp_generate_uuid4(), 'object' => 'event', 'livemode' => false,
			'type' => $type, 'data' => array( 'object' => self::$transport->session ) ), $event_overrides );
		$body = wp_json_encode( $event ); $timestamp = time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, self::$options['sahcfwc_stripe_webhook_key'] );
		$request = new WP_REST_Request( 'POST', '/sahcfwc/v1/webhooks/stripe-listener' );
		$request->set_body( $body );
		$request->set_header( 'stripe-signature', 't=' . $timestamp . ',v1=' . ( $bad_signature ? str_repeat( '0', 64 ) : $signature ) );
		return rest_ensure_response( rest_do_request( $request ) )->get_status();
	}
	public static function held( $fixture ) {
		$state = self::snapshot( $fixture );
		self::check( 'awaiting_settlement_not_paid', 'on-hold' === $state['status'] && ! $state['paid'] && ! $state['transaction'] && ! $state['completed'] );
		self::check( 'on_hold_stock_once', 8 === $state['stock'] && 0 === $state['reserved'] );
		self::check( 'on_hold_coupon_once', 1 === $state['coupon_usage'] );
		self::check( 'financial_snapshot_unchanged', $fixture['total'] === $state['total'] );
		return $state;
	}
	public static function cart( $fixture ) {
		WC()->session = new SAHCFWC_Test_Lifecycle_Session();
		WC()->customer = new WC_Customer( 0, true );
		WC()->customer->set_billing_email( 'offline-lifecycle@example.test' );
		WC()->cart = new WC_Cart();
		$key = WC()->cart->add_to_cart( $fixture['product'], 2 );
		self::check( 'fixture_cart_created', (bool) $key );
		WC()->cart->calculate_totals();
		$order = wc_get_order( $fixture['order'] ); $order->set_cart_hash( WC()->cart->get_cart_hash() ); $order->save();
		WC()->session->set( 'order_awaiting_payment', $fixture['order'] );
		return $key;
	}
	public static function run( $case ) {
		$currency_case = in_array( $case, array( 'currency_usd', 'currency_eur', 'currency_jpy', 'currency_bhd' ), true );
		$currency = $currency_case ? strtoupper( substr( $case, 9 ) ) : 'USD';
		$customer_id = 0;
		if ( false !== strpos( $case, 'coupon_customer' ) ) {
			$customer_id = wp_insert_user( array( 'user_login' => 'offline-coupon-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'user_email' => 'offline-' . wp_generate_uuid4() . '@example.test', 'role' => 'customer' ) );
			if ( is_wp_error( $customer_id ) ) { throw new RuntimeException( 'Could not create the fixture customer.' ); }
			self::$fixture_ids['users'][] = $customer_id;
		}
		$fixture = self::fixture( $currency, 'downloadable_success' === $case, $customer_id );
		$before = self::snapshot( $fixture );
		$before_payment = self::$payment_events;
		if ( false !== strpos( $case, 'failed_coupon_' ) || 'paid_coupon_failure_ignored' === $case || 'unrecorded_coupon_failure' === $case ) {
			self::check( 'coupon_fixture_pending_accepted', self::pending( $fixture ) instanceof WC_Order );
			$order = wc_get_order( $fixture['order'] );
			$used_by = $order->get_user_id() ? (string) $order->get_user_id() : $order->get_billing_email();
			$coupon = new WC_Coupon( $fixture['coupon'] );
			// Other redemptions, including the same customer's earlier purchase,
			// must not be removed by this order's failed event or its replay.
			$coupon->increase_usage_count( $used_by );
			$coupon->increase_usage_count( 'another-offline-customer@example.test' );
			$usage = function () use ( $fixture, $used_by ) {
				$coupon = new WC_Coupon( $fixture['coupon'] ); $order = wc_get_order( $fixture['order'] );
				$used = array_map( 'strval', (array) get_post_meta( $fixture['coupon'], '_used_by' ) );
				return array( 'count' => (int) $coupon->get_usage_count(), 'same_customer' => count( array_keys( $used, $used_by, true ) ),
					'other_customer' => count( array_keys( $used, 'another-offline-customer@example.test', true ) ),
					'recorded' => (bool) $order->get_data_store()->get_recorded_coupon_usage_counts( $order ) );
			};
			self::check( 'three_coupon_uses_recorded', array( 'count' => 3, 'same_customer' => 2, 'other_customer' => 1, 'recorded' => true ) === $usage() );
			if ( 'paid_coupon_failure_ignored' === $case ) {
				self::$transport->session['payment_status'] = 'paid'; self::check( 'coupon_paid_fixture', self::complete( $fixture ) instanceof WC_Order );
				self::$transport->session['payment_status'] = 'unpaid';
			} elseif ( 'unrecorded_coupon_failure' === $case ) {
				// Simulate usage already restored, leaving only unrelated redemptions.
				$coupon->decrease_usage_count( $used_by ); $order->get_data_store()->set_recorded_coupon_usage_counts( $order, false );
			}
			$native_priority = has_action( 'woocommerce_order_status_failed', 'wc_update_coupon_usage_counts' );
			$legacy = 0 === strpos( $case, 'legacy_' );
			if ( $legacy && false !== $native_priority ) { remove_action( 'woocommerce_order_status_failed', 'wc_update_coupon_usage_counts', $native_priority ); }
			try {
				self::check( 'coupon_failure_accepted', self::fail( $fixture ) instanceof WC_Order );
				$expected = 'paid_coupon_failure_ignored' === $case
					? array( 'count' => 3, 'same_customer' => 2, 'other_customer' => 1, 'recorded' => true )
					: array( 'count' => 2, 'same_customer' => 1, 'other_customer' => 1, 'recorded' => false );
				self::check( 'coupon_usage_released_exactly_once_only_if_recorded_and_unpaid', $expected === $usage() );
				$first = self::snapshot( $fixture ); self::fail( $fixture );
				self::check( 'coupon_failure_replay_preserves_other_redemptions', $expected === $usage() && $first === self::snapshot( $fixture ) );
			} finally { if ( $legacy && false !== $native_priority ) { add_action( 'woocommerce_order_status_failed', 'wc_update_coupon_usage_counts', $native_priority ); } }
			return array( 'coupon_usage' => $usage(), 'after' => self::snapshot( $fixture ) );
		} elseif ( in_array( $case, array( 'pending_cart_cleanup', 'pending_changed_cart_preserved', 'pending_snapshot_retry', 'cancel_submitted', 'browser_pending_return' ), true ) ) {
			$key = self::cart( $fixture );
			$handler = new \SAHCFWC\Classes\SAHCFWC_Post_Checkout();
			$handler->sahcfwc_stripe_client = self::$client;
			try {
				if ( in_array( $case, array( 'cancel_submitted', 'browser_pending_return' ), true ) ) {
					$url = '';
					$redirect = function ( $target ) use ( &$url ) { $url = $target; throw new SAHCFWC_Test_Lifecycle_Redirect(); };
					add_filter( 'wp_redirect', $redirect, -99999 );
					try {
						if ( 'cancel_submitted' === $case ) { $handler->sahcfwc_stripe_cancel_checkout_order( $fixture['order'], self::$transport->session['id'] ); }
						else { $handler->sahcfwc_stripe_successful_checkout_order( $fixture['order'], self::$transport->session['id'] ); }
					} catch ( SAHCFWC_Test_Lifecycle_Redirect $stop ) { /* Capture, never exit the process. */ }
					finally { remove_filter( 'wp_redirect', $redirect, -99999 ); }
					self::check( 'submitted_payment_redirects_to_receipt', wc_get_order( $fixture['order'] )->get_checkout_order_received_url() === $url );
					self::held( $fixture );
					self::check( 'submitted_cart_cleared', WC()->cart->is_empty() && ! WC()->session->get( 'order_awaiting_payment' ) );
				} else {
					self::pending( $fixture ); self::held( $fixture );
					if ( 'pending_snapshot_retry' === $case ) {
						$builder = ( new ReflectionClass( '\SAHCFWC\Classes\SAHCFWC_Checkout_Button_Url_Ajax' ) )->newInstanceWithoutConstructor();
						$method = new ReflectionMethod( $builder, 'sahcfwc_get_cart_order_snapshot' ); $method->setAccessible( true );
						$calls = self::$transport->calls; $rejected = false;
						try { $method->invoke( $builder, WC()->cart ); }
						catch ( RuntimeException $error ) { $rejected = false !== strpos( $error->getMessage(), 'awaiting confirmation' ); }
						self::check( 'same_on_hold_cart_cannot_start_new_payment', $rejected && $calls === self::$transport->calls && $fixture['order'] === (int) WC()->session->get( 'order_awaiting_payment' ) );
					} else {
						if ( 'pending_changed_cart_preserved' === $case ) { WC()->cart->set_quantity( $key, 3 ); }
						$method = new ReflectionMethod( $handler, 'clear_order_cart' ); $method->setAccessible( true );
						$method->invoke( $handler, wc_get_order( $fixture['order'] ) );
						self::check( 'cart_cleanup_respects_snapshot', 'pending_changed_cart_preserved' === $case ? 3 === WC()->cart->get_cart_contents_count() : WC()->cart->is_empty() );
						self::check( 'old_payment_pointer_cleared', ! WC()->session->get( 'order_awaiting_payment' ) );
					}
				}
			} finally { WC()->cart = null; WC()->customer = null; WC()->session = null; }
		} elseif ( in_array( $case, array( 'pending_success', 'downloadable_success', 'pending_failure', 'legacy_failed_stock_hook', 'failure_then_success', 'signed_success', 'signed_failure' ), true ) ) {
			$signed = 0 === strpos( $case, 'signed_' );
			$result = $signed ? self::webhook( 'checkout.session.completed' ) : self::pending( $fixture );
			self::check( 'pending_accepted', $signed ? 200 === $result : $result instanceof WC_Order );
			$held = self::held( $fixture );
			$signed ? self::webhook( 'checkout.session.completed' ) : self::pending( $fixture );
			self::check( 'pending_replay_has_no_effects', $held === self::snapshot( $fixture ) );
			if ( in_array( $case, array( 'pending_failure', 'legacy_failed_stock_hook', 'failure_then_success', 'signed_failure' ), true ) ) {
				$priority = has_action( 'woocommerce_order_status_failed', 'wc_maybe_increase_stock_levels' );
				if ( 'legacy_failed_stock_hook' === $case && false !== $priority ) { remove_action( 'woocommerce_order_status_failed', 'wc_maybe_increase_stock_levels', $priority ); }
				try { $result = $signed ? self::webhook( 'checkout.session.async_payment_failed' ) : self::fail( $fixture ); }
				finally { if ( 'legacy_failed_stock_hook' === $case && false !== $priority ) { add_action( 'woocommerce_order_status_failed', 'wc_maybe_increase_stock_levels', $priority ); } }
				self::check( 'failure_accepted', $signed ? 200 === $result : $result instanceof WC_Order );
				$failed = self::snapshot( $fixture );
				self::check( 'failure_restores_inventory_and_coupon', 'failed' === $failed['status'] && 10 === $failed['stock'] && 0 === $failed['coupon_usage'] && 0 === $failed['reserved'] );
				self::check( 'failure_persisted_not_paid', $failed['failed'] && ! $failed['paid'] && ! $failed['completed'] && ! $failed['transaction'] );
				self::fail( $fixture ); self::pending( $fixture );
				if ( $signed ) { self::check( 'stale_submitted_event_acknowledged_after_failure', 200 === self::webhook( 'checkout.session.completed' ) ); }
				self::check( 'failure_replay_and_stale_pending_do_not_resurrect', $failed === self::snapshot( $fixture ) );
				if ( 'failure_then_success' !== $case ) { return array( 'before' => $before, 'after' => $failed ); }
			}
			self::$transport->session['payment_status'] = 'paid';
			$result = $signed ? self::webhook( 'checkout.session.async_payment_succeeded' ) : self::complete( $fixture );
			self::check( 'payment_accepted', $signed ? 200 === $result : $result instanceof WC_Order );
			$paid = self::snapshot( $fixture );
			self::check( 'paid_order_type_correct', ( 'downloadable_success' === $case ? 'completed' : 'processing' ) === $paid['status'] );
			self::check( 'paid_exactly_once', $paid['paid'] && $paid['transaction'] && $paid['completed'] && 1 === $paid['payment_events'] - $before_payment );
			self::check( 'paid_stock_and_coupon_once', 8 === $paid['stock'] && 1 === $paid['coupon_usage'] && 0 === $paid['reserved'] );
			$signed ? self::webhook( 'checkout.session.async_payment_succeeded' ) : self::complete( $fixture );
			self::$transport->session['payment_status'] = 'unpaid'; self::pending( $fixture ); self::fail( $fixture );
			self::check( 'paid_replays_no_demotion_or_email', $paid === self::snapshot( $fixture ) );
		} elseif ( 'complete_unpaid' === $case ) {
			$result = self::complete( $fixture );
			self::check( 'browser_unpaid_not_reported_paid', is_wp_error( $result ) && 'sahcfwc_payment_pending' === $result->get_error_code() );
			self::held( $fixture );
		} elseif ( 'paid_stale_unpaid' === $case ) {
			self::$transport->session['payment_status'] = 'paid';
			self::check( 'fixture_payment_completed', self::complete( $fixture ) instanceof WC_Order );
			$paid = self::snapshot( $fixture );
			self::$transport->session['payment_status'] = 'unpaid';
			$result = self::complete( $fixture );
			self::check( 'stale_unpaid_returns_existing_paid_order', $result instanceof WC_Order && $result->is_paid() );
			self::check( 'stale_unpaid_no_new_notice_or_order_effect', $paid === self::snapshot( $fixture ) );
		} elseif ( $currency_case ) {
			self::check( 'currency_pending_accepted', self::pending( $fixture ) instanceof WC_Order ); self::held( $fixture );
			self::$transport->session['payment_status'] = 'paid';
			self::check( 'currency_paid_accepted', self::complete( $fixture ) instanceof WC_Order && self::snapshot( $fixture )['paid'] );
		} elseif ( 0 === strpos( $case, 'closed_' ) ) {
			$status = substr( $case, 7 );
			$order = wc_get_order( $fixture['order'] ); $order->update_status( $status );
			$closed = self::snapshot( $fixture ); $result = self::pending( $fixture );
			self::check( 'closed_order_rejected', is_wp_error( $result ) && 'sahcfwc_order_closed' === $result->get_error_code() );
			self::check( 'closed_order_unchanged', $closed === self::snapshot( $fixture ) );
		} elseif ( in_array( $case, array( 'signed_bad_signature', 'signed_amount_mismatch', 'signed_event_mode', 'signed_unavailable', 'signed_open', 'signed_async_unpaid', 'signed_async_failed_open', 'signed_async_failed_expired' ), true ) ) {
			if ( 'signed_amount_mismatch' === $case ) { ++self::$transport->session['amount_total']; }
			if ( 'signed_unavailable' === $case ) { self::$transport->unavailable = true; }
			if ( in_array( $case, array( 'signed_open', 'signed_async_failed_open' ), true ) ) { self::$transport->session['status'] = 'open'; }
			if ( 'signed_async_failed_expired' === $case ) { self::$transport->session['status'] = 'expired'; }
			$calls = self::$transport->calls;
			$type = 'signed_async_unpaid' === $case ? 'checkout.session.async_payment_succeeded' : 'checkout.session.completed';
			if ( 0 === strpos( $case, 'signed_async_failed_' ) ) { $type = 'checkout.session.async_payment_failed'; }
			$status = self::webhook( $type, 'signed_bad_signature' === $case, 'signed_event_mode' === $case ? array( 'livemode' => true ) : array() );
			self::check( 'invalid_webhook_not_acknowledged', $status >= 400 );
			if ( 0 === strpos( $case, 'signed_async_failed_' ) ) { self::check( 'unconfirmed_failure_retryable', 503 === $status ); }
			if ( 'signed_bad_signature' === $case ) { self::check( 'bad_signature_prevents_api_call', $calls === self::$transport->calls ); }
			if ( 'signed_async_unpaid' === $case ) {
				self::check( 'async_unpaid_retryable', 503 === $status );
				self::check( 'async_unpaid_not_completed', ! self::snapshot( $fixture )['paid'] );
			} else { self::check( 'rejected_webhook_unchanged', $before === self::snapshot( $fixture ) ); }
		} elseif ( 'failure_releases_pending_reservation' === $case ) {
			self::check( 'fixture_has_reservation', 2 === $before['reserved'] ); self::fail( $fixture );
			$failed = self::snapshot( $fixture );
			self::check( 'pending_failure_releases_reservation_without_stock_increase', 0 === $failed['reserved'] && 10 === $failed['stock'] && 'failed' === $failed['status'] );
		} elseif ( 'lock_contention' === $case ) {
			global $wpdb;
			$other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$name = 'sahcfwc_' . md5( $wpdb->prefix . ':' . get_current_blog_id() . ':' . $fixture['order'] );
			try {
				self::check( 'separate_connection_lock_acquired', '1' === (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) );
				$result = self::pending( $fixture );
				self::check( 'concurrent_pending_retryable', is_wp_error( $result ) && 'sahcfwc_order_busy' === $result->get_error_code() && 503 === $result->get_error_data()['status'] );
				self::check( 'locked_order_unchanged', $before === self::snapshot( $fixture ) );
			} finally { $other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); $other->close(); }
		} else {
			$expected = null;
			switch ( $case ) {
				case 'open': case 'expired': self::$transport->session['status'] = $case; $expected = 'sahcfwc_payment_not_pending'; break;
				case 'already_paid_session': self::$transport->session['payment_status'] = 'paid'; $expected = 'sahcfwc_payment_not_pending'; break;
				case 'no_payment_required': self::$transport->session['payment_status'] = 'no_payment_required'; $expected = 'sahcfwc_payment_not_pending'; break;
				case 'missing_intent': self::$transport->session['payment_intent'] = ''; $expected = 'sahcfwc_missing_transaction'; break;
				case 'amount_mismatch': ++self::$transport->session['amount_total']; break;
				case 'currency_mismatch': self::$transport->session['currency'] = 'eur'; break;
				case 'livemode_mismatch': self::$transport->session['livemode'] = true; break;
				case 'session_mismatch': self::$transport->session['id'] = 'cs_test_unrelated'; break;
				case 'mode_mismatch': self::$transport->session['mode'] = 'setup'; break;
				case 'metadata_key_mismatch': self::$transport->session['metadata']['sahcfwc_order_key'] = 'wrong'; break;
				case 'metadata_order_mismatch': self::$transport->session['metadata']['sahcfwc_order_id'] = '0'; break;
				case 'other_gateway': $order = wc_get_order( $fixture['order'] ); $order->set_payment_method( 'cod' ); $order->save(); break;
				default: throw new RuntimeException( 'Unknown test case.' );
			}
			$result = self::pending( $fixture );
			self::check( 'invalid_pending_rejected', is_wp_error( $result ) && ( null === $expected || $expected === $result->get_error_code() ) );
			self::check( 'invalid_pending_no_effects', $before === self::snapshot( $fixture ) );
		}
		return array( 'before' => $before, 'after' => self::snapshot( $fixture ) );
	}
}

$qa_cases = array( 'pending_success', 'downloadable_success', 'pending_failure', 'legacy_failed_stock_hook', 'failure_then_success',
	'legacy_failed_coupon_guest', 'legacy_failed_coupon_customer', 'native_failed_coupon_guest', 'native_failed_coupon_customer', 'paid_coupon_failure_ignored', 'unrecorded_coupon_failure',
	'pending_cart_cleanup', 'pending_changed_cart_preserved', 'pending_snapshot_retry', 'cancel_submitted', 'browser_pending_return',
	'complete_unpaid', 'paid_stale_unpaid', 'currency_usd', 'currency_eur', 'currency_jpy', 'currency_bhd', 'closed_failed', 'closed_cancelled', 'closed_refunded',
	'signed_success', 'signed_failure', 'signed_bad_signature', 'signed_amount_mismatch', 'signed_event_mode', 'signed_unavailable', 'signed_open', 'signed_async_unpaid', 'signed_async_failed_open', 'signed_async_failed_expired',
	'failure_releases_pending_reservation', 'lock_contention', 'open', 'expired', 'already_paid_session', 'no_payment_required', 'missing_intent',
	'amount_mismatch', 'currency_mismatch', 'livemode_mismatch', 'session_mismatch', 'mode_mismatch', 'metadata_key_mismatch', 'metadata_order_mismatch', 'other_gateway' );
if ( ! empty( $args[0] ) ) {
	if ( ! in_array( $args[0], $qa_cases, true ) ) { throw new RuntimeException( 'Unknown case name.' ); }
	$qa_cases = array( $args[0] );
}
$qa_report = array( 'suite' => 'free-delayed-payment-lifecycle', 'network' => 'SDK mocked; WordPress HTTP and mail blocked', 'php' => PHP_VERSION,
	'woocommerce' => WC_VERSION, 'results' => array(), 'pass' => false );
$qa_sources = array( 'includes/classes/class-sahcfwc-payment-completion.php', 'includes/classes/class-sahcfwc-post-checkout.php', 'includes/webhooks/class-sahcfwc-stripe-listener.php', 'includes/traits/sahcfwc-order-totals.php' );
foreach ( $qa_sources as $qa_source ) { $qa_report['source_hashes'][ $qa_source ] = hash_file( 'sha256', dirname( __DIR__ ) . '/' . $qa_source ); }
global $wpdb;
$qa_active = false;
register_shutdown_function( function () use ( $wpdb, &$qa_active ) { if ( $qa_active ) { $wpdb->query( 'ROLLBACK' ); } } );
try {
	foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $qa_table ) {
		if ( 0 === strpos( $qa_table['Name'], $wpdb->prefix ) && 'InnoDB' !== $qa_table['Engine'] ) { throw new RuntimeException( 'All WordPress tables must use InnoDB for rollback safety.' ); }
	}
	if ( ! is_callable( array( 'SAHCFWC\\Classes\\SAHCFWC_Payment_Completion', 'pending' ) ) ) { throw new RuntimeException( 'Delayed-payment pending() implementation is required.' ); }
	add_filter( 'pre_http_request', function () { return new WP_Error( 'offline_fixture', 'External HTTP is blocked during this test.' ); }, PHP_INT_MAX );
	add_filter( 'pre_wp_mail', function () { ++SAHCFWC_Test_Lifecycle::$mail; return true; }, PHP_INT_MAX );
	add_filter( 'woocommerce_defer_transactional_emails', '__return_false', PHP_INT_MAX );
	add_action( 'woocommerce_payment_complete', function () { ++SAHCFWC_Test_Lifecycle::$payment_events; } );
	foreach ( SAHCFWC_Test_Lifecycle::$options as $qa_name => $qa_value ) {
		add_filter( 'pre_option_' . $qa_name, function () use ( $qa_name ) { return SAHCFWC_Test_Lifecycle::$options[ $qa_name ]; }, PHP_INT_MAX );
	}
	SAHCFWC_Test_Lifecycle::$transport = new SAHCFWC_Test_Lifecycle_HTTP();
	\SAHCFWC\Libraries\Stripe\ApiRequestor::setHttpClient( SAHCFWC_Test_Lifecycle::$transport );
	SAHCFWC_Test_Lifecycle::$client = new \SAHCFWC\Libraries\Stripe\StripeClient( 'sk_test_offline_fixture_not_a_real_key' );
	$wpdb->query( 'START TRANSACTION' ); $qa_active = true;
	wp_set_current_user( 0 ); WC()->cart = null; WC()->customer = null; WC()->session = null; WC()->mailer();
	foreach ( $qa_cases as $qa_case ) {
		SAHCFWC_Test_Lifecycle::$checks = array();
		try {
			$qa_result = SAHCFWC_Test_Lifecycle::run( $qa_case );
			$qa_result['pass'] = true;
		} catch ( Throwable $qa_error ) {
			// Do not print transport/credentials or arbitrary exception data.
			$qa_result = array( 'pass' => false, 'error_type' => get_class( $qa_error ), 'line' => $qa_error->getLine(),
				'assertion' => 0 === strpos( $qa_error->getMessage(), 'Assertion failed:' ) ? $qa_error->getMessage() : 'Fixture or implementation error; inspect the reported file and line.', 'file' => basename( $qa_error->getFile() ) );
		}
		$qa_result['checks'] = SAHCFWC_Test_Lifecycle::$checks;
		$qa_report['results'][ $qa_case ] = $qa_result;
	}
	$qa_report['pass'] = ! in_array( false, array_column( $qa_report['results'], 'pass' ), true );
} catch ( Throwable $qa_error ) {
	$qa_report['setup_error'] = get_class( $qa_error ) . ' at ' . basename( $qa_error->getFile() ) . ':' . $qa_error->getLine();
} finally {
	// No session/customer/queued Woo callbacks may persist fixture objects after rollback.
	remove_all_actions( 'shutdown' );
	if ( $qa_active ) { $wpdb->query( 'ROLLBACK' ); $qa_active = false; }
	$qa_report['rollback_rows'] = array( 'posts' => 0, 'orders' => 0, 'order_items' => 0, 'reserved_stock' => 0, 'users' => 0, 'usermeta' => 0 );
	foreach ( SAHCFWC_Test_Lifecycle::$fixture_ids['users'] as $qa_id ) {
		$qa_report['rollback_rows']['users'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID=%d", $qa_id ) );
		$qa_report['rollback_rows']['usermeta'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id=%d", $qa_id ) );
	}
	$qa_post_ids = array_merge( SAHCFWC_Test_Lifecycle::$fixture_ids['products'], SAHCFWC_Test_Lifecycle::$fixture_ids['coupons'], SAHCFWC_Test_Lifecycle::$fixture_ids['orders'] );
	foreach ( $qa_post_ids as $qa_id ) { $qa_report['rollback_rows']['posts'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID=%d", $qa_id ) ); }
	foreach ( SAHCFWC_Test_Lifecycle::$fixture_ids['orders'] as $qa_id ) {
		$qa_report['rollback_rows']['orders'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE id=%d", $qa_id ) );
		$qa_report['rollback_rows']['order_items'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id=%d", $qa_id ) );
		$qa_report['rollback_rows']['reserved_stock'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_reserved_stock WHERE order_id=%d", $qa_id ) );
	}
	$qa_report['rollback_clean'] = 0 === array_sum( $qa_report['rollback_rows'] );
	$qa_report['source_stable'] = true;
	foreach ( $qa_sources as $qa_source ) {
		if ( $qa_report['source_hashes'][ $qa_source ] !== hash_file( 'sha256', dirname( __DIR__ ) . '/' . $qa_source ) ) { $qa_report['source_stable'] = false; }
	}
	$qa_report['pass'] = $qa_report['pass'] && $qa_report['rollback_clean'] && $qa_report['source_stable'];
	echo wp_json_encode( $qa_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
}
if ( ! $qa_report['pass'] ) { WP_CLI::halt( 1 ); }
