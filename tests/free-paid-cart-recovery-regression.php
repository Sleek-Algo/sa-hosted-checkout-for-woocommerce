<?php
/** Offline paid-cart/AJAX recovery regression; exact installed FREE code, rollback fixtures. */
use SAHCFWC\Classes\SAHCFWC_Checkout_Button_Url_Ajax as Paid_Ajax;
use SAHCFWC\Classes\SAHCFWC_Payment_Completion as Paid_Completion;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! WP_HTTP_BLOCK_EXTERNAL || function_exists( 'sahcfwc_fs' )
	|| 'http://127.0.0.1:8888' !== rtrim( home_url(), '/' ) ) { throw new RuntimeException( 'Offline local FREE fixture required.' ); }
require_once SAHCFWC_DIR . '/includes/classes/class-sahcfwc-payment-completion.php';
add_filter( 'pre_http_request', function () { return new WP_Error( 'paid_offline', 'Offline regression.' ); }, PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
class Paid_Memory_Session extends WC_Session {
	public function __construct() { $this->_customer_id = 'offline-paid-recovery'; }
	public function init() {}
	public function save_data() {}
	public function has_session() { return true; }
	public function set_customer_session_cookie( $set ) {}
}
class Paid_Builder {
	use SAHCFWC\Traits\SAHCFWC_Order_Totals;
	public function snapshot( $cart ) { return $this->sahcfwc_get_cart_order_snapshot( $cart ); }
}
class Paid_Offline_HTTP implements SAHCFWC\Libraries\Stripe\HttpClient\ClientInterface {
	public $requests = 0;
	public function request( $method, $url, $headers, $params, $has_file, $api_mode = 'v1', $max_network_retries = null ) { $this->requests++; throw new RuntimeException( 'Recovery must not contact Stripe.' ); }
}
function paid_expect( $value, $message ) { global $paid_assertions; $paid_assertions++; if ( ! $value ) { throw new RuntimeException( $message ); } }
function paid_order_count() { return (int) wc_get_orders( array( 'limit' => 1, 'paginate' => true, 'type' => 'shop_order', 'return' => 'ids' ) )->total; }
function paid_fixture( $limited = false, $fee = false ) {
	global $paid_ids, $paid_fee_callback;
	if ( $paid_fee_callback ) { remove_action( 'woocommerce_cart_calculate_fees', $paid_fee_callback ); $paid_fee_callback = null; }
	WC()->session = new Paid_Memory_Session(); WC()->cart->set_cart_contents( array() ); WC()->cart->set_applied_coupons( array() ); WC()->cart->fees_api()->remove_all_fees();
	$product = new WC_Product_Simple(); $product->set_name( 'Offline paid recovery ' . count( $paid_ids ) ); $product->set_status( 'publish' ); $product->set_regular_price( '20' ); $product->set_price( '20' ); $product->set_virtual( true ); $product->set_tax_status( 'none' );
	if ( $limited ) { $product->set_manage_stock( true ); $product->set_stock_quantity( 1 ); }
	$product->save(); $paid_ids[] = $product->get_id();
	paid_expect( (bool) WC()->cart->add_to_cart( $product->get_id(), 1 ), 'Cart fixture could not be created.' );
	$coupon = null;
	if ( $limited ) {
		$coupon = new WC_Coupon(); $coupon->set_code( 'paid-recovery-' . $product->get_id() ); $coupon->set_discount_type( 'fixed_cart' ); $coupon->set_amount( 2 ); $coupon->set_usage_limit( 1 ); $coupon->save(); $paid_ids[] = $coupon->get_id(); WC()->cart->apply_coupon( $coupon->get_code() );
	}
	if ( $fee ) { $paid_fee_callback = function ( $cart ) { $cart->add_fee( 'Offline packaging', 3, false ); }; add_action( 'woocommerce_cart_calculate_fees', $paid_fee_callback ); }
	WC()->cart->calculate_totals();
	$order = ( new Paid_Builder() )->snapshot( WC()->cart ); $paid_ids[] = $order->get_id();
	$session_id = 'cs_test_paid_recovery_' . $order->get_id();
	foreach ( array( 'sahcfwc_stripe_checkout_session_id' => $session_id, 'sahcfwc_checkout_expected_amount' => (int) round( 100 * (float) $order->get_total() ), 'sahcfwc_checkout_currency' => 'usd', 'sahcfwc_checkout_mode' => 'test' ) as $key => $value ) { $order->update_meta_data( $key, $value ); }
	$order->save();
	$session = array( 'id' => $session_id, 'object' => 'checkout.session', 'status' => 'complete', 'payment_status' => 'paid', 'mode' => 'payment', 'livemode' => false,
		'currency' => 'usd', 'amount_total' => (int) round( 100 * (float) $order->get_total() ), 'payment_intent' => 'pi_offline_paid_' . $order->get_id(),
		'metadata' => array( 'sahcfwc_order_id' => (string) $order->get_id(), 'sahcfwc_order_key' => $order->get_order_key() ) );
	$shopper_session = WC()->session; WC()->session = new Paid_Memory_Session();
	try { $result = Paid_Completion::complete( $order->get_id(), $session, new stdClass() ); }
	finally { WC()->session = $shopper_session; }
	paid_expect( ! is_wp_error( $result ) && $result->is_paid(), 'Verified webhook-shaped completion failed.' );
	paid_expect( (int) WC()->session->get( 'order_awaiting_payment' ) === $order->get_id(), 'Webhook cleared the independent shopper session.' );
	return array( 'order' => wc_get_order( $order->get_id() ), 'product' => $product, 'coupon' => $coupon );
}
function paid_response() { return ( new Paid_Ajax() )->sahcfwc_strip_checkout_updated_url_handler(); }
function paid_case( $name, $callback ) {
	global $paid_results, $paid_http, $paid_fee_callback;
	wp_set_current_user( 0 ); WC()->customer = new WC_Customer( 0, true ); WC()->customer->set_billing_country( 'US' ); WC()->customer->set_billing_email( 'paid-recovery@example.test' ); wc_clear_notices();
	$paid_http = new Paid_Offline_HTTP(); SAHCFWC\Libraries\Stripe\ApiRequestor::setHttpClient( $paid_http );
	try { $callback(); paid_expect( 0 === $paid_http->requests, 'A recovery case contacted Stripe.' ); $paid_results[ $name ] = array( 'pass' => true ); }
	catch ( Throwable $error ) { $paid_results[ $name ] = array( 'pass' => false, 'error' => $error->getMessage() ); }
	finally { if ( $paid_fee_callback ) { remove_action( 'woocommerce_cart_calculate_fees', $paid_fee_callback ); $paid_fee_callback = null; } }
}
global $wpdb, $paid_ids, $paid_user_ids, $paid_assertions, $paid_results, $paid_http, $paid_fee_callback;
$paid_ids = array(); $paid_user_ids = array(); $paid_assertions = 0; $paid_results = array(); $paid_fee_callback = null; $paid_warnings = array();
$original = array( 'session' => WC()->session, 'customer' => WC()->customer, 'cart' => WC()->cart, 'user' => get_current_user_id() );
$options = array( 'sahcfwc_stripe_checkout_status' => 'yes', 'sahcfwc_stripe_test_secret_key' => '', 'sahcfwc_restricted_test_key' => '', 'sahcfwc_stripe_integration_mode' => 'test-mode', 'sahcfwc_api_key_type' => 'standard', 'woocommerce_currency' => 'USD', 'woocommerce_calc_taxes' => 'no', 'woocommerce_enable_coupons' => 'yes', 'woocommerce_default_country' => 'US:CA' );
$filters = array(); foreach ( $options as $name => $value ) { $filters[ $name ] = function () use ( $value ) { return $value; }; add_filter( 'pre_option_' . $name, $filters[ $name ], PHP_INT_MAX ); }
set_error_handler( function ( $level, $message ) use ( &$paid_warnings ) { if ( error_reporting() & $level ) { $paid_warnings[] = $message; } return true; } );
$wpdb->query( 'START TRANSACTION' );
try {
	WC()->session = new Paid_Memory_Session(); WC()->customer = new WC_Customer( 0, true ); WC()->cart = new WC_Cart();
	paid_case( 'guest_paid_reopen_returns_receipt_without_api_keys_or_new_order', function () {
		$fixture = paid_fixture(); $before = paid_order_count(); $response = paid_response();
		paid_expect( 'success' === $response['status'] && 'hosted' === $response['checkout_mode'], 'Paid reopen did not select receipt redirect.' );
		paid_expect( $fixture['order']->get_checkout_order_received_url() === $response['stripe_checkout_session_url'], 'Wrong receipt/order key.' );
		paid_expect( wp_parse_url( home_url(), PHP_URL_HOST ) === wp_parse_url( $response['stripe_checkout_session_url'], PHP_URL_HOST ), 'Receipt redirect is not same-origin.' );
		paid_expect( $before === paid_order_count(), 'Paid reopen created a new order.' );
		wc_clear_cart_after_payment(); paid_expect( WC()->cart->is_empty() && ! WC()->session->get( 'order_awaiting_payment' ), 'Normal receipt-page cleanup did not clear the stale cart/pointer.' );
	} );
	paid_case( 'verified_paid_snapshot_reuses_exact_order', function () {
		$fixture = paid_fixture(); $reused = ( new Paid_Builder() )->snapshot( WC()->cart );
		paid_expect( $fixture['order']->get_id() === $reused->get_id(), 'Snapshot method created a duplicate order.' );
	} );
	paid_case( 'identical_deliberate_purchase_after_receipt_cleanup_is_allowed', function () {
		global $paid_ids; $fixture = paid_fixture(); $first_id = $fixture['order']->get_id();
		wc_clear_cart_after_payment();
		paid_expect( WC()->cart->is_empty() && ! WC()->session->get( 'order_awaiting_payment' ), 'Receipt cleanup did not remove stale payment context.' );
		paid_expect( (bool) WC()->cart->add_to_cart( $fixture['product']->get_id(), 1 ), 'Same product could not be intentionally added again.' );
		WC()->cart->calculate_totals(); $next = ( new Paid_Builder() )->snapshot( WC()->cart ); $paid_ids[] = $next->get_id();
		paid_expect( $next->get_id() !== $first_id && $next->needs_payment(), 'Intentional identical repeat purchase was incorrectly blocked.' );
	} );
	paid_case( 'receipt_redirect_rejects_foreign_origin', function () {
		$fixture = paid_fixture(); $before = paid_order_count();
		$filter = function () { return 'https://untrusted.example/receipt'; }; add_filter( 'woocommerce_get_checkout_order_received_url', $filter );
		try { $response = paid_response(); } finally { remove_filter( 'woocommerce_get_checkout_order_received_url', $filter ); }
		paid_expect( 'failed' === $response['status'] && '' === $response['stripe_checkout_session_url'] && $before === paid_order_count(), 'Foreign-origin receipt redirect was accepted.' );
	} );
	paid_case( 'limited_coupon_and_last_stock_recover_before_revalidation', function () {
		$fixture = paid_fixture( true ); $before = paid_order_count(); $calculations = 0;
		paid_expect( 0 === (int) wc_get_product( $fixture['product']->get_id() )->get_stock_quantity(), 'Payment did not consume the last stock.' );
		paid_expect( 1 === (int) ( new WC_Coupon( $fixture['coupon']->get_id() ) )->get_usage_count(), 'Payment did not consume the coupon use.' );
		$counter = function () use ( &$calculations ) { $calculations++; }; add_action( 'woocommerce_before_calculate_totals', $counter );
		try { $response = paid_response(); } finally { remove_action( 'woocommerce_before_calculate_totals', $counter ); }
		paid_expect( 'success' === $response['status'] && 0 === $calculations && $before === paid_order_count(), 'Recovery revalidated/recalculated consumed cart or made another order.' );
	} );
	paid_case( 'genuinely_changed_cart_allows_new_order', function () {
		global $paid_ids; $fixture = paid_fixture(); $key = key( WC()->cart->get_cart() ); WC()->cart->set_quantity( $key, 2 );
		$next = ( new Paid_Builder() )->snapshot( WC()->cart ); $paid_ids[] = $next->get_id();
		paid_expect( $next->get_id() !== $fixture['order']->get_id() && $next->needs_payment(), 'Changed cart was incorrectly treated as already paid.' );
	} );
	foreach ( array( 'wrong_customer', 'wrong_gateway' ) as $kind ) {
		paid_case( $kind . '_cannot_receive_paid_order', function () use ( $kind ) {
			global $paid_ids; $fixture = paid_fixture();
			if ( 'wrong_customer' === $kind ) { $fixture['order']->set_customer_id( 987654321 ); } else { $fixture['order']->set_payment_method( 'cod' ); }
			$fixture['order']->save(); $next = ( new Paid_Builder() )->snapshot( WC()->cart ); $paid_ids[] = $next->get_id();
			paid_expect( $next->get_id() !== $fixture['order']->get_id() && $next->needs_payment(), 'Foreign paid order was exposed/reused.' );
		} );
	}
	foreach ( array( 'missing_marker', 'mismatched_marker', 'missing_snapshot', 'mismatched_snapshot' ) as $kind ) {
		paid_case( $kind . '_blocks_duplicate_payment', function () use ( $kind ) {
			$fixture = paid_fixture(); $before = paid_order_count();
			if ( 'missing_marker' === $kind ) { $fixture['order']->delete_meta_data( 'sahcfwc_completed_session_id' ); }
			elseif ( 'mismatched_marker' === $kind ) { $fixture['order']->update_meta_data( 'sahcfwc_completed_session_id', 'cs_test_other_fixture' ); }
			elseif ( 'missing_snapshot' === $kind ) { $fixture['order']->delete_meta_data( 'sahcfwc_cart_snapshot_hash' ); }
			else { $fixture['order']->update_meta_data( 'sahcfwc_cart_snapshot_hash', str_repeat( '0', 64 ) ); }
			$fixture['order']->save(); $response = paid_response();
			paid_expect( 'failed' === $response['status'] && false !== strpos( $response['message'], 'already been received' ) && '' === $response['stripe_checkout_session_url'], 'Unverified stale paid cart was not safely blocked.' );
			paid_expect( $before === paid_order_count(), 'Blocked paid cart created another order.' );
		} );
	}
	paid_case( 'missing_fee_objects_after_session_restore_fail_closed_without_recalculation', function () {
		$fixture = paid_fixture( false, true ); $before = paid_order_count(); $raw_hash = WC()->cart->get_cart_hash(); WC()->cart->fees_api()->remove_all_fees();
		paid_expect( $raw_hash === WC()->cart->get_cart_hash(), 'Fee-object absence unexpectedly changed stored raw cart hash.' );
		$response = paid_response();
		paid_expect( 'failed' === $response['status'] && false !== strpos( $response['message'], 'already been received' ) && $before === paid_order_count(), 'Missing fee snapshot created a duplicate payment instead of safe fallback.' );
	} );
	paid_case( 'registered_owner_recovers_own_receipt', function () {
		global $paid_user_ids; $user = wp_insert_user( array( 'user_login' => 'paid-recovery-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'registered-recovery@example.test', 'role' => 'customer' ) );
		paid_expect( ! is_wp_error( $user ), 'Registered fixture could not be created.' ); $paid_user_ids[] = $user; wp_set_current_user( $user ); WC()->customer = new WC_Customer( $user, true );
		$fixture = paid_fixture(); $response = paid_response();
		paid_expect( $fixture['order']->get_customer_id() === $user && 'success' === $response['status'] && $fixture['order']->get_checkout_order_received_url() === $response['stripe_checkout_session_url'], 'Registered owner failed receipt recovery.' );
	} );
} finally {
	$wpdb->query( 'ROLLBACK' ); restore_error_handler();
	foreach ( $filters as $name => $callback ) { remove_filter( 'pre_option_' . $name, $callback, PHP_INT_MAX ); }
	WC()->session = $original['session']; WC()->customer = $original['customer']; WC()->cart = $original['cart']; wp_set_current_user( $original['user'] );
}
$remaining = 0;
foreach ( array( $wpdb->posts => 'ID', $wpdb->prefix . 'wc_orders' => 'id' ) as $table => $column ) { $remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $column IN (" . implode( ',', array_map( 'absint', $paid_ids ) ) . ')' ); }
if ( $paid_user_ids ) { $remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN (" . implode( ',', array_map( 'absint', $paid_user_ids ) ) . ')' ); }
$paid_results['fixtures_rolled_back'] = array( 'pass' => 0 === $remaining, 'remaining_rows' => $remaining );
$paid_results['no_php_warnings'] = array( 'pass' => ! $paid_warnings, 'warnings' => array_values( array_unique( $paid_warnings ) ) );
$pass = ! in_array( false, array_column( $paid_results, 'pass' ), true );
echo wp_json_encode( array( 'suite' => 'free-paid-cart-recovery', 'pass' => $pass, 'total' => count( $paid_results ), 'assertions' => $paid_assertions, 'results' => $paid_results ), JSON_PRETTY_PRINT ) . PHP_EOL;
if ( ! $pass ) { WP_CLI::halt( 1 ); }
