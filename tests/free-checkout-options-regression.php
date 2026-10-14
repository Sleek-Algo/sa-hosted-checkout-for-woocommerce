<?php
/**
 * Offline configuration regression for a disposable LOCAL free WordPress site.
 * Run: wp eval-file tests/free-checkout-options-regression.php --path=/local/wordpress
 * Blocks HTTP/email, mocks the Stripe SDK, and rolls back fixtures/settings.
 * Set WP_HTTP_BLOCK_EXTERNAL=true in the local wp-config.php before bootstrap.
 */
use SAHCFWC\Classes\SAHCFWC_Payment_Methods as Methods;
use SAHCFWC\Classes\SAHCFWC_Payment_Gateway as Gateway;
use SAHCFWC\Classes\SAHCFWC_Checkout_Button_Url_Ajax as CheckoutAjax;
use SAHCFWC\Libraries\Stripe\ApiRequestor;
use SAHCFWC\Libraries\Stripe\StripeClient;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'WC' )
	|| function_exists( 'sahcfwc_fs' ) || ! defined( 'SAHCFWC_DIR' )
	|| 'sa-hosted-checkout-for-woocommerce' !== basename( rtrim( realpath( SAHCFWC_DIR ), '/\\' ) )
	|| ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
	throw new RuntimeException( 'Run only against a disposable localhost installation of the free plugin.' );
}
if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
	throw new RuntimeException( 'Enable WP_HTTP_BLOCK_EXTERNAL in the local wp-config.php before running offline tests.' );
}
require_once SAHCFWC_DIR . '/includes/classes/class-sahcfwc-payment-methods.php';
add_filter( 'pre_http_request', function () { return new WP_Error( 'sao_offline', 'Regression tests block external HTTP.' ); }, PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

class SAO_Helpers { use \SAHCFWC\Traits\SAHCFWC_Helpers; }
class SAO_Memory_Session extends WC_Session {
	public function __construct() { $this->_customer_id = 'qa-options-' . wp_generate_uuid4(); }
	public function init() {}
	public function save_data() {}
	public function has_session() { return true; }
	public function set_customer_session_cookie( $set ) {}
	public function destroy_session() { $this->_data = array(); }
	public function get_session( $key, $default = false ) { return $default; }
}

/** Strict SDK transport: unknown endpoints fail instead of reaching a network. */
class SAO_Stripe_Transport implements \SAHCFWC\Libraries\Stripe\HttpClient\ClientInterface {
	public $requests = array();
	public $sessions = array();
	public $customers = array();
	public $idempotent = array();
	public $lose_customer_response = false;
	public $lose_session_response = false;
	public $customer_mode_override = null;
	public $invalid_customer_id = false;
	public $omit_customer_mode = false;
	public function request( $method, $url, $headers, $params, $has_file, $api_mode = 'v1' ) {
		$method = strtolower( $method ); $path = wp_parse_url( $url, PHP_URL_PATH ); $idempotency = ''; $live = false;
		foreach ( $headers as $name => $value ) {
			$header = is_string( $name ) ? $name . ': ' . $value : $value;
			if ( 0 === stripos( $header, 'Idempotency-Key: ' ) ) { $idempotency = substr( $header, 17 ); }
			if ( 0 === stripos( $header, 'Authorization: ' ) ) { $live = false !== strpos( $header, '_live_' ); }
		}
		$this->requests[] = array( 'method' => $method, 'path' => $path, 'params' => $params, 'idempotency' => $idempotency );
		$cache_key = $path . ':' . $idempotency;
		if ( 'post' === $method && isset( $this->idempotent[ $cache_key ] ) ) {
			sao_expect( $params === $this->idempotent[ $cache_key ]['params'], 'Idempotent request parameters changed.' );
			$data = $this->idempotent[ $cache_key ]['data'];
		} elseif ( 'post' === $method && '/v1/customers' === $path ) {
			sao_expect( '' !== $idempotency, 'Customer request must have an idempotency key.' );
			$data = array( 'id' => 'cus_QA' . ( count( $this->customers ) + 1 ), 'object' => 'customer', 'livemode' => $live );
			if ( null !== $this->customer_mode_override ) { $data['livemode'] = $this->customer_mode_override; }
			if ( $this->invalid_customer_id ) { $data['id'] = 'invalid-customer'; }
			if ( $this->omit_customer_mode ) { unset( $data['livemode'] ); }
			$this->customers[] = $data;
			$this->idempotent[ $cache_key ] = array( 'params' => $params, 'data' => $data );
			if ( $this->lose_customer_response ) {
				$this->lose_customer_response = false;
				throw \SAHCFWC\Libraries\Stripe\Exception\ApiConnectionException::factory( 'Simulated lost customer response.' );
			}
		} elseif ( 'post' === $method && '/v1/checkout/sessions' === $path ) {
			sao_expect( '' !== $idempotency, 'Session request must have an idempotency key.' ); $total = 0;
			foreach ( $params['line_items'] as $line ) {
				sao_expect( isset( $line['price_data']['unit_amount'] ) && is_int( $line['price_data']['unit_amount'] ), 'Session prices must use integer minor units.' );
				$total += $line['quantity'] * $line['price_data']['unit_amount'];
			}
			$total += $params['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] ?? 0;
			sao_expect( empty( $params['discounts'] ), 'Configuration fixture must not create Stripe coupons.' );
			$data = array(
				'id' => 'cs_test_options_' . ( count( $this->sessions ) + 1 ), 'object' => 'checkout.session',
				'url' => 'https://checkout.stripe.com/offline-options-test', 'status' => 'open', 'payment_status' => 'unpaid',
				'mode' => 'payment', 'livemode' => $live, 'expires_at' => $params['expires_at'],
				'amount_total' => $total, 'currency' => $params['line_items'][0]['price_data']['currency'], 'metadata' => $params['metadata'],
			);
			$this->sessions[ $data['id'] ] = $data;
			$this->idempotent[ $cache_key ] = array( 'params' => $params, 'data' => $data );
			if ( $this->lose_session_response ) {
				$this->lose_session_response = false;
				throw \SAHCFWC\Libraries\Stripe\Exception\ApiConnectionException::factory( 'Simulated lost Session response.' );
			}
		} elseif ( 'get' === $method && isset( $this->sessions[ basename( $path ) ] ) ) {
			$data = $this->sessions[ basename( $path ) ];
		} elseif ( 'get' === $method && '/v1/account' === $path ) {
			$data = array( 'id' => 'acct_QAoffline', 'object' => 'account', 'country' => 'US' );
		} else { throw new RuntimeException( 'Unexpected mocked Stripe endpoint: ' . $method . ' ' . $path ); }
		return array( wp_json_encode( $data ), 200, array() );
	}
	public function matching( $method, $path ) {
		return array_values( array_filter( $this->requests, function ( $request ) use ( $method, $path ) {
			return $method === $request['method'] && $path === $request['path'];
		} ) );
	}
}
function sao_expect( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function sao_case( $name, $callback ) {
	global $sao_results, $sao_options, $sao_base, $sao_transport;
	$sao_options = $sao_base; $sao_transport = new SAO_Stripe_Transport(); ApiRequestor::setHttpClient( $sao_transport ); wc_clear_notices();
	try { $callback(); $sao_results[ $name ] = array( 'pass' => true ); }
	catch ( Throwable $error ) { $sao_results[ $name ] = array( 'pass' => false, 'error' => $error->getMessage() ); }
}
function sao_throws( $callback, $contains ) {
	try { $callback(); } catch ( Exception $error ) {
		sao_expect( false !== stripos( $error->getMessage(), $contains ), 'Unexpected error instead of the expected validation.' ); return;
	}
	throw new RuntimeException( 'Expected validation error was not raised.' );
}
function sao_order( $currency = 'USD' ) {
	global $sao_product, $sao_ids;
	$order = wc_create_order( array( 'status' => 'pending', 'customer_id' => 0 ) );
	sao_expect( $order instanceof WC_Order, 'Fixture order creation failed.' ); $sao_ids['orders'][] = $order->get_id();
	$order->set_currency( $currency ); $order->set_payment_method( 'sahcfwc_stripe_checkout' );
	$order->set_billing_email( 'qa-options@example.test' ); $order->set_billing_first_name( 'QA' ); $order->set_billing_last_name( 'Options' ); $order->set_billing_country( 'US' );
	$order->add_product( $sao_product, 2, array( 'subtotal' => 20, 'total' => 20 ) );
	$fee = new WC_Order_Item_Fee(); $fee->set_name( 'QA handling' ); $fee->set_amount( 2 ); $fee->set_total( 2 ); $order->add_item( $fee );
	$shipping = new WC_Order_Item_Shipping(); $shipping->set_method_title( 'QA shipping' ); $shipping->set_method_id( 'flat_rate' ); $shipping->set_total( 3 ); $order->add_item( $shipping );
	$order->calculate_totals( false ); $order->save(); sao_expect( '25.00' === $order->get_total(), 'Saved fixture must total 25.00.' ); return $order;
}
function sao_last_session_payload() {
	global $sao_transport; $requests = $sao_transport->matching( 'post', '/v1/checkout/sessions' );
	sao_expect( ! empty( $requests ), 'No Checkout Session payload was created.' ); return $requests[ count( $requests ) - 1 ]['params'];
}
function sao_assert_binding( $payload, $order ) {
	sao_expect( (string) $order->get_id() === $payload['metadata']['sahcfwc_order_id'], 'Session order metadata mismatch.' );
	sao_expect( $order->get_order_key() === $payload['metadata']['sahcfwc_order_key'], 'Session order key mismatch.' );
	sao_expect( $payload['metadata'] === $payload['payment_intent_data']['metadata'], 'PaymentIntent and Session binding differ.' );
	$stored = wc_get_order( $order->get_id() );
	sao_expect( 2500 === (int) $stored->get_meta( 'sahcfwc_checkout_expected_amount' ), 'Stored expected amount changed.' );
	sao_expect( 'test' === $stored->get_meta( 'sahcfwc_checkout_mode' ), 'Stored test mode missing.' );
	sao_expect( false === $stored->is_paid(), 'Configuration test must never mark an order paid.' );
}

// WP-CLI eval-file runs inside a method scope; make callback state explicit.
global $wpdb, $sao_results, $sao_warnings, $sao_ids, $sao_product, $sao_base, $sao_options, $sao_transport;
$sao_results = array(); $sao_warnings = array(); $sao_ids = array( 'orders' => array(), 'products' => array(), 'users' => array() );
$sao_original_user = get_current_user_id(); $sao_original_session = WC()->session; $sao_original_customer = WC()->customer;
// Short dummy credentials preserve mode detection without resembling Stripe-issued keys.
$sao_base = array(
	'sahcfwc_stripe_checkout_status' => 'yes', 'sahcfwc_stripe_integration_mode' => 'test-mode', 'sahcfwc_api_key_type' => 'standard',
	'sahcfwc_stripe_admin_test_mode_status' => 'no', 'sahcfwc_stripe_test_secret_key' => 'sk_test_dummy',
	'sahcfwc_stripe_live_secret_key' => 'sk_live_dummy', 'sahcfwc_restricted_test_key' => '', 'sahcfwc_restricted_live_key' => '',
	'sahcfwc_stripe_locale_status' => 'default', 'sahcfwc_stripe_locale' => 'en',
	'sahcfwc_stripe_payment_method_status' => 'automatic', 'sahcfwc_stripe_payment_methodes' => array(), 'sahcfwc_stripe_webhook_key' => '',
	'sahcfwc_stripe_acss_transaction_type' => '',
	'sahcfwc_stripe_phone_num_status' => 'no', 'sahcfwc_stripe_shipping_address_status' => 'no', 'sahcfwc_stripe_terms_condition_status' => 'no',
	'sahcfwc_stripe_adjustment_quantity' => 'no', 'woocommerce_currency' => 'USD', 'woocommerce_calc_taxes' => 'no',
	'woocommerce_enable_coupons' => 'yes', 'woocommerce_default_country' => 'US:CA',
	'woocommerce_sahcfwc_stripe_checkout_settings' => array( 'enabled' => 'yes', 'title' => 'QA Stripe Checkout' ),
);
$sao_options = $sao_base; $sao_option_filters = array();
foreach ( array_keys( $sao_base ) as $name ) {
	$sao_option_filters[ $name ] = function () use ( $name ) { global $sao_options; return $sao_options[ $name ]; };
	add_filter( 'pre_option_' . $name, $sao_option_filters[ $name ], PHP_INT_MAX );
}
$sao_setting_names = array( 'sahcfwc_stripe_locale_status', 'sahcfwc_stripe_locale', 'sahcfwc_stripe_payment_method_status', 'sahcfwc_stripe_payment_methodes', 'sahcfwc_stripe_acss_transaction_type' );
$sao_sql_names = "'" . implode( "','", array_map( 'esc_sql', $sao_setting_names ) ) . "'";
$sao_before_settings = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($sao_sql_names) ORDER BY option_name", ARRAY_A );
$sao_transaction = false;
register_shutdown_function( function () use ( &$sao_transaction, $wpdb ) { if ( $sao_transaction ) { $wpdb->query( 'ROLLBACK' ); } } );
set_error_handler( function ( $level, $message ) use ( &$sao_warnings ) { if ( error_reporting() & $level ) { $sao_warnings[] = $message; } return true; } );
try {
	foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) {
		if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && in_array( $table['Engine'], array( 'MyISAM', 'MEMORY' ), true ) ) { throw new RuntimeException( 'Use an InnoDB-only disposable site for rollback-safe tests.' ); }
	}
	sao_expect( false !== $wpdb->query( 'START TRANSACTION' ), 'Could not start rollback transaction.' ); $sao_transaction = true;
	WC()->session = new SAO_Memory_Session(); WC()->customer = new WC_Customer( 0, true );
	WC()->customer->set_billing_country( 'US' ); WC()->customer->set_billing_state( 'CA' ); WC()->customer->set_billing_postcode( '94107' ); WC()->customer->set_billing_email( 'qa-options@example.test' );
	if ( ! WC()->cart instanceof WC_Cart ) { WC()->cart = new WC_Cart(); }
	WC()->cart->set_cart_contents( array() ); WC()->cart->set_applied_coupons( array() ); wp_set_current_user( 0 );
	$sao_transport = new SAO_Stripe_Transport(); ApiRequestor::setHttpClient( $sao_transport );
	$sao_product = new WC_Product_Simple(); $sao_product->set_name( 'QA offline checkout options' ); $sao_product->set_status( 'publish' );
	$sao_product->set_regular_price( '10.00' ); $sao_product->set_virtual( true ); $sao_product->set_tax_status( 'none' ); $sao_product->save(); $sao_ids['products'][] = $sao_product->get_id();
	$helper = new SAO_Helpers();
	sao_case( 'locale_exact_official_enum', function () use ( $helper ) {
		$expected = explode( ' ', 'auto bg cs da de el en en-GB es es-419 et fi fil fr fr-CA hr hu id it ja ko lt lv ms mt nb nl pl pt pt-BR ro ru sk sl sv th tr vi zh zh-HK zh-TW' );
		sao_expect( $expected === array_column( $helper->sahcfwc_get_stripe_locale_options(), 'value' ), 'Locale enum differs from the reviewed Stripe list.' );
		foreach ( $expected as $locale ) { sao_expect( $locale === $helper->sahcfwc_sanitize_stripe_locale( $locale ), 'Supported locale rejected.' ); }
	} );
	sao_case( 'locale_invalid_values_use_auto', function () use ( $helper ) {
		foreach ( array( 'default', '', 'xx', 'en_US', 'FR', '<b>fr</b>', array( 'fr' ), null, false, 1 ) as $input ) { sao_expect( 'auto' === $helper->sahcfwc_sanitize_stripe_locale( $input ), 'Unsupported locale was accepted.' ); }
	} );
	sao_case( 'locale_automatic_ignores_saved_custom_language', function () use ( $helper ) {
		global $sao_options; $sao_options['sahcfwc_stripe_locale'] = 'fr';
		sao_expect( 'auto' === $helper->sahcfwc_get_stripe_checkout_locale(), 'Default mode must use automatic detection.' );
	} );
	sao_case( 'methods_normalize_legacy_brands_duplicates_and_retired', function () {
		$input = array( array( 'id' => ' VISA ', 'title' => 'Old title' ), 'mastercard', 'amex', 'card', 'JCBI', 'giropay', 'not_real', array(), 4, 'wechat_pay', ' WECHAT_PAY ' );
		sao_expect( array( 'card', 'wechat_pay' ) === Methods::normalize( $input ), 'Legacy normalization mismatch.' );
		sao_expect( array( array( 'id' => 'card', 'title' => 'card' ), array( 'id' => 'wechat_pay', 'title' => 'wechat_pay' ) ) === Methods::sanitize( $input ), 'Saved method format mismatch.' );
		sao_expect( array() === Methods::normalize( 'card' ), 'Non-array methods must be rejected.' );
	} );
	sao_case( 'methods_automatic_remains_card_only', function () {
		global $sao_options; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'ideal', 'wechat_pay' );
		sao_expect( array( 'card' ) === Methods::selected( 'USD' ), 'Default must not enable new methods.' );
	} );
	sao_case( 'methods_currency_filter_keeps_eligible_selection', function () {
		global $sao_options; $sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'ideal', 'card', 'paynow' );
		sao_expect( array( 'card' ) === Methods::selected( 'USD' ), 'USD filtering mismatch.' );
		sao_expect( array( 'ideal', 'card' ) === Methods::selected( 'eur' ), 'EUR filtering mismatch.' );
	} );
	sao_case( 'methods_empty_or_ineligible_fail_closed', function () {
		global $sao_options; $sao_options['sahcfwc_stripe_payment_method_status'] = 'manual';
		foreach ( array( array(), array( 'giropay' ), array( 'ideal' ) ) as $selected ) {
			$sao_options['sahcfwc_stripe_payment_methodes'] = $selected;
			sao_throws( function () { Methods::selected( 'USD' ); }, 'No configured payment method' );
		}
	} );
	sao_case( 'link_requires_cards', function () {
		global $sao_options, $sao_transport;
		$sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'link' );
		sao_throws( function () { Methods::selected( 'USD' ); }, 'requires' );
		sao_expect( 'failure' === ( new Gateway() )->process_payment( sao_order()->get_id() )['result'] && empty( $sao_transport->sessions ), 'Link without Cards reached Session creation.' );
	} );
	sao_case( 'link_with_cards_native_payload', function () {
		global $sao_options;
		$sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'card', 'link' );
		$order = sao_order(); $result = ( new Gateway() )->process_payment( $order->get_id() );
		sao_expect( 'success' === $result['result'], 'Link with Cards was rejected.' );
		$payload = sao_last_session_payload(); sao_expect( array( 'card', 'link' ) === $payload['payment_method_types'], 'Link and Cards payload mismatch.' ); sao_assert_binding( $payload, $order );
	} );
	foreach ( array( 'customer_balance' => 'USD', 'us_bank_account' => 'USD', 'acss_debit' => 'CAD', 'sepa_debit' => 'EUR', 'au_becs_debit' => 'AUD', 'bacs_debit' => 'GBP', 'oxxo' => 'MXN', 'konbini' => 'JPY', 'boleto' => 'BRL' ) as $method => $currency ) {
		sao_case( 'delayed_webhook_required_' . $method, function () use ( $method, $currency ) {
			global $sao_options; $sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( $method );
			sao_throws( function () use ( $currency ) { Methods::selected( $currency ); }, 'webhook' );
			$sao_options['sahcfwc_stripe_webhook_key'] = 'whsec_QAOfflineOnly';
			sao_expect( array( $method ) === Methods::selected( $currency ), 'Configured webhook did not allow delayed method.' );
		} );
	}
	sao_case( 'wechat_client_web_and_stale_bank_options_removed', function () {
		global $sao_options; $sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'wechat_pay' );
		$params = Methods::configure( array( 'payment_method_options' => array( 'customer_balance' => array( 'stale' => true ), 'wechat_pay' => array( 'client' => 'ios' ) ) ), sao_order() );
		sao_expect( array( 'wechat_pay' => array( 'client' => 'web' ) ) === $params['payment_method_options'], 'Wrong WeChat client or stale bank options.' );
	} );
	sao_case( 'acss_missing_or_invalid_mandate_fails_before_session', function () {
		global $sao_options, $sao_transport;
		$sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'acss_debit' ); $sao_options['sahcfwc_stripe_webhook_key'] = 'whsec_QAOfflineOnly';
		$order = sao_order( 'CAD' );
		foreach ( array( '', 'invalid', 'PERSONAL', array( 'personal' ) ) as $type ) {
			$sao_options['sahcfwc_stripe_acss_transaction_type'] = $type;
			sao_throws( function () use ( $order ) { Methods::configure( array(), $order ); }, 'mandate type' );
		}
		sao_expect( empty( $sao_transport->sessions ), 'Missing ACSS mandate created a Session.' );
	} );
	foreach ( array( 'personal', 'business' ) as $type ) {
		sao_case( 'acss_native_payload_' . $type, function () use ( $type ) {
			global $sao_options;
			$sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'acss_debit' ); $sao_options['sahcfwc_stripe_webhook_key'] = 'whsec_QAOfflineOnly'; $sao_options['sahcfwc_stripe_acss_transaction_type'] = $type;
			$order = sao_order( 'CAD' ); $result = ( new Gateway() )->process_payment( $order->get_id() );
			sao_expect( 'success' === $result['result'], 'ACSS native checkout failed.' );
			$payload = sao_last_session_payload();
			sao_expect( array( 'acss_debit' ) === $payload['payment_method_types'], 'ACSS payment method was not selected.' );
			sao_expect( array( 'payment_schedule' => 'sporadic', 'transaction_type' => $type ) === $payload['payment_method_options']['acss_debit']['mandate_options'], 'ACSS mandate payload mismatch.' );
			sao_assert_binding( $payload, $order );
		} );
	}
	foreach ( array( 'default' => null, 'fr' => 'fr', 'fr-CA' => 'fr-CA', 'zh-TW' => 'zh-TW', 'auto' => null, 'invalid' => null ) as $name => $expected ) {
		sao_case( 'native_payload_locale_' . $name, function () use ( $name, $expected ) {
			global $sao_options;
			if ( 'default' !== $name ) { $sao_options['sahcfwc_stripe_locale_status'] = 'custom'; $sao_options['sahcfwc_stripe_locale'] = 'invalid' === $name ? 'default' : $name; }
			$order = sao_order(); WC()->cart->set_cart_contents( array() );
			$result = ( new Gateway() )->process_payment( $order->get_id() ); sao_expect( 'success' === $result['result'], 'Native gateway failed.' );
			$payload = sao_last_session_payload(); sao_expect( $expected === ( $payload['locale'] ?? null ), 'Native locale payload mismatch.' );
			sao_expect( array( 'card' ) === $payload['payment_method_types'], 'Native default is not card-only.' ); sao_assert_binding( $payload, $order );
		} );
	}
	foreach ( array( 'default', 'fr-CA', 'wechat_pay' ) as $choice ) {
		sao_case( 'ajax_payload_' . $choice, function () use ( $choice ) {
			global $sao_options, $sao_product, $sao_ids;
			WC()->session->set( 'order_awaiting_payment', null ); WC()->cart->set_cart_contents( array() ); WC()->cart->set_applied_coupons( array() );
			if ( 'fr-CA' === $choice ) { $sao_options['sahcfwc_stripe_locale_status'] = 'custom'; $sao_options['sahcfwc_stripe_locale'] = 'fr-CA'; }
			if ( 'wechat_pay' === $choice ) { $sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'wechat_pay' ); }
			sao_expect( false !== WC()->cart->add_to_cart( $sao_product->get_id(), 2 ), 'Could not seed isolated cart.' );
			$result = ( new CheckoutAjax() )->sahcfwc_strip_checkout_updated_url_handler();
			$order_id = WC()->session->get( 'order_awaiting_payment' ); if ( $order_id ) { $sao_ids['orders'][] = $order_id; }
			sao_expect( is_array( $result ) && 'success' === $result['status'], 'AJAX checkout failed.' );
			$payload = sao_last_session_payload(); $order = wc_get_order( $order_id );
			sao_expect( ( 'fr-CA' === $choice ? 'fr-CA' : null ) === ( $payload['locale'] ?? null ), 'AJAX locale payload mismatch.' );
			sao_expect( array( 'wechat_pay' === $choice ? 'wechat_pay' : 'card' ) === $payload['payment_method_types'], 'AJAX selected methods mismatch.' );
			sao_expect( 2000 === (int) $order->get_meta( 'sahcfwc_checkout_expected_amount' ) && '20.00' === $order->get_total(), 'AJAX snapshot total changed.' );
			sao_expect( $payload['metadata']['sahcfwc_order_key'] === $order->get_order_key(), 'AJAX session binding mismatch.' );
			if ( 'wechat_pay' === $choice ) { sao_expect( 'web' === $payload['payment_method_options']['wechat_pay']['client'], 'AJAX WeChat option missing.' ); }
		} );
	}
	sao_case( 'native_ineligible_method_no_session_created', function () {
		global $sao_options, $sao_transport; $sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'ideal' );
		$result = ( new Gateway() )->process_payment( sao_order()->get_id() );
		sao_expect( 'failure' === $result['result'] && empty( $sao_transport->sessions ), 'Ineligible method exposed a payment session.' );
	} );
	sao_case( 'native_language_change_does_not_reuse_old_session', function () {
		global $sao_options, $sao_transport; $order = sao_order(); $gateway = new Gateway();
		sao_expect( 'success' === $gateway->process_payment( $order->get_id() )['result'], 'Initial native session failed.' );
		$sao_options['sahcfwc_stripe_locale_status'] = 'custom'; $sao_options['sahcfwc_stripe_locale'] = 'fr';
		$result = $gateway->process_payment( $order->get_id() );
		sao_expect( 'failure' === $result['result'] && 1 === count( $sao_transport->sessions ), 'Changed locale silently reused or duplicated an active session.' );
	} );
	sao_case( 'bank_transfer_payload_customer_and_repeat_session', function () {
		global $sao_options, $sao_transport;
		$sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'customer_balance' ); $sao_options['sahcfwc_stripe_webhook_key'] = 'whsec_QAOfflineOnly';
		$order = sao_order(); $gateway = new Gateway();
		sao_expect( 'success' === $gateway->process_payment( $order->get_id() )['result'], 'Bank transfer session failed.' );
		$payload = sao_last_session_payload();
		sao_expect( isset( $payload['customer'] ) && ! isset( $payload['customer_email'] ) && ! isset( $payload['customer_creation'] ), 'Bank Customer was not bound exclusively.' );
		sao_expect( array( 'funding_type' => 'bank_transfer', 'bank_transfer' => array( 'type' => 'us_bank_transfer' ) ) === $payload['payment_method_options']['customer_balance'], 'US bank transfer options mismatch.' );
		sao_assert_binding( $payload, $order );
		sao_expect( 'success' === $gateway->process_payment( $order->get_id() )['result'], 'Bank retry failed.' );
		sao_expect( 1 === count( $sao_transport->customers ) && 1 === count( $sao_transport->sessions ), 'Bank retry duplicated Customer or Session.' );
	} );
	sao_case( 'bank_lost_session_response_same_key_reuses_session', function () {
		global $sao_options, $sao_transport;
		$sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'customer_balance' ); $sao_options['sahcfwc_stripe_webhook_key'] = 'whsec_QAOfflineOnly';
		$order = sao_order(); $gateway = new Gateway(); $sao_transport->lose_session_response = true;
		sao_expect( 'failure' === $gateway->process_payment( $order->get_id() )['result'], 'Lost response must not expose a checkout URL.' );
		sao_expect( '' === wc_get_order( $order->get_id() )->get_meta( 'sahcfwc_stripe_checkout_session_id' ), 'Lost response unexpectedly persisted a Session ID.' );
		sao_expect( 'success' === $gateway->process_payment( $order->get_id() )['result'], 'Same-key uncertain Session retry did not recover.' );
		sao_expect( 1 === count( $sao_transport->customers ) && 1 === count( $sao_transport->sessions ), 'Same-key retry created a duplicate Customer or Session.' );
		sao_expect( 2 === count( $sao_transport->matching( 'post', '/v1/checkout/sessions' ) ), 'Uncertain Session was not retrieved by idempotent retry.' );
	} );
	sao_case( 'bank_lost_session_then_key_rotation_fails_closed', function () {
		global $sao_options, $sao_transport;
		$sao_options['sahcfwc_stripe_payment_method_status'] = 'manual'; $sao_options['sahcfwc_stripe_payment_methodes'] = array( 'customer_balance' ); $sao_options['sahcfwc_stripe_webhook_key'] = 'whsec_QAOfflineOnly';
		$order = sao_order(); $sao_transport->lose_session_response = true;
		sao_expect( 'failure' === ( new Gateway() )->process_payment( $order->get_id() )['result'], 'Lost response must fail safely.' );
		$saved = wc_get_order( $order->get_id() ); $original_scope = $saved->get_meta( 'sahcfwc_checkout_customer_scope' );
		sao_expect( '' !== $original_scope && '' !== $saved->get_meta( 'sahcfwc_checkout_payload_hash' ) && '' === $saved->get_meta( 'sahcfwc_stripe_checkout_session_id' ), 'Uncertain bank-request fixture is invalid.' );
		$original_secret = $sao_options['sahcfwc_stripe_test_secret_key'];
		$sao_options['sahcfwc_stripe_test_secret_key'] = 'sk_test_QARotatedScope'; $rotated_gateway = new Gateway();
		sao_expect( 'failure' === $rotated_gateway->process_payment( $order->get_id() )['result'], 'Rotated credentials must not replace an uncertain bank request.' );
		sao_expect( 1 === count( $sao_transport->matching( 'post', '/v1/customers' ) ) && 1 === count( $sao_transport->matching( 'post', '/v1/checkout/sessions' ) ), 'Credential rotation sent another Customer or Session request.' );
		sao_expect( $original_scope === wc_get_order( $order->get_id() )->get_meta( 'sahcfwc_checkout_customer_scope' ), 'Rejected rotation replaced the bound Customer scope.' );
		// Retry the same native payload, not an artificial mode-only reflection call.
		// Restoring credentials must recover the original Session without duplication.
		$sao_options['sahcfwc_stripe_test_secret_key'] = $original_secret;
		sao_expect( 'success' === ( new Gateway() )->process_payment( $order->get_id() )['result'], 'Restored credentials did not recover the original request.' );
		sao_expect( 1 === count( $sao_transport->customers ) && 1 === count( $sao_transport->sessions ), 'Recovery duplicated the Customer or Session.' );
	} );
	sao_case( 'bank_customer_binding_ignores_legacy_customer', function () {
		global $sao_transport; $order = sao_order(); $params = array( 'payment_method_types' => array( 'customer_balance' ), 'customer' => 'cus_LegacyOtherAccount', 'customer_email' => 'qa-options@example.test', 'customer_creation' => 'always' );
		$result = Methods::prepare_customer( $params, $order, new StripeClient( 'sk_test_QAFirstScope' ), 'sk_test_QAFirstScope' );
		sao_expect( 'cus_QA1' === $result['customer'] && ! isset( $result['customer_email'] ) && ! isset( $result['customer_creation'] ), 'Legacy customer/email was retained.' );
		$request = $sao_transport->matching( 'post', '/v1/customers' )[0]['params'];
		sao_expect( array( 'sahcfwc_order_id' => (string) $order->get_id(), 'sahcfwc_order_key' => $order->get_order_key() ) === $request['metadata'], 'Customer metadata not bound to order.' );
		$order->set_billing_email( 'qa-updated@example.test' ); $order->save();
		$again = Methods::prepare_customer( $params, $order, new StripeClient( 'sk_test_QAFirstScope' ), 'sk_test_QAFirstScope' );
		sao_expect( $result['customer'] === $again['customer'] && 1 === count( $sao_transport->matching( 'post', '/v1/customers' ) ), 'Same-scope customer was not reused.' );
	} );
	sao_case( 'bank_customer_credential_and_mode_scope', function () {
		global $sao_transport; $order = sao_order(); $params = array( 'payment_method_types' => array( 'customer_balance' ) ); $customers = array();
		foreach ( array( 'sk_test_QAFirstScope', 'sk_test_QAOtherAccount', 'sk_live_QALiveScope' ) as $secret ) {
			$result = Methods::prepare_customer( $params, $order, new StripeClient( $secret ), $secret ); $customers[] = $result['customer'];
		}
		sao_expect( 3 === count( array_unique( $customers ) ) && 3 === count( $sao_transport->customers ), 'Customer was reused across credentials or mode.' );
		sao_expect( false === $sao_transport->customers[0]['livemode'] && true === $sao_transport->customers[2]['livemode'], 'Mock mode boundary was not exercised.' );
	} );
	sao_case( 'bank_customer_lost_response_idempotent_retry', function () {
		global $sao_transport; $order = sao_order(); $params = array( 'payment_method_types' => array( 'customer_balance' ) ); $secret = 'sk_test_QARetry'; $client = new StripeClient( $secret );
		$sao_transport->lose_customer_response = true;
		sao_throws( function () use ( $params, $order, $client, $secret ) { Methods::prepare_customer( $params, $order, $client, $secret ); }, 'lost customer response' );
		sao_expect( '' === $order->get_meta( 'sahcfwc_checkout_customer_id' ), 'Unknown Customer was prematurely bound.' );
		$result = Methods::prepare_customer( $params, $order, $client, $secret ); $requests = $sao_transport->matching( 'post', '/v1/customers' );
		sao_expect( 'cus_QA1' === $result['customer'] && 1 === count( $sao_transport->customers ) && 2 === count( $requests ), 'Retry did not recover exactly one Customer.' );
		sao_expect( $requests[0]['idempotency'] === $requests[1]['idempotency'], 'Customer retry key changed.' );
	} );
	foreach ( array( 'wrong_mode', 'missing_mode', 'invalid_id' ) as $fault ) {
		sao_case( 'bank_customer_reject_' . $fault, function () use ( $fault ) {
			global $sao_transport; $order = sao_order();
			if ( 'wrong_mode' === $fault ) { $sao_transport->customer_mode_override = true; }
			if ( 'missing_mode' === $fault ) { $sao_transport->omit_customer_mode = true; }
			if ( 'invalid_id' === $fault ) { $sao_transport->invalid_customer_id = true; }
			sao_throws( function () use ( $order ) { Methods::prepare_customer( array( 'payment_method_types' => array( 'customer_balance' ) ), $order, new StripeClient( 'sk_test_QAInvalid' ), 'sk_test_QAInvalid' ); }, 'could not be verified' );
			sao_expect( '' === $order->get_meta( 'sahcfwc_checkout_customer_id' ), 'Rejected Customer was persisted.' );
		} );
	}
	sao_case( 'settings_registered_defaults_and_array_schema', function () {
		$settings = get_registered_settings();
		sao_expect( 'default' === $settings['sahcfwc_stripe_locale_status']['default'] && 'en' === $settings['sahcfwc_stripe_locale']['default'], 'Locale defaults changed.' );
		sao_expect( 'automatic' === $settings['sahcfwc_stripe_payment_method_status']['default'], 'Payment default changed.' );
		sao_expect( '' === $settings['sahcfwc_stripe_acss_transaction_type']['default'], 'ACSS must not assume personal or business use.' );
		sao_expect( array( '', 'personal', 'business' ) === $settings['sahcfwc_stripe_acss_transaction_type']['show_in_rest']['schema']['enum'], 'ACSS REST enum mismatch.' );
		$schema = $settings['sahcfwc_stripe_payment_methodes']['show_in_rest']['schema']['items'];
		sao_expect( 'object' === $schema['type'] && in_array( 'id', $schema['required'], true ), 'Legacy object-array schema missing.' );
		foreach ( array( 'sahcfwc_stripe_locale', 'sahcfwc_stripe_locale_status', 'sahcfwc_stripe_payment_methodes', 'sahcfwc_stripe_payment_method_status', 'sahcfwc_stripe_acss_transaction_type' ) as $name ) { sao_expect( is_callable( $settings[ $name ]['sanitize_callback'] ), 'Registered sanitizer is not callable.' ); }
	} );
	$sao_users = array();
	foreach ( array( 'administrator', 'subscriber' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'qa_options_' . str_replace( '-', '', wp_generate_uuid4() ), 'user_pass' => wp_generate_password( 32, true, true ), 'role' => $role ) );
		sao_expect( ! is_wp_error( $id ), 'Could not create rollback-only test user.' ); $sao_ids['users'][] = $id; $sao_users[ $role ] = $id;
	}
	foreach ( array( 'anonymous' => 0, 'subscriber' => $sao_users['subscriber'], 'administrator' => $sao_users['administrator'] ) as $role => $id ) {
		sao_case( 'rest_settings_permission_' . $role, function () use ( $role, $id ) {
			wp_set_current_user( $id ); $response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/settings' ) );
			sao_expect( 'administrator' === $role ? 200 === $response->get_status() : in_array( $response->get_status(), array( 401, 403 ), true ), 'Settings permission boundary failed.' );
			$route = '/sahcfwc/v1/stripe-local-payment-methods';
			$response = rest_do_request( new WP_REST_Request( 'GET', $route ) );
			if ( 'administrator' === $role ) {
				$data = $response->get_data();
				sao_expect( 200 === $response->get_status() && is_array( $data ) && ! empty( $data ), 'Administrator payment-method response failed.' );
				sao_expect( ! in_array( 'giropay', array_column( $data, 'id' ), true ), 'Retired payment method is still advertised.' );
			} else {
				sao_expect( in_array( $response->get_status(), array( 401, 403 ), true ), 'Payment-method route permission boundary failed.' );
			}
		} );
	}
	sao_case( 'rest_admin_save_locale_and_legacy_methods', function () use ( $sao_users, $sao_setting_names, $sao_option_filters ) {
		wp_set_current_user( $sao_users['administrator'] );
		foreach ( $sao_setting_names as $name ) { remove_filter( 'pre_option_' . $name, $sao_option_filters[ $name ], PHP_INT_MAX ); }
		try {
			$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
			$request->set_body_params( array( 'sahcfwc_stripe_locale_status' => 'custom', 'sahcfwc_stripe_locale' => 'fr-CA', 'sahcfwc_stripe_payment_method_status' => 'manual', 'sahcfwc_stripe_payment_methodes' => array( array( 'id' => 'visa' ), array( 'id' => 'wechat_pay' ), array( 'id' => 'giropay' ) ) ) );
			$response = rest_do_request( $request ); sao_expect( 200 === $response->get_status(), 'Administrator configuration save rejected.' );
			sao_expect( 'fr-CA' === get_option( 'sahcfwc_stripe_locale' ) && 'custom' === get_option( 'sahcfwc_stripe_locale_status' ), 'Locale did not persist.' );
			sao_expect( array( 'card', 'wechat_pay' ) === Methods::normalize( get_option( 'sahcfwc_stripe_payment_methodes' ) ), 'Legacy saved methods were not normalized.' );
			$request = new WP_REST_Request( 'POST', '/wp/v2/settings' ); $request->set_body_params( array( 'sahcfwc_stripe_locale' => 'default' ) );
			sao_expect( 200 === rest_do_request( $request )->get_status() && 'auto' === get_option( 'sahcfwc_stripe_locale' ), 'Invalid legacy locale was not safely sanitized.' );
		} finally { foreach ( $sao_setting_names as $name ) { add_filter( 'pre_option_' . $name, $sao_option_filters[ $name ], PHP_INT_MAX ); } }
	} );
	sao_case( 'rest_rejects_wrong_method_schema_and_status', function () use ( $sao_users ) {
		wp_set_current_user( $sao_users['administrator'] );
		foreach ( array( array( 'sahcfwc_stripe_payment_methodes' => array( 'card' ) ), array( 'sahcfwc_stripe_payment_method_status' => 'unsupported' ), array( 'sahcfwc_stripe_locale' => array( 'fr' ) ) ) as $params ) {
			$request = new WP_REST_Request( 'POST', '/wp/v2/settings' ); $request->set_body_params( $params ); sao_expect( 400 === rest_do_request( $request )->get_status(), 'Invalid REST shape/status was accepted.' );
		}
	} );
	sao_case( 'rest_acss_type_validation_and_save', function () use ( $sao_users, $sao_option_filters ) {
		wp_set_current_user( $sao_users['administrator'] ); $name = 'sahcfwc_stripe_acss_transaction_type';
		remove_filter( 'pre_option_' . $name, $sao_option_filters[ $name ], PHP_INT_MAX );
		try {
			foreach ( array( 'personal', 'business', '' ) as $value ) {
				$request = new WP_REST_Request( 'POST', '/wp/v2/settings' ); $request->set_body_params( array( $name => $value ) );
				sao_expect( 200 === rest_do_request( $request )->get_status() && $value === get_option( $name ), 'Valid ACSS type did not persist.' );
			}
			foreach ( array( 'invalid', 'PERSONAL', array( 'personal' ) ) as $value ) {
				$request = new WP_REST_Request( 'POST', '/wp/v2/settings' ); $request->set_body_params( array( $name => $value ) );
				sao_expect( 400 === rest_do_request( $request )->get_status() && '' === get_option( $name ), 'Invalid ACSS type was accepted or changed saved setting.' );
			}
		} finally { add_filter( 'pre_option_' . $name, $sao_option_filters[ $name ], PHP_INT_MAX ); }
	} );
	sao_case( 'rest_subscriber_cannot_save_settings', function () use ( $sao_users ) {
		wp_set_current_user( $sao_users['subscriber'] ); $request = new WP_REST_Request( 'POST', '/wp/v2/settings' ); $request->set_body_params( array( 'sahcfwc_stripe_locale' => 'fr' ) );
		sao_expect( 403 === rest_do_request( $request )->get_status(), 'Subscriber was able to change checkout configuration.' );
	} );
} catch ( Throwable $error ) {
	$sao_results['harness_setup_or_execution'] = array( 'pass' => false, 'error' => $error->getMessage() );
} finally {
	if ( $sao_transaction ) { $wpdb->query( 'ROLLBACK' ); $sao_transaction = false; }
	restore_error_handler();
	foreach ( $sao_option_filters as $name => $callback ) { remove_filter( 'pre_option_' . $name, $callback, PHP_INT_MAX ); wp_cache_delete( $name, 'options' ); }
	wp_cache_delete( 'alloptions', 'options' ); WC()->session = $sao_original_session; WC()->customer = $sao_original_customer; wp_set_current_user( $sao_original_user );
	// Keep network/mail blocks and the SDK mock installed through CLI shutdown.
}
$remaining = 0; $post_ids = array_merge( $sao_ids['products'], $sao_ids['orders'] );
if ( $post_ids ) { $remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', array_map( 'absint', $post_ids ) ) . ')' ); }
if ( $sao_ids['orders'] && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_orders' ) ) ) { $remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE id IN (" . implode( ',', array_map( 'absint', $sao_ids['orders'] ) ) . ')' ); }
if ( $sao_ids['users'] ) { $remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN (" . implode( ',', array_map( 'absint', $sao_ids['users'] ) ) . ')' ); }
$sao_after_settings = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($sao_sql_names) ORDER BY option_name", ARRAY_A );
$sao_results['rollback_fixtures_and_settings'] = array( 'pass' => 0 === $remaining && $sao_before_settings === $sao_after_settings, 'remaining_fixture_rows' => $remaining, 'settings_restored' => $sao_before_settings === $sao_after_settings );
$sao_results['no_php_warnings'] = array( 'pass' => empty( $sao_warnings ), 'warnings' => array_values( array_unique( $sao_warnings ) ) );
$sao_pass = ! in_array( false, array_column( $sao_results, 'pass' ), true );
echo wp_json_encode( array( 'suite' => 'free-checkout-options', 'network' => 'offline_sdk_mock_and_wp_http_block', 'pass' => $sao_pass, 'cases' => count( $sao_results ), 'results' => $sao_results ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
if ( ! $sao_pass ) { WP_CLI::halt( 1 ); }
