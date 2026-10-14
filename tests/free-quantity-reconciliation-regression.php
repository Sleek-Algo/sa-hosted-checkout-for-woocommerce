<?php
/**
 * FREE-only offline quantity reconciliation tests. Run with wp eval-file.
 * All fixtures are transactional; Stripe transport, WordPress HTTP and email
 * are blocked. Requires the disposable local site at 127.0.0.1:8888/test mode.
 * Optional first CLI argument selects one named case. JSON goes to stdout.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 'WordPress CLI only.' ); }
$qa_url = wp_parse_url( home_url() );
if ( ! isset( $qa_url['host'], $qa_url['port'] ) || '127.0.0.1' !== $qa_url['host'] || 8888 !== (int) $qa_url['port']
	|| function_exists( 'sahcfwc_fs' ) || 'test-mode' !== get_option( 'sahcfwc_stripe_integration_mode' ) ) {
	throw new RuntimeException( 'The local FREE test-mode site is required.' );
}
require_once dirname( __DIR__ ) . '/includes/classes/class-sahcfwc-payment-completion.php';
use SAHCFWC\Classes\SAHCFWC_Payment_Completion as QA_Q_Completion;
use SAHCFWC\Classes\SAHCFWC_Quantity_Adjustment as QA_Q_Adjustment;
use SAHCFWC\Classes\SAHCFWC_Quantity_Inventory as QA_Q_Inventory;
use SAHCFWC\Classes\SAHCFWC_Money as QA_Q_Money;

class SAHCFWC_Test_Quantity_HTTP implements \SAHCFWC\Libraries\Stripe\HttpClient\ClientInterface {
	public $lines = array(); public $page_size = 100; public $calls = 0; public $unavailable = false;
	public function request( $method, $url, $headers, $params, $has_file, $api_mode = 'v1' ) {
		++$this->calls;
		if ( $this->unavailable ) { throw new RuntimeException( 'Offline simulated outage.' ); }
		if ( 'get' !== strtolower( $method ) || ! preg_match( '#/checkout/sessions/cs_test_quantity_[0-9]+/line_items$#', wp_parse_url( $url, PHP_URL_PATH ) ) ) {
			throw new RuntimeException( 'Unexpected Stripe request blocked.' );
		}
		$offset = 0;
		if ( ! empty( $params['starting_after'] ) ) {
			foreach ( $this->lines as $index => $line ) { if ( $params['starting_after'] === $line['id'] ) { $offset = $index + 1; break; } }
		}
		$data = array_slice( $this->lines, $offset, $this->page_size );
		return array( wp_json_encode( array( 'object' => 'list', 'data' => $data, 'has_more' => $offset + count( $data ) < count( $this->lines ) ) ), 200, array() );
	}
}

class SAHCFWC_Test_Quantity {
	public static $http; public static $client; public static $mail = 0; public static $paid = 0;
	public static $posts = array(); public static $orders = array(); public static $checks = array(); public static $diagnostic = array();
	public static function check( $name, $pass ) {
		self::$checks[ $name ] = (bool) $pass;
		if ( ! $pass ) { throw new RuntimeException( 'Assertion failed: ' . $name ); }
	}
	public static function differences( $first, $second, $prefix = '' ) {
		$changes = array();
		foreach ( $first as $key => $value ) {
			if ( is_array( $value ) && isset( $second[ $key ] ) && is_array( $second[ $key ] ) ) { $changes = array_merge( $changes, self::differences( $value, $second[ $key ], $prefix . $key . '.' ) ); }
			elseif ( ! array_key_exists( $key, $second ) || $value !== $second[ $key ] ) { $changes[ $prefix . $key ] = array( $value, isset( $second[ $key ] ) ? $second[ $key ] : null ); }
		}
		return $changes;
	}
	public static function fixture( $case ) {
		$currency = 'jpy' === $case ? 'JPY' : ( 'bhd' === $case ? 'BHD' : 'USD' );
		$zero = 'zero_final' === $case;
		$product = new WC_Product_Simple(); $product->set_name( 'Offline quantity fixture ' . wp_generate_uuid4() );
		$product->set_status( 'publish' ); $product->set_regular_price( '10' ); $product->set_price( '10' );
		$product->set_manage_stock( true ); $product->set_stock_quantity( 100 ); $product->set_tax_status( 'none' );
		$product_id = $product->save(); self::$posts[] = $product_id;
		$coupon = new WC_Coupon(); $coupon->set_code( 'quantity-offline-' . wp_generate_uuid4() );
		$coupon->set_discount_type( 'fixed_cart' ); $coupon->set_amount( $zero ? 10 : 2 ); $coupon->set_usage_limit( 20 );
		$coupon_id = $coupon->save(); self::$posts[] = $coupon_id;
		$order = new WC_Order(); $order->set_status( 'pending' ); $order->set_currency( $currency );
		$order->set_payment_method( QA_Q_Completion::GATEWAY ); $order->set_billing_email( 'quantity-fixture@example.test' );
		$item = new WC_Order_Item_Product(); $item->set_product( $product ); $item->set_quantity( 2 ); $item->set_subtotal( 20 ); $item->set_total( $zero ? 10 : 18 );
		if ( ! $zero ) { $item->set_taxes( array( 'subtotal' => array( 1 => '2' ), 'total' => array( 1 => '2' ) ) ); }
		$order->add_item( $item );
		$coupon_item = new WC_Order_Item_Coupon(); $coupon_item->set_code( $coupon->get_code() ); $coupon_item->set_discount( $zero ? 10 : 2 ); $order->add_item( $coupon_item );
		if ( ! $zero ) {
			$fee = new WC_Order_Item_Fee(); $fee->set_name( 'Fixed handling' ); $fee->set_total( 3 ); $order->add_item( $fee );
			$tax = new WC_Order_Item_Tax(); $tax->set_rate_id( 1 ); $tax->set_label( 'Fixed tax' ); $tax->set_tax_total( 2 ); $order->add_item( $tax );
			$shipping = new WC_Order_Item_Shipping(); $shipping->set_method_id( 'flat_rate' ); $shipping->set_method_title( 'Fixed delivery' ); $shipping->set_total( 4 ); $order->add_item( $shipping );
		}
		$order->set_discount_total( $zero ? 10 : 2 ); $order->set_discount_tax( 0 );
		$order->set_shipping_total( $zero ? 0 : 4 ); $order->set_shipping_tax( 0 ); $order->set_cart_tax( $zero ? 0 : 2 ); $order->set_total( $zero ? 10 : 27 );
		$order_id = $order->save(); self::$orders[] = $order_id;
		$params = array( 'line_items' => array() );
		$params['line_items'][] = array( 'quantity' => 2, 'adjustable_quantity' => array( 'enabled' => true, 'minimum' => 1, 'maximum' => 5 ),
			'price_data' => array( 'currency' => strtolower( $currency ), 'unit_amount' => QA_Q_Money::to_minor( 10, $currency ), 'product_data' => array(
				'name' => 'Offline product', 'metadata' => array( 'order_id' => (string) $order_id, 'order_item_id' => (string) $item->get_id(), 'product_id' => (string) $product_id, 'variation_id' => '0', 'sahcfwc_line_key' => 'row_0' ),
			) ) );
		if ( ! $zero ) {
			foreach ( array( array( 'fee', $fee->get_id(), 3 ), array( 'tax', $tax->get_id(), 2 ) ) as $fixed ) {
				$params['line_items'][] = array( 'quantity' => 1, 'price_data' => array( 'currency' => strtolower( $currency ), 'unit_amount' => QA_Q_Money::to_minor( $fixed[2], $currency ),
					'product_data' => array( 'name' => 'Offline fixed charge', 'metadata' => array( 'order_item_type' => $fixed[0], 'order_item_id' => (string) $fixed[1], 'sahcfwc_line_key' => 'row_' . count( $params['line_items'] ) ) ) ) );
			}
		}
		$contract = QA_Q_Adjustment::build_contract( $order, $params, QA_Q_Money::to_minor( $zero ? 10 : 2, $currency ), QA_Q_Money::to_minor( $zero ? 0 : 4, $currency ) );
		self::check( 'contract_built', isset( $contract['version'] ) && 1 === $contract['version'] );
		QA_Q_Inventory::reserve( $order, array( $item->get_id() => 5 ), time() + 3600 );
		$session_id = 'cs_test_quantity_' . $order_id;
		foreach ( array( QA_Q_Adjustment::CONTRACT => $contract, 'sahcfwc_stripe_checkout_session_id' => $session_id,
			'sahcfwc_checkout_expected_amount' => $contract['initial_total_minor'], 'sahcfwc_checkout_currency' => strtolower( $currency ), 'sahcfwc_checkout_mode' => 'test' ) as $key => $value ) { $order->update_meta_data( $key, $value ); }
		$order->save();
		$original_valid = QA_Q_Adjustment::validate_order_snapshot( wc_get_order( $order_id ), $contract );
		self::$diagnostic['original_snapshot_valid'] = true === $original_valid;
		if ( true !== $original_valid ) {
			$reader = new ReflectionMethod( 'SAHCFWC\\Classes\\SAHCFWC_Quantity_Adjustment', 'financial_state' ); $reader->setAccessible( true );
			self::$diagnostic['snapshot_differences'] = self::differences( $contract['financial_state'], $reader->invoke( null, wc_get_order( $order_id ) ) );
		}
		self::$http->lines = array(); self::$http->page_size = 'pagination' === $case ? 1 : 100; self::$http->unavailable = false;
		foreach ( $params['line_items'] as $index => $line ) {
			self::$http->lines[] = array( 'id' => 'li_offline_' . $order_id . '_' . $index, 'object' => 'item', 'quantity' => $line['quantity'],
				'amount_subtotal' => $line['quantity'] * $line['price_data']['unit_amount'], 'price' => array( 'id' => 'price_offline_' . $index, 'object' => 'price',
					'unit_amount' => $line['price_data']['unit_amount'], 'currency' => strtolower( $currency ), 'product' => array( 'id' => 'prod_offline_' . $index, 'object' => 'product', 'metadata' => $line['price_data']['product_data']['metadata'] ) ) );
		}
		$session = array( 'id' => $session_id, 'object' => 'checkout.session', 'mode' => 'payment', 'status' => 'complete', 'payment_status' => 'paid',
			'livemode' => false, 'currency' => strtolower( $currency ), 'amount_total' => $contract['initial_total_minor'], 'payment_intent' => 'pi_offline_' . $order_id,
			'metadata' => array( 'sahcfwc_order_id' => (string) $order_id, 'sahcfwc_order_key' => $order->get_order_key() ),
			'total_details' => array( 'amount_discount' => $contract['fixed_discount_minor'], 'amount_shipping' => $contract['fixed_shipping_minor'], 'amount_tax' => 0 ) );
		return array( 'order' => $order_id, 'item' => $item->get_id(), 'product' => $product_id, 'coupon' => $coupon_id, 'session' => $session, 'contract' => $contract );
	}
	public static function quantity( &$fixture, $quantity ) {
		self::$http->lines[0]['quantity'] = $quantity;
		self::$http->lines[0]['amount_subtotal'] = self::$http->lines[0]['price']['unit_amount'] * $quantity;
		$fixture['session']['amount_total'] = $fixture['contract']['initial_total_minor'] + ( $quantity - 2 ) * self::$http->lines[0]['price']['unit_amount'];
	}
	public static function snapshot( $fixture ) {
		global $wpdb;
		$order = wc_get_order( $fixture['order'] ); $item = $order->get_item( $fixture['item'] ); $coupon = new WC_Coupon( $fixture['coupon'] );
		return array( 'status' => $order->get_status(), 'paid' => $order->is_paid(), 'quantity' => (int) $item->get_quantity(), 'subtotal' => $item->get_subtotal(), 'line_total' => $item->get_total(),
			'order_total' => $order->get_total(), 'taxes' => $item->get_taxes(), 'discount' => $order->get_discount_total(), 'shipping' => $order->get_shipping_total(), 'tax' => $order->get_total_tax(),
			'fee' => array_sum( array_map( function ( $fee ) { return (float) $fee->get_total(); }, $order->get_fees() ) ),
			'stock' => (int) wc_get_product( $fixture['product'] )->get_stock_quantity(), 'coupon_usage' => (int) $coupon->get_usage_count(),
			'finalized' => '' !== $order->get_meta( QA_Q_Adjustment::FINAL_SESSION, true ), 'mail' => self::$mail, 'paid_events' => self::$paid,
			'notes' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_type='order_note'", $fixture['order'] ) ) );
	}
	public static function run( $case ) {
		global $wpdb;
		$fixture = self::fixture( $case ); $initial = self::snapshot( $fixture );
		$quantity = in_array( $case, array( 'decrease', 'zero_final' ), true ) ? 1 : ( 'unchanged' === $case ? 2 : 3 ); self::quantity( $fixture, $quantity );
		if ( 'open' === $case ) {
			$fixture['session']['status'] = 'open'; $fixture['session']['payment_status'] = 'unpaid';
			$result = QA_Q_Completion::validate( wc_get_order( $fixture['order'] ), $fixture['session'], self::$client );
			self::$diagnostic['response'] = is_wp_error( $result ) ? array( 'code' => $result->get_error_code(), 'status' => $result->get_error_data() ) : gettype( $result );
			self::$diagnostic['http_calls'] = self::$http->calls;
			self::check( 'open_adjusted_amount_valid', true === $result ); self::check( 'open_has_no_order_effects', $initial === self::snapshot( $fixture ) );
			return;
		}
		if ( 'partial_save' === $case ) { $order = wc_get_order( $fixture['order'] ); $item = $order->get_item( $fixture['item'] ); $item->set_quantity( 3 ); $item->set_subtotal( 30 ); $item->save(); }
		if ( in_array( $case, array( 'silent_item_write', 'silent_expected_write', 'silent_marker_write' ), true ) ) {
			if ( 'silent_item_write' === $case ) {
				$reject = function ( $check, $id, $key ) use ( $fixture ) { return $fixture['item'] === (int) $id && '_qty' === $key ? false : $check; };
				add_filter( 'update_order_item_metadata', $reject, PHP_INT_MAX, 3 );
			} elseif ( 'silent_expected_write' === $case ) {
				$meta_id = 0;
				foreach ( wc_get_order( $fixture['order'] )->get_meta_data() as $meta ) {
					if ( 'sahcfwc_checkout_expected_amount' === $meta->key ) { $meta_id = (int) $meta->id; break; }
				}
				self::check( 'expected_amount_has_persisted_metadata', $meta_id > 0 );
				$table = $wpdb->prefix . ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'wc_orders_meta' : 'postmeta' );
				$reject = function ( $query ) use ( $meta_id, $table ) {
					return 0 === strpos( ltrim( $query ), 'UPDATE `' . $table . '`' )
						&& preg_match( '/WHERE\s+`(?:id|meta_id)`\s*=\s*' . $meta_id . '\b/', $query ) ? 'SELECT 0' : $query;
				};
				add_filter( 'query', $reject, PHP_INT_MAX );
			} else {
				$reject = function ( $query ) { return 0 === strpos( ltrim( $query ), 'INSERT INTO' ) && false !== strpos( $query, QA_Q_Adjustment::FINAL_SESSION ) ? 'SELECT 0' : $query; };
				add_filter( 'query', $reject, PHP_INT_MAX );
			}
			try { $result = QA_Q_Completion::complete( $fixture['order'], $fixture['session'], self::$client ); }
			finally { remove_filter( 'silent_item_write' === $case ? 'update_order_item_metadata' : 'query', $reject, PHP_INT_MAX ); }
			$blocked = self::snapshot( $fixture );
			self::check( 'silent_write_failure_retries_without_fulfillment', is_wp_error( $result ) && 503 === $result->get_error_data()['status'] && ! $blocked['paid'] && ! $blocked['finalized'] && 100 === $blocked['stock'] && $initial['mail'] === $blocked['mail'] );
		}
		if ( 'lost_reservation' === $case ) {
			$wpdb->delete( $wpdb->prefix . 'wc_reserved_stock', array( 'order_id' => $fixture['order'] ), array( '%d' ) );
			for ( $i = 0; $i < 2; ++$i ) {
				$result = QA_Q_Completion::complete( $fixture['order'], $fixture['session'], self::$client );
				self::check( 'missing_hold_requires_review', is_wp_error( $result ) && 'sahcfwc_quantity_stock_review' === $result->get_error_code() && 503 === $result->get_error_data()['status'] );
			}
			$after = self::snapshot( $fixture );
			self::check( 'review_note_once_no_fulfillment', 1 === $after['notes'] - $initial['notes'] && ! $after['paid'] && 100 === $after['stock'] && 2 === $after['quantity'] && $initial['mail'] === $after['mail'] );
			return;
		}
		if ( 0 === strpos( $case, 'reject_' ) ) {
			switch ( $case ) {
				case 'reject_unit': ++self::$http->lines[0]['price']['unit_amount']; break;
				case 'reject_currency': self::$http->lines[0]['price']['currency'] = 'eur'; break;
				case 'reject_bounds': self::quantity( $fixture, 6 ); break;
				case 'reject_zero_quantity': self::quantity( $fixture, 0 ); break;
				case 'reject_fixed_row': self::$http->lines[1]['quantity'] = 2; self::$http->lines[1]['amount_subtotal'] *= 2; break;
				case 'reject_identity': self::$http->lines[0]['price']['product']['metadata']['product_id'] = '0'; break;
				case 'reject_duplicate': self::$http->lines[] = self::$http->lines[0]; break;
				case 'reject_missing': array_pop( self::$http->lines ); break;
				case 'reject_subtotal': ++self::$http->lines[0]['amount_subtotal']; break;
				case 'reject_discount': ++$fixture['session']['total_details']['amount_discount']; break;
				case 'reject_shipping': ++$fixture['session']['total_details']['amount_shipping']; break;
				case 'reject_automatic_tax': ++$fixture['session']['total_details']['amount_tax']; break;
				case 'reject_binding': $fixture['session']['metadata']['sahcfwc_order_key'] = 'wrong'; break;
				case 'reject_mode': $fixture['session']['livemode'] = true; break;
				case 'reject_order_drift': $order = wc_get_order( $fixture['order'] ); $order->set_shipping_total( 9 ); $order->save(); $initial = self::snapshot( $fixture ); break;
				case 'reject_manually_paid': $order = wc_get_order( $fixture['order'] ); $order->payment_complete( $fixture['session']['payment_intent'] ); $initial = self::snapshot( $fixture ); break;
				case 'reject_transport': self::$http->unavailable = true; break;
			}
			$calls = self::$http->calls;
			$result = QA_Q_Completion::complete( $fixture['order'], $fixture['session'], self::$client );
			self::check( 'tamper_or_outage_rejected', is_wp_error( $result ) );
			self::check( 'rejection_has_no_order_effects', $initial === self::snapshot( $fixture ) );
			if ( in_array( $case, array( 'reject_binding', 'reject_mode' ), true ) ) { self::check( 'binding_before_line_lookup', $calls === self::$http->calls ); }
			return;
		}
		if ( in_array( $case, array( 'delayed_success', 'delayed_failure', 'failure_then_paid' ), true ) ) {
			$fixture['session']['payment_status'] = 'unpaid';
			$result = QA_Q_Completion::pending( $fixture['order'], $fixture['session'], self::$client );
			self::check( 'delayed_order_finalized', $result instanceof WC_Order ); $held = self::snapshot( $fixture );
			self::check( 'stock_uses_final_quantity_before_on_hold', 'on-hold' === $held['status'] && 3 === $held['quantity'] && 97 === $held['stock'] && ! $held['paid'] && $held['finalized'] );
			QA_Q_Completion::pending( $fixture['order'], $fixture['session'], self::$client ); self::check( 'pending_replay_no_effects', $held === self::snapshot( $fixture ) );
			if ( in_array( $case, array( 'delayed_failure', 'failure_then_paid' ), true ) ) {
				$result = QA_Q_Completion::fail( $fixture['order'], $fixture['session'], self::$client ); $failed = self::snapshot( $fixture );
				self::check( 'failed_stock_and_coupon_restored', $result instanceof WC_Order && 'failed' === $failed['status'] && 100 === $failed['stock'] && 0 === $failed['coupon_usage'] );
				QA_Q_Completion::fail( $fixture['order'], $fixture['session'], self::$client ); self::check( 'failure_replay_no_effects', $failed === self::snapshot( $fixture ) );
				if ( 'failure_then_paid' === $case ) {
					wc_update_product_stock( $fixture['product'], 1, 'set' ); $fixture['session']['payment_status'] = 'paid';
					for ( $retry = 0; $retry < 2; ++$retry ) {
						$result = QA_Q_Completion::complete( $fixture['order'], $fixture['session'], self::$client );
						self::check( 'late_success_needs_new_stock_review', is_wp_error( $result ) && 'sahcfwc_quantity_stock_review' === $result->get_error_code() && 503 === $result->get_error_data()['status'] );
					}
					$late = self::snapshot( $fixture );
					self::check( 'no_overselling_or_late_fulfillment', 'failed' === $late['status'] && ! $late['paid'] && 1 === $late['stock'] && $late['mail'] === $failed['mail'] && $late['paid_events'] === $failed['paid_events'] && $late['notes'] === $failed['notes'] + 1 );
				}
				return;
			}
			$fixture['session']['payment_status'] = 'paid';
		}
		if ( 'zero_final' === $case ) { $fixture['session']['payment_status'] = 'no_payment_required'; $fixture['session']['payment_intent'] = null; }
		$calls = self::$http->calls;
		$result = QA_Q_Completion::complete( $fixture['order'], $fixture['session'], self::$client );
		self::$diagnostic['response'] = is_wp_error( $result ) ? array( 'code' => $result->get_error_code(), 'status' => $result->get_error_data() ) : gettype( $result );
		self::$diagnostic['http_calls'] = self::$http->calls;
		if ( is_wp_error( $result ) ) {
			$reader = new ReflectionMethod( 'SAHCFWC\\Classes\\SAHCFWC_Quantity_Adjustment', 'financial_state' ); $reader->setAccessible( true );
			self::$diagnostic['after_state'] = $reader->invoke( null, wc_get_order( $fixture['order'] ) );
		}
		self::check( 'payment_completed', $result instanceof WC_Order && $result->is_paid() );
		$after = self::snapshot( $fixture );
		self::check( 'quantity_and_stock_reconciled', $quantity === $after['quantity'] && 100 - $quantity === $after['stock'] && $after['finalized'] );
		self::check( 'total_matches_stripe', QA_Q_Money::to_minor( $after['order_total'], $fixture['session']['currency'] ) === $fixture['session']['amount_total'] );
		self::check( 'fixed_charges_and_taxes_unchanged', $after['discount'] === $initial['discount'] && $after['taxes'] === $initial['taxes'] && $after['fee'] === $initial['fee'] && $after['shipping'] === $initial['shipping'] && $after['tax'] === $initial['tax'] );
		self::check( 'coupon_and_payment_once', 1 === $after['coupon_usage'] && 1 === $after['paid_events'] - $initial['paid_events'] );
		if ( 'pagination' === $case ) { self::check( 'all_pages_requested', self::$http->calls - $calls >= 3 ); }
		QA_Q_Completion::complete( $fixture['order'], $fixture['session'], self::$client ); self::check( 'paid_replay_no_effects', $after === self::snapshot( $fixture ) );
	}
}

$qa_cases = array( 'open', 'increase', 'decrease', 'unchanged', 'pagination', 'partial_save', 'silent_item_write', 'silent_expected_write', 'silent_marker_write', 'delayed_success', 'delayed_failure', 'failure_then_paid', 'zero_final', 'jpy', 'bhd', 'lost_reservation',
	'reject_unit', 'reject_currency', 'reject_bounds', 'reject_zero_quantity', 'reject_fixed_row', 'reject_identity', 'reject_duplicate', 'reject_missing', 'reject_subtotal',
	'reject_discount', 'reject_shipping', 'reject_automatic_tax', 'reject_binding', 'reject_mode', 'reject_order_drift', 'reject_manually_paid', 'reject_transport' );
if ( ! empty( $args[0] ) ) { if ( ! in_array( $args[0], $qa_cases, true ) ) { throw new RuntimeException( 'Unknown test case.' ); } $qa_cases = array( $args[0] ); }
$qa_report = array( 'suite' => 'free-quantity-reconciliation', 'network' => 'mocked and blocked', 'results' => array(), 'pass' => false );
$qa_sources = array( 'includes/classes/class-sahcfwc-quantity-adjustment.php', 'includes/classes/class-sahcfwc-payment-completion.php', 'includes/classes/class-sahcfwc-quantity-inventory.php' );
foreach ( $qa_sources as $qa_source ) { $qa_report['source_hashes'][ $qa_source ] = hash_file( 'sha256', dirname( __DIR__ ) . '/' . $qa_source ); }
global $wpdb; $qa_active = false;
register_shutdown_function( function () use ( $wpdb, &$qa_active ) { if ( $qa_active ) { $wpdb->query( 'ROLLBACK' ); } } );
try {
	foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) { if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && 'InnoDB' !== $table['Engine'] ) { throw new RuntimeException( 'Transactional tables are required.' ); } }
	add_filter( 'pre_http_request', function () { return new WP_Error( 'qa_offline', 'External HTTP blocked.' ); }, PHP_INT_MAX );
	add_filter( 'pre_wp_mail', function () { ++SAHCFWC_Test_Quantity::$mail; return true; }, PHP_INT_MAX );
	add_filter( 'woocommerce_defer_transactional_emails', '__return_false', PHP_INT_MAX );
	add_action( 'woocommerce_payment_complete', function () { ++SAHCFWC_Test_Quantity::$paid; } );
	foreach ( array( 'woocommerce_manage_stock' => 'yes', 'woocommerce_hold_stock_minutes' => 60, 'woocommerce_calc_taxes' => 'no', 'woocommerce_currency' => 'USD' ) as $name => $value ) {
		add_filter( 'pre_option_' . $name, function () use ( $value ) { return $value; }, PHP_INT_MAX );
	}
	SAHCFWC_Test_Quantity::$http = new SAHCFWC_Test_Quantity_HTTP();
	\SAHCFWC\Libraries\Stripe\ApiRequestor::setHttpClient( SAHCFWC_Test_Quantity::$http );
	SAHCFWC_Test_Quantity::$client = new \SAHCFWC\Libraries\Stripe\StripeClient( 'sk_test_offline_quantity_fixture' );
	$wpdb->query( 'START TRANSACTION' ); $qa_active = true;
	wp_set_current_user( 0 ); WC()->cart = null; WC()->customer = null; WC()->session = null; WC()->mailer();
	foreach ( $qa_cases as $qa_case ) {
		SAHCFWC_Test_Quantity::$checks = array(); SAHCFWC_Test_Quantity::$diagnostic = array();
		try { SAHCFWC_Test_Quantity::run( $qa_case ); $result = array( 'pass' => true ); }
		catch ( Throwable $error ) { $result = array( 'pass' => false, 'type' => get_class( $error ), 'file' => basename( $error->getFile() ), 'line' => $error->getLine(), 'assertion' => 0 === strpos( $error->getMessage(), 'Assertion failed:' ) ? $error->getMessage() : 'Fixture or implementation error.' ); }
		$result['checks'] = SAHCFWC_Test_Quantity::$checks; $result['diagnostic'] = SAHCFWC_Test_Quantity::$diagnostic; $qa_report['results'][ $qa_case ] = $result;
	}
	$qa_report['pass'] = ! in_array( false, array_column( $qa_report['results'], 'pass' ), true );
} catch ( Throwable $error ) { $qa_report['setup_error'] = get_class( $error ) . ':' . $error->getLine(); }
finally {
	remove_all_actions( 'shutdown' ); if ( $qa_active ) { $wpdb->query( 'ROLLBACK' ); $qa_active = false; }
	$qa_report['rollback_rows'] = array( 'posts' => 0, 'orders' => 0, 'items' => 0, 'reservations' => 0 );
	foreach ( array_merge( SAHCFWC_Test_Quantity::$posts, SAHCFWC_Test_Quantity::$orders ) as $id ) { $qa_report['rollback_rows']['posts'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID=%d", $id ) ); }
	foreach ( SAHCFWC_Test_Quantity::$orders as $id ) {
		$qa_report['rollback_rows']['orders'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE id=%d", $id ) );
		$qa_report['rollback_rows']['items'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id=%d", $id ) );
		$qa_report['rollback_rows']['reservations'] += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_reserved_stock WHERE order_id=%d", $id ) );
	}
	$qa_report['source_stable'] = true;
	foreach ( $qa_sources as $qa_source ) { if ( $qa_report['source_hashes'][ $qa_source ] !== hash_file( 'sha256', dirname( __DIR__ ) . '/' . $qa_source ) ) { $qa_report['source_stable'] = false; } }
	$qa_report['rollback_clean'] = 0 === array_sum( $qa_report['rollback_rows'] );
	$qa_report['pass'] = $qa_report['pass'] && $qa_report['rollback_clean'] && $qa_report['source_stable'];
	echo wp_json_encode( $qa_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
}
if ( ! $qa_report['pass'] ) { WP_CLI::halt( 1 ); }
