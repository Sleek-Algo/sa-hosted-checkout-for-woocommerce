<?php
/**
 * Shared checkout presentation, safe Embedded session access and branding.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */
namespace SAHCFWC\Classes;

final class SAHCFWC_Checkout_Experience {
	private static $instance;
	private static $page_creation_scheduled = false;

	public static function get_instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'sahcfwc_checkout', array( $this, 'shortcode' ) );
		add_action( 'wp_ajax_sahcfwc_get_embedded_session', array( $this, 'embedded_session' ) );
		add_action( 'wp_ajax_nopriv_sahcfwc_get_embedded_session', array( $this, 'embedded_session' ) );
		add_action( 'template_redirect', array( $this, 'private_page_headers' ), 0 );
		add_action( 'template_redirect', array( $this, 'prepare_checkout_replacement' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ), 100 );
		add_action( 'updated_option', array( $this, 'setting_saved' ), 10, 1 );
		add_action( 'added_option', array( $this, 'setting_saved' ), 10, 1 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'settings_request_completed' ), 10, 3 );
		if ( isset( $_GET['sahcfwc_checkout'] ) && ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	}

	public static function register_settings() {
		$settings = array(
			'sahcfwc_stripe_checkout_type' => array( 'default' => 'hosted', 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_mode' ) ),
			'sahcfwc_stripe_embedded_page_id' => array( 'default' => 0, 'type' => 'integer', 'sanitize_callback' => array( __CLASS__, 'sanitize_page_id' ) ),
			'sahcfwc_stripe_test_public_key' => array( 'default' => '', 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_test_public_key' ) ),
			'sahcfwc_stripe_live_public_key' => array( 'default' => '', 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_live_public_key' ) ),
			'sahcfwc_stripe_branding_enabled' => array( 'default' => 'no', 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_yes_no' ) ),
		);
		foreach ( $settings as $name => $args ) {
			$args['show_in_rest'] = true;
			register_setting( 'sahcfwc_stripe_checkout_settings', $name, $args );
		}
		$properties = array();
		foreach ( array( 'display_name', 'background_color', 'button_color', 'font_family', 'border_style', 'logo_url', 'icon_url' ) as $field ) {
			$properties[ $field ] = array( 'type' => 'string', 'default' => '' );
		}
		register_setting( 'sahcfwc_stripe_checkout_settings', 'sahcfwc_stripe_branding_settings', array(
			'default' => array(), 'type' => 'object',
			'sanitize_callback' => array( __CLASS__, 'sanitize_branding' ),
			'show_in_rest' => array( 'schema' => array( 'type' => 'object', 'properties' => $properties, 'additionalProperties' => false ) ),
		) );
	}

	public static function sanitize_mode( $value ) {
		if ( 'embedded' === $value ) { return 'embedded_popup'; }
		return in_array( $value, array( 'hosted', 'embedded_page', 'embedded_popup' ), true ) ? $value : 'hosted';
	}

	public static function mode() { return self::sanitize_mode( get_option( 'sahcfwc_stripe_checkout_type', 'hosted' ) ); }
	public static function sanitize_yes_no( $value ) { return 'yes' === $value ? 'yes' : 'no'; }
	public static function sanitize_test_public_key( $value ) { return self::validate_public_key( $value, 'test' ); }
	public static function sanitize_live_public_key( $value ) { return self::validate_public_key( $value, 'live' ); }

	/** Validate on output as well as save: legacy settings may contain secret keys. */
	public static function validate_public_key( $value, $mode ) {
		if ( ! is_string( $value ) || ! in_array( $mode, array( 'test', 'live' ), true ) ) { return ''; }
		$value = trim( $value );
		return preg_match( '/^pk_' . $mode . '_[A-Za-z0-9]{8,}$/D', $value ) ? $value : '';
	}

	public static function public_key( $mode ) {
		return self::validate_public_key( get_option( 'sahcfwc_stripe_' . $mode . '_public_key', '' ), $mode );
	}

	public static function fonts() {
		return array( 'default', 'be_vietnam_pro', 'bitter', 'chakra_petch', 'hahmlet', 'inconsolata', 'inter', 'lato', 'lora', 'm_plus_1_code', 'montserrat', 'noto_sans', 'noto_sans_jp', 'noto_serif', 'nunito', 'open_sans', 'pridi', 'pt_sans', 'pt_serif', 'raleway', 'roboto', 'roboto_slab', 'source_sans_pro', 'titillium_web', 'ubuntu_mono', 'zen_maru_gothic' );
	}

	public static function sanitize_branding( $input ) {
		$input = is_array( $input ) ? $input : array();
		$output = array();
		if ( isset( $input['display_name'] ) && is_string( $input['display_name'] ) ) {
			$name = sanitize_text_field( $input['display_name'] );
			if ( '' !== $name ) { $output['display_name'] = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 40 ) : substr( $name, 0, 40 ); }
		}
		foreach ( array( 'background_color', 'button_color' ) as $field ) {
			if ( isset( $input[ $field ] ) && is_string( $input[ $field ] ) && preg_match( '/^#[0-9a-fA-F]{6}$/D', $input[ $field ] ) ) { $output[ $field ] = strtolower( $input[ $field ] ); }
		}
		if ( isset( $input['font_family'] ) && in_array( $input['font_family'], self::fonts(), true ) ) { $output['font_family'] = $input['font_family']; }
		if ( isset( $input['border_style'] ) && in_array( $input['border_style'], array( 'pill', 'rectangular', 'rounded' ), true ) ) { $output['border_style'] = $input['border_style']; }
		foreach ( array( 'logo_url', 'icon_url' ) as $field ) {
			if ( empty( $input[ $field ] ) || ! is_string( $input[ $field ] ) ) { continue; }
			$url = esc_url_raw( $input[ $field ], array( 'https' ) );
			$host = wp_parse_url( $url, PHP_URL_HOST );
			$path = wp_parse_url( $url, PHP_URL_PATH );
			if ( ! is_string( $path ) || ! preg_match( '/\\.(png|jpg|jpeg|jfif|pjpeg|pjp|svg)$/iD', $path ) ) {
				if ( function_exists( 'add_settings_error' ) ) {
					add_settings_error( 'sahcfwc_stripe_branding_settings', 'sahcfwc_invalid_brand_image', __( 'Use a public HTTPS image URL ending in .png, .jpg, .jpeg, .jfif, .pjpeg, .pjp, or .svg for the Hosted Checkout logo.', 'sa-hosted-checkout-for-woocommerce' ) );
				}
				continue;
			}
			// Stripe, not WordPress, fetches this public image. Do not accept local URLs.
			if ( $url && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && $host
				&& 'localhost' !== strtolower( $host ) && false !== strpos( $host, '.' )
				&& ( ! filter_var( $host, FILTER_VALIDATE_IP ) || filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) )
				&& ! wp_parse_url( $url, PHP_URL_USER ) && ! wp_parse_url( $url, PHP_URL_PASS ) ) { $output[ $field ] = $url; }
		}
		// Stripe accepts one brand image per Session, not both a logo and an icon.
		if ( isset( $output['logo_url'] ) ) { unset( $output['icon_url'] ); }
		return $output;
	}

	/** Only a public WordPress page containing our shortcode can receive orders. */
	private static function valid_page( $id ) {
		$page = $id ? get_post( $id ) : null;
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status || $page->post_password
			|| ! has_shortcode( $page->post_content, 'sahcfwc_checkout' ) ) { return false; }
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $name ) {
			if ( function_exists( 'wc_get_page_id' ) && (int) $id === wc_get_page_id( $name ) ) { return false; }
		}
		$url = get_permalink( $id );
		return $url && wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	}

	public static function sanitize_page_id( $input ) {
		$id = is_scalar( $input ) ? absint( $input ) : 0;
		if ( ! $id || self::valid_page( $id ) ) { return $id; }
		add_settings_error( 'sahcfwc_stripe_embedded_page_id', 'sahcfwc_invalid_embedded_page', __( 'Choose a published page containing [sahcfwc_checkout], or use the automatic payment page.', 'sa-hosted-checkout-for-woocommerce' ) );
		return 0;
	}

	public static function page_id() {
		$selected = absint( get_option( 'sahcfwc_stripe_embedded_page_id', 0 ) );
		if ( $selected ) { return self::valid_page( $selected ) ? $selected : 0; }
		$automatic = absint( get_option( 'sahcfwc_stripe_auto_embedded_page_id', 0 ) );
		return self::valid_page( $automatic ) ? $automatic : 0;
	}

	public function setting_saved( $option ) {
		if ( ! in_array( $option, array( 'sahcfwc_stripe_checkout_type', 'sahcfwc_stripe_embedded_page_id' ), true )
			|| ! current_user_can( 'manage_options' ) || self::$page_creation_scheduled ) { return; }
		self::$page_creation_scheduled = true;
		// REST saves options one by one. Wait until the selected page is saved too.
		add_action( 'shutdown', array( __CLASS__, 'ensure_payment_page' ), 5 );
	}

	/** Also repair a deleted automatic page when unchanged settings are saved. */
	public function settings_request_completed( $response, $handler, $request ) {
		if ( '/wp/v2/settings' === $request->get_route() && in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true )
			&& ! is_wp_error( $response ) && ( ! is_object( $response ) || ! method_exists( $response, 'get_status' ) || $response->get_status() < 400 ) ) {
			self::ensure_payment_page();
		}
		return $response;
	}

	/** Only an authenticated settings save may create a page, never a shopper GET. */
	public static function ensure_payment_page() {
		if ( ! current_user_can( 'manage_options' ) || 'hosted' === self::mode() || self::page_id() ) { return; }
		if ( ! add_option( 'sahcfwc_embedded_page_creation_lock', time(), '', false ) ) { return; }
		try {
			$id = wp_insert_post( array(
				'post_type' => 'page', 'post_status' => 'publish',
				'post_title' => __( 'Secure payment', 'sa-hosted-checkout-for-woocommerce' ),
				'post_name' => 'stripe-payment', 'post_content' => '[sahcfwc_checkout]',
				'post_author' => get_current_user_id(), 'comment_status' => 'closed', 'ping_status' => 'closed',
			), true );
			if ( ! is_wp_error( $id ) && $id ) { update_option( 'sahcfwc_stripe_auto_embedded_page_id', (int) $id, false ); }
		} finally {
			delete_option( 'sahcfwc_embedded_page_creation_lock' );
		}
	}

	public static function payment_page_url( $order ) {
		$page_id = self::page_id();
		if ( ! $page_id ) { throw new \RuntimeException( __( 'The payment page is unavailable. Ask the store to save its checkout settings and try again.', 'sa-hosted-checkout-for-woocommerce' ) ); }
		return add_query_arg( array( 'sahcfwc_checkout' => '1', 'order_id' => $order->get_id(), 'order_key' => $order->get_order_key() ), get_permalink( $page_id ) );
	}

	public static function return_url( $order, $session_id = '{CHECKOUT_SESSION_ID}' ) {
		$url = add_query_arg( array(
			'wc-ajax' => 'sahcfwc_stripe_embedded_checkout_return', 'order_id' => $order->get_id(),
			'order_key' => $order->get_order_key(), 'sessionid' => $session_id,
		), home_url( '/' ) );
		return str_replace( '%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $url );
	}

	/** Configure before hashing; uncertain requests keep their original parameters. */
	public static function configure_session_params( $params, $order, $secret ) {
		$mode = self::mode();
		if ( 'hosted' !== $mode ) {
			$payment_mode = 0 === strpos( $secret, 'sk_live_' ) || 0 === strpos( $secret, 'rk_live_' ) ? 'live' : 'test';
			if ( ! self::public_key( $payment_mode ) ) { throw new \RuntimeException( __( 'Embedded Checkout requires a valid Stripe publishable key for the selected payment mode.', 'sa-hosted-checkout-for-woocommerce' ) ); }
			self::payment_page_url( $order );
			unset( $params['success_url'], $params['cancel_url'] );
			$params['ui_mode'] = 'embedded';
			$params['return_url'] = self::return_url( $order );
			$params['redirect_on_completion'] = 'always';
		} else {
			// Preserve the original Hosted request shape for safe upgrade/retry.
			unset( $params['ui_mode'], $params['return_url'], $params['redirect_on_completion'] );
			$params['success_url'] = home_url( '/' );
			$params['cancel_url'] = home_url( '/' );
		}
		unset( $params['branding_settings'] );
		if ( 'yes' === get_option( 'sahcfwc_stripe_branding_enabled', 'no' ) ) {
			$branding = self::sanitize_branding( get_option( 'sahcfwc_stripe_branding_settings', array() ) );
			// Clover supports image overrides only on Hosted Checkout. Retain saved images for a later Hosted selection.
			if ( 'hosted' !== $mode ) { unset( $branding['logo_url'], $branding['icon_url'] ); }
			foreach ( array( 'logo', 'icon' ) as $image ) {
				if ( ! empty( $branding[ $image . '_url' ] ) ) {
					$branding[ $image ] = array( 'type' => 'url', 'url' => $branding[ $image . '_url' ] );
					unset( $branding[ $image . '_url' ] );
				}
			}
			if ( $branding ) { $params['branding_settings'] = $branding; }
		}
		return $params;
	}

	/** Rendering follows the actual reused Session, not changed merchant settings. */
	public static function session_mode( $order, $session ) {
		$ui = SAHCFWC_Payment_Completion::value( $session, 'ui_mode', 'hosted' );
		if ( ! in_array( $ui, array( 'embedded', 'embedded_page' ), true ) ) { return 'hosted'; }
		$saved = self::sanitize_mode( $order->get_meta( 'sahcfwc_checkout_experience', true ) );
		return in_array( $saved, array( 'embedded_page', 'embedded_popup' ), true ) ? $saved : 'embedded_popup';
	}

	public static function response_for_session( $order, $session, $include_secret = false ) {
		// Creation runs under a lock with a freshly loaded order object. Reload
		// here rather than reading stale metadata from the caller's snapshot.
		$order = $order instanceof \WC_Order ? wc_get_order( $order->get_id() ) : false;
		if ( ! $order instanceof \WC_Order ) { throw new \RuntimeException( __( 'The order could not be found.', 'sa-hosted-checkout-for-woocommerce' ) ); }
		$order->read_meta_data( true );
		$mode = self::session_mode( $order, $session );
		$session_id = (string) SAHCFWC_Payment_Completion::value( $session, 'id', '' );
		if ( ! $session_id || ! hash_equals( (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true ), $session_id ) ) {
			throw new \RuntimeException( __( 'This checkout does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$url = 'hosted' === $mode ? (string) SAHCFWC_Payment_Completion::value( $session, 'url', '' ) : self::payment_page_url( $order );
		if ( ! $url ) { throw new \RuntimeException( __( 'Checkout is unavailable. Please try again.', 'sa-hosted-checkout-for-woocommerce' ) ); }
		$result = array( 'status' => 'success', 'message' => '', 'stripe_checkout_session_url' => esc_url_raw( $url ), 'checkout_mode' => $mode );
		if ( 'hosted' !== $mode ) {
			$payment_mode = SAHCFWC_Payment_Completion::value( $session, 'livemode', false ) ? 'live' : 'test';
			$key = self::public_key( $payment_mode );
			if ( ! $key ) { throw new \RuntimeException( __( 'Embedded Checkout requires a valid Stripe publishable key. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ) ); }
			$result['embedded_url'] = esc_url_raw( $url );
			$result['publishable_key'] = $key;
			$result['return_url'] = self::return_url( $order, $session_id );
			if ( $include_secret || 'embedded_popup' === $mode ) {
				$client_secret = (string) SAHCFWC_Payment_Completion::value( $session, 'client_secret', '' );
				if ( ! $client_secret || 0 !== strpos( $client_secret, $session_id . '_secret_' ) ) { throw new \RuntimeException( __( 'The payment session could not be loaded. Please try again.', 'sa-hosted-checkout-for-woocommerce' ) ); }
				$result['client_secret'] = $client_secret;
			}
		}
		return $result;
	}

	private static function authorize_order( $id, $key ) {
		$order = is_scalar( $id ) ? wc_get_order( absint( $id ) ) : false;
		$key = is_string( $key ) ? sanitize_text_field( wp_unslash( $key ) ) : '';
		if ( ! $order instanceof \WC_Order || SAHCFWC_Payment_Completion::GATEWAY !== $order->get_payment_method()
			|| 'trash' === $order->get_status() || ! $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return new \WP_Error( 'sahcfwc_invalid_order', __( 'The checkout link is invalid or has expired.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		return $order;
	}

	/** Keep order keys and short-lived client secrets out of shared/browser caches on every supported WordPress version. */
	public static function private_cache_headers( $headers ) {
		$headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0, private';
		$headers['Pragma'] = 'no-cache';
		$headers['Expires'] = 'Wed, 11 Jan 1984 05:00:00 GMT';
		return $headers;
	}

	private static function privacy_headers() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		// Older WordPress defaults omit no-store/private. Keep this request private even if another handler sends headers later.
		add_filter( 'nocache_headers', array( __CLASS__, 'private_cache_headers' ), PHP_INT_MAX );
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}
	}

	public function private_page_headers() {
		$post = get_queried_object();
		if ( isset( $_GET['sahcfwc_checkout'] ) || ( $post instanceof \WP_Post && has_shortcode( $post->post_content, 'sahcfwc_checkout' ) ) ) { self::privacy_headers(); }
	}

	/** Take over ordinary checkout pages, never payment/receipt endpoints or API requests. */
	public static function should_replace_checkout() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true )
			|| isset( $_GET['wc-ajax'] ) || isset( $_GET['wc-api'] )
			|| 'yes' !== get_option( 'sahcfwc_stripe_checkout_status', 'no' )
			|| ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url()
			|| ! function_exists( 'WC' ) || ! WC()->cart ) { return false; }
		return ! WC()->cart->is_empty() && WC()->cart->needs_payment();
	}

	public function prepare_checkout_replacement() {
		if ( ! self::should_replace_checkout() ) { return; }
		self::privacy_headers();
		// Replace the actual renderers before WooCommerce outputs either form.
		add_filter( 'pre_do_shortcode_tag', array( $this, 'replace_checkout_shortcode' ), 10, 2 );
		add_filter( 'pre_render_block', array( $this, 'replace_checkout_block' ), 10, 2 );
	}

	public function replace_checkout_shortcode( $output, $tag ) {
		return 'woocommerce_checkout' === $tag && self::should_replace_checkout() ? self::checkout_launcher() : $output;
	}

	public function replace_checkout_block( $output, $block ) {
		return 'woocommerce/checkout' === ( $block['blockName'] ?? '' ) && self::should_replace_checkout() ? self::checkout_launcher() : $output;
	}

	/** GET renders only a private launcher. The existing nonce-protected POST creates/reuses the session. */
	private static function checkout_launcher() {
		self::enqueue_scripts( true );
		return '<div class="sahcfwc-checkout-launcher"><p class="sahcfwc-launcher-status" role="status" aria-live="polite">'
			. esc_html__( 'Loading secure checkout…', 'sa-hosted-checkout-for-woocommerce' )
			. '</p><button type="button" class="button sahcfwc-launcher-retry" hidden>'
			. esc_html__( 'Try again', 'sa-hosted-checkout-for-woocommerce' )
			. '</button><p><a href="' . esc_url( wc_get_cart_url() ) . '">'
			. esc_html__( 'Return to cart', 'sa-hosted-checkout-for-woocommerce' )
			. '</a></p><noscript>' . esc_html__( 'Enable JavaScript to continue to secure checkout.', 'sa-hosted-checkout-for-woocommerce' )
			. '</noscript></div>';
	}

	public function shortcode() {
		self::privacy_headers();
		$order = self::authorize_order( $_GET['order_id'] ?? '', $_GET['order_key'] ?? '' );
		if ( is_wp_error( $order ) ) {
			return '<div class="woocommerce-info">' . esc_html__( 'Start checkout from your cart or order to open secure payment.', 'sa-hosted-checkout-for-woocommerce' ) . '</div>';
		}
		self::enqueue_scripts( true );
		$mode = self::sanitize_mode( $order->get_meta( 'sahcfwc_checkout_experience', true ) );
		if ( 'hosted' === $mode ) { $mode = 'embedded_page'; }
		return '<div class="sahcfwc-embedded-checkout" data-order-id="' . esc_attr( $order->get_id() ) . '" data-order-key="' . esc_attr( $order->get_order_key() )
			. '" data-checkout-mode="' . esc_attr( $mode ) . '" data-security="' . esc_attr( wp_create_nonce( 'sahcfwc-embedded-' . $order->get_id() . '-' . $order->get_order_key() ) )
			. '" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"><div class="sahcfwc-embedded-status" role="status" aria-live="polite">'
			. esc_html__( 'Loading secure payment...', 'sa-hosted-checkout-for-woocommerce' )
			. '</div><div class="sahcfwc-embedded-mount"></div></div>';
	}

	public function embedded_session() {
		self::privacy_headers();
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_send_json( array( 'status' => 'failed', 'message' => __( 'Use a valid checkout link to continue.', 'sa-hosted-checkout-for-woocommerce' ) ), 405 ); }
		$order = self::authorize_order( $_POST['order_id'] ?? '', $_POST['order_key'] ?? '' );
		if ( is_wp_error( $order ) ) { wp_send_json( array( 'status' => 'failed', 'message' => $order->get_error_message() ), 403 ); }
		$nonce = isset( $_POST['security'] ) && is_string( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sahcfwc-embedded-' . $order->get_id() . '-' . $order->get_order_key() ) ) {
			wp_send_json( array( 'status' => 'failed', 'message' => __( 'Your checkout link has expired. Refresh the page and try again.', 'sa-hosted-checkout-for-woocommerce' ) ), 403 );
		}
		require_once __DIR__ . '/class-sahcfwc-payment-completion.php';
		try {
			$client = SAHCFWC_Payment_Completion::client_for_order( $order );
			if ( is_wp_error( $client ) ) { throw new \RuntimeException( $client->get_error_message() ); }
			$id = (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true );
			if ( ! $id ) { throw new \RuntimeException( __( 'Checkout is still being prepared. Please try again.', 'sa-hosted-checkout-for-woocommerce' ) ); }
			$session = $client->checkout->sessions->retrieve( $id, array() );
			$valid = SAHCFWC_Payment_Completion::validate( $order, $session, $client );
			if ( is_wp_error( $valid ) ) { throw new \RuntimeException( $valid->get_error_message() ); }
			if ( 'complete' === $session->status ) {
				wp_send_json( array( 'status' => 'success', 'checkout_mode' => 'hosted', 'stripe_checkout_session_url' => self::return_url( $order, $id ) ) );
			}
			if ( ! $order->needs_payment() || 'open' !== $session->status || (int) $session->expires_at <= time() + 5 ) {
				throw new \RuntimeException( __( 'This payment session has ended. Return to your cart or order to start checkout again.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$result = self::response_for_session( $order, $session, true );
		} catch ( \Exception $error ) {
			// Never send Stripe exception text: it may contain submitted API data.
			$result = array( 'status' => 'failed', 'message' => __( 'Unable to load secure payment. Refresh the page or return to your cart and try again.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		wp_send_json( $result );
	}

	public static function enqueue_scripts( $force = false ) {
		if ( ! $force && 'yes' !== get_option( 'sahcfwc_stripe_checkout_status', 'no' ) ) { return; }
		$path = SAHCFWC_DIR . 'assets/frontend/checkout/sahcfwc-checkout.asset.php';
		$asset = file_exists( $path ) ? require $path : array( 'dependencies' => array( 'jquery', 'wp-i18n' ), 'version' => SAHCFWC_VERSION );
		wp_enqueue_script( 'sahcfwc-stripe-js', 'https://js.stripe.com/clover/stripe.js', array(), null, true );
		$dependencies = array_unique( array_merge( $asset['dependencies'], array( 'jquery', 'sahcfwc-stripe-js' ) ) );
		wp_enqueue_style( 'sahcfwc-checkout', SAHCFWC_URL . '/assets/frontend/checkout/style-sahcfwc-checkout.css', array(), $asset['version'] );
		wp_style_add_data( 'sahcfwc-checkout', 'rtl', 'replace' );
		wp_enqueue_script( 'sahcfwc-checkout', SAHCFWC_URL . '/assets/frontend/checkout/sahcfwc-checkout.js', $dependencies, $asset['version'], true );
		wp_localize_script( 'sahcfwc-checkout', 'sahcfwc_frontend_localized_data', array(
			'ajax' => array( 'url' => admin_url( 'admin-ajax.php' ), 'action' => 'sahcfwc_get_stripe_checkout_url', 'security' => wp_create_nonce( 'sahcfwc-get-stripe-checkout-url-ajax-nonce' ) ),
			'wc_checkout_url' => wc_get_checkout_url(), 'wc_cart_url' => wc_get_cart_url(),
			'is_wc_cart_page' => is_cart() ? 'yes' : 'no', 'checkout_mode' => self::mode(),
			'checkout_error' => __( 'Unable to start checkout. Please try again.', 'sa-hosted-checkout-for-woocommerce' ),
		) );
		wp_set_script_translations( 'sahcfwc-checkout', 'sa-hosted-checkout-for-woocommerce' );
	}
}

