<?php
/**
 * SAHCFWC_Stripe_Listener class.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */

namespace SAHCFWC\Webhooks;

if ( ! class_exists( '\SAHCFWC\Webhooks\SAHCFWC_Stripe_Listener' ) ) {
	/**
	 * Load stripe webhook events
	 *
	 * This class run when webhook trigger events
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class SAHCFWC_Stripe_Listener {
		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\SAHCFWC_Singleton;
		use \SAHCFWC\Traits\SAHCFWC_Helpers;
		use \SAHCFWC\Traits\SAHCFWC_RestAPI;
		/**
		 * Api url.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private static $sahcfwc_api_route_base_url = 'webhooks/stripe-listener';

		/**
		 * Stripe clint.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $sahcfwc_stripe;

		/**
		 * Stripe secret key.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private $sahcfwc_stripe_secret = '';

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Hooks
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			$this->sahcfwc_stripe_secret = $this->sahcfwc_get_stripe_secret_key();
			if ( ! empty( $this->sahcfwc_stripe_secret ) ) {
				try {
					if ( class_exists( '\SAHCFWC\Libraries\Stripe\StripeClient' ) ) {
						$this->sahcfwc_stripe = new \SAHCFWC\Libraries\Stripe\StripeClient(
							array(
								'api_key'        => $this->sahcfwc_stripe_secret,
								'stripe_version' => '2023-08-16',
							)
						);
					}
				} catch ( \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException $e ) {
					$error = $e;
				} catch ( \Exception $e ) {
					$error = $e;
				}
			}
			add_action( 'rest_api_init', array( $this, 'sahcfwc_add_secure_routes' ) );
			add_action( 'sahcfwc_charge_succeeded', array( $this, 'sahcfwc_charge_succeeded_handler' ) );
			add_action(
				'sahcfwc_payment_intent_succeeded',
				array( $this, 'sahcfwc_payment_intent_succeeded_handler' ),
				10,
				1
			);
		}

		/**
		 * Register routes.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_add_secure_routes() {
			register_rest_route(
				$this->sahcfwc_get_api_base_url(),
				self::$sahcfwc_api_route_base_url,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'sahcfwc_application_webhook_callback' ),
					'permission_callback' => array( $this, 'sahcfwc_permissions' ),
				)
			);
		}

		/**
		 * Webhook callback.
		 *
		 * @since 1.0.0
		 *
		 * @param  \WP_REST_Request|object $request this is object.
		 * @return object
		 */
		public function sahcfwc_application_webhook_callback( \WP_REST_Request $request ) {

            // @codingStandardsIgnoreStart
            $payload = @file_get_contents( 'php://input' );
            // @codingStandardsIgnoreEnd
			$webhook_secret = get_option( 'sahcfwc_stripe_webhook_key' );
			$sig_header     = $request->get_header( 'stripe_signature' );

			try {

				$event = null;
				if ( class_exists( '\SAHCFWC\Libraries\Stripe\Webhook' ) ) {
					$event = \SAHCFWC\Libraries\Stripe\Webhook::constructEvent( $payload, $sig_header, $webhook_secret );
				}

				$event_type   = ( isset( $event->type ) ) ? $event->type : '';
				$event_data   = ( isset( $event->data ) ) ? $event->data : null;
				$event_action = '';

				if ( 'charge.succeeded' === $event_type ) {
					$event_action = 'sahcfwc_charge_succeeded';
				} elseif ( 'checkout.session.completed' === $event_type ) {
					$event_action = 'sahcfwc_session_completed';
				} elseif ( 'payment_intent.succeeded' === $event_type ) {
					$event_action = 'sahcfwc_payment_intent_succeeded';
				} elseif ( 'payout.paid' === $event_type ) {
					$event_action = 'sahcfwc_payout_paid';
				} elseif ( 'payout.failed' === $event_type ) {
					$event_action = 'sahcfwc_payout_failed';
				}

				if ( ! empty( $event_type ) && ! empty( $event_action ) ) {
					do_action( $event_action, $event_data );
					return wp_send_json_success(
						array(
							'event_type'   => esc_html( $event_type ),
							'event_action' => esc_html( $event_action ),
						)
					);
				} else {
					return wp_send_json_error(
						array(
							'event_type' => esc_html( $event_type ),
							'reason'     => esc_html( 'Sorry!, We do not need this Event because it is not utilized.' ),
						)
					);
				}
			} catch ( \UnexpectedValueException $e ) {
				// Invalid payload.
				return wp_send_json_error(
					array(
						'reason'  => esc_html( 'Invalid payload' ),
						'payload' => esc_html( $payload ),
					)
				);
			} catch ( \SAHCFWC\Libraries\Stripe\Exception\SignatureVerificationException $e ) {
				// Invalid Signature.
				return wp_send_json_error(
					array(
						'reason'    => esc_html( 'Invalid Signature' ),
						'Signature' => esc_html( $sig_header ),
					)
				);
			}
		}

		/**
		 * Webhook callback.
		 *
		 * @since 1.0.0
		 *
		 * @param  object $event_data stripe data.
		 * @return void
		 */
		public function sahcfwc_charge_succeeded_handler( $event_data ) {
			global $wpdb;
			$balance_transaction   = $event_data->object->balance_transaction;
			$payment_method        = $event_data->object->payment_intent;
			$metadata              = $event_data->object->metadata;
			$order_id              = $metadata['sahcfwc_order_id'];
			$order                 = wc_get_order( $order_id );
			$billing_address       = $event_data->object->billing_details->address;
			$shipping_address      = $event_data->object->shipping->address;
			$billing_address_data  = array(
				'first_name' => ( isset( $event_data->object->billing_details->name ) ? sanitize_text_field( $event_data->object->billing_details->name ) : '' ),
				'address_1'  => ( isset( $billing_address->line1 ) ? sanitize_text_field( $billing_address->line1 ) : '' ),
				'address_2'  => ( isset( $billing_address->line2 ) ? sanitize_text_field( $billing_address->line2 ) : '' ),
				'city'       => ( isset( $billing_address->city ) ? sanitize_text_field( $billing_address->city ) : '' ),
				'state'      => ( isset( $billing_address->state ) ? sanitize_text_field( $billing_address->state ) : '' ),
				'postcode'   => ( isset( $billing_address->postal_code ) ? sanitize_text_field( $billing_address->postal_code ) : '' ),
				'country'    => ( isset( $billing_address->country ) ? sanitize_text_field( $billing_address->country ) : '' ),
				'email'      => ( isset( $event_data->object->billing_details->email ) ? sanitize_email( $event_data->object->billing_details->email ) : '' ),
				'phone'      => ( isset( $event_data->object->billing_details->phone ) ? sanitize_text_field( $event_data->object->billing_details->phone ) : '' ),
			);
			$shipping_address_data = array(
				'first_name' => ( isset( $shipping_address->name ) ? sanitize_text_field( $shipping_address->name ) : '' ),
				'address_1'  => ( isset( $shipping_address->line1 ) ? sanitize_text_field( $shipping_address->line1 ) : '' ),
				'address_2'  => ( isset( $shipping_address->line2 ) ? sanitize_text_field( $shipping_address->line2 ) : '' ),
				'city'       => ( isset( $shipping_address->city ) ? sanitize_text_field( $shipping_address->city ) : '' ),
				'state'      => ( isset( $shipping_address->state ) ? sanitize_text_field( $shipping_address->state ) : '' ),
				'postcode'   => ( isset( $shipping_address->postal_code ) ? sanitize_text_field( $shipping_address->postal_code ) : '' ),
				'country'    => ( isset( $shipping_address->country ) ? sanitize_text_field( $shipping_address->country ) : '' ),
			);
			$order->set_billing_first_name( $billing_address_data['first_name'] );
			$order->set_billing_address_1( $billing_address_data['address_1'] );
			$order->set_billing_address_2( $billing_address_data['address_2'] );
			$order->set_billing_city( $billing_address_data['city'] );
			$order->set_billing_state( $billing_address_data['state'] );
			$order->set_billing_postcode( ( isset( $billing_address_data['postcode'] ) ? $billing_address_data['postcode'] : '' ) );
			$order->set_billing_country( $billing_address_data['country'] );
			$order->set_billing_email( $billing_address_data['email'] );
			update_user_meta( $order->get_customer_id(), 'billing_first_name', $billing_address_data['first_name'] );
			update_user_meta( $order->get_customer_id(), 'billing_last_name', ( isset( $billing_address_data['last_name'] ) ? $billing_address_data['last_name'] : '' ) );
			update_user_meta( $order->get_customer_id(), 'billing_address_1', $billing_address_data['address_1'] );
			update_user_meta( $order->get_customer_id(), 'billing_address_2', $billing_address_data['address_2'] );
			update_user_meta( $order->get_customer_id(), 'billing_city', $billing_address_data['city'] );
			update_user_meta( $order->get_customer_id(), 'billing_state', $billing_address_data['state'] );
			update_user_meta( $order->get_customer_id(), 'billing_postcode', ( isset( $billing_address_data['postcode'] ) ? $billing_address_data['postcode'] : '' ) );
			update_user_meta( $order->get_customer_id(), 'billing_country', $billing_address_data['country'] );
			update_user_meta( $order->get_customer_id(), 'billing_email', $billing_address_data['email'] );
			$order->set_shipping_first_name( $shipping_address_data['first_name'] );
			$order->set_shipping_address_1( $shipping_address_data['address_1'] );
			$order->set_shipping_address_2( $shipping_address_data['address_2'] );
			$order->set_shipping_city( $shipping_address_data['city'] );
			$order->set_shipping_state( $shipping_address_data['state'] );
			$order->set_shipping_postcode( $shipping_address_data['postcode'] );
			$order->set_shipping_country( $shipping_address_data['country'] );
			update_user_meta( $order->get_customer_id(), 'shipping_first_name', $shipping_address_data['first_name'] );
			update_user_meta( $order->get_customer_id(), 'shipping_last_name', ( isset( $shipping_address_data['last_name'] ) ? $shipping_address_data['last_name'] : '' ) );
			update_user_meta( $order->get_customer_id(), 'shipping_address_1', $shipping_address_data['address_1'] );
			update_user_meta( $order->get_customer_id(), 'shipping_address_2', $shipping_address_data['address_2'] );
			update_user_meta( $order->get_customer_id(), 'shipping_city', $shipping_address_data['city'] );
			update_user_meta( $order->get_customer_id(), 'shipping_state', $shipping_address_data['state'] );
			update_user_meta( $order->get_customer_id(), 'shipping_postcode', $shipping_address_data['postcode'] );
			$order->add_order_note( html_entity_decode( ' Payement ID: https://dashboard.stripe.com/test/payments/' . $payment_method . ' <br>Transaction ID: ' . $balance_transaction ) );
			$order->save();
		}

		/**
		 * Function payment intent succeeded handler.
		 *
		 * @since 1.0.0
		 *
		 * @param  object $event_data stripe data.
		 * @return void
		 */
		public function sahcfwc_payment_intent_succeeded_handler( $event_data ) {
			$wc_order_meta = $event_data->object->metadata;
			$order_id      = $wc_order_meta['sahcfwc_order_id'];
			$order         = wc_get_order( $order_id );
			if ( isset( $order ) && ! empty( $order ) ) {
				$order_meta_data = $order->get_meta_data();
			} else {
				$order_meta_data = '';
			}
			$order_stripe_checkout_id = 0;
			if ( '' !== $order_meta_data ) {
				if ( is_array( $order->get_meta_data() ) && count( $order->get_meta_data() ) > 0 ) {
					foreach ( $order->get_meta_data() as $object ) {
						$object_array = array_values( (array) $object );
						foreach ( $object_array as $object_item ) {
							if ( 'sahcfwc_stripe_checkout_session_id' === $object_item['key'] ) {
								$order_stripe_checkout_id = $object_item['value'];
								break;
							}
						}
					}
				}
			}
			/**
			 * Tmp Block execution.
			 */
			if ( 0 === $order_stripe_checkout_id ) {
				return;
			}
			$stripe_checkout_session_all_line_items = $this->sahcfwc_stripe->checkout->sessions->allLineItems( $order_stripe_checkout_id, array() );
			$wc_order_line_items                    = $order->get_items();
			foreach ( $stripe_checkout_session_all_line_items->data as $order_product_index => $stripe_checkout_session_line_item ) {
				$stripe_product_id                     = $stripe_checkout_session_line_item->price->product;
				$stripe_product                        = $this->sahcfwc_stripe->products->retrieve( $stripe_product_id, array() );
				$stripe_product_quantity               = $stripe_checkout_session_line_item->quantity;
				$striep_checkout_session_wc_product_id = $stripe_product->metadata->product_id;
				foreach ( $wc_order_line_items as $item_id => $wc_order_line_item ) {
					$wc_order_line_item_data = $wc_order_line_item->get_data();
					$wc_order_product_id     = $wc_order_line_item_data['product_id'];
					if ( (int) $wc_order_product_id === (int) $striep_checkout_session_wc_product_id ) {
						$wc_order_line_item_product = wc_get_product( $wc_order_product_id );
						// product quantity.
						$wc_order_product_quantity = $wc_order_line_item_data['quantity'];
						if ( (int) $wc_order_product_quantity !== (int) $stripe_product_quantity ) {
							$wc_order_line_item->set_quantity( $stripe_product_quantity );
							$wc_order_line_item_product_tax_excluded_total_price = wc_get_price_excluding_tax(
								$wc_order_line_item_product,
								array(
									'qty' => $wc_order_line_item->get_quantity(),
								)
							);
							// Get the total discount amount applied by coupons.
							$coupon_discount_amount = $order->get_discount_total();
							$wc_order_line_item->set_total( $wc_order_line_item_product_tax_excluded_total_price );
							$wc_order_line_item->set_subtotal( $wc_order_line_item_product_tax_excluded_total_price );
							$wc_order_line_item->save();
						}
					}
				}
				$order->calculate_totals();
			}
		}

		/**
		 * Remove applyed coupon.
		 *
		 * @since 1.0.0
		 *
		 * @param  object $order WooCommerce data.
		 * @param  array  $coupon_code WooCommerce coupon.
		 * @return void
		 */
		public function sahcfwc_remove_coupon_from_order( $order, $coupon_code ) {
			$removed = $order->remove_coupon( $coupon_code );
			if ( $removed ) {
				$order->calculate_totals();
				$order->save();
			}
		}

	}

}
