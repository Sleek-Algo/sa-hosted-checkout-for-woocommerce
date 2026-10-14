<?php
/**
 * Offline FREE release edge tests: cache races, CPT, shared stock and fixed rows.
 * Run with wp eval-file against the disposable local FREE test-mode site only.
 * Optional argument selects one case. All owned fixtures are rolled back.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL
	|| function_exists( 'sahcfwc_fs' ) || 'http://127.0.0.1:8888' !== rtrim( home_url(), '/' )
	|| 'test-mode' !== get_option( 'sahcfwc_stripe_integration_mode' ) ) { throw new RuntimeException( 'The disposable FREE local test-mode site is required.' ); }
require_once dirname( __DIR__ ) . '/includes/classes/class-sahcfwc-payment-completion.php';
use SAHCFWC\Classes\SAHCFWC_Payment_Completion as QECompletion;
use SAHCFWC\Classes\SAHCFWC_Quantity_Adjustment as QEAdjustment;
use SAHCFWC\Classes\SAHCFWC_Quantity_Inventory as QEInventory;

class SAHCFWC_Release_Edge_HTTP implements SAHCFWC\Libraries\Stripe\HttpClient\ClientInterface {
	public $lines = array();
	public function request( $method, $url, $headers, $params, $has_file, $api_mode = 'v1' ) {
		if ( 'get' !== strtolower( $method ) || ! preg_match( '#/checkout/sessions/cs_test_release_edge_[0-9]+/line_items$#', wp_parse_url( $url, PHP_URL_PATH ) ) ) { throw new RuntimeException( 'Unexpected Stripe transport blocked.' ); }
		return array( wp_json_encode( array( 'object' => 'list', 'data' => $this->lines, 'has_more' => false ) ), 200, array() );
	}
}

class SAHCFWC_Release_Edge {
	public static $posts = array(); public static $orders = array(); public static $checks = array(); public static $diagnostics = array();
	public static $mail = 0; public static $payments = 0; public static $client; public static $http;
	public static function check( $name, $pass ) { self::$checks[ $name ] = (bool) $pass; if ( ! $pass ) { throw new RuntimeException( 'Assertion failed: ' . $name ); } }
	public static function product( $type = 'simple', $parent = 0 ) {
		$product = 'variable' === $type ? new WC_Product_Variable() : ( 'variation' === $type ? new WC_Product_Variation() : new WC_Product_Simple() );
		$product->set_name( 'Offline release edge ' . wp_generate_uuid4() ); $product->set_status( 'publish' ); $product->set_regular_price( '10' ); $product->set_price( '10' );
		$product->set_tax_status( 'none' ); $product->set_manage_stock( 'variation' !== $type );
		if ( $parent ) { $product->set_parent_id( $parent ); } else { $product->set_stock_quantity( 20 ); }
		$product->save(); self::$posts[] = $product->get_id(); return $product;
	}
	public static function fixture( $kind = 'plain' ) {
		$products = array(); $parent = null;
		if ( 'shared' === $kind ) { $parent = self::product( 'variable' ); $products = array( self::product( 'variation', $parent->get_id() ), self::product( 'variation', $parent->get_id() ) ); }
		else { $products[] = self::product(); if ( 'split' === $kind ) { $products[] = self::product(); } }
		$order = new WC_Order(); $order->set_status( 'pending' ); $order->set_currency( 'USD' ); $order->set_payment_method( QECompletion::GATEWAY ); $order->set_billing_email( 'release-edge@example.test' );
		$ids = array();
		foreach ( $products as $index => $product ) {
			$quantity = 'shared' === $kind ? 1 : ( $index ? 3 : 2 ); $subtotal = $index && 'split' === $kind ? 10 : 10 * $quantity;
			$ids[] = $order->add_product( $product, $quantity, array( 'subtotal' => $subtotal, 'total' => $subtotal ) );
		}
		$order->calculate_totals( false ); $order->save(); self::$orders[] = $order->get_id();
		$initial_minor = (int) round( (float) $order->get_total() * 100 );
		$session = array( 'id' => 'cs_test_release_edge_' . $order->get_id(), 'object' => 'checkout.session', 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid', 'livemode' => false,
			'currency' => 'usd', 'amount_total' => $initial_minor, 'payment_intent' => 'pi_release_edge_' . $order->get_id(),
			'metadata' => array( 'sahcfwc_order_id' => (string) $order->get_id(), 'sahcfwc_order_key' => $order->get_order_key() ),
			'total_details' => array( 'amount_discount' => 0, 'amount_shipping' => 0, 'amount_tax' => 0 ) );
		self::$http->lines = array(); $maxima = array(); $params = array( 'line_items' => array() );
		if ( 'plain' !== $kind ) {
			foreach ( $order->get_items() as $id => $item ) {
				$fixed = 'split' === $kind && $item->get_product_id() === $products[1]->get_id();
				$tiers = $fixed ? array( array( 2, 333 ), array( 1, 334 ) ) : array( array( (int) $item->get_quantity(), 1000 ) );
				foreach ( $tiers as $tier ) {
					$key = 'row_' . count( $params['line_items'] ); $meta = array( 'sahcfwc_line_key' => $key, 'order_id' => (string) $order->get_id(), 'order_item_id' => (string) $id,
						'product_id' => (string) $item->get_product_id(), 'variation_id' => (string) $item->get_variation_id() );
					$line = array( 'quantity' => $tier[0], 'price_data' => array( 'currency' => 'usd', 'unit_amount' => $tier[1], 'product_data' => array( 'name' => 'Offline item', 'metadata' => $meta ) ) );
					if ( ! $fixed ) { $line['adjustable_quantity'] = array( 'enabled' => true, 'minimum' => 1, 'maximum' => 5 ); $maxima[ $id ] = 5; }
					$params['line_items'][] = $line;
					self::$http->lines[] = array( 'id' => 'li_edge_' . $key, 'object' => 'item', 'quantity' => $tier[0], 'amount_subtotal' => $tier[0] * $tier[1],
						'price' => array( 'id' => 'price_edge_' . $key, 'object' => 'price', 'unit_amount' => $tier[1], 'currency' => 'usd',
							'product' => array( 'id' => 'prod_edge_' . $key, 'object' => 'product', 'metadata' => $meta ) ) );
				}
			}
			$contract = QEAdjustment::build_contract( $order, $params, 0, 0 ); QEInventory::reserve( $order, $maxima, time() + 3600 ); $order->update_meta_data( QEAdjustment::CONTRACT, $contract );
		}
		foreach ( array( 'sahcfwc_stripe_checkout_session_id' => $session['id'], 'sahcfwc_checkout_expected_amount' => $initial_minor, 'sahcfwc_checkout_currency' => 'usd', 'sahcfwc_checkout_mode' => 'test' ) as $key => $value ) { $order->update_meta_data( $key, $value ); }
		$order->save();
		return array( 'order' => $order->get_id(), 'products' => $products, 'parent' => $parent, 'items' => $ids, 'session' => $session );
	}
	public static function finish( $fixture ) { return QECompletion::complete( $fixture['order'], $fixture['session'], self::$client ); }
	public static function observation( $fixture ) {
		$order = new WC_Order( $fixture['order'] ); $stock = array();
		foreach ( $fixture['products'] as $product ) { $fresh_product = wc_get_product( $product->get_id() ); $owner = $fresh_product->get_stock_managed_by_id(); $stock[ $owner ] = (int) wc_get_product( $owner )->get_stock_quantity(); }
		return array( 'status' => $order->get_status(), 'total' => $order->get_total(), 'stock' => array_values( $stock ), 'mail' => self::$mail, 'payments' => self::$payments );
	}
	public static function run( $case ) {
		if ( 'missing_order' === $case ) { $result = QECompletion::complete( 0, array(), self::$client ); self::check( 'missing_order_is_rejected_without_fatal', is_wp_error( $result ) && 'sahcfwc_invalid_order' === $result->get_error_code() ); return; }
		if ( 'refund_object' === $case ) {
			$fixture = self::fixture(); $refund = new WC_Order_Refund(); $refund->set_parent_id( $fixture['order'] ); $refund->set_amount( 10 ); $refund->set_currency( 'USD' ); $refund->save(); self::$orders[] = $refund->get_id();
			$correct_type = QECompletion::with_order_lock( $refund->get_id(), function ( $order ) { return $order instanceof WC_Order_Refund; } );
			$result = QECompletion::complete( $refund->get_id(), $fixture['session'], self::$client );
			self::check( 'factory_preserves_refund_type_and_payment_rejects_it', true === $correct_type && is_wp_error( $result ) && 'sahcfwc_invalid_order' === $result->get_error_code() ); return;
		}
		if ( 'lock_canonical_id' === $case ) {
			global $wpdb; $fixture = self::fixture(); $other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$name = 'sahcfwc_' . md5( $wpdb->prefix . ':' . get_current_blog_id() . ':' . $fixture['order'] ); $called = false;
			try { self::check( 'second_connection_holds_canonical_lock', '1' === (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) );
				$result = QECompletion::with_order_lock( '000' . $fixture['order'], function () use ( &$called ) { $called = true; } );
				self::check( 'numeric_alias_cannot_bypass_order_lock', ! $called && is_wp_error( $result ) && 'sahcfwc_order_busy' === $result->get_error_code() );
			} finally { $other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); $other->close(); } return;
		}
		if ( 0 === strpos( $case, 'cached_' ) ) {
			self::check( 'actual_hpos_order_cache_enabled', Automattic\WooCommerce\Utilities\OrderUtil::orders_cache_usage_is_enabled() );
			$fixture = self::fixture(); $stale = new WC_Order( $fixture['order'] );
			self::check( 'first_payment_succeeds', self::finish( $fixture ) instanceof WC_Order );
			if ( 'cached_refunded_replay' === $case ) { $closed = new WC_Order( $fixture['order'] ); $closed->update_status( 'refunded' ); }
			$before = self::observation( $fixture );
			// Model this request's old in-memory cache while another request has settled.
			$cache = wc_get_container()->get( Automattic\WooCommerce\Caches\OrderCache::class ); $cache->set( $stale, $fixture['order'] );
			self::check( 'stale_pending_cache_is_injected', 'pending' === wc_get_order( $fixture['order'] )->get_status() );
			$result = self::finish( $fixture ); $after = self::observation( $fixture ); self::$diagnostics = array( 'before' => $before, 'after' => $after, 'response' => is_wp_error( $result ) ? $result->get_error_code() : get_class( $result ) );
			self::check( 'lock_rereads_committed_order_without_duplicate_effects', $before === $after ); return;
		}
		$kind = 'fixed_split_reconciliation' === $case ? 'split' : ( 0 === strpos( $case, 'shared_' ) ? 'shared' : 'quantity' );
		$fixture = self::fixture( $kind );
		if ( 'cpt_legacy_lookup' === $case ) {
			$other = self::fixture( 'plain' ); $found = QECompletion::find_order( $fixture['session']['id'] );
			self::check( 'legacy_lookup_finds_only_bound_order', $found instanceof WC_Order && $fixture['order'] === $found->get_id() ); return;
		}
		$sum = 0;
		foreach ( self::$http->lines as $index => &$line ) {
			if ( 'split' !== $kind || 0 === $index ) { $line['quantity'] = 'shared' === $kind ? 3 + $index : 3; $line['amount_subtotal'] = $line['quantity'] * $line['price']['unit_amount']; }
			$sum += $line['amount_subtotal'];
		} unset( $line ); $fixture['session']['amount_total'] = $sum;
		if ( 'shared_delayed_failure' === $case ) {
			$fixture['session']['payment_status'] = 'unpaid'; $result = QECompletion::pending( $fixture['order'], $fixture['session'], self::$client );
			self::check( 'shared_on_hold_deducts_final_combined_quantity', $result instanceof WC_Order && array( 13 ) === self::observation( $fixture )['stock'] );
			$result = QECompletion::fail( $fixture['order'], $fixture['session'], self::$client );
			self::check( 'shared_failure_restores_parent_once', $result instanceof WC_Order && array( 20 ) === self::observation( $fixture )['stock'] ); $before = self::observation( $fixture );
			QECompletion::fail( $fixture['order'], $fixture['session'], self::$client ); self::check( 'shared_failure_replay_unchanged', $before === self::observation( $fixture ) ); return;
		}
		$result = self::finish( $fixture ); self::check( 'adjusted_payment_succeeds', $result instanceof WC_Order && $result->is_paid() );
		$after = self::observation( $fixture ); self::$diagnostics = $after;
		self::check( 'final_total_and_stock', (int) round( 100 * (float) $after['total'] ) === $sum && ( 'shared' === $kind ? array( 13 ) : ( 'split' === $kind ? array( 17, 17 ) : array( 17 ) ) ) === $after['stock'] );
		if ( 'split' === $kind ) { $item = ( new WC_Order( $fixture['order'] ) )->get_item( $fixture['items'][1] ); self::check( 'fixed_split_item_not_rewritten', 3 === (int) $item->get_quantity() && '10' === wc_format_decimal( $item->get_subtotal(), false, true ) && '10' === wc_format_decimal( $item->get_total(), false, true ) ); }
		self::finish( $fixture ); self::check( 'settlement_replay_unchanged', $after === self::observation( $fixture ) );
	}
}

$qe_cases = array( 'cached_paid_replay', 'cached_refunded_replay', 'shared_paid', 'shared_delayed_failure', 'fixed_split_reconciliation', 'cpt_paid', 'cpt_legacy_lookup', 'missing_order', 'refund_object', 'lock_canonical_id' );
if ( ! empty( $args[0] ) ) { if ( ! in_array( $args[0], $qe_cases, true ) ) { throw new RuntimeException( 'Unknown edge case.' ); } $qe_cases = array( $args[0] ); }
$qe_report = array( 'suite' => 'free-release-edge', 'network' => 'mocked SDK; HTTP and email blocked', 'results' => array(), 'pass' => false );
$qe_sources = array( 'includes/classes/class-sahcfwc-payment-completion.php', 'includes/classes/class-sahcfwc-quantity-adjustment.php', 'includes/classes/class-sahcfwc-quantity-inventory.php', 'includes/traits/sahcfwc-order-totals.php' );
foreach ( $qe_sources as $file ) { $qe_report['source_hashes'][ $file ] = hash_file( 'sha256', dirname( __DIR__ ) . '/' . $file ); }
global $wpdb; $qe_active = false;
register_shutdown_function( function () use ( &$qe_active, $wpdb ) { if ( $qe_active ) { $wpdb->query( 'ROLLBACK' ); } } );
try {
	foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) { if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && 'InnoDB' !== $table['Engine'] ) { throw new RuntimeException( 'InnoDB-only fixtures required.' ); } }
	add_filter( 'pre_http_request', function () { return new WP_Error( 'qe_offline', 'HTTP blocked.' ); }, PHP_INT_MAX );
	add_filter( 'pre_wp_mail', function () { ++SAHCFWC_Release_Edge::$mail; return true; }, PHP_INT_MAX );
	add_filter( 'woocommerce_defer_transactional_emails', '__return_false', PHP_INT_MAX );
	add_action( 'woocommerce_payment_complete', function () { ++SAHCFWC_Release_Edge::$payments; } );
	foreach ( array( 'woocommerce_manage_stock' => 'yes', 'woocommerce_hold_stock_minutes' => 60, 'woocommerce_calc_taxes' => 'no', 'woocommerce_custom_orders_table_data_sync_enabled' => 'no' ) as $key => $value ) { add_filter( 'pre_option_' . $key, function () use ( $value ) { return $value; }, PHP_INT_MAX ); }
	SAHCFWC_Release_Edge::$http = new SAHCFWC_Release_Edge_HTTP(); SAHCFWC\Libraries\Stripe\ApiRequestor::setHttpClient( SAHCFWC_Release_Edge::$http ); SAHCFWC_Release_Edge::$client = new SAHCFWC\Libraries\Stripe\StripeClient( 'sk_test_release_edge_offline' );
	$wpdb->query( 'START TRANSACTION' ); $qe_active = true; wp_set_current_user( 0 ); WC()->cart = null; WC()->customer = null; WC()->session = null; WC()->mailer();
	foreach ( $qe_cases as $case ) {
		$storage = function () use ( $case ) { return 0 === strpos( $case, 'cpt_' ) ? 'no' : 'yes'; }; add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage, PHP_INT_MAX );
		SAHCFWC_Release_Edge::$checks = array(); SAHCFWC_Release_Edge::$diagnostics = array();
		try { SAHCFWC_Release_Edge::check( 'storage_mode_matches_test', Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() === ( 0 !== strpos( $case, 'cpt_' ) ) ); SAHCFWC_Release_Edge::run( $case ); $result = array( 'pass' => true ); }
		catch ( Throwable $error ) { $result = array( 'pass' => false, 'error' => $error->getMessage(), 'file' => basename( $error->getFile() ), 'line' => $error->getLine() ); }
		finally { remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $storage, PHP_INT_MAX ); }
		$result['checks'] = SAHCFWC_Release_Edge::$checks; $result['diagnostics'] = SAHCFWC_Release_Edge::$diagnostics; $qe_report['results'][ $case ] = $result;
	}
	$qe_report['pass'] = ! in_array( false, array_column( $qe_report['results'], 'pass' ), true );
} catch ( Throwable $error ) { $qe_report['setup_error'] = get_class( $error ) . ':' . $error->getLine(); }
finally {
	remove_all_actions( 'shutdown' ); if ( $qe_active ) { $wpdb->query( 'ROLLBACK' ); $qe_active = false; }
	$remaining = 0;
	foreach ( array_merge( SAHCFWC_Release_Edge::$posts, SAHCFWC_Release_Edge::$orders ) as $id ) { $remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID=%d", $id ) ); }
	foreach ( SAHCFWC_Release_Edge::$orders as $id ) { foreach ( array( 'wc_orders' => 'id', 'wc_reserved_stock' => 'order_id', 'woocommerce_order_items' => 'order_id' ) as $table => $key ) { $remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE {$key}=%d", $id ) ); } }
	$qe_report['rollback_rows'] = $remaining; $qe_report['source_stable'] = true;
	foreach ( $qe_sources as $file ) { if ( $qe_report['source_hashes'][ $file ] !== hash_file( 'sha256', dirname( __DIR__ ) . '/' . $file ) ) { $qe_report['source_stable'] = false; } }
	$qe_report['pass'] = $qe_report['pass'] && 0 === $remaining && $qe_report['source_stable'];
}
echo wp_json_encode( $qe_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
if ( ! $qe_report['pass'] ) { WP_CLI::halt( 1 ); }
