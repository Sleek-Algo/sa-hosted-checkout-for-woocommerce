<?php
/** Local-only, offline adjustable Checkout creation/retry integration regression. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL
	|| function_exists( 'sahcfwc_fs' ) || 'http://127.0.0.1:8888' !== rtrim( home_url(), '/' ) ) {
	throw new RuntimeException( 'Requires the disposable local FREE site with external HTTP blocked.' );
}
require_once SAHCFWC_DIR . '/includes/classes/class-sahcfwc-payment-completion.php';
use SAHCFWC\Classes\SAHCFWC_Payment_Completion as SQB_Completion;
use SAHCFWC\Libraries\Stripe\ApiRequestor;
use SAHCFWC\Libraries\Stripe\StripeClient;
global $wpdb, $sqb_ids, $sqb_options, $sqb_base, $sqb_http, $sqb_results;
add_filter( 'pre_http_request', function () { return new WP_Error( 'sqb_offline', 'Offline regression.' ); }, PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

class SQB_Builder {
	use SAHCFWC\Traits\SAHCFWC_Order_Totals;
	use SAHCFWC\Traits\SAHCFWC_Helpers;
	public $sahcfwc_stripe_secret = 'sk_test_offline_quantity_fixture';
	public function create( $order ) {
		return $this->sahcfwc_create_order_checkout_session( $order, array( 'mode' => 'payment', 'success_url' => home_url(), 'cancel_url' => home_url() ) );
	}
}
class SQB_HTTP implements SAHCFWC\Libraries\Stripe\HttpClient\ClientInterface {
	public $sessions = array(); public $lines = array(); public $coupons = array(); public $cache = array(); public $requests = array();
	public $lose_response = false;
	public function request( $method, $url, $headers, $params, $has_file, $api_mode = 'v1', $max_network_retries = null ) {
		$method = strtolower( $method ); $path = wp_parse_url( $url, PHP_URL_PATH ); $key = '';
		foreach ( $headers as $name => $value ) {
			$header = is_string( $name ) ? $name . ': ' . $value : $value;
			if ( 0 === stripos( $header, 'Idempotency-Key: ' ) ) { $key = substr( $header, 17 ); }
		}
		$this->requests[] = array( 'method' => $method, 'path' => $path, 'params' => $params, 'key' => $key );
		if ( 'post' === $method && isset( $this->cache[ $path . $key ] ) ) {
			$cached = $this->cache[ $path . $key ]; sqb_assert( $cached['params'] === $params, 'Immutable request changed on retry.' );
			$data = '/v1/checkout/sessions' === $path ? $this->sessions[ $cached['id'] ] : $this->coupons[ $cached['id'] ];
		} elseif ( 'post' === $method && '/v1/coupons' === $path ) {
			$id = 'coupon_qb_' . ( count( $this->coupons ) + 1 ); $data = array_merge( array( 'id' => $id, 'object' => 'coupon' ), $params );
			$this->coupons[ $id ] = $data; $this->cache[ $path . $key ] = array( 'id' => $id, 'params' => $params );
		} elseif ( 'post' === $method && '/v1/checkout/sessions' === $path ) {
			if ( $params['expires_at'] < time() + 1800 ) {
				throw SAHCFWC\Libraries\Stripe\Exception\InvalidRequestException::factory( 'Expired request.', 400, null, array(), array(), null, 'expires_at' );
			}
			$id = 'cs_test_qb_' . ( count( $this->sessions ) + 1 ); $subtotal = 0; $lines = array();
			foreach ( $params['line_items'] as $index => $line ) {
				$price = $line['price_data']; $amount = $price['unit_amount'] * $line['quantity']; $subtotal += $amount;
				$lines[] = array( 'id' => 'li_' . $id . '_' . $index, 'object' => 'item', 'quantity' => $line['quantity'], 'currency' => $price['currency'],
					'amount_subtotal' => $amount, 'amount_total' => $amount, 'amount_tax' => 0, 'amount_discount' => 0,
					'price' => array( 'id' => 'price_' . $index, 'object' => 'price', 'unit_amount' => $price['unit_amount'], 'currency' => $price['currency'],
						'product' => array( 'id' => 'prod_' . $index, 'object' => 'product', 'metadata' => $price['product_data']['metadata'] ?? array() ) ) );
			}
			$discount = isset( $params['discounts'][0]['coupon'] ) ? $this->coupons[ $params['discounts'][0]['coupon'] ]['amount_off'] : 0;
			$shipping = $params['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] ?? 0;
			$data = array( 'id' => $id, 'object' => 'checkout.session', 'mode' => 'payment', 'status' => 'open', 'payment_status' => 'unpaid',
				'livemode' => false, 'currency' => $params['line_items'][0]['price_data']['currency'], 'amount_subtotal' => $subtotal,
				'amount_total' => $subtotal + $shipping - $discount, 'metadata' => $params['metadata'], 'expires_at' => $params['expires_at'],
				'total_details' => array( 'amount_discount' => $discount, 'amount_shipping' => $shipping, 'amount_tax' => 0 ),
				'url' => 'https://checkout.stripe.com/offline-only', 'payment_intent' => 'pi_offline_' . $id );
			$this->sessions[ $id ] = $data; $this->lines[ $id ] = $lines; $this->cache[ $path . $key ] = array( 'id' => $id, 'params' => $params );
			if ( $this->lose_response ) { $this->lose_response = false; throw SAHCFWC\Libraries\Stripe\Exception\ApiConnectionException::factory( 'Simulated lost response.' ); }
		} elseif ( 'get' === $method && preg_match( '#/checkout/sessions/([^/]+)/line_items$#', $path, $match ) ) {
			$lines = $this->lines[ $match[1] ]; $start = 0;
			if ( ! empty( $params['starting_after'] ) ) { foreach ( $lines as $index => $line ) { if ( $line['id'] === $params['starting_after'] ) { $start = $index + 1; } } }
			$page = array_slice( $lines, $start, 2 );
			$data = array( 'object' => 'list', 'data' => $page, 'has_more' => $start + count( $page ) < count( $lines ), 'url' => $path );
		} elseif ( 'get' === $method && isset( $this->sessions[ basename( $path ) ] ) ) { $data = $this->sessions[ basename( $path ) ];
		} elseif ( 'post' === $method && preg_match( '#/checkout/sessions/([^/]+)/expire$#', $path, $match ) ) {
			$this->sessions[ $match[1] ]['status'] = 'expired'; $data = $this->sessions[ $match[1] ];
		} else { throw new RuntimeException( 'Unexpected offline route: ' . $method . ' ' . $path ); }
		return array( wp_json_encode( $data ), 200, array() );
	}
	public function change( $id, $quantity, $status = 'open', $payment = 'unpaid' ) {
		$this->lines[ $id ][0]['quantity'] = $quantity;
		$this->lines[ $id ][0]['amount_subtotal'] = $quantity * $this->lines[ $id ][0]['price']['unit_amount'];
		$this->lines[ $id ][0]['amount_total'] = $this->lines[ $id ][0]['amount_subtotal'];
		$subtotal = array_sum( array_column( $this->lines[ $id ], 'amount_subtotal' ) );
		$this->sessions[ $id ]['amount_subtotal'] = $subtotal;
		$this->sessions[ $id ]['amount_total'] = $subtotal + $this->sessions[ $id ]['total_details']['amount_shipping'] - $this->sessions[ $id ]['total_details']['amount_discount'];
		$this->sessions[ $id ]['status'] = $status; $this->sessions[ $id ]['payment_status'] = $payment;
		return SAHCFWC\Libraries\Stripe\Checkout\Session::constructFrom( $this->sessions[ $id ] );
	}
	public function payload() { $requests = array_values( array_filter( $this->requests, function ( $r ) { return 'post' === $r['method'] && '/v1/checkout/sessions' === $r['path']; } ) ); return end( $requests )['params']; }
}
function sqb_assert( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function sqb_fixture( $discount = 4, $managed = true, $quantity = 2, $subtotal = 20 ) {
	global $sqb_ids;
	$product = new WC_Product_Simple(); $product->set_name( 'Offline quantity builder ' . wp_generate_uuid4() ); $product->set_status( 'publish' );
	$product->set_regular_price( '10' ); $product->set_price( '10' ); $product->set_tax_status( 'none' ); $product->set_manage_stock( $managed );
	if ( $managed ) { $product->set_stock_quantity( 50 ); } $product->save(); $sqb_ids['products'][] = $product->get_id();
	$order = new WC_Order(); $order->set_status( 'pending' ); $order->set_currency( 'USD' ); $order->set_payment_method( 'sahcfwc_stripe_checkout' );
	$order->add_product( $product, $quantity, array( 'subtotal' => $subtotal, 'total' => $subtotal - $discount ) );
	$fee = new WC_Order_Item_Fee(); $fee->set_name( 'Handling' ); $fee->set_total( 2 ); $order->add_item( $fee );
	$shipping = new WC_Order_Item_Shipping(); $shipping->set_method_title( 'Fixed shipping' ); $shipping->set_method_id( 'flat_rate' ); $shipping->set_total( 3 ); $order->add_item( $shipping );
	if ( $discount ) { $coupon = new WC_Order_Item_Coupon(); $coupon->set_code( 'frozen-quantity-coupon' ); $coupon->set_discount( $discount ); $order->add_item( $coupon ); }
	$order->calculate_totals( false ); $order->save(); $sqb_ids['orders'][] = $order->get_id(); return array( $order, $product );
}
function sqb_case( $name, $callback ) {
	global $sqb_results, $sqb_http, $sqb_options, $sqb_base;
	$sqb_options = $sqb_base; $sqb_http = new SQB_HTTP(); ApiRequestor::setHttpClient( $sqb_http );
	try { $callback(); $sqb_results[ $name ] = array( 'pass' => true ); }
	catch ( Throwable $e ) { $sqb_results[ $name ] = array( 'pass' => false, 'error' => $e->getMessage() ); }
}
$sqb_options = $sqb_base = array( 'sahcfwc_stripe_adjustment_quantity' => 'yes', 'sahcfwc_stripe_quantity_maximum' => 10,
	'sahcfwc_stripe_payment_method_status' => 'automatic', 'sahcfwc_stripe_locale_status' => 'default',
	'sahcfwc_api_key_type' => 'standard', 'sahcfwc_stripe_test_secret_key' => 'sk_test_offline_quantity_fixture',
	'sahcfwc_stripe_webhook_key' => 'whsec_offline_quantity_fixture', 'woocommerce_manage_stock' => 'yes', 'woocommerce_calc_taxes' => 'no' );
$sqb_filters = array(); foreach ( $sqb_options as $name => $value ) { $sqb_filters[ $name ] = function () use ( $name ) { global $sqb_options; return $sqb_options[ $name ]; }; add_filter( 'pre_option_' . $name, $sqb_filters[ $name ], PHP_INT_MAX ); }
$sqb_results = array(); $sqb_ids = array( 'products' => array(), 'orders' => array() ); $sqb_transaction = false; $sqb_builder = new SQB_Builder();
$sqb_warnings = array(); set_error_handler( function ( $severity, $message ) use ( &$sqb_warnings ) { if ( error_reporting() & $severity ) { $sqb_warnings[] = $message; } return false; } );
register_shutdown_function( function () use ( &$sqb_transaction, $wpdb ) { if ( $sqb_transaction ) { $wpdb->query( 'ROLLBACK' ); } } );
try {
	foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) { if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && in_array( $table['Engine'], array( 'MyISAM', 'MEMORY' ), true ) ) { throw new RuntimeException( 'InnoDB-only test database required.' ); } }
	$wpdb->query( 'START TRANSACTION' ); $sqb_transaction = true;
	sqb_case( 'disabled_preserves_previous_net_price_payload', function () use ( $sqb_builder ) {
		global $sqb_options, $sqb_http; $sqb_options['sahcfwc_stripe_adjustment_quantity'] = 'no'; list( $order ) = sqb_fixture(); $sqb_builder->create( $order ); $p = $sqb_http->payload();
		sqb_assert( 800 === $p['line_items'][0]['price_data']['unit_amount'] && empty( $p['line_items'][0]['adjustable_quantity'] ) && empty( $p['discounts'] ), 'Disabled checkout behavior changed.' );
	} );
	sqb_case( 'gross_price_fixed_coupon_and_bounded_maximum', function () use ( $sqb_builder ) {
		global $sqb_http; list( $order ) = sqb_fixture(); $session = $sqb_builder->create( $order ); $p = $sqb_http->payload(); $stored = wc_get_order( $order->get_id() );
		// The SDK serializes booleans to form-encoded strings before transport.
		sqb_assert( 1000 === $p['line_items'][0]['price_data']['unit_amount'] && array( 'enabled' => 'true', 'minimum' => 1, 'maximum' => 10 ) === $p['line_items'][0]['adjustable_quantity'], 'Quantity payload wrong.' );
		sqb_assert( 400 === $sqb_http->coupons[ $p['discounts'][0]['coupon'] ]['amount_off'] && 2100 === $session->amount_total, 'Original coupon or initial total changed.' );
		sqb_assert( 1 === $stored->get_meta( 'sahcfwc_quantity_contract' )['version'], 'Contract missing.' );
	} );
	sqb_case( 'changed_open_session_reused_without_order_mutation', function () use ( $sqb_builder ) {
		global $sqb_http; list( $order ) = sqb_fixture(); $session = $sqb_builder->create( $order ); $sqb_http->change( $session->id, 3 ); $retry = $sqb_builder->create( wc_get_order( $order->get_id() ) );
		sqb_assert( $session->id === $retry->id && 3100 === $retry->amount_total && '21.00' === wc_get_order( $order->get_id() )->get_total() && 1 === count( $sqb_http->sessions ), 'Open retry expired or mutated an edited checkout.' );
	} );
	sqb_case( 'saved_contract_survives_admin_disable_and_limit_change', function () use ( $sqb_builder ) {
		global $sqb_options, $sqb_http; list( $order ) = sqb_fixture(); $s = $sqb_builder->create( $order ); $sqb_http->change( $s->id, 4 ); $sqb_options['sahcfwc_stripe_adjustment_quantity'] = 'no'; $sqb_options['sahcfwc_stripe_quantity_maximum'] = 1;
		sqb_assert( 4100 === $sqb_builder->create( wc_get_order( $order->get_id() ) )->amount_total, 'Current settings altered an existing contract.' );
	} );
	sqb_case( 'lost_response_replayed_after_settings_change', function () use ( $sqb_builder ) {
		global $sqb_options, $sqb_http; list( $order ) = sqb_fixture(); $sqb_http->lose_response = true;
		try { $sqb_builder->create( $order ); throw new RuntimeException( 'Expected connection error.' ); } catch ( SAHCFWC\Libraries\Stripe\Exception\ApiConnectionException $e ) {}
		$sqb_options['sahcfwc_stripe_adjustment_quantity'] = 'no'; $sqb_options['sahcfwc_stripe_locale_status'] = 'custom'; $sqb_options['sahcfwc_stripe_locale'] = 'fr';
		$s = $sqb_builder->create( wc_get_order( $order->get_id() ) ); sqb_assert( 1 === count( $sqb_http->sessions ) && 2100 === $s->amount_total, 'Unknown request created a second checkout.' );
	} );
	sqb_case( 'confirmed_expired_session_gets_fresh_contract', function () use ( $sqb_builder ) {
		global $sqb_http; list( $order ) = sqb_fixture(); $s = $sqb_builder->create( $order ); $sqb_http->sessions[ $s->id ]['status'] = 'expired'; $sqb_http->change( $s->id, 3, 'expired' );
		$next = $sqb_builder->create( wc_get_order( $order->get_id() ) ); sqb_assert( $next->id !== $s->id && 2100 === $next->amount_total, 'Expired edited checkout not safely replaced.' );
	} );
	sqb_case( 'enabling_after_uncertain_standard_checkout_does_not_duplicate', function () use ( $sqb_builder ) {
		global $sqb_options, $sqb_http; $sqb_options['sahcfwc_stripe_adjustment_quantity'] = 'no'; list( $order ) = sqb_fixture( 0, false ); $sqb_http->lose_response = true;
		try { $sqb_builder->create( $order ); } catch ( SAHCFWC\Libraries\Stripe\Exception\ApiConnectionException $e ) {}
		$sqb_options['sahcfwc_stripe_adjustment_quantity'] = 'yes'; $blocked = false;
		try { $sqb_builder->create( wc_get_order( $order->get_id() ) ); } catch ( RuntimeException $e ) { $blocked = false !== strpos( $e->getMessage(), 'settings changed' ); }
		sqb_assert( $blocked && 1 === count( $sqb_http->sessions ), 'Enabling quantities duplicated an uncertain standard checkout.' );
	} );
	sqb_case( 'paid_quantity_increase_syncs_fixed_coupon_and_actual_stock', function () use ( $sqb_builder ) {
		global $sqb_http; list( $order, $product ) = sqb_fixture(); $s = $sqb_builder->create( $order ); $final = $sqb_http->change( $s->id, 3, 'complete', 'paid' );
		$result = SQB_Completion::complete( $order->get_id(), $final, new StripeClient( 'sk_test_offline_quantity_fixture' ) );
		sqb_assert( ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : 'Finalize failed.' );
		$stored = wc_get_order( $order->get_id() ); $item = current( $stored->get_items( 'line_item' ) );
		sqb_assert( 3 === (int) $item->get_quantity() && 30.0 === (float) $item->get_subtotal() && 26.0 === (float) $item->get_total() && 31.0 === (float) $stored->get_total() && 4.0 === (float) $stored->get_discount_total(), 'Final quantities or frozen coupon totals wrong.' );
		sqb_assert( 47 === wc_get_product( $product->get_id() )->get_stock_quantity(), 'Actual purchased stock was not deducted exactly once.' );
		$again = SQB_Completion::complete( $order->get_id(), $final, new StripeClient( 'sk_test_offline_quantity_fixture' ) ); sqb_assert( ! is_wp_error( $again ) && 47 === wc_get_product( $product->get_id() )->get_stock_quantity(), 'Replay changed stock.' );
	} );
	sqb_case( 'split_price_product_remains_fixed', function () use ( $sqb_builder ) {
		global $sqb_http; list( $order ) = sqb_fixture( 0, false, 3, 10 ); $sqb_builder->create( $order );
		foreach ( $sqb_http->payload()['line_items'] as $line ) { sqb_assert( empty( $line['adjustable_quantity'] ), 'A rounding split became independently adjustable.' ); }
	} );
	sqb_case( 'discount_minimum_prevents_negative_item_total', function () use ( $sqb_builder ) {
		global $sqb_http; list( $order ) = sqb_fixture( 15 ); $sqb_builder->create( $order ); sqb_assert( 2 === $sqb_http->payload()['line_items'][0]['adjustable_quantity']['minimum'], 'Unsafe discounted minimum.' );
	} );
	sqb_case( 'webhook_required_before_stripe_request', function () use ( $sqb_builder ) {
		global $sqb_http, $sqb_options; $sqb_options['sahcfwc_stripe_webhook_key'] = ''; list( $order ) = sqb_fixture(); $blocked = false;
		try { $sqb_builder->create( $order ); } catch ( RuntimeException $e ) { $blocked = false !== strpos( $e->getMessage(), 'webhook' ); }
		sqb_assert( $blocked && ! $sqb_http->requests, 'Unreliable adjustable checkout created without webhook configuration.' );
	} );
} catch ( Throwable $e ) { $sqb_results['suite_error'] = array( 'pass' => false, 'error' => $e->getMessage() ); }
finally {
	if ( $sqb_transaction ) { $wpdb->query( 'ROLLBACK' ); $sqb_transaction = false; }
	foreach ( $sqb_filters as $name => $callback ) { remove_filter( 'pre_option_' . $name, $callback, PHP_INT_MAX ); }
	restore_error_handler();
}
$remaining = 0; foreach ( array_merge( $sqb_ids['products'], $sqb_ids['orders'] ) as $id ) { $remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID=%d", $id ) ); }
foreach ( $sqb_ids['orders'] as $id ) { $remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE id=%d", $id ) ); $remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_reserved_stock WHERE order_id=%d", $id ) ); }
$sqb_results['fixture_rollback'] = array( 'pass' => 0 === $remaining, 'remaining_rows' => $remaining );
$sqb_results['no_php_warnings'] = array( 'pass' => ! $sqb_warnings, 'warnings' => $sqb_warnings );
$pass = ! in_array( false, array_column( $sqb_results, 'pass' ), true );
echo wp_json_encode( array( 'suite' => 'free-quantity-builder', 'pass' => $pass, 'total' => count( $sqb_results ), 'results' => $sqb_results ), JSON_PRETTY_PRINT ) . PHP_EOL;
if ( ! $pass ) { WP_CLI::halt( 1 ); }
