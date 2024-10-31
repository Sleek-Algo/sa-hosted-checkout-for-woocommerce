<?php
/**
 * Post_Checkout class.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\Classes;

if ( ! class_exists( '\\SAHCFWC\\Classes\\Post_Checkout' ) ) {
	/**
	 * Load Plugin functionality
	 *
	 * This class call all the core functionality
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class Post_Checkout {
		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\Singleton;
		use \SAHCFWC\Traits\Helpers;
		/**
		 * Stripe secret key.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $sahcfwc_stripe_secret = '';

		/**
		 * Stripe cancel URl.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_cancel_url;

		/**
		 * Stripe client.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_client;

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Hooks
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			$this->sahcfwc_stripe_cancel_url = get_option( 'sahcfwc_stripe_cancel_url' );
			$this->sahcfwc_stripe_secret     = $this->sahcfwc_get_stripe_secret_key();
			if ( ! empty( $this->sahcfwc_stripe_secret ) ) {
				$this->sahcfwc_stripe_client = new \SAHCFWC\Libraries\Stripe\StripeClient( $this->sahcfwc_stripe_secret );
			}
			if ( ! empty( $this->sahcfwc_stripe_secret ) ) {
				try {
					\SAHCFWC\Libraries\Stripe\Stripe::setApiKey( $this->sahcfwc_stripe_secret );
				} catch ( \SAHCFWC\Libraries\Stripe\Exception\AuthenticationException $e ) {
					$error = $e;
				} catch ( \Exception $e ) {
					$error = $e;
				}
				add_action( 'wc_ajax_sahcfwc_stripe_checkout_order', array( $this, 'sahcfwc_stripe_checkout_order_callback' ) );
				add_action( 'wc_ajax_sahcfwc_stripe_cancel_order', array( $this, 'sahcfwc_stripe_cancel_order_callback' ) );
			}
		}

		/**
		 * Cancel order functionlaitly.
		 *
		 * @since       1.0.0
		 *
		 * @return      void
		 */
		public function sahcfwc_stripe_cancel_order_callback() {
			if ( ! check_ajax_referer( 'sahcfwc_checkout_nonce', 'wpnonce' ) ) {
				wc_add_notice( esc_html__( 'Nice try.', 'sa-hosted-checkout-for-woocommerce' ), 'notice' );
				exit;
			}
			$delete_temp_order = get_option( 'sahcfwc_stripe_delete_temp_order_status' );
			$order_id          = ( isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( intval( $_GET['order_id'] ) ) ) : '' );
			$checkoutsessionid = ( isset( $_GET['sessionid'] ) ? sanitize_text_field( wp_unslash( $_GET['sessionid'] ) ) : '' );
			$order             = wc_get_order( $order_id );
			if ( 'yes' === $delete_temp_order ) {
				// Preserve the cart data.
				$cart = WC()->cart->get_cart();
				// Delete the order.
				$order->delete( true );
				if ( ! empty( $this->sahcfwc_stripe_cancel_url ) ) {
					wp_safe_redirect( $this->sahcfwc_stripe_cancel_url );
				} else {
					$shop_page_url = get_permalink( wc_get_page_id( 'shop' ) );
					wp_safe_redirect( $shop_page_url );
				}
				exit;
			} else {
				if ( ! empty( $this->sahcfwc_stripe_cancel_url ) ) {
					wp_safe_redirect( $this->sahcfwc_stripe_cancel_url );
				} else {
					wp_safe_redirect( wc_get_cart_url() );
				}
			}
			exit;
		}

		/**
		 * Update the billing and shipping detail on the woocommerce.
		 *
		 * @since       1.0.0
		 *
		 * @return      void
		 */
		public function sahcfwc_stripe_checkout_order_callback() {
			if ( ! check_ajax_referer( 'sahcfwc_checkout_nonce', 'wpnonce' ) ) {
				wc_add_notice( esc_html__( 'Nice try.', 'sa-hosted-checkout-for-woocommerce' ), 'notice' );
				return;
			}
			$session_id                 = ( isset( $_GET['sessionid'] ) && ! empty( $_GET['sessionid'] ) ? sanitize_text_field( wp_unslash( $_GET['sessionid'] ) ) : '' );
			$stripe_checkout_session_id = trim( $session_id );
			/**
			 * Setup WC Cart Session.
			 */
			$tmp_stored_wc_cart = get_option( 'sahcfwc_stripe_checkout_' . $stripe_checkout_session_id );
			WC()->session->cart = ( isset( $tmp_stored_wc_cart['cart'] ) ? $tmp_stored_wc_cart['cart'] : '' );
			/**
			 * Get Stripe Session Data.
			 */
			$stripe_checkout_session = $this->sahcfwc_stripe_client->checkout->sessions->retrieve( $stripe_checkout_session_id, array() );
			/**
			 * Skip if required data is not set as expected.
			 */
			if ( ! WC()->cart || ! WC()->cart instanceof \WC_Cart || ! is_object( $stripe_checkout_session ) ) {
				return;
			}
			$order_id = ( isset( $_GET['order_id'] ) && ! empty( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : '' );
			/**
			 * Get Order.
			 */
			$order = wc_get_order( $order_id );
			// Remove all existing items from the order.
			foreach ( $order->get_items() as $item_id => $item ) {
				$order->remove_item( $item_id );
			}
			// Save the order after removing items.
			$order->save();
			/**
			 * Start - Stripe Checkout Quanity Adguestment.
			 */
			$stripe_checkout_chsession_line_items = $this->sahcfwc_stripe_client->checkout->sessions->allLineItems(
				$stripe_checkout_session_id,
				array(
					'limit'  => 100,
					'expand' => array( 'data.price.product' ),
				)
			);
			$stripe_checkout_chsession_line_items = $stripe_checkout_chsession_line_items['data'];
			foreach ( WC()->cart->get_cart() as $wc_cart_item_key => $wc_cart_item ) {
				foreach ( $stripe_checkout_chsession_line_items as $stripe_checkout_chsession_line_item ) {
					if ( $stripe_checkout_chsession_line_item->price->product->metadata->cart_item_key === $wc_cart_item_key ) {
						WC()->cart->set_quantity( $wc_cart_item_key, $stripe_checkout_chsession_line_item->quantity );
					}
				}
			}
			/**
			 * End - Stripe Checkout Quanity Adguestment.
			 */
			/**
			 * Recalculate WC Cart Totals.
			 */
			WC()->cart->calculate_totals();
			/**
			 * Set WC Order Data.
			 */
			$order->set_customer_id( apply_filters( 'sahcfwc_woocommerce_checkout_customer_id', get_current_user_id() ) );
			$order->set_currency( get_woocommerce_currency() );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_customer_ip_address( \WC_Geolocation::get_ip_address() );
			$order->set_customer_user_agent( wc_get_user_agent() );
			/**
			 * Set WC Checkout Object.
			 */
			$checkout = WC()->checkout();
			$checkout->set_data_from_cart( $order );
			/**
			 * Calculate Totals so it will be updated.
			 */
			$order->calculate_totals();
			/**
			 * Update order Payment Status.
			 */
			$order->payment_complete();
			$order->update_status( 'completed' );
			/**
			 * Save Order Changes.
			 */
			$order->save();
			do_action( 'sahcfwc_woocommerce_new_order', $order_id, $order );
			if ( isset( $stripe_checkout_session->client_reference_id ) && is_numeric( $stripe_checkout_session->client_reference_id ) ) {
				update_post_meta( $order_id, '_customer_user', $stripe_checkout_session->client_reference_id );
			}
			/**
			 * Set WC Order ID with Stripe Session ID.
			 */
			update_option( 'sahcfwc_stripe_checkout_session_' . $stripe_checkout_session_id . '_wc_order_id', $order_id );
			/**
			 * Trigger WC Email - we can remove it later.
			 */
			$mailer = WC()->mailer();
			$mails  = $mailer->get_emails();
			if ( ! empty( $mails ) ) {
				foreach ( $mails as $mail ) {
					if ( 'new_order' === $mail->id || 'customer_processing_order' === $mail->id || 'customer_completed_order' === $mail->id ) {
						$mail->trigger( $order->get_id() );
					}
				}
			}
			/**
			 * Destryo the WC Cart.
			 */
			WC()->cart->empty_cart();
			try {
				$payment_gateway_object = new \SAHCFWC\Classes\Payment_Gateway();
			} catch ( \Exception $e ) {
				$error = $e;
			}
			$result = array(
				'result'   => 'success',
				'redirect' => $payment_gateway_object->get_return_url( $order ),
			);
			wp_safe_redirect( $result['redirect'] );
			exit;
		}

		/**
		 * Change thank you page to custom page.
		 *
		 * @since 1.0.0
		 *
		 * @param  int   $id WooCommerce order id.
		 * @param  array $args arguments.
		 * @return void thankyou page redirect.
		 */
		public function sahcfwc_update_wc_order_fields( $id, $args = array() ) {
			if ( ! empty( $args ) ) {
				foreach ( $args as $key => $value ) {
					update_post_meta( $id, $key, $value );
				}
			}
		}

	}

}
