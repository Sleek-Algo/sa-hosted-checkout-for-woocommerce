<?php
/**
 * Verify and settle an immutable WooCommerce order exactly once.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */
namespace SAHCFWC\Classes;

require_once dirname( __DIR__ ) . '/traits/sahcfwc-order-totals.php';

class SAHCFWC_Payment_Completion {
	const GATEWAY = 'sahcfwc_stripe_checkout';

	public static function value( $object, $key, $default = null ) {
		if ( is_array( $object ) || $object instanceof \ArrayAccess ) {
			return isset( $object[ $key ] ) ? $object[ $key ] : $default;
		}
		return is_object( $object ) && isset( $object->$key ) ? $object->$key : $default;
	}

	public static function error( $code, $message, $status = 400 ) {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/** Only the stored session or a private order key may locate an order. */
	public static function find_order( $session_id, $metadata = null, $order_id = 0 ) {
		$key = (string) self::value( $metadata, 'sahcfwc_order_key', '' );
		if ( ! $order_id && $key ) {
			$order_id = wc_get_order_id_by_order_key( $key );
		}
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof \WC_Order && self::GATEWAY === $order->get_payment_method() ) {
				return $order;
			}
			return false;
		}
		if ( ! $session_id ) {
			return false;
		}
		$args = array(
			'limit' => 2,
			'type' => 'shop_order',
		);
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$args['meta_query'] = array( array( 'key' => 'sahcfwc_stripe_checkout_session_id', 'value' => $session_id ) );
		} else {
			// CPT order storage intentionally ignores WC_Order_Query's HPOS-only meta_query.
			$args['meta_key'] = 'sahcfwc_stripe_checkout_session_id';
			$args['meta_value'] = $session_id;
		}
		$orders = wc_get_orders( $args );
		return 1 === count( $orders ) && self::GATEWAY === $orders[0]->get_payment_method() && hash_equals( (string) $orders[0]->get_meta( 'sahcfwc_stripe_checkout_session_id', true ), $session_id ) ? $orders[0] : false;
	}

	/** The order's payment mode survives admin-only test mode and settings changes. */
	public static function client_for_order( $order, $livemode = null ) {
		if ( ! $order instanceof \WC_Order ) {
			return self::error( 'sahcfwc_invalid_order', __( 'The checkout link is invalid or has expired.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$mode = $order->get_meta( 'sahcfwc_checkout_mode', true );
		if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) {
			$session_id = $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true );
			$mode = null !== $livemode ? ( $livemode ? 'live' : 'test' ) : ( 0 === strpos( $session_id, 'cs_test_' ) ? 'test' : 'live' );
		}
		$restricted = 'restricted' === get_option( 'sahcfwc_api_key_type', 'standard' );
		$option = $restricted ? 'sahcfwc_restricted_' . $mode . '_key' : 'sahcfwc_stripe_' . $mode . '_secret_key';
		$key = trim( (string) get_option( $option, '' ) );
		$prefix = ( $restricted ? 'rk_' : 'sk_' ) . $mode . '_';
		if ( ! $key || 0 !== strpos( $key, $prefix ) ) {
			return self::error( 'sahcfwc_missing_key', __( 'Stripe is not configured for this order.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
		}
		return new \SAHCFWC\Libraries\Stripe\StripeClient( $key );
	}

	/** Bind the authenticated Stripe response to the saved financial snapshot. */
	public static function validate( $order, $session, $client ) {
		if ( ! $order instanceof \WC_Order || self::GATEWAY !== $order->get_payment_method() || 'trash' === $order->get_status() ) {
			return self::error( 'sahcfwc_invalid_order', __( 'This checkout does not match a payable order.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$session_id = (string) self::value( $session, 'id', '' );
		$saved_id = (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true );
		if ( ! $saved_id ) {
			return self::error( 'sahcfwc_session_not_saved', __( 'Checkout is still being prepared. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
		}
		if ( ! $session_id || ! hash_equals( $saved_id, $session_id ) || 'payment' !== self::value( $session, 'mode' ) ) {
			return self::error( 'sahcfwc_session_mismatch', __( 'This checkout does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$metadata = self::value( $session, 'metadata', array() );
		$metadata_id = (string) self::value( $metadata, 'sahcfwc_order_id', '' );
		$metadata_key = (string) self::value( $metadata, 'sahcfwc_order_key', '' );
		if ( $metadata_id || $metadata_key ) {
			if ( (string) $order->get_id() !== $metadata_id || ! $metadata_key || ! hash_equals( $order->get_order_key(), $metadata_key ) ) {
				return self::error( 'sahcfwc_order_binding', __( 'This checkout does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
		} else {
			// Legacy sessions did not have session metadata. Require their saved ID AND intent order key.
			$intent_id = self::value( $session, 'payment_intent' );
			if ( is_object( $intent_id ) ) { $intent_id = self::value( $intent_id, 'id', '' ); }
			if ( ! $intent_id ) {
				return self::error( 'sahcfwc_legacy_binding', __( 'The payment could not be verified for this order.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			try {
				$intent = $client->paymentIntents->retrieve( $intent_id, array() );
			} catch ( \Exception $e ) {
				return self::error( 'sahcfwc_stripe_unavailable', __( 'Unable to verify the payment. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
			}
			$intent_key = (string) self::value( self::value( $intent, 'metadata' ), 'sahcfwc_order_key', '' );
			if ( ! $intent_key || ! hash_equals( $order->get_order_key(), $intent_key ) ) {
				return self::error( 'sahcfwc_legacy_binding', __( 'The payment could not be verified for this order.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
		}
		$currency = strtolower( $order->get_currency() );
		$saved_currency = $order->get_meta( 'sahcfwc_checkout_currency', true );
		$expected = $order->get_meta( 'sahcfwc_checkout_expected_amount', true );
		if ( '' === $expected ) { $expected = SAHCFWC_Money::to_minor( $order->get_total(), $currency ); }
		$current_total = SAHCFWC_Money::to_minor( $order->get_total(), $currency );
		if ( ! is_numeric( $expected ) || (int) $expected !== $current_total || null === self::value( $session, 'amount_total' ) || (int) self::value( $session, 'amount_total' ) !== (int) $expected || $currency !== strtolower( (string) self::value( $session, 'currency', '' ) ) || ( $saved_currency && $currency !== strtolower( $saved_currency ) ) ) {
			return self::error( 'sahcfwc_amount_mismatch', __( 'The payment amount or currency does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$mode = $order->get_meta( 'sahcfwc_checkout_mode', true );
		if ( ! in_array( $mode, array( 'test', 'live' ), true ) ) { $mode = 0 === strpos( $saved_id, 'cs_test_' ) ? 'test' : 'live'; }
		if ( null === self::value( $session, 'livemode' ) || ( 'live' === $mode ) !== (bool) self::value( $session, 'livemode' ) ) {
			return self::error( 'sahcfwc_mode_mismatch', __( 'The payment mode does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		return true;
	}

	/** A connection-scoped database lock serializes return, cancellation and webhook delivery. */
	public static function with_order_lock( $order_id, $callback ) {
		global $wpdb;
		$name = 'sahcfwc_' . md5( $wpdb->prefix . ':' . get_current_blog_id() . ':' . $order_id );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) ) {
			return self::error( 'sahcfwc_order_busy', __( 'The payment is being processed. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
		}
		try {
			return call_user_func( $callback, wc_get_order( $order_id ) );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}

	/** Never rebuild or recalculate an order after sending its snapshot to Stripe. */
	public static function complete( $order_id, $session, $client ) {
		return self::with_order_lock( $order_id, function ( $order ) use ( $session, $client ) {
			$valid = self::validate( $order, $session, $client );
			if ( is_wp_error( $valid ) ) { return $valid; }
			$status = self::value( $session, 'payment_status', '' );
			if ( 'expired' === self::value( $session, 'status' ) ) {
				return self::error( 'sahcfwc_session_expired', __( 'This checkout has expired. Return to your cart to try again.', 'sa-hosted-checkout-for-woocommerce' ), 409 );
			}
			$free_order = 0 === SAHCFWC_Money::to_minor( $order->get_total(), $order->get_currency() );
			if ( 'complete' !== self::value( $session, 'status' ) || ( 'paid' !== $status && ! ( $free_order && 'no_payment_required' === $status ) ) ) {
				return self::error( 'sahcfwc_payment_pending', __( 'Payment has not been confirmed yet.', 'sa-hosted-checkout-for-woocommerce' ), 409 );
			}
			$intent_id = self::value( $session, 'payment_intent', '' );
			if ( is_object( $intent_id ) ) { $intent_id = self::value( $intent_id, 'id', '' ); }
			if ( ! $free_order && ! $intent_id ) {
				return self::error( 'sahcfwc_missing_transaction', __( 'The payment could not be verified.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
			}
			$completed = $order->get_meta( 'sahcfwc_completed_session_id', true );
			if ( $completed || $order->is_paid() ) {
				$transaction = $order->get_transaction_id();
				if ( $transaction && $intent_id && ! hash_equals( $transaction, $intent_id ) ) {
					return self::error( 'sahcfwc_transaction_mismatch', __( 'The payment does not match the completed order.', 'sa-hosted-checkout-for-woocommerce' ) );
				}
				return $order;
			}
			if ( $order->has_status( array( 'refunded', 'trash' ) ) ) {
				return self::error( 'sahcfwc_order_closed', __( 'This order can no longer be completed.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			self::set_customer_details( $order, $session );
			// Persist the receipt data before normal WooCommerce payment emails are triggered.
			$order->save();
			$order->payment_complete( (string) $intent_id );
			if ( ! $order->is_paid() ) {
				return self::error( 'sahcfwc_completion_failed', __( 'Unable to update the order payment status. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
			}
			$order->update_meta_data( 'sahcfwc_completed_session_id', self::value( $session, 'id' ) );
			$order->save();
			return $order;
		} );
	}

	public static function fail( $order_id, $session, $client ) {
		return self::with_order_lock( $order_id, function ( $order ) use ( $session, $client ) {
			$valid = self::validate( $order, $session, $client );
			if ( is_wp_error( $valid ) ) { return $valid; }
			if ( ! $order->is_paid() && ! $order->get_meta( 'sahcfwc_completed_session_id', true ) && 'unpaid' === self::value( $session, 'payment_status' ) && $order->has_status( array( 'pending', 'on-hold' ) ) ) {
				$order->update_status( 'failed', __( 'Stripe reported that the payment failed.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			return $order;
		} );
	}

	/** Fill receipt addresses from verified Checkout details; do not alter customer accounts. */
	private static function set_customer_details( $order, $session ) {
		$billing = self::value( $session, 'customer_details' );
		$shipping = self::value( $session, 'shipping_details', self::value( self::value( $session, 'collected_information' ), 'shipping_details' ) );
		foreach ( array( 'billing' => $billing, 'shipping' => $shipping ) as $type => $details ) {
			if ( ! $details ) { continue; }
			$name = trim( (string) self::value( $details, 'name', '' ) );
			$names = explode( ' ', $name, 2 );
			$values = array( 'first_name' => $names[0], 'last_name' => isset( $names[1] ) ? $names[1] : '', 'phone' => self::value( $details, 'phone', '' ) );
			$address = self::value( $details, 'address', array() );
			foreach ( array( 'line1' => 'address_1', 'line2' => 'address_2', 'city' => 'city', 'state' => 'state', 'postal_code' => 'postcode', 'country' => 'country' ) as $stripe_key => $wc_key ) {
				$values[ $wc_key ] = self::value( $address, $stripe_key, '' );
			}
			if ( 'billing' === $type && is_email( self::value( $details, 'email', '' ) ) ) { $values['email'] = self::value( $details, 'email' ); }
			foreach ( $values as $key => $value ) {
				$get = 'get_' . $type . '_' . $key;
				$set = 'set_' . $type . '_' . $key;
				if ( '' !== (string) $value && is_callable( array( $order, $set ) ) && ! $order->$get() ) {
					$order->$set( sanitize_text_field( $value ) );
				}
			}
		}
	}
}

