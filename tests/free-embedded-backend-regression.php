<?php
/**
 * Offline Embedded Checkout backend regression on the disposable FREE site.
 *
 * Run with WP-CLI eval-file and WP_HTTP_BLOCK_EXTERNAL=true before bootstrap.
 * Real WordPress/WooCommerce, strict mocked Stripe transport, transaction rollback,
 * in-process option overrides, no emails or external requests, no premium runtime.
 */
use SAHCFWC\Classes\SAHCFWC_Checkout_Experience as SAE_Experience;
use SAHCFWC\Classes\SAHCFWC_Payment_Completion as SAE_Completion;
use SAHCFWC\Libraries\Stripe\ApiRequestor;
use SAHCFWC\Libraries\Stripe\StripeClient;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL
	|| function_exists( 'sahcfwc_fs' ) || ! function_exists( 'WC' )
	|| 'http://127.0.0.1:8888' !== rtrim( home_url(), '/' )
	|| 'sa-hosted-checkout-for-woocommerce' !== basename( rtrim( realpath( SAHCFWC_DIR ), '/\\' ) ) ) {
	throw new RuntimeException( 'Requires the disposable local FREE site with external HTTP blocked.' );
}
require_once SAHCFWC_DIR . '/includes/classes/class-sahcfwc-payment-completion.php';
require_once SAHCFWC_DIR . '/includes/classes/class-sahcfwc-checkout-experience.php';
add_filter( 'pre_http_request', function () { return new WP_Error( 'sae_offline', 'Offline regression.' ); }, PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
if ( ! defined( 'DOING_AJAX' ) ) { define( 'DOING_AJAX', true ); }

class SAE_JSON_Exit extends Error {}
class SAE_Builder {
	use SAHCFWC\Traits\SAHCFWC_Order_Totals;
	use SAHCFWC\Traits\SAHCFWC_Helpers;
	public $sahcfwc_stripe_secret = 'sk_test_dummy';
	public function create( $order ) {
		return $this->sahcfwc_create_order_checkout_session( $order, array( 'mode' => 'payment', 'success_url' => home_url(), 'cancel_url' => home_url() ) );
	}
}
class SAE_HTTP implements SAHCFWC\Libraries\Stripe\HttpClient\ClientInterface {
	public $sessions = array(), $requests = array(), $cache = array();
	public $lose_response = false, $fail_retrieve = false;
	public function request( $method, $url, $headers, $params, $has_file, $api_mode = 'v1', $max_network_retries = null ) {
		$method = strtolower( $method ); $path = wp_parse_url( $url, PHP_URL_PATH ); $key = ''; $version = '';
		foreach ( $headers as $name => $value ) {
			$header = is_string( $name ) ? $name . ': ' . $value : $value;
			if ( 0 === stripos( $header, 'Idempotency-Key: ' ) ) { $key = substr( $header, 17 ); }
			if ( 0 === stripos( $header, 'Stripe-Version: ' ) ) { $version = substr( $header, 16 ); }
		}
		$this->requests[] = array( 'method' => $method, 'path' => $path, 'params' => $params, 'idempotency' => $key, 'version' => $version );
		if ( 'post' === $method && '/v1/checkout/sessions' === $path ) {
			if ( isset( $this->cache[ $key ] ) ) {
				sae_expect( $this->cache[ $key ]['params'] === $params, 'Idempotent request parameters changed.' );
				$data = $this->sessions[ $this->cache[ $key ]['id'] ];
			} else {
				$total = 0;
				foreach ( $params['line_items'] as $line ) { $total += $line['quantity'] * $line['price_data']['unit_amount']; }
				$total += $params['shipping_options'][0]['shipping_rate_data']['fixed_amount']['amount'] ?? 0;
				sae_expect( empty( $params['discounts'] ), 'Embedded fixture unexpectedly requires a coupon endpoint.' );
				$id = 'cs_test_embedded_' . ( count( $this->sessions ) + 1 );
				$ui = $params['ui_mode'] ?? 'hosted';
				$data = array(
					'id' => $id, 'object' => 'checkout.session', 'mode' => 'payment', 'status' => 'open',
					'payment_status' => 'unpaid', 'livemode' => false, 'currency' => $params['line_items'][0]['price_data']['currency'],
					'amount_total' => $total, 'amount_subtotal' => $total, 'expires_at' => $params['expires_at'],
					'metadata' => $params['metadata'], 'ui_mode' => $ui, 'payment_intent' => 'pi_embedded_fixture',
					'url' => 'hosted' === $ui ? 'https://checkout.stripe.com/offline-embedded-regression' : null,
					'client_secret' => 'embedded' === $ui ? $id . '_secret_fixture' : null,
				);
				$this->sessions[ $id ] = $data;
				$this->cache[ $key ] = array( 'params' => $params, 'id' => $id );
				if ( $this->lose_response ) {
					$this->lose_response = false;
					throw SAHCFWC\Libraries\Stripe\Exception\ApiConnectionException::factory( 'Simulated uncertain response.' );
				}
			}
		} elseif ( 'get' === $method && isset( $this->sessions[ basename( $path ) ] ) ) {
			if ( $this->fail_retrieve ) {
				throw SAHCFWC\Libraries\Stripe\Exception\ApiConnectionException::factory( 'Unsafe fixture message sk_test_dummy must never reach shoppers.' );
			}
			$data = $this->sessions[ basename( $path ) ];
		} elseif ( 'post' === $method && preg_match( '#/checkout/sessions/([^/]+)/expire$#', $path, $match ) && isset( $this->sessions[ $match[1] ] ) ) {
			$this->sessions[ $match[1] ]['status'] = 'expired'; $data = $this->sessions[ $match[1] ];
		} else {
			throw new RuntimeException( 'Unexpected mocked Stripe route.' );
		}
		return array( wp_json_encode( $data ), 200, array() );
	}
	public function payload() {
		$requests = array_values( array_filter( $this->requests, function ( $request ) { return 'post' === $request['method'] && '/v1/checkout/sessions' === $request['path']; } ) );
		return end( $requests )['params'];
	}
	public function creation_count() {
		return count( array_filter( $this->requests, function ( $request ) { return 'post' === $request['method'] && '/v1/checkout/sessions' === $request['path']; } ) );
	}
}
function sae_expect( $condition, $message ) {
	global $sae_assertions;
	$sae_assertions++;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function sae_throws( $callback, $contains = '' ) {
	try { $callback(); } catch ( Exception $error ) {
		sae_expect( ! $contains || false !== stripos( $error->getMessage(), $contains ), 'Expected safe validation error was not returned.' ); return;
	}
	throw new RuntimeException( 'Expected validation error was not raised.' );
}
function sae_case( $name, $callback ) {
	global $sae_results, $sae_options, $sae_base, $sae_http;
	$sae_options = $sae_base; $sae_http = new SAE_HTTP(); ApiRequestor::setHttpClient( $sae_http ); wp_set_current_user( 0 );
	try { $callback(); $sae_results[ $name ] = array( 'pass' => true ); }
	catch ( Throwable $error ) { $sae_results[ $name ] = array( 'pass' => false, 'error' => $error->getMessage() ); }
}
function sae_order() {
	global $sae_product, $sae_ids;
	$order = new WC_Order(); $order->set_status( 'pending' ); $order->set_currency( 'USD' ); $order->set_payment_method( SAE_Completion::GATEWAY );
	$order->add_product( $sae_product, 2, array( 'subtotal' => 20, 'total' => 20 ) );
	$fee = new WC_Order_Item_Fee(); $fee->set_name( 'Embedded handling' ); $fee->set_total( 2 ); $order->add_item( $fee );
	$shipping = new WC_Order_Item_Shipping(); $shipping->set_method_title( 'Fixed shipping' ); $shipping->set_method_id( 'flat_rate' ); $shipping->set_total( 3 ); $order->add_item( $shipping );
	$order->calculate_totals( false ); $order->save(); $sae_ids['orders'][] = $order->get_id(); return $order;
}
function sae_endpoint( $order, $overrides = array(), $method = 'POST' ) {
	$saved_post = $_POST; $saved_method = $_SERVER['REQUEST_METHOD'] ?? null;
	$_POST = array_merge( array( 'order_id' => $order->get_id(), 'order_key' => $order->get_order_key(), 'security' => wp_create_nonce( 'sahcfwc-embedded-' . $order->get_id() . '-' . $order->get_order_key() ) ), $overrides );
	$_SERVER['REQUEST_METHOD'] = $method;
	$handler = function () { return function () { throw new SAE_JSON_Exit( 'JSON response intercepted.' ); }; };
	add_filter( 'wp_die_ajax_handler', $handler, PHP_INT_MAX );
	ob_start();
	try { SAE_Experience::get_instance()->embedded_session(); }
	catch ( SAE_JSON_Exit $error ) {}
	finally {
		$output = ob_get_clean(); remove_filter( 'wp_die_ajax_handler', $handler, PHP_INT_MAX );
		$_POST = $saved_post; if ( null === $saved_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $saved_method; }
	}
	$data = json_decode( $output, true );
	sae_expect( is_array( $data ) && isset( $data['status'] ), 'Endpoint did not return a JSON contract.' );
	return $data;
}

global $wpdb, $sae_results, $sae_assertions, $sae_options, $sae_base, $sae_http, $sae_product, $sae_ids, $sae_filters;
$sae_results = array(); $sae_assertions = 0; $sae_warnings = array();
$sae_ids = array( 'orders' => array(), 'products' => array(), 'pages' => array() );
$sae_original_user = get_current_user_id();
$sae_base = array(
	'sahcfwc_stripe_checkout_status' => 'yes', 'sahcfwc_stripe_checkout_type' => 'embedded_page',
	'sahcfwc_stripe_integration_mode' => 'test-mode', 'sahcfwc_stripe_admin_test_mode_status' => 'no',
	'sahcfwc_api_key_type' => 'standard', 'sahcfwc_stripe_test_secret_key' => 'sk_test_dummy',
	'sahcfwc_stripe_test_public_key' => 'pk_test_fixture00', 'sahcfwc_stripe_live_public_key' => 'pk_live_fixture00',
	'sahcfwc_stripe_branding_enabled' => 'no', 'sahcfwc_stripe_branding_settings' => array(),
	'sahcfwc_stripe_embedded_page_id' => 0, 'sahcfwc_stripe_auto_embedded_page_id' => 0,
	'sahcfwc_stripe_adjustment_quantity' => 'no', 'sahcfwc_stripe_payment_method_status' => 'automatic',
	'sahcfwc_stripe_locale_status' => 'default', 'woocommerce_calc_taxes' => 'no',
);
$sae_options = $sae_base; $sae_filters = array();
foreach ( array_keys( $sae_base ) as $name ) {
	$sae_filters[ $name ] = function () use ( $name ) { global $sae_options; return $sae_options[ $name ]; };
	add_filter( 'pre_option_' . $name, $sae_filters[ $name ], PHP_INT_MAX );
}
$sae_setting_names = array( 'sahcfwc_stripe_checkout_type', 'sahcfwc_stripe_embedded_page_id', 'sahcfwc_stripe_auto_embedded_page_id', 'sahcfwc_embedded_page_creation_lock', 'sahcfwc_stripe_branding_enabled', 'sahcfwc_stripe_branding_settings' );
$sae_sql_names = "'" . implode( "','", array_map( 'esc_sql', $sae_setting_names ) ) . "'";
$sae_before = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($sae_sql_names) ORDER BY option_name", ARRAY_A );
$sae_transaction = false;
register_shutdown_function( function () use ( &$sae_transaction, $wpdb ) { if ( $sae_transaction ) { $wpdb->query( 'ROLLBACK' ); } } );
set_error_handler( function ( $severity, $message, $file, $line ) use ( &$sae_warnings ) { if ( error_reporting() & $severity ) { $sae_warnings[] = basename( $file ) . ':' . $line . ': ' . $message; } return true; } );

try {
	foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) {
		if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && in_array( $table['Engine'], array( 'MyISAM', 'MEMORY' ), true ) ) { throw new RuntimeException( 'Rollback-safe InnoDB tables required.' ); }
	}
	sae_expect( false !== $wpdb->query( 'START TRANSACTION' ), 'Could not start rollback transaction.' ); $sae_transaction = true;
	$sae_product = new WC_Product_Simple(); $sae_product->set_name( 'Offline Embedded regression' );
	$sae_product->set_status( 'publish' ); $sae_product->set_regular_price( '10' ); $sae_product->set_price( '10' );
	$sae_product->set_tax_status( 'none' ); $sae_product->set_manage_stock( false ); $sae_product->save(); $sae_ids['products'][] = $sae_product->get_id();
	$sae_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Offline Embedded payment fixture', 'post_content' => '[sahcfwc_checkout]' ), true );
	sae_expect( ! is_wp_error( $sae_page ), 'Payment page fixture failed.' ); $sae_ids['pages'][] = $sae_page;
	$sae_base['sahcfwc_stripe_auto_embedded_page_id'] = $sae_page; $sae_options = $sae_base;
	$sae_builder = new SAE_Builder();

	sae_case( 'mode_defaults_and_legacy_mapping', function () {
		sae_expect( 'hosted' === SAE_Experience::sanitize_mode( null ), 'Missing setting must preserve Hosted.' );
		sae_expect( 'hosted' === SAE_Experience::sanitize_mode( array() ), 'Invalid mode must preserve Hosted.' );
		sae_expect( 'embedded_popup' === SAE_Experience::sanitize_mode( 'embedded' ), 'Legacy premium Embedded mapping changed.' );
		foreach ( array( 'hosted', 'embedded_page', 'embedded_popup' ) as $mode ) { sae_expect( $mode === SAE_Experience::sanitize_mode( $mode ), 'Supported presentation rejected.' ); }
	} );
	sae_case( 'strict_publishable_key_allowlist', function () {
		sae_expect( 'pk_test_fixture00' === SAE_Experience::validate_public_key( ' pk_test_fixture00 ', 'test' ), 'Valid test key rejected.' );
		sae_expect( 'pk_live_fixture00' === SAE_Experience::validate_public_key( 'pk_live_fixture00', 'live' ), 'Valid live key rejected.' );
		foreach ( array( 'sk_test_dummy', 'rk_test_dummy', 'pk_live_fixture00', 'pk_test_short', 'pk_test_fixture00<script>', array(), null, 123, "pk_test_fixture00\nother" ) as $bad ) {
			sae_expect( '' === SAE_Experience::validate_public_key( $bad, 'test' ), 'Invalid/wrong-mode/secret public-key value accepted.' );
		}
	} );
	sae_case( 'legacy_wrong_field_secret_never_exposed', function () {
		global $sae_options; $sae_options['sahcfwc_stripe_test_public_key'] = 'sk_test_dummy';
		sae_expect( '' === SAE_Experience::public_key( 'test' ), 'Legacy wrong-field secret leaked.' );
		sae_expect( '' === ( new SAE_Builder() )->sahcfwc_get_stripe_public_key(), 'Legacy helper exposed a secret.' );
	} );
	sae_case( 'branding_supported_values_and_mutual_exclusive_logo', function () {
		$clean = SAE_Experience::sanitize_branding( array( 'display_name' => '<b>Store</b>', 'background_color' => '#ABCDEF', 'button_color' => '#123456', 'font_family' => 'inter', 'border_style' => 'rounded', 'logo_url' => 'https://example.com/logo.png', 'icon_url' => 'https://example.com/icon.png', 'injected' => 'ignored' ) );
		sae_expect( 'Store' === $clean['display_name'] && '#abcdef' === $clean['background_color'], 'Brand name or color was not sanitized.' );
		sae_expect( 'inter' === $clean['font_family'] && 'rounded' === $clean['border_style'], 'Supported branding enums changed.' );
		sae_expect( isset( $clean['logo_url'] ) && ! isset( $clean['icon_url'], $clean['injected'] ), 'Unexpected branding fields survived.' );
	} );
	sae_case( 'branding_rejects_untrusted_values', function () {
		$clean = SAE_Experience::sanitize_branding( array( 'display_name' => array(), 'background_color' => 'red', 'button_color' => '#123;display:none', 'font_family' => 'arbitrary', 'border_style' => 'evil', 'logo_url' => 'javascript:alert(1)', 'icon_url' => 'http://example.com/icon.png' ) );
		sae_expect( array() === $clean, 'Unsafe branding survived validation.' );
		foreach ( array( 'https://127.0.0.1/logo.png', 'https://localhost/logo.png', 'https://192.168.1.1/logo.png', 'https://user:password@example.com/logo.png' ) as $url ) { sae_expect( array() === SAE_Experience::sanitize_branding( array( 'logo_url' => $url ) ), 'Private or credential-bearing branding URL accepted.' ); }
		sae_expect( array() === SAE_Experience::sanitize_branding( 'not-an-object' ), 'Invalid object accepted.' );
	} );
	sae_case( 'branding_all_reviewed_fonts_and_shapes', function () {
		foreach ( SAE_Experience::fonts() as $font ) { sae_expect( array( 'font_family' => $font ) === SAE_Experience::sanitize_branding( array( 'font_family' => $font ) ), 'Supported font rejected.' ); }
		foreach ( array( 'pill', 'rectangular', 'rounded' ) as $shape ) { sae_expect( array( 'border_style' => $shape ) === SAE_Experience::sanitize_branding( array( 'border_style' => $shape ) ), 'Supported shape rejected.' ); }
	} );
	sae_case( 'branding_image_format_allowlist', function () {
		foreach ( array( 'png', 'jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'svg', 'PNG' ) as $extension ) {
			$url = 'https://example.com/logo.' . $extension . '?v=1';
			sae_expect( array( 'logo_url' => $url ) === SAE_Experience::sanitize_branding( array( 'logo_url' => $url ) ), 'Supported image URL path rejected.' );
		}
		foreach ( array( 'https://example.com/logo.webp', 'https://example.com/logo.gif', 'https://example.com/image?file=logo.png', 'https://example.com/logo.png/invalid' ) as $url ) {
			sae_expect( array() === SAE_Experience::sanitize_branding( array( 'logo_url' => $url ) ), 'Unsupported image URL path accepted.' );
		}
	} );
	sae_case( 'embedded_payload_has_return_not_hosted_urls', function () use ( $sae_builder ) {
		global $sae_http; $order = sae_order(); $session = $sae_builder->create( $order ); $params = $sae_http->payload();
		sae_expect( 'embedded' === $params['ui_mode'] && ! isset( $params['success_url'], $params['cancel_url'] ), 'Embedded includes Hosted-only URL parameters.' );
		sae_expect( false !== strpos( $params['return_url'], '{CHECKOUT_SESSION_ID}' ) && false !== strpos( $params['return_url'], 'order_key=' ), 'Server return binding missing.' );
		sae_expect( 2500 === $session->amount_total && empty( $params['branding_settings'] ), 'Totals or default branding changed.' );
		sae_expect( '2025-09-30.clover' === $sae_http->requests[0]['version'], 'SDK request is not pinned to reviewed Clover API.' );
	} );
	sae_case( 'hosted_payload_preserves_legacy_shape_without_public_key', function () use ( $sae_builder ) {
		global $sae_options, $sae_http; $sae_options['sahcfwc_stripe_checkout_type'] = 'hosted'; $sae_options['sahcfwc_stripe_test_public_key'] = '';
		$sae_builder->create( sae_order() ); $params = $sae_http->payload();
		sae_expect( isset( $params['success_url'], $params['cancel_url'] ) && ! isset( $params['ui_mode'], $params['return_url'] ), 'Hosted request shape changed.' );
	} );
	sae_case( 'session_branding_is_opt_in_and_not_account_mutation', function () use ( $sae_builder ) {
		global $sae_options, $sae_http; $sae_options['sahcfwc_stripe_branding_enabled'] = 'yes'; $sae_options['sahcfwc_stripe_checkout_type'] = 'hosted';
		$sae_options['sahcfwc_stripe_branding_settings'] = array( 'button_color' => '#112233', 'logo_url' => 'https://example.com/logo.png', 'font_family' => 'inter' );
		$sae_builder->create( sae_order() ); $params = $sae_http->payload();
		sae_expect( array( 'type' => 'url', 'url' => 'https://example.com/logo.png' ) === $params['branding_settings']['logo'], 'Branding image API shape invalid.' );
		sae_expect( '#112233' === $params['branding_settings']['button_color'] && 1 === count( $sae_http->requests ), 'Branding mutated an account or issued extra API calls.' );
	} );
	sae_case( 'embedded_branding_omits_unsupported_images_but_preserves_supported_fields', function () use ( $sae_builder ) {
		global $sae_options, $sae_http;
		$sae_options['sahcfwc_stripe_branding_enabled'] = 'yes';
		$supported = array( 'display_name' => 'Fixture store', 'background_color' => '#ffffff', 'button_color' => '#112233', 'font_family' => 'inter', 'border_style' => 'rounded' );
		foreach ( array( 'embedded_page', 'embedded_popup' ) as $mode ) {
			foreach ( array( 'logo_url', 'icon_url' ) as $image ) {
				$sae_options['sahcfwc_stripe_checkout_type'] = $mode;
				$sae_options['sahcfwc_stripe_branding_settings'] = $supported + array( $image => 'https://example.com/logo.png' );
				$sae_builder->create( sae_order() ); $params = $sae_http->payload();
				sae_expect( $supported === $params['branding_settings'], 'Embedded branding forwarded an unsupported image or lost supported styling.' );
				sae_expect( isset( $sae_options['sahcfwc_stripe_branding_settings'][ $image ] ), 'Embedded mode deleted the saved Hosted image.' );
			}
		}
		$sae_options['sahcfwc_stripe_branding_settings'] = array( 'logo_url' => 'https://example.com/logo.png' );
		$sae_builder->create( sae_order() );
		sae_expect( ! isset( $sae_http->payload()['branding_settings'] ), 'Image-only Embedded settings sent an empty unsupported branding object.' );
	} );
	sae_case( 'missing_publishable_key_stops_before_api', function () use ( $sae_builder ) {
		global $sae_options, $sae_http; $sae_options['sahcfwc_stripe_test_public_key'] = '';
		sae_throws( function () use ( $sae_builder ) { $sae_builder->create( sae_order() ); }, 'publishable key' );
		sae_expect( ! $sae_http->requests, 'Missing key created a payable Session.' );
	} );
	sae_case( 'wrong_mode_publishable_key_stops_before_api', function () use ( $sae_builder ) {
		global $sae_options, $sae_http; $sae_options['sahcfwc_stripe_test_public_key'] = 'pk_live_fixture00';
		sae_throws( function () use ( $sae_builder ) { $sae_builder->create( sae_order() ); }, 'publishable key' );
		sae_expect( ! $sae_http->requests, 'Wrong-mode public key created a Session.' );
	} );
	sae_case( 'missing_payment_page_stops_before_api', function () use ( $sae_builder ) {
		global $sae_options, $sae_http; $sae_options['sahcfwc_stripe_auto_embedded_page_id'] = 0;
		sae_throws( function () use ( $sae_builder ) { $sae_builder->create( sae_order() ); }, 'payment page' );
		sae_expect( ! $sae_http->requests, 'Missing payment page created a Session.' );
	} );
	sae_case( 'page_response_is_minimal_and_secret_free', function () use ( $sae_builder ) {
		$order = sae_order(); $session = $sae_builder->create( $order ); $response = SAE_Experience::response_for_session( $order, $session );
		sae_expect( 'embedded_page' === $response['checkout_mode'] && ! isset( $response['client_secret'], $response['checkout_session'], $response['metadata'] ), 'Page response exposed session data.' );
		sae_expect( 'pk_test_fixture00' === $response['publishable_key'] && false === strpos( wp_json_encode( $response ), 'sk_test_' ), 'Page response leaked a secret API key.' );
		sae_expect( false === strpos( $response['return_url'], '{CHECKOUT_SESSION_ID}' ) && false !== strpos( $response['return_url'], $session->id ), 'Browser return URL has an unresolved template.' );
		sae_expect( wp_parse_url( home_url(), PHP_URL_HOST ) === wp_parse_url( $response['embedded_url'], PHP_URL_HOST ), 'Embedded page was not same-origin.' );
	} );
	sae_case( 'popup_response_has_only_scoped_client_secret', function () use ( $sae_builder ) {
		global $sae_options; $sae_options['sahcfwc_stripe_checkout_type'] = 'embedded_popup';
		$order = sae_order(); $session = $sae_builder->create( $order ); $response = SAE_Experience::response_for_session( $order, $session );
		sae_expect( 'embedded_popup' === $response['checkout_mode'] && $session->client_secret === $response['client_secret'], 'Popup cannot initialize Embedded Checkout.' );
		sae_expect( 8 === count( $response ) && ! isset( $response['metadata'], $response['payment_intent'] ), 'Popup response is not minimal.' );
	} );
	sae_case( 'response_requires_exact_saved_session_binding', function () use ( $sae_builder ) {
		$order = sae_order(); $session = $sae_builder->create( $order );
		$data = $session->toArray(); $data['id'] = 'cs_test_other_fixture'; $session = SAHCFWC\Libraries\Stripe\Checkout\Session::constructFrom( $data );
		sae_throws( function () use ( $order, $session ) { SAE_Experience::response_for_session( $order, $session, true ); }, 'does not match' );
	} );
	sae_case( 'response_rejects_invalid_client_secret', function () use ( $sae_builder ) {
		$order = sae_order(); $session = $sae_builder->create( $order ); $session->client_secret = 'sk_test_dummy';
		sae_throws( function () use ( $order, $session ) { SAE_Experience::response_for_session( $order, $session, true ); }, 'could not be loaded' );
	} );
	sae_case( 'hosted_open_session_survives_embedded_setting_change', function () use ( $sae_builder ) {
		global $sae_options, $sae_http; $sae_options['sahcfwc_stripe_checkout_type'] = 'hosted';
		$order = sae_order(); $original = $sae_builder->create( $order ); $sae_options['sahcfwc_stripe_checkout_type'] = 'embedded_page'; $sae_options['sahcfwc_stripe_test_public_key'] = '';
		$again = $sae_builder->create( wc_get_order( $order->get_id() ) ); $response = SAE_Experience::response_for_session( $order, $again );
		sae_expect( $original->id === $again->id && 1 === $sae_http->creation_count(), 'Settings change created a second payable Session.' );
		sae_expect( 'hosted' === $response['checkout_mode'] && ! isset( $response['client_secret'] ), 'Response used current setting instead of real Session UI.' );
	} );
	sae_case( 'embedded_open_session_keeps_saved_presentation', function () use ( $sae_builder ) {
		global $sae_options, $sae_http; $order = sae_order(); $original = $sae_builder->create( $order );
		$sae_options['sahcfwc_stripe_checkout_type'] = 'embedded_popup'; $sae_options['sahcfwc_stripe_branding_enabled'] = 'yes'; $sae_options['sahcfwc_stripe_branding_settings'] = array( 'button_color' => '#445566' );
		$again = $sae_builder->create( wc_get_order( $order->get_id() ) ); $response = SAE_Experience::response_for_session( $order, $again );
		sae_expect( $original->id === $again->id && 'embedded_page' === $response['checkout_mode'] && 1 === $sae_http->creation_count(), 'Open Session was replaced after appearance change.' );
	} );
	foreach ( array( 'amount', 'order_id', 'order_key', 'currency', 'mode' ) as $corruption ) {
		sae_case( 'reuse_rejects_mismatched_' . $corruption, function () use ( $sae_builder, $corruption ) {
			global $sae_http; $order = sae_order(); $session = $sae_builder->create( $order );
			if ( 'amount' === $corruption ) { $sae_http->sessions[ $session->id ]['amount_total']++; }
			if ( 'order_id' === $corruption ) { $sae_http->sessions[ $session->id ]['metadata']['sahcfwc_order_id'] = '999999999'; }
			if ( 'order_key' === $corruption ) { $sae_http->sessions[ $session->id ]['metadata']['sahcfwc_order_key'] = 'wrong'; }
			if ( 'currency' === $corruption ) { $sae_http->sessions[ $session->id ]['currency'] = 'eur'; }
			if ( 'mode' === $corruption ) { $sae_http->sessions[ $session->id ]['livemode'] = true; }
			sae_throws( function () use ( $sae_builder, $order ) { $sae_builder->create( wc_get_order( $order->get_id() ) ); } );
			sae_expect( 1 === $sae_http->creation_count(), 'Invalid reuse created another payable Session.' );
		} );
	}
	sae_case( 'expired_unpaid_session_gets_new_idempotency_generation', function () use ( $sae_builder ) {
		global $sae_http; $order = sae_order(); $original = $sae_builder->create( $order );
		$sae_http->sessions[ $original->id ]['status'] = 'expired'; $again = $sae_builder->create( wc_get_order( $order->get_id() ) );
		sae_expect( $original->id !== $again->id && 2 === count( $sae_http->sessions ), 'Expired session replayed instead of safe replacement.' );
		sae_expect( 1 === (int) wc_get_order( $order->get_id() )->get_meta( 'sahcfwc_checkout_generation' ), 'Replacement generation missing.' );
	} );
	sae_case( 'complete_pending_session_cannot_be_replaced', function () use ( $sae_builder ) {
		global $sae_http; $order = sae_order(); $original = $sae_builder->create( $order ); $sae_http->sessions[ $original->id ]['status'] = 'complete';
		sae_throws( function () use ( $sae_builder, $order ) { $sae_builder->create( wc_get_order( $order->get_id() ) ); }, 'being processed' );
		sae_expect( 1 === $sae_http->creation_count(), 'Pending payment got a new payable Session.' );
	} );
	sae_case( 'uncertain_response_replays_identical_request', function () use ( $sae_builder ) {
		global $sae_http; $order = sae_order(); $sae_http->lose_response = true;
		sae_throws( function () use ( $sae_builder, $order ) { $sae_builder->create( $order ); }, 'uncertain' );
		$session = $sae_builder->create( wc_get_order( $order->get_id() ) );
		sae_expect( 'open' === $session->status && 1 === count( $sae_http->sessions ), 'Uncertain request duplicated payment.' );
	} );
	sae_case( 'uncertain_response_settings_change_is_blocked', function () use ( $sae_builder ) {
		global $sae_http, $sae_options; $order = sae_order(); $sae_http->lose_response = true;
		sae_throws( function () use ( $sae_builder, $order ) { $sae_builder->create( $order ); }, 'uncertain' );
		$sae_options['sahcfwc_stripe_checkout_type'] = 'hosted';
		sae_throws( function () use ( $sae_builder, $order ) { $sae_builder->create( wc_get_order( $order->get_id() ) ); }, 'settings changed' );
		sae_expect( 1 === count( $sae_http->sessions ), 'Changed uncertain request created another payment.' );
	} );
	sae_case( 'endpoint_valid_page_fetch_is_secret_scoped', function () use ( $sae_builder ) {
		global $sae_http; $order = sae_order(); $session = $sae_builder->create( $order ); $response = sae_endpoint( $order );
		sae_expect( 'success' === $response['status'] && $session->client_secret === $response['client_secret'], 'Valid owner cannot retrieve Embedded secret.' );
		sae_expect( 1 === $sae_http->creation_count() && ! isset( $response['metadata'], $response['checkout_session'] ), 'Fetch created a Session or exposed full object.' );
	} );
	foreach ( array( 'wrong_key', 'wrong_nonce', 'wrong_order', 'get_method' ) as $invalid ) {
		sae_case( 'endpoint_rejects_' . $invalid, function () use ( $sae_builder, $invalid ) {
			global $sae_http; $order = sae_order(); $sae_builder->create( $order ); $before = count( $sae_http->requests );
			$override = 'wrong_key' === $invalid ? array( 'order_key' => 'wrong' ) : ( 'wrong_nonce' === $invalid ? array( 'security' => 'wrong' ) : ( 'wrong_order' === $invalid ? array( 'order_id' => 999999999 ) : array() ) );
			$response = sae_endpoint( $order, $override, 'get_method' === $invalid ? 'GET' : 'POST' );
			sae_expect( 'failed' === $response['status'] && ! isset( $response['client_secret'] ) && $before === count( $sae_http->requests ), 'Unauthorized fetch contacted Stripe or exposed a secret.' );
		} );
	}
	sae_case( 'endpoint_redacts_upstream_exception', function () use ( $sae_builder ) {
		global $sae_http; $order = sae_order(); $sae_builder->create( $order ); $sae_http->fail_retrieve = true; $response = sae_endpoint( $order );
		sae_expect( 'failed' === $response['status'] && false === strpos( wp_json_encode( $response ), 'sk_test_' ) && false === strpos( wp_json_encode( $response ), 'Unsafe fixture' ), 'Raw upstream error exposed.' );
	} );
	sae_case( 'endpoint_complete_redirects_to_verified_return', function () use ( $sae_builder ) {
		global $sae_http; $order = sae_order(); $session = $sae_builder->create( $order ); $sae_http->sessions[ $session->id ]['status'] = 'complete'; $response = sae_endpoint( $order );
		sae_expect( 'hosted' === $response['checkout_mode'] && false !== strpos( $response['stripe_checkout_session_url'], 'sahcfwc_stripe_embedded_checkout_return' ) && ! isset( $response['client_secret'] ), 'Completed Session did not defer to verified server return.' );
		sae_expect( ! wc_get_order( $order->get_id() )->is_paid(), 'Client-secret fetch marked an order paid.' );
	} );
	sae_case( 'endpoint_expired_session_does_not_create_replacement', function () use ( $sae_builder ) {
		global $sae_http; $order = sae_order(); $session = $sae_builder->create( $order ); $sae_http->sessions[ $session->id ]['status'] = 'expired'; $response = sae_endpoint( $order );
		sae_expect( 'failed' === $response['status'] && ! isset( $response['client_secret'] ) && 1 === $sae_http->creation_count(), 'Expired fetch created a new Session.' );
	} );
	sae_case( 'shortcode_authorization_and_cache_policy', function () use ( $sae_builder ) {
		$order = sae_order(); $sae_builder->create( $order ); $saved = $_GET; $headers_seen = array();
		// Simulate the WordPress 6.3 defaults even when this suite runs on newer WordPress.
		$filter = function ( $headers ) { $headers['Cache-Control'] = 'no-cache, must-revalidate, max-age=0'; unset( $headers['Pragma'] ); return $headers; };
		add_filter( 'nocache_headers', $filter, 0 );
		try {
			$_GET = array( 'order_id' => $order->get_id(), 'order_key' => $order->get_order_key(), 'sahcfwc_checkout' => 1 );
			$html = SAE_Experience::get_instance()->shortcode();
			$headers_seen = wp_get_nocache_headers();
			sae_expect( false !== strpos( $html, 'data-security=' ) && false !== strpos( $html, 'sahcfwc-embedded-mount' ), 'Authorized shortcode lacks mount contract.' );
			sae_expect( false === strpos( $html, '_secret_' ) && defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, 'HTML contains client secret or can be cached.' );
			sae_expect( ! empty( $headers_seen['Cache-Control'] ) && false !== strpos( $headers_seen['Cache-Control'], 'no-store' ), 'No-store response policy missing.' );
			sae_expect( false !== strpos( $headers_seen['Cache-Control'], 'private' ) && false !== strpos( $headers_seen['Cache-Control'], 'no-cache' ) && false !== strpos( $headers_seen['Cache-Control'], 'max-age=0' ), 'Private revalidation policy missing.' );
			sae_expect( 'no-cache' === ( $headers_seen['Pragma'] ?? '' ) && strtotime( $headers_seen['Expires'] ?? '' ) < time(), 'Legacy proxy cache protection missing.' );
			$_GET['order_key'] = 'wrong'; $html = SAE_Experience::get_instance()->shortcode();
			sae_expect( false === strpos( $html, 'data-security=' ) && false === strpos( $html, $order->get_order_key() ), 'Unauthorized shortcode exposed order access.' );
		} finally { $_GET = $saved; remove_filter( 'nocache_headers', $filter, 0 ); }
	} );
	sae_case( 'custom_page_validation_rejects_missing_shortcode_and_drafts', function () {
		global $sae_ids; $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Invalid payment fixture', 'post_content' => 'No shortcode' ), true ); $sae_ids['pages'][] = $id;
		sae_expect( 0 === SAE_Experience::sanitize_page_id( $id ), 'Page without payment shortcode accepted.' );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft', 'post_content' => '[sahcfwc_checkout]' ) );
		sae_expect( 0 === SAE_Experience::sanitize_page_id( $id ), 'Draft payment page accepted.' );
	} );
	sae_case( 'anonymous_page_creation_is_forbidden', function () {
		global $sae_options, $wpdb; $sae_options['sahcfwc_stripe_auto_embedded_page_id'] = 0;
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page'" );
		SAE_Experience::ensure_payment_page();
		sae_expect( $before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page'" ), 'Anonymous request created a WordPress page.' );
	} );
	sae_case( 'authenticated_page_creation_is_single_and_recoverable', function () {
		global $sae_options, $sae_ids, $sae_filters, $wpdb;
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) ); sae_expect( ! empty( $admins ), 'Local administrator fixture unavailable.' );
		wp_set_current_user( $admins[0] ); sae_expect( current_user_can( 'manage_options' ), 'Local administrator lacks settings capability.' );
		// update_option must see the real row existence; a pre_option override of
		// a missing row would intentionally fool WordPress into UPDATE, not INSERT.
		remove_filter( 'pre_option_sahcfwc_stripe_auto_embedded_page_id', $sae_filters['sahcfwc_stripe_auto_embedded_page_id'], PHP_INT_MAX );
		try {
			update_option( 'sahcfwc_stripe_auto_embedded_page_id', 0, false );
			SAE_Experience::ensure_payment_page();
			$id = (int) get_option( 'sahcfwc_stripe_auto_embedded_page_id', 0 );
			sae_expect( $id > 0 && $id !== (int) $sae_options['sahcfwc_stripe_auto_embedded_page_id'] && has_shortcode( get_post_field( 'post_content', $id ), 'sahcfwc_checkout' ), 'Authenticated settings flow did not create a payment page.' );
			$sae_ids['pages'][] = $id;
			$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page'" ); SAE_Experience::ensure_payment_page();
			sae_expect( $before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page'" ), 'Repeated save created duplicate payment pages.' );
			sae_expect( ! $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", 'sahcfwc_embedded_page_creation_lock' ) ), 'Page creation lock was not released.' );
		} finally { add_filter( 'pre_option_sahcfwc_stripe_auto_embedded_page_id', $sae_filters['sahcfwc_stripe_auto_embedded_page_id'], PHP_INT_MAX ); }
	} );
} catch ( Throwable $error ) {
	$sae_results['suite_error'] = array( 'pass' => false, 'error' => $error->getMessage() );
} finally {
	if ( $sae_transaction ) { $wpdb->query( 'ROLLBACK' ); $sae_transaction = false; }
	foreach ( $sae_filters as $name => $callback ) { remove_filter( 'pre_option_' . $name, $callback, PHP_INT_MAX ); }
	wp_set_current_user( $sae_original_user );
	restore_error_handler();
}
$sae_remaining = 0;
foreach ( array_merge( $sae_ids['products'], $sae_ids['pages'], $sae_ids['orders'] ) as $id ) { $sae_remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID=%d", $id ) ); }
foreach ( $sae_ids['orders'] as $id ) {
	$sae_remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE id=%d", $id ) );
	$sae_remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_reserved_stock WHERE order_id=%d", $id ) );
}
$sae_after = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($sae_sql_names) ORDER BY option_name", ARRAY_A );
$sae_results['fixture_rollback'] = array( 'pass' => 0 === $sae_remaining, 'remaining_rows' => $sae_remaining );
$sae_results['settings_unchanged'] = array( 'pass' => $sae_before === $sae_after );
$sae_results['no_php_warnings'] = array( 'pass' => ! $sae_warnings, 'warnings' => $sae_warnings );
$sae_pass = ! in_array( false, array_column( $sae_results, 'pass' ), true );
echo wp_json_encode( array( 'suite' => 'free-embedded-backend', 'pass' => $sae_pass, 'total' => count( $sae_results ), 'assertions' => $sae_assertions, 'results' => $sae_results ), JSON_PRETTY_PRINT ) . PHP_EOL;
if ( ! $sae_pass ) { WP_CLI::halt( 1 ); }

