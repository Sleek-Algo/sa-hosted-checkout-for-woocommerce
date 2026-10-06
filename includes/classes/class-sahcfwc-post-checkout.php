<?php
/**
 * Handle verified Checkout returns without changing the saved order totals.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */
namespace SAHCFWC\Classes;

require_once __DIR__ . '/class-sahcfwc-payment-completion.php';

class SAHCFWC_Post_Checkout {
	use \SAHCFWC\Traits\SAHCFWC_Singleton;
	use \SAHCFWC\Traits\SAHCFWC_Helpers;

	public $sahcfwc_stripe_cancel_url;
	public $sahcfwc_stripe_client;

	public function __construct() {
		$this->sahcfwc_stripe_cancel_url = get_option( 'sahcfwc_stripe_cancel_url', '' );
		add_action( 'wc_ajax_sahcfwc_stripe_checkout_order', array( $this, 'sahcfwc_stripe_checkout_order_callback' ) );
		add_action( 'wc_ajax_sahcfwc_stripe_cancel_order', array( $this, 'sahcfwc_stripe_cancel_order_callback' ) );

	}

	/** Old gateway URLs encoded the numeric order ID. Decode only strict numeric IDs. */
	private function request_order( $allow_legacy_return = false ) {
		$raw_id = isset( $_GET['order_id'] ) && is_scalar( $_GET['order_id'] ) ? (string) wp_unslash( $_GET['order_id'] ) : '';
		if ( ! ctype_digit( $raw_id ) ) {
			$decoded = base64_decode( $raw_id, true );
			$raw_id = is_string( $decoded ) && ctype_digit( $decoded ) ? $decoded : '';
		}
		$order = $raw_id ? wc_get_order( absint( $raw_id ) ) : false;
		if ( ! $order instanceof \WC_Order || SAHCFWC_Payment_Completion::GATEWAY !== $order->get_payment_method() || 'trash' === $order->get_status() ) {
			return SAHCFWC_Payment_Completion::error( 'sahcfwc_invalid_order', __( 'The checkout link is invalid or has expired.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$key = isset( $_GET['order_key'] ) && is_scalar( $_GET['order_key'] ) ? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : '';
		if ( $key && hash_equals( $order->get_order_key(), $key ) ) { return $order; }
		// Existing success links remain usable only with their nonce AND exact saved session.
		$nonce = isset( $_GET['wpnonce'] ) ? $_GET['wpnonce'] : ( isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '' );
		$session_id = $this->request_session_id();
		if ( ! $key && $allow_legacy_return && is_scalar( $nonce ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'sahcfwc_checkout_nonce' ) && $session_id && hash_equals( (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true ), $session_id ) ) { return $order; }
		return SAHCFWC_Payment_Completion::error( 'sahcfwc_invalid_order_key', __( 'The checkout link is invalid or has expired.', 'sa-hosted-checkout-for-woocommerce' ), 403 );
	}

	private function request_session_id() {
		return isset( $_GET['sessionid'] ) && is_scalar( $_GET['sessionid'] ) ? sanitize_text_field( wp_unslash( $_GET['sessionid'] ) ) : '';
	}

	private function redirect_error( $error ) {
		if ( WC()->session ) { wc_add_notice( $error->get_error_message(), 'error' ); }
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	public function sahcfwc_stripe_checkout_order_callback() {
		$order = $this->request_order( true );
		if ( is_wp_error( $order ) ) { $this->redirect_error( $order ); }
		$this->sahcfwc_stripe_successful_checkout_order( $order->get_id(), $this->request_session_id() );
	}

	public function sahcfwc_stripe_successful_checkout_order( $order_id, $session_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $session_id || ! hash_equals( (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true ), $session_id ) ) {
			$this->redirect_error( SAHCFWC_Payment_Completion::error( 'sahcfwc_session_mismatch', __( 'This checkout does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) ) );
		}
		$client = $this->sahcfwc_stripe_client ? $this->sahcfwc_stripe_client : SAHCFWC_Payment_Completion::client_for_order( $order );
		if ( is_wp_error( $client ) ) { $this->redirect_error( $client ); }
		try {
			$session = $client->checkout->sessions->retrieve( $session_id, array() );
		} catch ( \Exception $e ) {
			$this->redirect_error( SAHCFWC_Payment_Completion::error( 'sahcfwc_stripe_unavailable', __( 'Unable to verify the payment. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 ) );
		}
		$result = SAHCFWC_Payment_Completion::complete( $order_id, $session, $client );
		if ( is_wp_error( $result ) ) {
			if ( 'sahcfwc_payment_pending' === $result->get_error_code() && 'complete' === SAHCFWC_Payment_Completion::value( $session, 'status' ) ) {
				if ( WC()->session ) { wc_add_notice( __( 'Your payment is awaiting confirmation.', 'sa-hosted-checkout-for-woocommerce' ), 'notice' ); }
				wp_safe_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
			$this->redirect_error( $result );
		}
		if ( WC()->cart && $result->get_cart_hash() && hash_equals( $result->get_cart_hash(), WC()->cart->get_cart_hash() ) ) {
			WC()->cart->empty_cart();
		}
		if ( WC()->session && (int) WC()->session->get( 'order_awaiting_payment' ) === $result->get_id() ) {
			WC()->session->__unset( 'order_awaiting_payment' );
		}
		wp_safe_redirect( $result->get_checkout_order_received_url() );
		exit;
	}

	public function sahcfwc_stripe_cancel_order_callback() {
		$order = $this->request_order();
		if ( is_wp_error( $order ) ) { $this->redirect_error( $order ); }
		$this->sahcfwc_stripe_cancel_checkout_order( $order->get_id(), $this->request_session_id() );
	}

	public function sahcfwc_stripe_cancel_checkout_order( $order_id, $session_id = '' ) {
		$order = wc_get_order( $order_id );
		$client = $this->sahcfwc_stripe_client ? $this->sahcfwc_stripe_client : SAHCFWC_Payment_Completion::client_for_order( $order );
		if ( is_wp_error( $client ) ) { $this->redirect_error( $client ); }
		$result = SAHCFWC_Payment_Completion::with_order_lock( $order_id, function ( $locked_order ) use ( $session_id, $client ) {
			if ( ! $locked_order ) { return SAHCFWC_Payment_Completion::error( 'sahcfwc_invalid_order', __( 'The checkout link is invalid or has expired.', 'sa-hosted-checkout-for-woocommerce' ) ); }
			$saved_id = (string) $locked_order->get_meta( 'sahcfwc_stripe_checkout_session_id', true );
			if ( ! $saved_id || ( $session_id && '{CHECKOUT_SESSION_ID}' !== $session_id && ! hash_equals( $saved_id, $session_id ) ) ) {
				return SAHCFWC_Payment_Completion::error( 'sahcfwc_session_mismatch', __( 'This checkout does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			if ( $locked_order->is_paid() || $locked_order->get_meta( 'sahcfwc_completed_session_id', true ) ) { return $locked_order; }
			try {
				$session = $client->checkout->sessions->retrieve( $saved_id, array() );
				$valid = SAHCFWC_Payment_Completion::validate( $locked_order, $session, $client );
				if ( is_wp_error( $valid ) ) { return $valid; }
				if ( 'unpaid' !== SAHCFWC_Payment_Completion::value( $session, 'payment_status' ) || 'complete' === SAHCFWC_Payment_Completion::value( $session, 'status' ) ) { return $locked_order; }
				if ( 'open' === SAHCFWC_Payment_Completion::value( $session, 'status' ) ) {
					$session = $client->checkout->sessions->expire( $saved_id, array() );
				}
				if ( $saved_id !== SAHCFWC_Payment_Completion::value( $session, 'id' ) || 'expired' !== SAHCFWC_Payment_Completion::value( $session, 'status' ) || 'unpaid' !== SAHCFWC_Payment_Completion::value( $session, 'payment_status' ) ) {
					return SAHCFWC_Payment_Completion::error( 'sahcfwc_cancel_pending', __( 'Checkout could not be cancelled. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
				}
				if ( $locked_order->has_status( array( 'pending', 'failed', 'cancelled' ) ) ) {
					if ( ! $locked_order->has_status( 'cancelled' ) ) { $locked_order->update_status( 'cancelled', __( 'Stripe Checkout was cancelled.', 'sa-hosted-checkout-for-woocommerce' ) ); }
					if ( 'yes' === get_option( 'sahcfwc_stripe_delete_temp_order_status' ) ) { $locked_order->delete( false ); }
				}
				return $locked_order;
			} catch ( \Exception $e ) {
				return SAHCFWC_Payment_Completion::error( 'sahcfwc_cancel_failed', __( 'Unable to cancel checkout. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
			}
		} );
		if ( is_wp_error( $result ) ) { $this->redirect_error( $result ); }
		if ( $result->is_paid() ) {
			wp_safe_redirect( $result->get_checkout_order_received_url() );
		} else {
			if ( WC()->session && (int) WC()->session->get( 'order_awaiting_payment' ) === $order_id ) { WC()->session->__unset( 'order_awaiting_payment' ); }
			wp_safe_redirect( $this->sahcfwc_stripe_cancel_url ? $this->sahcfwc_stripe_cancel_url : wc_get_cart_url() );
		}
		exit;
	}

	/** Kept for integrations that update order metadata through this helper. */
	public function sahcfwc_update_wc_order_fields( $id, $args = array() ) {
		$order = wc_get_order( $id );
		if ( ! $order ) { return; }
		foreach ( $args as $key => $value ) { $order->update_meta_data( $key, $value ); }
		$order->save();
	}

}
