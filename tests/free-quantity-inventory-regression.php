<?php
/**
 * Local FREE inventory regression: wp eval-file tests/free-quantity-inventory-regression.php.
 * Requires WP_HTTP_BLOCK_EXTERNAL=true before bootstrap. No Stripe requests are made.
 * Real WooCommerce fixture writes run in one transaction and always roll back.
 */
use SAHCFWC\Classes\SAHCFWC_Quantity_Inventory as QI;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'WC' ) || function_exists( 'sahcfwc_fs' )
	|| ! defined( 'SAHCFWC_DIR' ) || 'sa-hosted-checkout-for-woocommerce' !== basename( rtrim( realpath( SAHCFWC_DIR ), '/\\' ) )
	|| ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1', '::1' ), true )
	|| ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
	throw new RuntimeException( 'Requires the disposable LOCAL free WordPress site with HTTP blocked at bootstrap.' );
}
require_once dirname( __DIR__ ) . '/includes/classes/class-sahcfwc-quantity-inventory.php';
add_filter( 'pre_http_request', function () { return new WP_Error( 'qai_offline', 'External HTTP is disabled in this regression.' ); }, PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

class QAI_Stripe_Blocker implements \SAHCFWC\Libraries\Stripe\HttpClient\ClientInterface {
	public function request( $method, $url, $headers, $params, $has_file, $api_mode = 'v1' ) {
		throw new RuntimeException( 'Inventory regression must not call Stripe.' );
	}
}
\SAHCFWC\Libraries\Stripe\ApiRequestor::setHttpClient( new QAI_Stripe_Blocker() );

class QAI {
	public static $results = array();
	public static $ids = array( 'products' => array(), 'orders' => array() );
	public static $warnings = array();
	public static $options = array( 'woocommerce_manage_stock' => 'yes', 'woocommerce_hold_stock_minutes' => '60', 'woocommerce_calc_taxes' => 'no', 'woocommerce_currency' => 'USD' );
	public static function expect( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
	public static function throws( $callback, $contains ) {
		try { $callback(); } catch ( Exception $error ) { self::expect( false !== stripos( $error->getMessage(), $contains ), 'Unexpected validation: ' . $error->getMessage() ); return; }
		throw new RuntimeException( 'Expected validation was not raised.' );
	}
	public static function test( $name, $callback ) {
		try { $callback(); self::$results[ $name ] = array( 'pass' => true ); }
		catch ( Throwable $error ) { self::$results[ $name ] = array( 'pass' => false, 'error' => $error->getMessage() ); }
	}
	public static function product( $stock = 10, $backorders = 'no', $managed = true, $individual = false ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'QA inventory ' . wp_generate_uuid4() ); $product->set_status( 'publish' ); $product->set_regular_price( '10' ); $product->set_price( '10' );
		$product->set_tax_status( 'none' ); $product->set_virtual( true ); $product->set_manage_stock( $managed ); $product->set_stock_quantity( $stock );
		$product->set_backorders( $backorders ); $product->set_sold_individually( $individual ); $product->set_stock_status( 'instock' );
		self::$ids['products'][] = $product->save(); return $product;
	}
	public static function variations( $stock = 10 ) {
		$parent = new WC_Product_Variable(); $parent->set_name( 'QA shared inventory ' . wp_generate_uuid4() ); $parent->set_status( 'publish' );
		$parent->set_manage_stock( true ); $parent->set_stock_quantity( $stock ); $parent->set_stock_status( 'instock' ); self::$ids['products'][] = $parent->save();
		$result = array();
		foreach ( array( 'small', 'large' ) as $size ) {
			$product = new WC_Product_Variation(); $product->set_parent_id( $parent->get_id() ); $product->set_attributes( array( 'size' => $size ) );
			$product->set_status( 'publish' ); $product->set_regular_price( '10' ); $product->set_price( '10' ); $product->set_virtual( true );
			$product->set_manage_stock( false ); $product->set_stock_status( 'instock' ); self::$ids['products'][] = $product->save();
			$result[] = wc_get_product( $product->get_id() );
		}
		return array( $parent, $result[0], $result[1] );
	}
	public static function order( $lines ) {
		$order = new WC_Order(); $order->set_status( 'pending' ); $order->set_payment_method( QI::GATEWAY ); $order->set_currency( 'USD' );
		$order->set_billing_email( 'qa-inventory@example.test' );
		foreach ( $lines as $line ) { $order->add_product( $line[0], $line[1] ); }
		$order->calculate_totals( false ); self::$ids['orders'][] = $order->save(); return $order;
	}
	public static function item_ids( $order ) { return array_map( 'intval', array_keys( $order->get_items() ) ); }
	public static function rows( $order ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT product_id,stock_quantity,expires FROM {$wpdb->prefix}wc_reserved_stock WHERE order_id=%d ORDER BY product_id", $order->get_id() ), ARRAY_A );
	}
	public static function snapshot( $order ) {
		$items = array(); foreach ( wc_get_order( $order->get_id() )->get_items() as $id => $item ) { $items[ $id ] = array( $item->get_quantity(), $item->get_total(), $item->get_subtotal() ); }
		return array( 'items' => $items, 'total' => wc_get_order( $order->get_id() )->get_total() );
	}
	public static function contract( $order, $expires ) {
		$id = self::item_ids( $order )[0];
		$order->update_meta_data( 'sahcfwc_quantity_contract', array( 'version' => 1, 'rows' => array( array( 'kind' => 'product', 'order_item_id' => $id, 'initial_qty' => 2, 'min' => 1, 'max' => 5, 'adjustable' => true ) ) ) );
		$order->update_meta_data( 'sahcfwc_checkout_expires_at', $expires ); $order->save_meta_data();
	}
}
global $wpdb;
$qai_transaction = false; $qai_option_filters = array();
foreach ( QAI::$options as $name => $value ) {
	$qai_option_filters[ $name ] = function () use ( $name ) { return QAI::$options[ $name ]; };
	add_filter( 'pre_option_' . $name, $qai_option_filters[ $name ], PHP_INT_MAX );
}
register_shutdown_function( function () use ( &$qai_transaction, $wpdb ) { if ( $qai_transaction ) { $wpdb->query( 'ROLLBACK' ); } } );
set_error_handler( function ( $level, $message ) { if ( error_reporting() & $level ) { QAI::$warnings[] = $message; } return true; } );
try {
	foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) {
		if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && in_array( $table['Engine'], array( 'MyISAM', 'MEMORY' ), true ) ) { throw new RuntimeException( 'Use an InnoDB-only disposable site for rollback safety.' ); }
	}
	QAI::expect( false !== $wpdb->query( 'START TRANSACTION' ), 'Could not start transaction.' ); $qai_transaction = true;

	QAI::test( 'limits_simple_cap', function () {
		$order = QAI::order( array( array( QAI::product( 6 ), 2 ) ) ); $id = QAI::item_ids( $order )[0];
		QAI::expect( array( $id => 6 ) === QI::limits( $order, array( $id => 10 ) ), 'Maximum not capped to stock.' );
	} );
	QAI::test( 'limits_shared_parent_deterministic', function () {
		list( $parent, $small, $large ) = QAI::variations( 10 ); $order = QAI::order( array( array( $small, 2 ), array( $large, 3 ) ) ); list( $a, $b ) = QAI::item_ids( $order );
		QAI::expect( $small->get_stock_managed_by_id() === $parent->get_id() && $large->get_stock_managed_by_id() === $parent->get_id(), 'Shared-stock fixture invalid.' );
		$want = array( $a => 7, $b => 3 );
		QAI::expect( $want === QI::limits( $order, array( $b => 10, $a => 10 ) ), 'Shared pool or stable item ordering failed.' );
	} );
	QAI::test( 'limits_fixed_sibling_consumes_pool', function () {
		list( $parent, $small, $large ) = QAI::variations( 10 ); $order = QAI::order( array( array( $small, 2 ), array( $large, 3 ) ) ); $a = QAI::item_ids( $order )[0];
		QAI::expect( array( $a => 7 ) === QI::limits( $order, array( $a => 10 ) ), 'Fixed sibling was not included in baseline stock.' );
	} );
	QAI::test( 'limits_competing_hold_and_own_hold_excluded', function () {
		$product = QAI::product( 10 ); $other = QAI::order( array( array( $product, 3 ) ) ); wc_reserve_stock_for_order( $other );
		$order = QAI::order( array( array( $product, 2 ) ) ); wc_reserve_stock_for_order( $order ); $id = QAI::item_ids( $order )[0];
		QAI::expect( array( $id => 7 ) === QI::limits( $order, array( $id => 10 ) ), 'Competing or own reservation handled incorrectly.' );
	} );
	QAI::test( 'limits_insufficient_baseline_fails', function () {
		$product = QAI::product( 3 ); $other = QAI::order( array( array( $product, 2 ) ) ); wc_reserve_stock_for_order( $other );
		$order = QAI::order( array( array( $product, 2 ) ) ); $id = QAI::item_ids( $order )[0];
		QAI::throws( function () use ( $order, $id ) { QI::limits( $order, array( $id => 10 ) ); }, 'Not enough stock' );
	} );
	foreach ( array( 'unmanaged', 'backorders' ) as $kind ) {
		QAI::test( 'limits_and_reserve_' . $kind, function () use ( $kind ) {
			$product = QAI::product( 1, 'backorders' === $kind ? 'yes' : 'no', 'unmanaged' !== $kind ); $order = QAI::order( array( array( $product, 2 ) ) ); $id = QAI::item_ids( $order )[0]; $expires = time() + 1800;
			$maxima = QI::limits( $order, array( $id => 10 ) ); QI::reserve( $order, $maxima, $expires );
			QAI::expect( 10 === $maxima[ $id ] && array() === QAI::rows( $order ) && QI::has_reservation( $order, $maxima, $expires ), 'Unbounded stock produced a physical reservation or wrong limit.' );
		} );
	}
	QAI::test( 'limits_sold_individually_cap', function () {
		$order = QAI::order( array( array( QAI::product( 10, 'no', true, true ), 1 ) ) ); $id = QAI::item_ids( $order )[0];
		QAI::expect( array( $id => 1 ) === QI::limits( $order, array( $id => 10 ) ), 'Sold-individually limit not respected.' );
	} );
	QAI::test( 'invalid_limits_and_fractional_items_rejected', function () {
		$order = QAI::order( array( array( QAI::product(), 2 ) ) ); $id = QAI::item_ids( $order )[0];
		foreach ( array( array(), array( $id => 1 ), array( $id => 2.5 ), array( 999999999 => 3 ) ) as $map ) {
			QAI::throws( function () use ( $order, $map ) { QI::limits( $order, $map ); }, 'quantity limits' );
		}
		$integer_priority = has_filter( 'woocommerce_stock_amount', 'intval' );
		if ( false !== $integer_priority ) { remove_filter( 'woocommerce_stock_amount', 'intval', $integer_priority ); }
		try {
			$item = $order->get_item( $id ); $item->set_quantity( 1.5 ); $item->save();
			QAI::throws( function () use ( $order, $id ) { QI::limits( wc_get_order( $order->get_id() ), array( $id => 10 ) ); }, 'adjustable checkout quantities' );
		} finally { if ( false !== $integer_priority ) { add_filter( 'woocommerce_stock_amount', 'intval', $integer_priority ); } }
	} );
	QAI::test( 'reserve_maximum_preserves_order_and_stock', function () {
		$product = QAI::product(); $order = QAI::order( array( array( $product, 2 ) ) ); $id = QAI::item_ids( $order )[0]; $before = QAI::snapshot( $order ); $expires = time() + 1800; $filter_before = has_filter( 'woocommerce_order_item_quantity' );
		QI::reserve( $order, array( $id => 7 ), $expires ); $rows = QAI::rows( $order );
		QAI::expect( 1 === count( $rows ) && 7 === (int) $rows[0]['stock_quantity'] && QI::has_reservation( $order, array( $id => 7 ), $expires ), 'Maximum hold was not persisted through expiry.' );
		QAI::expect( $before === QAI::snapshot( $order ) && 10 === (int) wc_get_product( $product->get_id() )->get_stock_quantity(), 'Reservation changed purchased quantities/totals/stock.' );
		QAI::expect( $filter_before === has_filter( 'woocommerce_order_item_quantity' ), 'Temporary quantity filter leaked.' );
		QAI::expect( array( $id => 7 ) === wc_get_order( $order->get_id() )->get_meta( 'sahcfwc_quantity_stock_maxima' ), 'Hold audit metadata missing.' );
	} );
	QAI::test( 'reserve_shared_parent_single_row', function () {
		list( $parent, $small, $large ) = QAI::variations(); $order = QAI::order( array( array( $small, 2 ), array( $large, 3 ) ) ); list( $a, $b ) = QAI::item_ids( $order );
		$maxima = QI::limits( $order, array( $a => 10, $b => 10 ) ); QI::reserve( $order, $maxima, time() + 1800 ); $rows = QAI::rows( $order );
		QAI::expect( 1 === count( $rows ) && $parent->get_id() === (int) $rows[0]['product_id'] && 10 === (int) $rows[0]['stock_quantity'], 'Shared stock reserved twice or against variation ID.' );
	} );
	QAI::test( 'immediate_retry_reuses_sufficient_rows', function () {
		$order = QAI::order( array( array( QAI::product(), 2 ) ) ); $id = QAI::item_ids( $order )[0]; $expires = time() + 1800; QI::reserve( $order, array( $id => 5 ), $expires );
		$rows = QAI::rows( $order ); $reserved_at = $order->get_meta( 'sahcfwc_quantity_stock_reserved_at' );
		QI::reserve( $order, array( $id => 5 ), $expires );
		QAI::expect( $rows === QAI::rows( $order ) && $reserved_at === $order->get_meta( 'sahcfwc_quantity_stock_reserved_at' ), 'Identical retry rewrote/released holds.' );
	} );
	QAI::test( 'current_reservation_gate_rejects_missing_and_expired', function () {
		global $wpdb;
		$order = QAI::order( array( array( QAI::product(), 2 ) ) ); $id = QAI::item_ids( $order )[0]; $map = array( $id => 5 );
		QAI::expect( false === QI::has_current_reservation( $order, $map ), 'Missing reservation accepted for settlement.' );
		QI::reserve( $order, $map, time() + 1800 );
		QAI::expect( true === QI::has_current_reservation( $order, $map ), 'Live reservation rejected for settlement.' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}wc_reserved_stock SET expires=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE order_id=%d", $order->get_id() ) );
		QAI::expect( false === QI::has_current_reservation( $order, $map ), 'Expired reservation accepted for settlement.' );
	} );
	QAI::test( 'current_reservation_rejects_stale_cancelled_order_row', function () {
		global $wpdb;
		$product = QAI::product(); $order = QAI::order( array( array( $product, 2 ) ) ); $id = QAI::item_ids( $order )[0]; $map = array( $id => 5 );
		QI::reserve( $order, $map, time() + 1800 ); $row = QAI::rows( $order )[0];
		$order->update_status( 'cancelled' );
		$wpdb->insert( $wpdb->prefix . 'wc_reserved_stock', array( 'order_id' => $order->get_id(), 'product_id' => $product->get_id(), 'stock_quantity' => 5, 'timestamp' => current_time( 'mysql', true ), 'expires' => $row['expires'] ) );
		QAI::expect( false === QI::has_current_reservation( $order, $map ), 'Inactive reservation on cancelled order was treated as live stock.' );
	} );
	QAI::test( 'reserve_failure_removes_temporary_filter', function () {
		$order = QAI::order( array( array( QAI::product( 2 ), 2 ) ) ); $id = QAI::item_ids( $order )[0]; $before = has_filter( 'woocommerce_order_item_quantity' );
		QAI::throws( function () use ( $order, $id ) { QI::reserve( $order, array( $id => 5 ), time() + 1800 ); }, 'Not enough' );
		QAI::expect( array() === QAI::rows( $order ) && $before === has_filter( 'woocommerce_order_item_quantity' ), 'Failed reservation leaked a hold or quantity filter.' );
	} );
	QAI::test( 'partial_reservation_failure_releases_new_holds', function () {
		$order = QAI::order( array( array( QAI::product( 10 ), 2 ), array( QAI::product( 2 ), 2 ) ) ); list( $a, $b ) = QAI::item_ids( $order );
		QAI::throws( function () use ( $order, $a, $b ) { QI::reserve( $order, array( $a => 5, $b => 5 ), time() + 1800 ); }, 'Not enough' );
		QAI::expect( array() === QAI::rows( $order ), 'Partial failure retained new holds.' );
	} );
	foreach ( array( 'sahcfwc_stripe_checkout_session_id' => 'cs_test_inventory', 'sahcfwc_checkout_payload_hash' => 'uncertain-request-hash' ) as $key => $value ) {
		QAI::test( 'uncertain_existing_hold_preserved_' . $key, function () use ( $key, $value ) {
			$order = QAI::order( array( array( QAI::product(), 2 ) ) ); $id = QAI::item_ids( $order )[0]; $expires = time() + 1800;
			QI::reserve( $order, array( $id => 5 ), $expires ); $order->update_meta_data( $key, $value ); $order->save_meta_data(); $rows = QAI::rows( $order );
			QAI::throws( function () use ( $order, $id, $expires ) { QI::reserve( $order, array( $id => 7 ), $expires ); }, 'needs verification' );
			QAI::expect( $rows === QAI::rows( $order ), 'Uncertain checkout hold was changed or released.' );
			QI::reserve( $order, array( $id => 5 ), $expires ); QAI::expect( $rows === QAI::rows( $order ), 'Safe existing hold was not reusable.' );
		} );
	}
	QAI::test( 'shortened_hold_filter_fails_before_checkout', function () {
		$order = QAI::order( array( array( QAI::product(), 2 ) ) ); $id = QAI::item_ids( $order )[0]; $short = function () { return 1; };
		$expires = time() + 1800;
		add_filter( 'woocommerce_order_hold_stock_minutes', $short, PHP_INT_MAX );
		try {
			if ( version_compare( WC_VERSION, '8.8', '>=' ) ) {
				QAI::throws( function () use ( $order, $id, $expires ) { QI::reserve( $order, array( $id => 5 ), $expires ); }, 'full checkout period' );
				QAI::expect( '' === $order->get_meta( 'sahcfwc_quantity_stock_maxima' ), 'Inadequate reservation marked successful.' );
			} else {
				// WooCommerce added this filter in 8.8; earlier versions retain the requested hold.
				QI::reserve( $order, array( $id => 5 ), $expires );
				QAI::expect( QI::has_reservation( $order, array( $id => 5 ), $expires ), 'The original full-duration reservation was not retained.' );
			}
		}
		finally { remove_filter( 'woocommerce_order_hold_stock_minutes', $short, PHP_INT_MAX ); }
	} );
	QAI::test( 'disabled_stock_reservations_fail_closed', function () {
		$order = QAI::order( array( array( QAI::product(), 2 ) ) ); $id = QAI::item_ids( $order )[0]; $map = array( $id => 5 );
		add_filter( 'woocommerce_hold_stock_for_checkout', '__return_false', PHP_INT_MAX );
		try {
			QAI::throws( function () use ( $order, $map ) { QI::limits( $order, $map ); }, 'reservations are disabled' );
			QAI::throws( function () use ( $order, $map ) { QI::reserve( $order, $map, time() + 1800 ); }, 'reservations are disabled' );
		} finally { remove_filter( 'woocommerce_hold_stock_for_checkout', '__return_false', PHP_INT_MAX ); }
		QAI::expect( array() === QAI::rows( $order ), 'Disabled reservations still wrote a hold.' );
	} );
	QAI::test( 'actual_stock_reduction_not_maximum_and_hold_released', function () {
		$product = QAI::product(); $order = QAI::order( array( array( $product, 2 ) ) ); $id = QAI::item_ids( $order )[0];
		QI::reserve( $order, array( $id => 7 ), time() + 1800 ); $order->update_status( 'on-hold' );
		QAI::expect( 8 === (int) wc_get_product( $product->get_id() )->get_stock_quantity() && array() === QAI::rows( $order ), 'On-hold deducted maximum or retained temporary hold.' );
		$order->update_status( 'cancelled' ); QAI::expect( 10 === (int) wc_get_product( $product->get_id() )->get_stock_quantity(), 'Cancellation did not restore actual stock.' );
	} );
	QAI::test( 'cron_only_active_adjustable_order_protected', function () {
		$order = QAI::order( array( array( QAI::product(), 2 ) ) ); QAI::contract( $order, time() + 1800 );
		QI::register_hooks(); QI::register_hooks();
		QAI::expect( false === apply_filters( 'woocommerce_cancel_unpaid_order', true, $order ), 'Active adjustable order was not protected.' );
		QAI::expect( false === QI::should_cancel_unpaid_order( false, $order ), 'Earlier cancellation veto was overridden.' );
		$order->update_meta_data( 'sahcfwc_checkout_expires_at', time() - 100 ); QAI::expect( false === QI::should_cancel_unpaid_order( true, $order ), 'Payment delivery grace was omitted.' );
		$order->update_meta_data( 'sahcfwc_checkout_expires_at', time() - 301 ); QAI::expect( true === QI::should_cancel_unpaid_order( true, $order ), 'Expired protection never ends.' );
	} );
	QAI::test( 'cron_normal_other_gateway_and_malformed_unchanged', function () {
		$order = QAI::order( array( array( QAI::product(), 2 ) ) );
		QAI::expect( true === QI::should_cancel_unpaid_order( true, $order ), 'Normal plugin order was protected.' );
		QAI::contract( $order, time() + 1800 ); $order->set_payment_method( 'cod' );
		QAI::expect( true === QI::should_cancel_unpaid_order( true, $order ), 'Other gateway was protected.' );
		$order->set_payment_method( QI::GATEWAY ); $order->update_meta_data( 'sahcfwc_quantity_contract', array( 'version' => 1, 'rows' => array( array( 'kind' => 'product', 'order_item_id' => 999999999, 'min' => 1, 'max' => 5, 'adjustable' => true ) ) ) );
		QAI::expect( true === QI::should_cancel_unpaid_order( true, $order ), 'Unknown item contract was protected.' );
		QAI::contract( $order, time() + 1800 ); $order->update_meta_data( 'sahcfwc_completed_session_id', 'cs_test_paid' );
		QAI::expect( true === QI::should_cancel_unpaid_order( true, $order ), 'Previously completed order was protected.' );
	} );
	QAI::test( 'invalid_expiry_and_wrong_gateway_do_not_write', function () {
		$order = QAI::order( array( array( QAI::product(), 2 ) ) ); $id = QAI::item_ids( $order )[0];
		QAI::throws( function () use ( $order, $id ) { QI::reserve( $order, array( $id => 5 ), time() - 1 ); }, 'expiration' );
		$order->set_payment_method( 'cod' );
		QAI::throws( function () use ( $order, $id ) { QI::reserve( $order, array( $id => 5 ), time() + 1800 ); }, 'could not be verified' );
		QAI::expect( array() === QAI::rows( $order ), 'Invalid request changed stock reservations.' );
	} );
} catch ( Throwable $error ) {
	QAI::$results['harness'] = array( 'pass' => false, 'error' => $error->getMessage() );
} finally {
	if ( $qai_transaction ) { $wpdb->query( 'ROLLBACK' ); $qai_transaction = false; }
	restore_error_handler();
	foreach ( $qai_option_filters as $name => $callback ) { remove_filter( 'pre_option_' . $name, $callback, PHP_INT_MAX ); }
}
$qai_remaining = 0;
$qai_post_ids = array_merge( QAI::$ids['products'], QAI::$ids['orders'] );
if ( $qai_post_ids ) { $qai_remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', array_map( 'absint', $qai_post_ids ) ) . ')' ); }
if ( QAI::$ids['orders'] ) {
	$qai_ids = implode( ',', array_map( 'absint', QAI::$ids['orders'] ) );
	$qai_remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_reserved_stock WHERE order_id IN ($qai_ids)" );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_orders' ) ) ) { $qai_remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE id IN ($qai_ids)" ); }
}
QAI::$results['rollback'] = array( 'pass' => 0 === $qai_remaining, 'remaining_fixture_rows' => $qai_remaining );
QAI::$results['no_php_warnings'] = array( 'pass' => ! QAI::$warnings, 'warnings' => array_values( array_unique( QAI::$warnings ) ) );
$qai_pass = ! in_array( false, array_column( QAI::$results, 'pass' ), true );
echo wp_json_encode( array( 'suite' => 'free-quantity-inventory', 'network' => 'no_stripe_calls_wp_http_blocked', 'pass' => $qai_pass, 'cases' => count( QAI::$results ), 'results' => QAI::$results ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
if ( ! $qai_pass ) { WP_CLI::halt( 1 ); }
