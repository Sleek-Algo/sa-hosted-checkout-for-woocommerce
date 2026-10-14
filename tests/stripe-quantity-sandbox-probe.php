<?php
/** Explicit sandbox API contract probe; creates/expires a Session and deletes its test coupon. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ( $args[0] ?? '' ) !== 'confirm-sandbox'
	|| function_exists( 'sahcfwc_fs' ) || 'http://127.0.0.1:8888' !== rtrim( home_url(), '/' )
	|| 'test-mode' !== get_option( 'sahcfwc_stripe_integration_mode' ) ) {
	throw new RuntimeException( 'Explicit confirmation and local FREE test mode required.' );
}
$sqp_option = 'restricted' === get_option( 'sahcfwc_api_key_type', 'standard' ) ? 'sahcfwc_restricted_test_key' : 'sahcfwc_stripe_test_secret_key';
$sqp_key = trim( (string) get_option( $sqp_option ) );
if ( ! preg_match( '/^(sk|rk)_test_/', $sqp_key ) ) { throw new RuntimeException( 'Sandbox key required.' ); }
$sqp_client = new SAHCFWC\Libraries\Stripe\StripeClient( $sqp_key );
SAHCFWC\Libraries\Stripe\Stripe::setMaxNetworkRetries( 0 );
$sqp_http = SAHCFWC\Libraries\Stripe\HttpClient\CurlClient::instance(); $sqp_http->setConnectTimeout( 10 ); $sqp_http->setTimeout( 25 );
SAHCFWC\Libraries\Stripe\ApiRequestor::setHttpClient( $sqp_http );
$sqp_session = null; $sqp_coupon = null; $sqp_run = wp_generate_uuid4(); $sqp_report = array( 'scope' => 'Stripe sandbox API only; no WooCommerce orders or payments', 'checks' => array(), 'cleanup' => array() );
try {
	$sqp_coupon = $sqp_client->coupons->create( array( 'amount_off' => 400, 'currency' => 'usd', 'duration' => 'once', 'max_redemptions' => 1,
		'redeem_by' => time() + 3600, 'name' => 'Frozen test discount', 'metadata' => array( 'sa_quantity_test' => $sqp_run ) ), array( 'idempotency_key' => 'sa-quantity-coupon-' . $sqp_run ) );
	$sqp_lines = array();
	foreach ( array( array( 'Product', 1000, 2 ), array( 'Handling', 200, 1 ), array( 'Tax', 150, 1 ) ) as $index => $row ) {
		$sqp_line = array( 'quantity' => $row[2], 'price_data' => array( 'currency' => 'usd', 'unit_amount' => $row[1],
			'product_data' => array( 'name' => 'Quantity sandbox probe: ' . $row[0], 'metadata' => array( 'sahcfwc_line_key' => 'row_' . $index, 'sa_quantity_test' => $sqp_run ) ) ) );
		if ( 0 === $index ) { $sqp_line['adjustable_quantity'] = array( 'enabled' => true, 'minimum' => 1, 'maximum' => 10 ); }
		$sqp_lines[] = $sqp_line;
	}
	$sqp_session = $sqp_client->checkout->sessions->create( array( 'mode' => 'payment', 'payment_method_types' => array( 'card' ),
		'line_items' => $sqp_lines, 'discounts' => array( array( 'coupon' => $sqp_coupon->id ) ),
		'shipping_options' => array( array( 'shipping_rate_data' => array( 'display_name' => 'Fixed shipping', 'type' => 'fixed_amount', 'fixed_amount' => array( 'amount' => 300, 'currency' => 'usd' ) ) ) ),
		'success_url' => home_url( '/?quantity-probe=success' ), 'cancel_url' => home_url( '/?quantity-probe=cancel' ),
		'expires_at' => time() + 3600, 'metadata' => array( 'sa_quantity_test' => $sqp_run ) ), array( 'idempotency_key' => 'sa-quantity-session-' . $sqp_run ) );
	$sqp_report['checks']['test_mode'] = ! $sqp_session->livemode;
	$sqp_report['checks']['initial_total_2250'] = 2250 === $sqp_session->amount_total;
	$sqp_report['checks']['discount_frozen_400'] = 400 === $sqp_session->total_details->amount_discount;
	$sqp_report['checks']['shipping_fixed_300'] = 300 === $sqp_session->total_details->amount_shipping;
	$sqp_report['checks']['no_stripe_tax_recalculation'] = 0 === $sqp_session->total_details->amount_tax;
	$sqp_final_rows = array(); $sqp_cursor = ''; $sqp_pages = 0;
	do {
		$sqp_params = array( 'limit' => 1, 'expand' => array( 'data.price.product' ) ); if ( $sqp_cursor ) { $sqp_params['starting_after'] = $sqp_cursor; }
		$sqp_page = $sqp_client->checkout->sessions->allLineItems( $sqp_session->id, $sqp_params ); ++$sqp_pages;
		foreach ( $sqp_page->data as $sqp_line ) {
			$sqp_row_key = $sqp_line->price->product->metadata->sahcfwc_line_key;
			$sqp_final_rows[ $sqp_row_key ] = array( 'quantity' => $sqp_line->quantity, 'unit_minor' => $sqp_line->price->unit_amount, 'subtotal' => $sqp_line->amount_subtotal );
			$sqp_cursor = $sqp_line->id;
		}
		if ( $sqp_pages > 5 ) { throw new RuntimeException( 'Unexpected pagination.' ); }
	} while ( $sqp_page->has_more );
	$sqp_report['checks']['expanded_product_identity_and_types'] = isset( $sqp_final_rows['row_0'] ) && array( 'quantity' => 2, 'unit_minor' => 1000, 'subtotal' => 2000 ) === $sqp_final_rows['row_0'];
	$sqp_report['checks']['all_fixed_rows_retrievable'] = 3 === count( $sqp_final_rows ) && 200 === ( $sqp_final_rows['row_1']['subtotal'] ?? -1 ) && 150 === ( $sqp_final_rows['row_2']['subtotal'] ?? -1 );
	$sqp_report['checks']['pagination'] = 3 === $sqp_pages;
} catch ( Throwable $sqp_error ) {
	$sqp_message = str_replace( $sqp_key, '[redacted]', $sqp_error->getMessage() );
	$sqp_report['error'] = preg_replace( '/(?:sk|rk)_(?:test|live)_[A-Za-z0-9_]+/', '[redacted]', $sqp_message );
} finally {
	try {
		if ( $sqp_session && ! $sqp_session->livemode && 'open' === $sqp_session->status ) { $sqp_expired = $sqp_client->checkout->sessions->expire( $sqp_session->id, array() ); $sqp_report['cleanup']['session_expired'] = 'expired' === $sqp_expired->status; }
	} catch ( Throwable $sqp_error ) { $sqp_report['cleanup']['session_expired'] = false; }
	try {
		if ( $sqp_coupon && ! $sqp_coupon->livemode ) { $sqp_deleted = $sqp_client->coupons->delete( $sqp_coupon->id, array() ); $sqp_report['cleanup']['coupon_deleted'] = (bool) $sqp_deleted->deleted; }
	} catch ( Throwable $sqp_error ) { $sqp_report['cleanup']['coupon_deleted'] = false; }
}
$sqp_report['pass'] = ! isset( $sqp_report['error'] ) && 8 === count( $sqp_report['checks'] ) && ! in_array( false, $sqp_report['checks'], true )
	&& ! in_array( false, $sqp_report['cleanup'], true ) && 2 === count( $sqp_report['cleanup'] );
echo wp_json_encode( $sqp_report, JSON_PRETTY_PRINT ) . PHP_EOL;
if ( ! $sqp_report['pass'] ) { WP_CLI::halt( 1 ); }
