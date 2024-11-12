<?php
/**
 * SAHCFWC_Checkout_Button_Url_Ajax class.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\Classes;

use Automattic\WooCommerce\Utilities\OrderUtil;
if ( ! class_exists( ' \\SAHCFWC\\Classes\\SAHCFWC_Checkout_Button_Url_Ajax' ) ) {
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
			$sahcfwc_stripe_checkout_status = get_option( 'sahcfwc_stripe_checkout_status' );
			if ( 'yes' === $sahcfwc_stripe_checkout_status ) {
				$stipe_url = $this->sahcfwc_strip_checkout_updated_url_handler();
			}
			wp_send_json( $stipe_url );
		}

		/**
		 * Handle Stripe checkout functionality.
		 *
		 * @since 1.0.0
		 *
		 * @return array|result The Stripe checkout.
		 */
		public function sahcfwc_strip_checkout_updated_url_handler() {
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
			if( class_exists('\SAHCFWC\Libraries\Stripe\Stripe') ){
				\SAHCFWC\Libraries\Stripe\Stripe::setApiKey( $this->sahcfwc_stripe_secret );
			}
			if( class_exists('\SAHCFWC\Libraries\Stripe\StripeClient') ){
				$this->sahcfwc_stripe_client = new \SAHCFWC\Libraries\Stripe\StripeClient( $this->sahcfwc_stripe_secret );
			}
			if ( WC()->session->get( 'order_awaiting_payment' ) ) {
				$order_id = WC()->session->get( 'order_awaiting_payment' );
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$order = wc_get_order( $order_id );
				} else {
					$order = get_post( $order_id );
				}
			} else {
				$checkout = WC()->checkout();
				$order_id = $checkout->create_order( array() );
				// Store Order ID in session so it can be re-used after payment failure.
				WC()->session->set( 'order_awaiting_payment', $order_id );
				// We save the session early because if the payment gateway hangs
				// the request will never finish, thus the session data will neved be saved,
				// and this can lead to duplicate orders if the user submits the order again.
				WC()->session->save_data();
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$order = wc_get_order( $order_id );
				} else {
					$order = get_post( $order_id );
				}
			}
			if ( false === $order || empty( $order ) ) {
				WC()->session->set( 'order_awaiting_payment', '' );
				$checkout = WC()->checkout();
				$order_id = $checkout->create_order( array() );
				WC()->session->set( 'order_awaiting_payment', $order_id );
				WC()->session->save_data();
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$order = wc_get_order( $order_id );
				} else {
					$order = get_post( $order_id );
				}
			}
			/**
			 * Set Payment Gateway
			 */
			$order->set_payment_method( 'sahcfwc_stripe_checkout' );
			do_action( 'sahcfwc_woocommerce_before_calculate_totals', $cart );
			$lineitems = array();
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$item_name                    = $cart_item['data']->get_title();
				$quantity                     = $cart_item['quantity'];
				$price                        = $cart_item['data']->get_price();
				$metadata                     = array();
				$metadata['cart_item_key']    = $cart_item_key;
				$metadata['order_id']         = $order_id;
				$metadata['product_id']       = $cart_item['product_id'];
				$lineitem                     = array();
				$lineitem['quantity']         = $quantity;
				$lineitemdata                 = array();
				$lineitemdata['currency']     = $currency;
				$_product                     = wc_get_product( $cart_item['product_id'] );
				$description                  = wc_get_formatted_cart_item_data( $cart_item, true );
				$lineitemdata['product_data'] = array(
					'name'     => $item_name,
					'metadata' => $metadata,
				);
				$images                       = array();
				if ( $_product->get_image_id() ) {
					$image    = wp_get_attachment_image_src( $_product->get_image_id() );
					$images[] = ( isset( $image[0] ) && ! empty( $image[0] ) ? $image[0] : SAHCFWC_URL_ASSETS_FRONTEND_IMAGES . '/sahcfwc-woocommerce-placeholder.png' );
				} else {
					$images[] = SAHCFWC_URL_ASSETS_FRONTEND_IMAGES . '/sahcfwc-woocommerce-placeholder.png';
				}
				$lineitemdata['product_data']['images'] = $images;
				if ( $description ) {
					$lineitemdata['product_data']['description'] = $description;
				}
				$lineitemdata['product_data']['metadata'] = $metadata;
				$price                                    = number_format(
					(float) $price,
					2,
					'.',
					''
				);
				$lineitemdata['unit_amount_decimal']      = preg_replace( '/\\D/', '', $price );
				$lineitem['price_data']                   = $lineitemdata;
				$adjustable_quantity                      = array();
				$lineitems[]                              = $lineitem;
			}
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
			// create stripe customer.
			if ( empty( $customer_id ) ) {
				$customer    = $this->sahcfwc_create_stripe_customer( $order, $user );
				$customer_id = $customer->id;
				// saved stripe customer for charging  cards later.
				update_user_meta( $user_id, 'sahcfwc_stripe_ch_customer_id', $customer_id );
			}
			$checkoutarray['customer'] = $customer_id;
			if ( ! empty( $this->sahcfwc_stripe_shipping_address_status ) && 'yes' === $this->sahcfwc_stripe_shipping_address_status ) {
				$wc_customer_shipping_country = WC()->cart->get_customer()->get_shipping_country();
				$package                      = array(
					'destination' => array(
						'country' => $wc_customer_shipping_country,
					),
				);
				$shipping_zone                = wc_get_shipping_zone( $package );
				if ( $shipping_zone ) {
					$zone_locations  = $shipping_zone->get_zone_locations();
					$countries_array = array();
					foreach ( $zone_locations as $locations ) {
						array_push( $countries_array, $locations->code );
					}
					$checkoutarray['shipping_address_collection'] = array(
						'allowed_countries' => $countries_array,
					);
				}
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
			$sahcfwc_chosen_shipping_method_data = $this->sahcfwc_chosen_shipping_method_data();
			if ( is_array( $sahcfwc_chosen_shipping_method_data ) && isset( $sahcfwc_chosen_shipping_method_data['label'] ) ) {
				$wc_cart_shipping_total            = ( '' !== $cart->get_shipping_total() && 0 !== (int) $cart->get_shipping_total() ? $cart->get_shipping_total() : 0 );
				$checkoutarray['shipping_options'] = array(
					array(
						'shipping_rate_data' => array(
							'display_name' => $sahcfwc_chosen_shipping_method_data['label'],
							'fixed_amount' => array(
								'amount'   => $this->sahcfwc_get_stripe_amount( $wc_cart_shipping_total, $currency ),
								'currency' => $currency,
							),
							'type'         => 'fixed_amount',
						),
					),
				);
			}
			if ( $order->get_total() > 0 ) {
				// Mark as processing or on-hold (payment won't be taken until delivery).
				$check_id = ( isset( $this->sahcfwc_id ) ? '' : '' );
				$order->update_status( apply_filters( "sahcfwc_woocommerce_{$check_id}_process_payment_order_status", ( $order->has_downloadable_item() ? 'on-hold' : 'pending' ), $order ), esc_html__( 'Payment to be made upon delivery.', 'sa-hosted-checkout-for-woocommerce' ) );
			} else {
				$order->payment_complete();
			}
			$name                                 = sanitize_text_field( $cart->get_customer()->get_billing_first_name() ) . ' ' . sanitize_text_field( $cart->get_customer()->get_billing_last_name() );
			$email                                = sanitize_email( $cart->get_customer()->get_billing_email() );
			$checkoutarray['payment_intent_data'] = array(
				'description' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' Order #' . $order->get_order_number(),
				'metadata'    => array(
					'sahcfwc_order_id'       => $order->get_order_number(),
					'sahcfwc_name'           => $name,
					'sahcfwc_customer_email' => $email,
					'sahcfwc_order_key'      => $order->get_order_key(),
					'sahcfwc_site_url'       => get_bloginfo( 'name' ),
					'sahcfwc_plugin_name'    => get_site_url(),
				),
			);
			$num                                  = 0;
			foreach ( WC()->cart->get_applied_coupons() as $coupon ) {
				$checkoutarray['payment_intent_data']['metadata'][ 'coupon_data_' . ( ++$num ) ] = $coupon;
			}
			try {
				if( class_exists('\SAHCFWC\Libraries\Stripe\Checkout\Session') ){
					$checkout_session = \SAHCFWC\Libraries\Stripe\Checkout\Session::create( $checkoutarray, $this->sahcfwc_stripe_secret );
				}
			} catch ( \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException $e ) {
				$error = esc_html__( 'Error creating checkout session:', 'sa-hosted-checkout-for-woocommerce' ) . $e->getMessage();
				$url   = 'javascript:;';
			} catch ( \Exception $e ) {
				$error = esc_html__( 'Error creating checkout session: ', 'sa-hosted-checkout-for-woocommerce' ) . $e->getMessage();
				$url   = 'javascript:;';
			}
			/**
			 * Save Stripe Checkout id in to options table to be use latter after payment to complete the order.
			 */
			update_option(
				( 'sahcfwc_stripe_checkout_' . isset( $checkout_session->id ) ? $checkout_session->id : '' ),
				array(
					'cart' => $cart->get_cart(),
				)
			);
			// add order_session_id.
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->add_meta_data( '_sahcfwc_stripe_checkout_session_id', ( isset( $checkout_session->id ) ? $checkout_session->id : '' ) );
				$order->save_meta_data();
				$order->save();
			} else {
				add_post_meta( $order_id, '_sahcfwc_stripe_checkout_session_id', $checkout_session->id );
			}
			$result = array(
				'stripe_checkout_session_url' => ( isset( $checkout_session->url ) && ! empty( $checkout_session->url ) ? esc_url( $checkout_session->url ) : esc_url( $url ) ),
				'status'                      => ( isset( $url ) && ! empty( $url ) ? esc_html( 'failed' ) : esc_html( 'success' ) ),
				'message'                     => ( isset( $error ) && ! empty( $error ) ? esc_html( $error ) : '' ),
			);
			return $result;
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
			if ( ! $currency ) {
				$currency = get_woocommerce_currency();
			}
			if ( in_array( strtoupper( $currency ), $this->sahcfwc_zerocurrency(), true ) ) {
				// Zero decimal currencies.
				$total = absint( $total );
			} else {
				$total = round( $total, 2 ) * 100;
				// In cents.
			}
			return $total;
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
