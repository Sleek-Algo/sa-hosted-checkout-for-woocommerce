<?php
/**
 * Signed Stripe webhooks settle the same immutable order as browser returns.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */
namespace SAHCFWC\Webhooks;

require_once dirname( __DIR__ ) . '/classes/class-sahcfwc-payment-completion.php';

use SAHCFWC\Classes\SAHCFWC_Payment_Completion as Completion;

class SAHCFWC_Stripe_Listener {
	use \SAHCFWC\Traits\SAHCFWC_Singleton;
	use \SAHCFWC\Traits\SAHCFWC_RestAPI;

	private $sahcfwc_stripe;

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'sahcfwc_add_secure_routes' ) );
		add_action( 'sahcfwc_session_completed', array( $this, 'sahcfwc_session_completed_handler' ) );
		add_action( 'sahcfwc_charge_succeeded', array( $this, 'sahcfwc_charge_succeeded_handler' ) );
		add_action( 'sahcfwc_payment_intent_succeeded', array( $this, 'sahcfwc_payment_intent_succeeded_handler' ) );
	}

	public function sahcfwc_add_secure_routes() {
		register_rest_route( $this->sahcfwc_get_api_base_url(), 'webhooks/stripe-listener', array(
			'methods' => 'POST',
			'callback' => array( $this, 'sahcfwc_application_webhook_callback' ),
			// Stripe authenticates with a signature, not an administrator session.
			'permission_callback' => '__return_true',
		) );
	}

	public function sahcfwc_application_webhook_callback( \WP_REST_Request $request ) {
		$secret = trim( (string) get_option( 'sahcfwc_stripe_webhook_key', '' ) );
		if ( ! $secret ) {
			return new \WP_REST_Response( array( 'received' => false, 'message' => 'Webhook signing is not configured.' ), 503 );
		}
		try {
			$event = \SAHCFWC\Libraries\Stripe\Webhook::constructEvent( $request->get_body(), $request->get_header( 'stripe-signature' ), $secret );
		} catch ( \UnexpectedValueException $e ) {
			return new \WP_REST_Response( array( 'received' => false, 'message' => 'Invalid webhook payload.' ), 400 );
		} catch ( \SAHCFWC\Libraries\Stripe\Exception\SignatureVerificationException $e ) {
			return new \WP_REST_Response( array( 'received' => false, 'message' => 'Invalid webhook signature.' ), 400 );
		}
		$type = Completion::value( $event, 'type', '' );
		$object = Completion::value( Completion::value( $event, 'data' ), 'object' );
		if ( ! in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'payment_intent.succeeded', 'charge.succeeded' ), true ) ) {
			return new \WP_REST_Response( array( 'received' => true, 'ignored' => true ), 200 );
		}
		if ( ! $object || ! Completion::value( $object, 'id' ) ) {
			return new \WP_REST_Response( array( 'received' => false, 'message' => 'Invalid webhook object.' ), 400 );
		}
		try {
			$result = $this->process_event( $type, $object, Completion::value( $event, 'livemode' ) );
			if ( is_wp_error( $result ) ) {
				$data = $result->get_error_data();
				$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 503;
				return new \WP_REST_Response( array( 'received' => false, 'message' => $result->get_error_message() ), $status );
			}
		} catch ( \Throwable $e ) {
			// Stripe will retry; never disclose the payload, signature, keys or customer details.
			return new \WP_REST_Response( array( 'received' => false, 'message' => 'Unable to process the payment event. Please retry.' ), 503 );
		}
		return new \WP_REST_Response( array( 'received' => true ), 200 );
	}

	private function process_event( $type, $object, $livemode = null ) {
		$is_session = 0 === strpos( $type, 'checkout.session.' );
		$session_id = $is_session ? (string) Completion::value( $object, 'id', '' ) : '';
		$metadata = Completion::value( $object, 'metadata', array() );
		$order = Completion::find_order( $session_id, $metadata );
		if ( ! $order ) { return true; } // Other integrations may share the Stripe account.
		$saved_id = (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true );
		if ( ! $saved_id ) {
			return Completion::error( 'sahcfwc_session_not_saved', __( 'Checkout is still being prepared. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
		}
		if ( $is_session && ! hash_equals( $saved_id, $session_id ) ) {
			return Completion::error( 'sahcfwc_session_mismatch', __( 'This checkout does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$client = $this->sahcfwc_stripe ? $this->sahcfwc_stripe : Completion::client_for_order( $order, $livemode );
		if ( is_wp_error( $client ) ) { return $client; }
		try { $session = $client->checkout->sessions->retrieve( $saved_id, array() ); }
		catch ( \Exception $e ) { return Completion::error( 'sahcfwc_stripe_unavailable', __( 'Unable to verify the payment. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 ); }
		if ( null !== $livemode && (bool) $livemode !== (bool) Completion::value( $session, 'livemode' ) ) {
			return Completion::error( 'sahcfwc_event_mode_mismatch', __( 'The payment mode does not match the event.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		if ( ! $is_session ) {
			$intent_id = 'charge.succeeded' === $type ? Completion::value( $object, 'payment_intent', '' ) : Completion::value( $object, 'id', '' );
			$session_intent = Completion::value( $session, 'payment_intent', '' );
			if ( is_object( $session_intent ) ) { $session_intent = Completion::value( $session_intent, 'id', '' ); }
			$event_key = (string) Completion::value( $metadata, 'sahcfwc_order_key', '' );
			$event_order = (string) Completion::value( $metadata, 'sahcfwc_order_id', '' );
			$new_snapshot = '' !== $order->get_meta( 'sahcfwc_checkout_expected_amount', true );
			if ( ! $intent_id || $intent_id !== $session_intent || ! $event_key || ! hash_equals( $order->get_order_key(), $event_key ) || ( $new_snapshot && (string) $order->get_id() !== $event_order ) ) {
				return Completion::error( 'sahcfwc_intent_mismatch', __( 'The payment does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
		}
		if ( 'checkout.session.async_payment_failed' === $type && 'unpaid' === Completion::value( $session, 'payment_status' ) ) {
			$result = Completion::fail( $order->get_id(), $session, $client );
		} else {
			$result = Completion::complete( $order->get_id(), $session, $client );
		}
		if ( is_wp_error( $result ) && 'sahcfwc_payment_pending' === $result->get_error_code() ) {
			if ( 'checkout.session.completed' !== $type ) {
				return Completion::error( 'sahcfwc_event_pending', __( 'Payment confirmation is still being processed. Please retry.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
			}
			// Delayed payment methods remain unpaid until async_payment_succeeded.
			$this->record_session( $order, $session );
			return true;
		}
		if ( ! is_wp_error( $result ) ) { $this->record_session( $result, $session ); }
		return $result;
	}

	public function sahcfwc_session_completed_handler( $event_data ) {
		return $this->process_event( 'checkout.session.completed', Completion::value( $event_data, 'object' ) );
	}
	public function sahcfwc_charge_succeeded_handler( $event_data ) {
		return $this->process_event( 'charge.succeeded', Completion::value( $event_data, 'object' ) );
	}
	public function sahcfwc_payment_intent_succeeded_handler( $event_data ) {
		return $this->process_event( 'payment_intent.succeeded', Completion::value( $event_data, 'object' ) );
	}

	private function record_session( $order, $session ) {}
}
