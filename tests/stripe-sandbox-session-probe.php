<?php
/**
 * Explicit sandbox API contract probe. Creates and expires test Checkout Sessions.
 * Run only with: wp eval-file tests/stripe-sandbox-session-probe.php confirm-sandbox
 * No WooCommerce orders, charges, refunds or live resources are created.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! isset( $args[0] ) || 'confirm-sandbox' !== $args[0]
	|| ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1', '::1' ), true )
	|| function_exists( 'sahcfwc_fs' ) || 'test-mode' !== get_option( 'sahcfwc_stripe_integration_mode' ) ) {
	throw new RuntimeException( 'Explicit confirmation and a local free test-mode site are required.' );
}
$probe_option = 'restricted' === get_option( 'sahcfwc_api_key_type', 'standard' ) ? 'sahcfwc_restricted_test_key' : 'sahcfwc_stripe_test_secret_key';
$probe_secret = trim( (string) get_option( $probe_option, '' ) );
if ( ! preg_match( '/^(sk|rk)_test_/', $probe_secret ) ) { throw new RuntimeException( 'A sandbox API key is required.' ); }
$probe_client = new \SAHCFWC\Libraries\Stripe\StripeClient( $probe_secret );
\SAHCFWC\Libraries\Stripe\Stripe::setMaxNetworkRetries( 0 );
$probe_http = \SAHCFWC\Libraries\Stripe\HttpClient\CurlClient::instance();
$probe_http->setConnectTimeout( 10 ); $probe_http->setTimeout( 25 );
\SAHCFWC\Libraries\Stripe\ApiRequestor::setHttpClient( $probe_http );
$probe_run = wp_generate_uuid4();
$probe_report = array( 'scope' => 'Sandbox Checkout Session API contracts only; no payments', 'results' => array(), 'cleanup' => array() );
$probe_cases = array(
	'card_french' => array( 'usd', array( 'card' ), 'fr' ),
	'card_french_canada' => array( 'cad', array( 'card' ), 'fr-CA' ),
	'card_chinese_taiwan' => array( 'usd', array( 'card' ), 'zh-TW' ),
	'link' => array( 'usd', array( 'card', 'link' ), 'auto' ),
	'link_only' => array( 'usd', array( 'link' ), 'auto' ),
	'canadian_debit' => array( 'cad', array( 'acss_debit' ), 'en' ),
	'canadian_debit_business' => array( 'cad', array( 'acss_debit' ), 'en' ),
	'us_bank_transfer' => array( 'usd', array( 'customer_balance' ), 'en' ),
	'wechat_pay' => array( 'usd', array( 'wechat_pay' ), 'en' ),
	'sepa_debit' => array( 'eur', array( 'sepa_debit' ), 'en' ),
);
if ( isset( $args[1] ) ) {
	if ( 'usd' === $args[1] ) {
		$probe_cases = array();
		foreach ( array( 'affirm', 'afterpay_clearpay', 'klarna', 'alipay', 'zip', 'paypal' ) as $probe_method ) {
			$probe_cases[ $probe_method ] = array( 'usd', array( $probe_method ), 'en' );
		}
	} elseif ( 'catalog' === $args[1] ) {
		$probe_cases = array();
		$probe_currencies = \SAHCFWC\Classes\SAHCFWC_Payment_Methods::currencies();
		foreach ( \SAHCFWC\Classes\SAHCFWC_Payment_Methods::allowed() as $probe_method ) {
			if ( in_array( $probe_method, array( 'card', 'link', 'customer_balance', 'acss_debit', 'wechat_pay', 'sepa_debit' ), true ) ) { continue; }
			$probe_cases[ $probe_method ] = array( $probe_currencies[ $probe_method ][0] ?? 'usd', array( $probe_method ), 'en' );
		}
	} else {
		if ( ! isset( $probe_cases[ $args[1] ] ) ) { throw new RuntimeException( 'Unknown sandbox probe case.' ); }
		$probe_cases = array( $args[1] => $probe_cases[ $args[1] ] );
	}
}
foreach ( $probe_cases as $probe_name => $probe_case ) {
	$probe_session = null; $probe_customer = null;
	try {
		$probe_params = array(
			'mode' => 'payment', 'payment_method_types' => $probe_case[1], 'locale' => $probe_case[2],
			'success_url' => 'http://127.0.0.1:8888/?sandbox-probe=success', 'cancel_url' => 'http://127.0.0.1:8888/?sandbox-probe=cancel',
			'line_items' => array( array( 'quantity' => 1, 'price_data' => array( 'currency' => $probe_case[0], 'unit_amount' => 5000,
				'product_data' => array( 'name' => 'SA Hosted Checkout sandbox API probe - not a purchase' ) ) ) ),
			'metadata' => array( 'sa_test_run' => $probe_run, 'sa_test_case' => $probe_name ),
		);
		if ( 'wechat_pay' === $probe_name ) { $probe_params['payment_method_options']['wechat_pay'] = array( 'client' => 'web' ); }
		if ( in_array( 'acss_debit', $probe_case[1], true ) ) {
			$probe_params['payment_method_options']['acss_debit']['mandate_options'] = array(
				'payment_schedule' => 'sporadic', 'transaction_type' => 'canadian_debit_business' === $probe_name ? 'business' : 'personal',
			);
		}
		if ( 'us_bank_transfer' === $probe_name ) {
			$probe_customer = $probe_client->customers->create( array( 'metadata' => array( 'sa_test_run' => $probe_run ) ), array( 'idempotency_key' => 'sa-probe-customer-' . $probe_run ) );
			if ( $probe_customer->livemode ) { throw new RuntimeException( 'Unexpected live-mode response.' ); }
			$probe_params['customer'] = $probe_customer->id;
			$probe_params['payment_method_options']['customer_balance'] = array( 'funding_type' => 'bank_transfer', 'bank_transfer' => array( 'type' => 'us_bank_transfer' ) );
		}
		$probe_session = $probe_client->checkout->sessions->create( $probe_params, array( 'idempotency_key' => 'sa-probe-' . $probe_run . '-' . $probe_name ) );
		$probe_report['results'][ $probe_name ] = array( 'accepted' => true, 'amount_matches' => 5000 === $probe_session->amount_total,
			'currency_matches' => $probe_case[0] === $probe_session->currency, 'locale_matches' => $probe_case[2] === $probe_session->locale, 'test_mode' => ! $probe_session->livemode );
	} catch ( \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException $probe_error ) {
		$probe_message = str_replace( $probe_secret, '[redacted]', $probe_error->getMessage() );
		$probe_message = preg_replace( '/(?:sk|rk)_(?:test|live)_[A-Za-z0-9_]+/', '[redacted]', $probe_message );
		$probe_report['results'][ $probe_name ] = array( 'accepted' => false, 'code' => $probe_error->getStripeCode(), 'message' => $probe_message );
	} catch ( Throwable $probe_error ) {
		$probe_report['results'][ $probe_name ] = array( 'accepted' => false, 'error' => get_class( $probe_error ) );
	} finally {
		try {
			if ( $probe_session && ! $probe_session->livemode && 'open' === $probe_session->status ) {
				$probe_expired = $probe_client->checkout->sessions->expire( $probe_session->id, array() );
				$probe_report['cleanup'][ $probe_name ] = 'expired' === $probe_expired->status;
			}
			if ( $probe_customer && ! $probe_customer->livemode && $probe_run === $probe_customer->metadata->sa_test_run ) {
				$probe_deleted = $probe_client->customers->delete( $probe_customer->id, array() );
				$probe_report['cleanup']['probe_customer_deleted'] = (bool) $probe_deleted->deleted;
			}
		} catch ( Throwable $probe_error ) { $probe_report['cleanup'][ $probe_name ] = false; }
	}
}
echo wp_json_encode( $probe_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
