<?php
/**
 * SAHCFWC_Checkout_Button_Url_Ajax class.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */

namespace SAHCFWC\Classes;

require_once dirname( __DIR__ ) . '/traits/sahcfwc-order-totals.php';

if ( ! class_exists( ' \SAHCFWC\Classes\SAHCFWC_Checkout_Button_Url_Ajax' ) ) {
	/**
	 * Load Ajax handler functionality
	 *
	 * This class handle ajax functionality to add the hook
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class SAHCFWC_Checkout_Button_Url_Ajax {
		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\SAHCFWC_Singleton;
		use \SAHCFWC\Traits\SAHCFWC_Helpers;
		use \SAHCFWC\Traits\SAHCFWC_Order_Totals;
		/**
		 * Stripe secret key.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $sahcfwc_stripe_secret = '';

		/**
		 * Stripe cleint id.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $sahcfwc_stripe_client = '';

		/**
		 * Stripe shipping address status.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_shipping_address_status;

		/**
		 * Stripe terms condition status.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_terms_condition_status;

		/**
		 * Stripe phone number status.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_phone_num_status;

		/**
		 * Stripe locale.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_locale;

		/**
		 * Stripe locale satus.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_locale_staus;

		/**
		 * Stripe custom field status.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_field_status;

		/**
		 * Stripe custom text field.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_text_field;

		/**
		 * Stripe custom text field label.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_text_field_label;

		/**
		 * Stripe custom text field optional.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_text_field_optional;

		/**
		 * Stripe custom dropdown field optional.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_droupdown_field_optional;

		/**
		 * Stripe custom number field.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_number_field;

		/**
		 * Stripe custom number field label.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_number_field_label;

		/**
		 * Stripe custom number field optional.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_number_field_optional;

		/**
		 * Stripe custom number field options.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_field_options;

		/**
		 *  Stripe custom dropdown field.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_dropdown_field;

		/**
		 *  Stripe custom dropdown field label.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_custom_droupdown_field_label;

		/**
		 *  Stripe counrty payment method status.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_country_payment_method_status;

		/**
		 *  Stripe stripe adjustment quanitity.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_adjustment_quantity;

		/**
		 *  Stripe after shopping address text.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_after_shpping_address_text;

		/**
		 *  Stripe stripe after submition text.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_after_submit_text;

		/**
		 *  Stripe befour text.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_befour_text;

		/**
		 * Stripe customize terms service text.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_customize_terms_service_text;

		/**
		 * Sahcfwc id.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_id;

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Action Hooks
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			$this->sahcfwc_id = 'sahcfwc_stripe_checkout';
			$this->sahcfwc_set_stripe_secret();
			add_action( 'wp_enqueue_scripts', array( $this, 'sahcfwc_scripts_handler' ), 100 );
			add_action( 'wp_ajax_sahcfwc_get_stripe_checkout_url', array( $this, 'sahcfwc_get_stripe_checkout_url_handler' ) );
			add_action( 'wp_ajax_nopriv_sahcfwc_get_stripe_checkout_url', array( $this, 'sahcfwc_get_stripe_checkout_url_handler' ) );
		}

		/**
		 * Handle scripe function
		 *
		 * @return void
		 */
		public function sahcfwc_scripts_handler() {
			$sahcfwc_stripe_checkout_status = get_option( 'sahcfwc_stripe_checkout_status' );
			if ( 'yes' === $sahcfwc_stripe_checkout_status ) {
				wp_enqueue_style(
					'sahcfwc_frontend_style',
					SAHCFWC_URL_ASSETS_FRONTEND_CSS . '/sahcfwc-frontend-style.css',
					array(),
					SAHCFWC_VERSION,
					'all'
				);
				wp_enqueue_script(
					'sahcfwc_frontend_script',
					SAHCFWC_URL_ASSETS_FRONTEND_JS . '/sahcfwc-frontend.js',
					array( 'jquery' ),
					SAHCFWC_VERSION,
					true
				);
				wp_localize_script(
					'sahcfwc_frontend_script',
					'sahcfwc_frontend_localized_data',
					array(
						'ajax'            => array(
							'url'      => admin_url( 'admin-ajax.php' ),
							'action'   => 'sahcfwc_get_stripe_checkout_url',
							'security' => wp_create_nonce( 'sahcfwc-get-stripe-checkout-url-ajax-nonce' ),
						),
						'wc_checkout_url' => wc_get_checkout_url(),
						'wc_cart_url'     => wc_get_cart_url(),
						'is_wc_cart_page' => ( is_cart() ? 'yes' : 'no' ),
						'checkout_error'  => esc_html__( 'Unable to start checkout. Please try again.', 'sa-hosted-checkout-for-woocommerce' ),
					)
				);
			}
		}

		/**
		 * Handle stripecheckout URL
		 *
		 * @return void
		 */
		public function sahcfwc_get_stripe_checkout_url_handler() {
			if ( ! check_ajax_referer( 'sahcfwc-get-stripe-checkout-url-ajax-nonce', 'security' ) ) {
				wp_send_json(
					array(
						'message' => esc_html__( 'A security error has occurred. Please refresh the page and try again.', 'sa-hosted-checkout-for-woocommerce' ),
					),
					403
				);
			}
			wp_send_json( $this->sahcfwc_strip_checkout_updated_url_handler() );
		}

		/**
		 * Handle Stripe checkout functionality.
		 *
		 * @since 1.0.0
		 *
		 * @return array|result The Stripe checkout.
		 */
		public function sahcfwc_strip_checkout_updated_url_handler() {
			if ( 'yes' !== get_option( 'sahcfwc_stripe_checkout_status' ) ) {
				return array( 'stripe_checkout_session_url' => '', 'status' => 'failed', 'message' => __( 'Stripe Checkout is disabled.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$user        = ( is_user_logged_in() ? wp_get_current_user() : null );
			$user_id     = get_current_user_id();
			$customer_id = ( isset( $user->ID ) ? get_user_meta( $user->ID, 'sahcfwc_stripe_ch_customer_id', true ) : 0 );
			$currency    = get_woocommerce_currency();
			$cart        = WC()->cart;
			/**
			 * Skip if cart empty
			 */
			if ( $cart->is_empty() ) {
				return '';
			}
			/**
			 * Skip if Stripe Secreet key is not set in plugin settings
			 */
			if ( ! isset( $this->sahcfwc_stripe_secret ) || empty( $this->sahcfwc_stripe_secret ) ) {
				return '';
			}
			if ( class_exists( '\SAHCFWC\Libraries\Stripe\Stripe' ) ) {
				\SAHCFWC\Libraries\Stripe\Stripe::setApiKey( $this->sahcfwc_stripe_secret );
			}
			if ( class_exists( '\SAHCFWC\Libraries\Stripe\StripeClient' ) ) {
				$this->sahcfwc_stripe_client = new \SAHCFWC\Libraries\Stripe\StripeClient( $this->sahcfwc_stripe_secret );
			}
			$cart->calculate_totals();
			if ( (float) $cart->get_total( 'edit' ) <= 0 ) {
				return array( 'stripe_checkout_session_url' => wc_get_checkout_url(), 'status' => 'success', 'message' => '' );
			}
			try {
				$order = $this->sahcfwc_get_cart_order_snapshot( $cart );
			} catch ( \Exception $error ) {
				return array( 'stripe_checkout_session_url' => '', 'status' => 'failed', 'message' => $error->getMessage() );
			}
			$order_id = $order->get_id();
			$currency = $order->get_currency();
			$lineitems = $this->sahcfwc_order_line_items( $order );
			$checkoutarray = array(
				'line_items'                 => $lineitems,
				'mode'                       => 'payment',
				'success_url'                => wp_sanitize_redirect( home_url() ) . '/?wc-ajax=sahcfwc_stripe_checkout_order&sessionid={CHECKOUT_SESSION_ID}&order_id=' . $order_id . '&wpnonce=' . wp_create_nonce( 'sahcfwc_checkout_nonce' ),
				'cancel_url'                 => wp_sanitize_redirect( home_url() ) . '/?wc-ajax=sahcfwc_stripe_cancel_order&sessionid={CHECKOUT_SESSION_ID}&wpnonce=' . wp_create_nonce( 'sahcfwc_checkout_nonce' ) . '&order_id=' . $order_id,
				'expires_at'                 => time() + 3600 * 1,
				'billing_address_collection' => 'required',
			);
			if ( ! empty( $this->sahcfwc_stripe_phone_num_status ) && 'yes' === $this->sahcfwc_stripe_phone_num_status ) {
				$checkoutarray['phone_number_collection'] = array(
					'enabled' => true,
				);
			}
			// Checkout collects customer details without cross-mode customer reuse.
			if ( ! empty( $this->sahcfwc_stripe_shipping_address_status ) && 'yes' === $this->sahcfwc_stripe_shipping_address_status ) {
				// The shared builder validates the saved destination and country fallback.
				$checkoutarray['shipping_address_collection'] = array(
					'allowed_countries' => array( $order->get_shipping_country() ),
				);
			}
			if ( ! empty( $this->sahcfwc_stripe_terms_condition_status ) && 'yes' === $this->sahcfwc_stripe_terms_condition_status ) {
				$checkoutarray['consent_collection'] = array(
					'terms_of_service' => 'required',
				);
			}
			$checkoutarray['payment_method_types'] = array( 'card' );
			if ( ! is_null( $user ) ) {
				$checkoutarray['client_reference_id'] = $user->ID;
			}
			// The shared builder uses the saved order for shipping and payment totals.
			$name                                 = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
			$email                                = sanitize_email( $order->get_billing_email() );
			$checkoutarray['payment_intent_data'] = array(
				'description' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' Order #' . $order->get_order_number(),
				'metadata'    => array(
					'sahcfwc_order_id'       => $order->get_id(),
					'sahcfwc_name'           => $name,
					'sahcfwc_customer_email' => $email,
					'sahcfwc_order_key'      => $order->get_order_key(),
					'sahcfwc_site_url'       => get_bloginfo( 'name' ),
					'sahcfwc_plugin_name'    => get_site_url(),
				),
			);
			$num                                  = 0;
			foreach ( $order->get_coupon_codes() as $coupon ) {
				$checkoutarray['payment_intent_data']['metadata'][ 'coupon_data_' . ( ++$num ) ] = $coupon;
			}
			try {
				if ( class_exists( '\SAHCFWC\Libraries\Stripe\Checkout\Session' ) ) {
					$checkout_session = $this->sahcfwc_create_order_checkout_session( $order, $checkoutarray );
				}
			} catch ( \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException $e ) {
				$error = esc_html__( 'Unable to start Stripe Checkout. Please try again or contact the store.', 'sa-hosted-checkout-for-woocommerce' );
				$url   = 'javascript:;';
			} catch ( \Exception $e ) {
				$error = esc_html__( 'Unable to start Stripe Checkout. Please try again or contact the store.', 'sa-hosted-checkout-for-woocommerce' );
				$url   = 'javascript:;';
			}
			// The shared builder has persisted the session-to-order binding.
			$result = array(
				'stripe_checkout_session_url' => ( isset( $checkout_session->url ) && ! empty( $checkout_session->url ) ? esc_url( $checkout_session->url ) : esc_url( isset( $url ) ? $url : '' ) ),
				'status'                      => ( isset( $url ) && ! empty( $url ) ? esc_html( 'failed' ) : esc_html( 'success' ) ),
				'message'                     => ( isset( $error ) && ! empty( $error ) ? esc_html( $error ) : '' ),
			);
			return $result;
		}

		/**
		 * Add WooCommerce fees to the Stripe Checkout total.
		 *
		 * @param array    $lineitems Stripe Checkout line items.
		 * @param WC_Order $order WooCommerce order.
		 * @param string   $currency Order currency.
		 * @return array
		 */
		private function sahcfwc_add_order_fees_line_item( $lineitems, $order, $currency ) {
			foreach ( $order->get_items( 'fee' ) as $fee ) {
				$fee_total = (float) $fee->get_total();

				if ( 0 >= $fee_total ) {
					continue;
				}

				$lineitems[] = array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => $currency,
						'product_data' => array(
							'name' => sanitize_text_field( $fee->get_name() ),
						),
						'unit_amount'  => $this->sahcfwc_get_stripe_amount( $fee_total, $currency ),
					),
				);
			}

			return $lineitems;
		}

		/**
		 * Add WooCommerce tax rates to the Stripe Checkout total.
		 *
		 * @param array    $lineitems Stripe Checkout line items.
		 * @param WC_Order $order WooCommerce order.
		 * @param string   $currency Order currency.
		 * @return array
		 */
		private function sahcfwc_add_order_tax_line_items( $lineitems, $order, $currency ) {
			foreach ( $order->get_items( 'tax' ) as $tax ) {
				$tax_total = (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();

				if ( 0 >= $tax_total ) {
					continue;
				}

				$tax_label   = sanitize_text_field( $tax->get_label() );
				$lineitems[] = array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => $currency,
						'product_data' => array(
							'name' => $tax_label ? $tax_label : esc_html__( 'Tax', 'sa-hosted-checkout-for-woocommerce' ),
						),
						'unit_amount'  => $this->sahcfwc_get_stripe_amount( $tax_total, $currency ),
					),
				);
			}

			return $lineitems;
		}

		/**
		 * Convert a WooCommerce unit price to Stripe minor units without losing precision.
		 *
		 * @param float  $total Unit price.
		 * @param string $currency Order currency.
		 * @return string
		 */
		private function sahcfwc_get_stripe_decimal_amount( $total, $currency ) {
			$multiplier = in_array( strtoupper( $currency ), $this->sahcfwc_zerocurrency(), true ) ? 1 : 100;
			$amount     = number_format( max( 0, (float) $total ) * $multiplier, 12, '.', '' );
			$amount     = rtrim( rtrim( $amount, '0' ), '.' );

			return '' === $amount ? '0' : $amount;
		}

		/**
		 * Round ammound .
		 *
		 * @since 1.0.0
		 *
		 * @param  int    $total ammount.
		 * @param  string $currency .
		 * @return int|total ammount.
		 */
		public function sahcfwc_get_stripe_amount( $total, $currency = '' ) {
			return \SAHCFWC\Classes\SAHCFWC_Money::to_minor( $total, $currency ? $currency : get_woocommerce_currency() );
		}

		/**
		 * Currency list.
		 *
		 * @since 1.0.0
		 *
		 * @return array currency.
		 */
		public function sahcfwc_zerocurrency() {
			return array(
				'BIF',
				'CLP',
				'DJF',
				'GNF',
				'JPY',
				'KMF',
				'KRW',
				'MGA',
				'PYG',
				'RWF',
				'VUV',
				'XAF',
				'XOF',
				'XPF',
				'VND',
			);
		}

		/**
		 * Set values.
		 *
		 * @since 1.0.0
		 *
		 * @see get_option()
		 * @return void
		 */
		private function sahcfwc_set_stripe_secret() {
			$this->sahcfwc_stripe_shipping_address_status = sanitize_text_field( get_option( 'sahcfwc_stripe_shipping_address_status' ) );
			$this->sahcfwc_stripe_terms_condition_status  = sanitize_text_field( get_option( 'sahcfwc_stripe_terms_condition_status' ) );
			$this->sahcfwc_stripe_phone_num_status        = sanitize_text_field( get_option( 'sahcfwc_stripe_phone_num_status' ) );
			$this->sahcfwc_stripe_secret                  = $this->sahcfwc_get_stripe_secret_key();
		}

	}

}
