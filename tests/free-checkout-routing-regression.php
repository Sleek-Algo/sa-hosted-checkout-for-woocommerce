<?php
/** Local Free-only routing checks. No orders, settings, payments or page content are changed. */
use SAHCFWC\Classes\SAHCFWC_Checkout_Experience as Experience;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL
	|| 'http://127.0.0.1:8888' !== untrailingslashit( home_url() ) || function_exists( 'sahcfwc_fs' ) ) {
	throw new RuntimeException( 'Use the offline local Free WordPress installation only.' );
}

$original = array( WC()->cart, $GLOBALS['wp_query'], $GLOBALS['wp']->query_vars, $_GET, $_SERVER['REQUEST_METHOD'] ?? null );
$enabled = 'yes'; $checkout = true; $ajax = false; $cron = false; $http = 0; $orders = 0; $passed = 0;
$cart = new class extends WC_Cart {
	public $empty_fixture = false;
	public $paid_fixture = true;
	public function __construct() {}
	public function is_empty() { return $this->empty_fixture; }
	public function needs_payment() { return $this->paid_fixture; }
};
$filters = array(
	'pre_option_sahcfwc_stripe_checkout_status' => function () use ( &$enabled ) { return $enabled; },
	'woocommerce_is_checkout' => function () use ( &$checkout ) { return $checkout; },
	'wp_doing_ajax' => function () use ( &$ajax ) { return $ajax; },
	'wp_doing_cron' => function () use ( &$cron ) { return $cron; },
	'pre_http_request' => function () use ( &$http ) { ++$http; return new WP_Error( 'offline', 'Network blocked.' ); },
);
foreach ( $filters as $hook => $callback ) { add_filter( $hook, $callback, PHP_INT_MAX ); }
$order_hook = function () use ( &$orders ) { ++$orders; };
add_action( 'woocommerce_new_order', $order_hook );
$expect = function ( $condition, $label ) use ( &$passed ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$passed;
};
$experience = Experience::get_instance();
ob_start();
try {
	WC()->cart = $cart;
	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp']->query_vars = array();
	$_GET = array();
	foreach ( array( 'GET', 'HEAD' ) as $method ) {
		$_SERVER['REQUEST_METHOD'] = $method;
		$expect( Experience::should_replace_checkout(), $method . ' paid checkout uses Stripe launcher' );
	}
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$expect( ! Experience::should_replace_checkout(), 'form submissions are not replaced' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$enabled = 'no'; $expect( ! Experience::should_replace_checkout(), 'disabled plugin' ); $enabled = 'yes';
	$cart->empty_fixture = true; $expect( ! Experience::should_replace_checkout(), 'empty cart' ); $cart->empty_fixture = false;
	$cart->paid_fixture = false; $expect( ! Experience::should_replace_checkout(), 'zero total' ); $cart->paid_fixture = true;
	$checkout = false; $expect( ! Experience::should_replace_checkout(), 'unrelated page' ); $checkout = true;
	$ajax = true; $expect( ! Experience::should_replace_checkout(), 'AJAX excluded' ); $ajax = false;
	$cron = true; $expect( ! Experience::should_replace_checkout(), 'cron excluded' ); $cron = false;
	foreach ( array( 'wc-ajax', 'wc-api' ) as $key ) {
		$_GET[ $key ] = 'callback'; $expect( ! Experience::should_replace_checkout(), $key . ' excluded' ); unset( $_GET[ $key ] );
	}
	foreach ( WC()->query->get_query_vars() as $key => $value ) {
		$GLOBALS['wp']->query_vars = array( $key => '123' );
		$expect( ! Experience::should_replace_checkout(), 'endpoint preserved: ' . $key );
	}
	$GLOBALS['wp']->query_vars = array();
	$experience->prepare_checkout_replacement();
	$expect( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE, 'private checkout cannot be page cached' );
	$classic = do_shortcode( '[woocommerce_checkout]' );
	$block = render_block( array( 'blockName' => 'woocommerce/checkout', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	foreach ( array( 'classic' => $classic, 'block' => $block ) as $name => $html ) {
		$expect( false !== strpos( $html, 'sahcfwc-checkout-launcher' ), $name . ' renderer replaced' );
		$expect( false === strpos( $html, '<form' ) && false === strpos( $html, 'billing_' ), $name . ' has no WooCommerce billing form' );
	}
	$expect( 'unchanged' === $experience->replace_checkout_shortcode( 'unchanged', 'other' ), 'unrelated shortcode preserved' );
	$expect( 'unchanged' === $experience->replace_checkout_block( 'unchanged', array( 'blockName' => 'core/paragraph' ) ), 'unrelated block preserved' );
	$cart->paid_fixture = false;
	$expect( false === $experience->replace_checkout_shortcode( false, 'woocommerce_checkout' ), 'zero total still uses WooCommerce' );
	$cart->paid_fixture = true;
	$expect( 0 === $http && 0 === $orders, 'rendering never requests Stripe or creates an order' );

	$ajax = true; $status = 0;
	$status_filter = function ( $header, $code ) use ( &$status ) { $status = $code; return $header; };
	$die_filter = function () { return function () { throw new RuntimeException( 'routing-test-response' ); }; };
	add_filter( 'status_header', $status_filter, 10, 2 );
	add_filter( 'wp_die_ajax_handler', $die_filter );
	$handler = ( new ReflectionClass( '\SAHCFWC\Classes\SAHCFWC_Checkout_Button_Url_Ajax' ) )->newInstanceWithoutConstructor();
	ob_start();
	try { $handler->sahcfwc_get_stripe_checkout_url_handler(); }
	catch ( RuntimeException $error ) { if ( 'routing-test-response' !== $error->getMessage() ) { throw $error; } }
	$response = json_decode( ob_get_clean(), true );
	$expect( 405 === $status && 'failed' === ( $response['status'] ?? '' ), 'session creation refuses GET before nonce or payment processing' );
	remove_filter( 'status_header', $status_filter );
	remove_filter( 'wp_die_ajax_handler', $die_filter );
} finally {
	ob_end_clean();
	foreach ( $filters as $hook => $callback ) { remove_filter( $hook, $callback, PHP_INT_MAX ); }
	remove_action( 'woocommerce_new_order', $order_hook );
	remove_filter( 'pre_do_shortcode_tag', array( $experience, 'replace_checkout_shortcode' ), 10 );
	remove_filter( 'pre_render_block', array( $experience, 'replace_checkout_block' ), 10 );
	list( WC()->cart, $GLOBALS['wp_query'], $GLOBALS['wp']->query_vars, $_GET, $_SERVER['REQUEST_METHOD'] ) = $original;
}
WP_CLI::success( 'All ' . $passed . ' checkout routing checks passed. No persistent fixtures or payments created.' );
