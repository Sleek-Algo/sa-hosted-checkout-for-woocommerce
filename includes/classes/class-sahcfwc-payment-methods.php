<?php
/**
 * Shared, edition-independent Stripe payment method configuration.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */
namespace SAHCFWC\Classes;

final class SAHCFWC_Payment_Methods {
	/** Canonical Stripe API identifiers; card brands are represented by "card". */
	public static function allowed() {
		return array( 'card', 'customer_balance', 'us_bank_account', 'ideal', 'affirm', 'afterpay_clearpay', 'paynow', 'paypal', 'oxxo', 'acss_debit', 'konbini', 'promptpay', 'p24', 'sepa_debit', 'eps', 'fpx', 'grabpay', 'klarna', 'link', 'boleto', 'blik', 'au_becs_debit', 'bacs_debit', 'bancontact', 'alipay', 'wechat_pay', 'zip', 'cashapp', 'revolut_pay', 'mobilepay', 'swish' );
	}

	/** Read both the existing [{id,title}] option and canonical identifier arrays. */
	public static function normalize( $input ) {
		if ( ! is_array( $input ) ) { return array(); }
		$result = array();
		$brands = array( 'visa', 'mastercard', 'amex', 'american_express', 'diners', 'diners_club', 'discover', 'jcb', 'jcbi', 'cartes_bancaires', 'unionpay', 'union_pay' );
		foreach ( $input as $item ) {
			$id = is_array( $item ) && isset( $item['id'] ) ? $item['id'] : $item;
			if ( ! is_string( $id ) ) { continue; }
			$id = strtolower( trim( $id ) );
			if ( in_array( $id, $brands, true ) ) { $id = 'card'; }
			if ( in_array( $id, self::allowed(), true ) && ! in_array( $id, $result, true ) ) { $result[] = $id; }
		}
		return $result;
	}

	public static function sanitize( $input ) {
		return array_map( function ( $id ) { return array( 'id' => $id, 'title' => $id ); }, self::normalize( $input ) );
	}

	public static function sanitize_status( $input ) {
		return 'manual' === $input ? 'manual' : 'automatic';
	}

	public static function register_settings() {
		self::register_acss_setting();
		register_setting( 'sahcfwc_stripe_checkout_settings', 'sahcfwc_stripe_payment_methodes', array(
			'default' => array(), 'type' => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'show_in_rest' => array( 'schema' => array( 'items' => array(
				'type' => 'object', 'required' => array( 'id' ),
				'properties' => array( 'id' => array( 'type' => 'string' ), 'title' => array( 'type' => 'string' ) ),
			) ) ),
		) );
		register_setting( 'sahcfwc_stripe_checkout_settings', 'sahcfwc_stripe_payment_method_status', array(
			'default' => 'automatic', 'type' => 'string',
			'show_in_rest' => array( 'schema' => array( 'enum' => array( 'automatic', 'manual' ) ) ),
			'sanitize_callback' => array( __CLASS__, 'sanitize_status' ),
		) );
	}

	public static function register_acss_setting() {
		register_setting( 'sahcfwc_stripe_checkout_settings', 'sahcfwc_stripe_acss_transaction_type', array(
			'default' => '', 'type' => 'string',
			'show_in_rest' => array( 'schema' => array( 'enum' => array( '', 'personal', 'business' ) ) ),
			'sanitize_callback' => array( __CLASS__, 'sanitize_acss_type' ),
		) );
	}

	public static function sanitize_acss_type( $input ) {
		return in_array( $input, array( 'personal', 'business' ), true ) ? $input : '';
	}

	/** Currency eligibility only. Stripe remains authoritative for account/customer eligibility. */
	public static function currencies() {
		// https://docs.stripe.com/payments/payment-methods/payment-method-support
		return array(
			'customer_balance' => array( 'usd' ), // This integration offers US bank transfers.
			'us_bank_account' => array( 'usd' ), 'cashapp' => array( 'usd' ),
			'ideal' => array( 'eur' ), 'sepa_debit' => array( 'eur' ), 'eps' => array( 'eur' ), 'bancontact' => array( 'eur' ),
			'affirm' => array( 'cad', 'usd' ), 'acss_debit' => array( 'cad', 'usd' ),
			'au_becs_debit' => array( 'aud' ), 'bacs_debit' => array( 'gbp' ),
			'paynow' => array( 'sgd' ), 'promptpay' => array( 'thb' ), 'fpx' => array( 'myr' ),
			'grabpay' => array( 'myr', 'sgd' ), 'p24' => array( 'eur', 'pln' ),
			'blik' => array( 'pln' ), 'konbini' => array( 'jpy' ), 'oxxo' => array( 'mxn' ),
			'boleto' => array( 'brl' ), 'swish' => array( 'sek' ), 'zip' => array( 'aud', 'usd' ),
			'mobilepay' => array( 'dkk', 'eur', 'nok', 'sek' ),
			'afterpay_clearpay' => array( 'aud', 'cad', 'eur', 'nzd', 'gbp', 'usd' ),
			'klarna' => array( 'aud', 'cad', 'chf', 'czk', 'dkk', 'eur', 'gbp', 'nok', 'nzd', 'pln', 'ron', 'sek', 'usd' ),
			'paypal' => array( 'aud', 'cad', 'chf', 'czk', 'dkk', 'eur', 'gbp', 'hkd', 'nok', 'nzd', 'pln', 'sek', 'sgd', 'usd' ),
			'alipay' => array( 'aud', 'cad', 'cny', 'eur', 'gbp', 'hkd', 'jpy', 'myr', 'nzd', 'sgd', 'usd' ),
			'wechat_pay' => array( 'aud', 'cad', 'chf', 'cny', 'dkk', 'eur', 'gbp', 'hkd', 'jpy', 'nok', 'sek', 'sgd', 'usd' ),
			'revolut_pay' => array( 'eur', 'gbp', 'ron', 'huf', 'pln', 'dkk' ),
		);
	}

	public static function selected( $currency ) {
		// The historical "automatic" setting was card-only. Do not silently enable new methods.
		if ( 'manual' !== get_option( 'sahcfwc_stripe_payment_method_status', 'automatic' ) ) { return array( 'card' ); }
		$methods = self::normalize( get_option( 'sahcfwc_stripe_payment_methodes', array() ) );
		$currencies = self::currencies();
		$currency = strtolower( $currency );
		$methods = array_values( array_filter( $methods, function ( $id ) use ( $currencies, $currency ) {
			return ! isset( $currencies[ $id ] ) || in_array( $currency, $currencies[ $id ], true );
		} ) );
		if ( ! $methods ) {
			throw new \RuntimeException( __( 'No configured payment method supports this order currency. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		if ( in_array( 'link', $methods, true ) && ! in_array( 'card', $methods, true ) ) {
			throw new \RuntimeException( __( 'Link requires Cards to be enabled in the payment method settings. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$delayed = array( 'customer_balance', 'us_bank_account', 'acss_debit', 'sepa_debit', 'au_becs_debit', 'bacs_debit', 'oxxo', 'konbini', 'boleto' );
		if ( array_intersect( $methods, $delayed ) && 0 !== strpos( trim( (string) get_option( 'sahcfwc_stripe_webhook_key', '' ) ), 'whsec_' ) ) {
			throw new \RuntimeException( __( 'Bank debit, bank transfer and voucher payments require a configured Stripe webhook. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		return $methods;
	}

	/** Finalize common payment settings before hashing the Checkout request. */
	public static function configure( $params, $order ) {
		$methods = self::selected( $order->get_currency() );
		$params['payment_method_types'] = $methods;
		// Prevent stale options from an earlier edition's payload from leaking into this selection.
		foreach ( array( 'wechat_pay', 'customer_balance', 'acss_debit' ) as $id ) { unset( $params['payment_method_options'][ $id ] ); }
		if ( in_array( 'wechat_pay', $methods, true ) ) { $params['payment_method_options']['wechat_pay'] = array( 'client' => 'web' ); }
		if ( in_array( 'acss_debit', $methods, true ) ) {
			$type = self::sanitize_acss_type( get_option( 'sahcfwc_stripe_acss_transaction_type', '' ) );
			if ( ! $type ) {
				throw new \RuntimeException( __( 'Canadian bank debit payments require a configured mandate type. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$params['payment_method_options']['acss_debit']['mandate_options'] = array( 'payment_schedule' => 'sporadic', 'transaction_type' => $type );
		}
		if ( in_array( 'customer_balance', $methods, true ) ) {
			$params['payment_method_options']['customer_balance'] = array(
				'funding_type' => 'bank_transfer', 'bank_transfer' => array( 'type' => 'us_bank_transfer' ),
			);
		}
		if ( empty( $params['payment_method_options'] ) ) { unset( $params['payment_method_options'] ); }
		return $params;
	}

	/**
	 * Bank transfers require a Customer. Bind it to this order and credential scope,
	 * never to the old unscoped user-meta customer ID. Called under the order lock.
	 */
	public static function prepare_customer( $params, $order, $client, $secret ) {
		if ( ! in_array( 'customer_balance', $params['payment_method_types'], true ) ) { return $params; }
		$scope = hash_hmac( 'sha256', 'sahcfwc-order-customer', $secret );
		$id = (string) $order->get_meta( 'sahcfwc_checkout_customer_id' );
		if ( ! $id || ! hash_equals( (string) $order->get_meta( 'sahcfwc_checkout_customer_scope' ), $scope ) ) {
			// Keep the request immutable even if receipt/contact details are filled later.
			$customer = $client->customers->create( array( 'metadata' => array(
				'sahcfwc_order_id' => (string) $order->get_id(), 'sahcfwc_order_key' => $order->get_order_key(),
			) ), array( 'idempotency_key' => 'sahcfwc-customer-' . hash( 'sha256', $scope . $order->get_order_key() ) ) );
			if ( ! isset( $customer->id, $customer->livemode ) || ! preg_match( '/^cus_[A-Za-z0-9]+$/', $customer->id )
				|| (bool) $customer->livemode !== (bool) preg_match( '/^(sk|rk)_live_/', $secret ) ) {
				throw new \RuntimeException( __( 'The bank transfer customer could not be verified.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$id = $customer->id;
			$order->update_meta_data( 'sahcfwc_checkout_customer_id', $id );
			$order->update_meta_data( 'sahcfwc_checkout_customer_scope', $scope );
			$order->save();
		}
		unset( $params['customer_email'], $params['customer_creation'] );
		$params['customer'] = $id;
		return $params;
	}
}
