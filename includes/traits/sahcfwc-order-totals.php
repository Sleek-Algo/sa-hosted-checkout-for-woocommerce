<?php
/** Order snapshots and Stripe Checkout totals. */
namespace SAHCFWC\Classes;

final class SAHCFWC_Money {
	public static function multiplier( $currency ) {
		$currency = strtoupper( $currency );
		if ( in_array( $currency, array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ), true ) ) { return 1; }
		if ( in_array( $currency, array( 'BHD', 'JOD', 'KWD', 'OMR', 'TND' ), true ) ) { return 1000; }
		return 100;
	}

	public static function to_minor( $amount, $currency ) {
		// Stripe retains a two-decimal representation for whole ISK/UGX amounts.
		if ( in_array( strtoupper( $currency ), array( 'ISK', 'UGX' ), true ) ) { return (int) round( (float) $amount, 0, PHP_ROUND_HALF_UP ) * 100; }
		return (int) round( (float) $amount * self::multiplier( $currency ), 0, PHP_ROUND_HALF_UP );
	}
}

namespace SAHCFWC\Traits;

trait SAHCFWC_Order_Totals {
	/** Convert a finalized amount to the integer minor units used by Stripe. */
	private function sahcfwc_order_minor_amount( $amount, $currency ) {
		return \SAHCFWC\Classes\SAHCFWC_Money::to_minor( $amount, $currency );
	}

	/** A cart change must never rewrite an order with an outstanding payment session. */
	private function sahcfwc_get_cart_order_snapshot( $cart ) {
		do_action( 'sahcfwc_woocommerce_before_calculate_totals', $cart );
		$awaiting_id = WC()->session->get( 'order_awaiting_payment', 0 );
		$order = wc_get_order( $awaiting_id );
		$held_keys = array();
		$held_user_keys = array();
		if ( $order && $order->needs_payment() && 'sahcfwc_stripe_checkout' === $order->get_payment_method()
			&& (int) $order->get_customer_id() === get_current_user_id() ) {
			$held_keys = (array) $order->get_meta( '_coupon_held_keys' );
			$held_user_keys = (array) $order->get_meta( '_coupon_held_keys_for_users' );
		}
		// Ignore only this order's own reservation while checking for an EXACT
		// snapshot reuse. No reservation is released and no new order is created
		// with these temporary limits. All other coupon restrictions still apply.
		$own_limit = function ( $limit, $coupon ) use ( $held_keys ) {
			$id = $coupon->get_id();
			return $limit > 0 && isset( $held_keys[ $id ] )
				&& preg_match( '/^_coupon_held_([0-9]+)_/', $held_keys[ $id ], $expiry ) && (int) $expiry[1] > time()
				&& metadata_exists( 'post', $id, $held_keys[ $id ] ) ? $limit + 1 : $limit;
		};
		$own_user_limit = function ( $limit, $coupon ) use ( $held_user_keys ) {
			$id = $coupon->get_id();
			return $limit > 0 && isset( $held_user_keys[ $id ] )
				&& preg_match( '/^_maybe_used_by_([0-9]+)_/', $held_user_keys[ $id ], $expiry ) && (int) $expiry[1] > time()
				&& metadata_exists( 'post', $id, $held_user_keys[ $id ] ) ? $limit + 1 : $limit;
		};
		add_filter( 'woocommerce_coupon_get_usage_limit', $own_limit, 9999, 2 );
		add_filter( 'woocommerce_coupon_get_usage_limit_per_user', $own_user_limit, 9999, 2 );
		try {
			$cart->calculate_totals();
			$this->sahcfwc_validate_cart_coupons( $cart );
			$fingerprint = $this->sahcfwc_cart_snapshot_fingerprint( $cart );
			if ( $order && $order->needs_payment() && 'sahcfwc_stripe_checkout' === $order->get_payment_method()
				&& (int) $order->get_customer_id() === get_current_user_id()
				&& hash_equals( (string) $order->get_meta( 'sahcfwc_cart_snapshot_hash' ), $fingerprint ) ) {
				return $order;
			}
		} finally {
			remove_filter( 'woocommerce_coupon_get_usage_limit', $own_limit, 9999 );
			remove_filter( 'woocommerce_coupon_get_usage_limit_per_user', $own_user_limit, 9999 );
		}
		if ( $held_keys || $held_user_keys ) {
			// A changed cart needs a new reservation. Never bypass the old hold.
			$cart->calculate_totals();
			$this->sahcfwc_validate_cart_coupons( $cart );
			$fingerprint = $this->sahcfwc_cart_snapshot_fingerprint( $cart );
		}
		if ( (float) $cart->get_total( 'edit' ) <= 0 ) { return null; }
		$customer = $cart->get_customer();
		$addresses = array( 'billing' => $customer->get_billing( 'edit' ), 'shipping' => $customer->get_shipping( 'edit' ) );
		// WC_Checkout can reuse an awaiting order itself, so clear that pointer first.
		WC()->session->set( 'order_awaiting_payment', null );
		// create_order() only copies addresses supplied in its data array. Use the
		// trusted cart customer, not raw request fields or a later Stripe response.
		$order_data = array( 'payment_method' => 'sahcfwc_stripe_checkout' );
		foreach ( $addresses as $prefix => $address ) {
			foreach ( $address as $field => $value ) { $order_data[ $prefix . '_' . $field ] = $value; }
		}
		$order_id = WC()->checkout()->create_order( $order_data );
		if ( is_wp_error( $order_id ) ) {
			WC()->session->set( 'order_awaiting_payment', $awaiting_id );
			throw new \RuntimeException( $order_id->get_error_message() );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			WC()->session->set( 'order_awaiting_payment', $awaiting_id );
			throw new \RuntimeException( __( 'Unable to create an order. Please try again.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$order->set_payment_method( 'sahcfwc_stripe_checkout' );
		$order->set_cart_hash( $cart->get_cart_hash() );
		$order->update_meta_data( 'sahcfwc_cart_snapshot_hash', $fingerprint );
		$order->save();
		WC()->session->set( 'order_awaiting_payment', $order->get_id() );
		WC()->session->save_data();
		return $order;
	}

	/** Fail visibly if an applied coupon becomes ineligible; never charge full price silently. */
	private function sahcfwc_validate_cart_coupons( $cart ) {
		$discounts = new \WC_Discounts( $cart );
		foreach ( $cart->get_applied_coupons() as $code ) {
			if ( ! wc_coupons_enabled() ) {
				throw new \RuntimeException( __( 'Coupons are disabled. Remove the coupon from your cart before continuing.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$valid = $discounts->is_coupon_valid( new \WC_Coupon( $code ) );
			if ( is_wp_error( $valid ) ) { throw new \RuntimeException( wp_strip_all_tags( $valid->get_error_message() ) ); }
		}
	}

	private function sahcfwc_cart_snapshot_fingerprint( $cart ) {
		$customer = $cart->get_customer();
		$addresses = array( 'billing' => $customer->get_billing( 'edit' ), 'shipping' => $customer->get_shipping( 'edit' ) );
		return hash( 'sha256', wp_json_encode( array(
			'cart' => $cart->get_cart_hash(),
			'totals' => $cart->get_totals(),
			'fees' => $cart->get_fees(),
			'shipping' => WC()->session->get( 'chosen_shipping_methods', array() ),
			'coupons' => $cart->get_applied_coupons(),
			// get_data() excludes pending address changes on an existing customer.
			'customer' => array_merge( $customer->get_data(), $addresses ),
			'currency' => get_woocommerce_currency(),
		) ) );
	}

	/** Product identities, quantities and prices all come from the saved order. */
	private function sahcfwc_order_line_items( $order ) {
		$currency = strtolower( $order->get_currency() );
		$lines = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$amount = $this->sahcfwc_order_minor_amount( $item->get_total(), $currency );
			if ( $amount < 0 ) { continue; }
			$quantity = (float) $item->get_quantity();
			$name = $item->get_name();
			if ( $quantity < 1 || floor( $quantity ) !== $quantity ) {
				$name .= ' (' . $quantity . ')';
				$quantity = 1;
			}
			$product_data = array( 'name' => sanitize_text_field( $name ), 'metadata' => array(
				'order_id' => (string) $order->get_id(),
				'order_item_id' => (string) $item->get_id(),
				'product_id' => (string) $item->get_product_id(),
				'variation_id' => (string) $item->get_variation_id(),
			) );
			$product = $item->get_product();
			$description = array();
			foreach ( $item->get_formatted_meta_data() as $meta ) {
				$description[] = sanitize_text_field( wp_strip_all_tags( $meta->display_key . ': ' . $meta->display_value ) );
			}
			if ( $description ) { $product_data['description'] = implode( ', ', $description ); }
			$image = $product ? wp_get_attachment_image_url( $product->get_image_id(), 'full' ) : false;
			if ( $image && 'https' === wp_parse_url( $image, PHP_URL_SCHEME ) ) { $product_data['images'] = array( $image ); }
			// Checkout payment mode requires integer minor-unit prices. Split only the
			// rounding remainder, preserving the exact order amount and total quantity.
			$quantity = (int) $quantity;
			$quantum = in_array( $currency, array( 'isk', 'ugx' ), true ) ? 100 : 1;
			$whole_units = (int) round( $amount / $quantum );
			$base_amount = (int) floor( $whole_units / $quantity ) * $quantum;
			$remainder = $whole_units % $quantity;
			$tiers = array( array( $quantity - $remainder, $base_amount ), array( $remainder, $base_amount + $quantum ) );
			foreach ( $tiers as $tier ) {
				if ( $tier[0] <= 0 ) { continue; }
				$line = array( 'quantity' => $tier[0], 'price_data' => array(
					'currency' => $currency, 'product_data' => $product_data, 'unit_amount' => $tier[1],
				) );
				// Keep the existing premium option; changing this feature is deferred.
				if ( function_exists( 'sahcfwc_fs' ) && sahcfwc_fs()->is__premium_only() && 'yes' === $this->sahcfwc_stripe_adjustment_quantity ) {
					$line['adjustable_quantity'] = array( 'enabled' => true, 'minimum' => $tier[0] );
				}
				$lines[] = $line;
			}
		}
		foreach ( $order->get_items( 'fee' ) as $item ) {
			$amount = $this->sahcfwc_order_minor_amount( $item->get_total(), $currency );
			if ( $amount > 0 ) { $lines[] = $this->sahcfwc_fixed_order_line( $item->get_name(), $amount, $currency, 'fee', $item->get_id() ); }
		}
		foreach ( $order->get_items( 'tax' ) as $item ) {
			$amount = $this->sahcfwc_order_minor_amount( (float) $item->get_tax_total() + (float) $item->get_shipping_tax_total(), $currency );
			if ( $amount > 0 ) { $lines[] = $this->sahcfwc_fixed_order_line( $item->get_label() ?: __( 'Tax', 'sa-hosted-checkout-for-woocommerce' ), $amount, $currency, 'tax', $item->get_id() ); }
		}
		return $lines;
	}

	private function sahcfwc_fixed_order_line( $name, $amount, $currency, $type, $item_id = 0 ) {
		return array( 'quantity' => 1, 'price_data' => array(
			'currency' => $currency, 'unit_amount' => $amount,
			'product_data' => array( 'name' => sanitize_text_field( $name ), 'metadata' => array( 'order_item_type' => $type, 'order_item_id' => (string) $item_id ) ),
		) );
	}

	/** Build and persist a reconciled snapshot before making any payment API request. */
	private function sahcfwc_create_order_checkout_session( $order, $params ) {
		require_once dirname( __DIR__ ) . '/classes/class-sahcfwc-payment-completion.php';
		if ( ! $order ) { throw new \RuntimeException( __( 'The order could not be found.', 'sa-hosted-checkout-for-woocommerce' ) ); }
		$result = \SAHCFWC\Classes\SAHCFWC_Payment_Completion::with_order_lock( $order->get_id(), function ( $fresh_order ) use ( $params ) {
			return $this->sahcfwc_create_locked_order_checkout_session( $fresh_order, $params );
		} );
		if ( is_wp_error( $result ) ) { throw new \RuntimeException( $result->get_error_message() ); }
		return $result;
	}

	private function sahcfwc_create_locked_order_checkout_session( $order, $params, $allow_expiry_retry = true ) {
		$original_params = $params;
		if ( ! $order || ! $order->needs_payment() ) {
			throw new \RuntimeException( __( 'This order does not require payment.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$currency = strtolower( $order->get_currency() );
		$expected = $this->sahcfwc_order_minor_amount( $order->get_total(), $currency );
		if ( in_array( $currency, array( 'isk', 'ugx' ), true ) && abs( (float) $order->get_total() - round( (float) $order->get_total() ) ) > 0.000001 ) {
			throw new \RuntimeException( __( 'Stripe requires a whole-number order total for this currency.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		if ( $expected <= 0 ) {
			throw new \RuntimeException( __( 'Complete this order through the WooCommerce checkout.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$params['line_items'] = $this->sahcfwc_order_line_items( $order );
		unset( $params['discounts'], $params['shipping_options'], $params['automatic_tax'], $params['allow_promotion_codes'] );
		$subtotal = 0;
		foreach ( $params['line_items'] as $line ) {
			$price = $line['price_data'];
			$subtotal += (int) round( (float) ( isset( $price['unit_amount'] ) ? $price['unit_amount'] : $price['unit_amount_decimal'] ) * $line['quantity'] );
		}
		$shipping = max( 0, $this->sahcfwc_order_minor_amount( $order->get_shipping_total(), $currency ) );
		$shipping_names = array();
		foreach ( $order->get_items( 'shipping' ) as $item ) { $shipping_names[] = $item->get_name(); }
		$shipping_name = implode( ', ', $shipping_names ) ?: __( 'Shipping', 'sa-hosted-checkout-for-woocommerce' );
		$discount = $subtotal + $shipping - $expected;
		if ( $discount < 0 ) {
			$params['line_items'][] = $this->sahcfwc_fixed_order_line( __( 'Rounding adjustment', 'sa-hosted-checkout-for-woocommerce' ), -$discount, $currency, 'rounding' );
			$subtotal -= $discount;
			$discount = 0;
		}
		// Stripe coupons cannot reduce shipping rates. Include shipping as a named line
		// when a negative adjustment also offsets the shipping charge.
		if ( $shipping > 0 && $discount > $subtotal ) {
			$params['line_items'][] = $this->sahcfwc_fixed_order_line( $shipping_name, $shipping, $currency, 'shipping' );
			$subtotal += $shipping;
			$shipping = 0;
		}
		if ( $shipping > 0 ) {
			$params['shipping_options'] = array( array( 'shipping_rate_data' => array(
				'display_name' => sanitize_text_field( $shipping_name ), 'type' => 'fixed_amount',
				'fixed_amount' => array( 'amount' => $shipping, 'currency' => $currency ),
			) ) );
		}
		if ( $subtotal + $shipping - $discount !== $expected || count( $params['line_items'] ) > 100 ) {
			throw new \RuntimeException( __( 'The order cannot be represented accurately in Stripe Checkout. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$binding = array( 'sahcfwc_order_id' => (string) $order->get_id(), 'sahcfwc_order_key' => $order->get_order_key() );
		$params['metadata'] = $binding;
		$params['payment_intent_data'] = array( 'description' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' Order #' . $order->get_order_number(), 'metadata' => $binding );
		// Never reuse a user-meta Stripe customer from another account or mode.
		unset( $params['customer'], $params['customer_email'] );
		if ( $order->get_billing_email() ) { $params['customer_email'] = $order->get_billing_email(); }
		$params['client_reference_id'] = (string) $order->get_id();
		if ( isset( $params['shipping_address_collection'] ) ) {
			$destination = $order->get_shipping_country() ?: $order->get_billing_country();
			$destination = $destination ?: WC()->countries->get_base_country();
			if ( ! preg_match( '/^[A-Z]{2}$/', $destination ) ) {
				throw new \RuntimeException( __( 'Select a shipping country before starting payment.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			// Zone locations can be states, postcodes or continents, which Stripe rejects.
			$params['shipping_address_collection'] = array( 'allowed_countries' => array( $destination ) );
		}
		foreach ( array( 'success_url', 'cancel_url', 'return_url' ) as $url_key ) {
			if ( isset( $params[ $url_key ] ) ) {
				$action = 'cancel_url' === $url_key ? 'sahcfwc_stripe_cancel_order' : ( 'return_url' === $url_key ? 'sahcfwc_stripe_embedded_checkout_return' : 'sahcfwc_stripe_checkout_order' );
				$params[ $url_key ] = add_query_arg( array( 'wc-ajax' => $action, 'order_id' => $order->get_id(), 'order_key' => $order->get_order_key() ), home_url( '/' ) );
				if ( 'cancel_url' !== $url_key ) { $params[ $url_key ] .= '&sessionid={CHECKOUT_SESSION_ID}'; }
				// add_query_arg encodes the placeholder; Stripe requires it literally.
				$params[ $url_key ] = str_replace( '%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $params[ $url_key ] );
			}
		}
		$discount_names = array();
		foreach ( $order->get_items( 'fee' ) as $fee ) { if ( (float) $fee->get_total() < 0 ) { $discount_names[] = $fee->get_name(); } }
		$discount_name = $discount_names ? implode( ', ', $discount_names ) : __( 'Order adjustment', 'sa-hosted-checkout-for-woocommerce' );
		$mode = preg_match( '/^(sk|rk)_live_/', $this->sahcfwc_stripe_secret ) ? 'live' : 'test';
		$hash_params = $params;
		unset( $hash_params['expires_at'] );
		$fingerprint = hash( 'sha256', wp_json_encode( array( $hash_params, $discount, $discount_name, $mode ) ) );
		$previous_hash = (string) $order->get_meta( 'sahcfwc_checkout_payload_hash' );
		$session_id = (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id' );
		$client = new \SAHCFWC\Libraries\Stripe\StripeClient( $this->sahcfwc_stripe_secret );
		if ( $session_id ) {
			$previous_session = $client->checkout->sessions->retrieve( $session_id, array() );
			if ( 'open' === $previous_session->status ) {
				if ( $previous_hash === $fingerprint && (int) $previous_session->expires_at > time() + 30 ) {
					$this->sahcfwc_assert_session_amount( $previous_session, $expected, $currency, $mode, $client, $order->get_id() );
					return $previous_session;
				}
				throw new \RuntimeException( __( 'An active payment session already exists for this order. Return to the cart to start a new checkout.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			if ( 'expired' !== $previous_session->status ) {
				throw new \RuntimeException( __( 'This payment is being processed. Please check your order before trying again.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
		}
		$expires = (int) $order->get_meta( 'sahcfwc_checkout_expires_at' );
		// An uncertain request must retain its exact parameters and idempotency key,
		// even when its saved expiry is now too near for a brand-new Session.
		if ( $previous_hash !== $fingerprint || $expires <= 0 || $session_id ) { $expires = time() + 3600; }
		$params['expires_at'] = $expires;
		$order->set_payment_method( 'sahcfwc_stripe_checkout' );
		$order->update_meta_data( 'sahcfwc_checkout_expected_amount', $expected );
		$order->update_meta_data( 'sahcfwc_checkout_currency', $currency );
		$order->update_meta_data( 'sahcfwc_checkout_mode', $mode );
		$order->update_meta_data( 'sahcfwc_checkout_payload_hash', $fingerprint );
		$order->update_meta_data( 'sahcfwc_checkout_expires_at', $expires );
		$order->save();
		$idempotency = 'sahcfwc-' . hash( 'sha256', $order->get_order_key() . $fingerprint . $expires );
		try {
			if ( $discount > 0 ) {
				$coupon_id = $idempotency === $order->get_meta( 'sahcfwc_checkout_discount_request_key' ) ? $order->get_meta( 'sahcfwc_checkout_discount_id' ) : '';
				if ( ! $coupon_id ) {
					$request_expiry_parameter = 'redeem_by';
					$coupon = $client->coupons->create( array(
						'amount_off' => $discount, 'currency' => $currency, 'duration' => 'once',
						'max_redemptions' => 1, 'redeem_by' => $expires,
						'name' => function_exists( 'mb_substr' ) ? mb_substr( sanitize_text_field( $discount_name ), 0, 40 ) : substr( sanitize_text_field( $discount_name ), 0, 40 ),
						'metadata' => $binding,
					), array( 'idempotency_key' => $idempotency . '-discount' ) );
					$coupon_id = $coupon->id;
					$order->update_meta_data( 'sahcfwc_checkout_discount_request_key', $idempotency );
					$order->update_meta_data( 'sahcfwc_checkout_discount_id', $coupon_id );
					$order->save();
				}
				$params['discounts'] = array( array( 'coupon' => $coupon_id ) );
			}
			$request_expiry_parameter = 'expires_at';
			$session = $client->checkout->sessions->create( $params, array( 'idempotency_key' => $idempotency ) );
		} catch ( \SAHCFWC\Libraries\Stripe\Exception\InvalidRequestException $error ) {
			// Replay first: Stripe returns a previously created Session for the same key.
			// Rotate only after an explicit expiry validation failure proves that this
			// request did not create a Session (or its prerequisite coupon).
			$expiry_invalid = 'expires_at' === $request_expiry_parameter ? $expires < time() + 1800 : $expires <= time();
			$expiry_error = $request_expiry_parameter === $error->getStripeParam();
			// API 2024-10-28.acacia omits param for this specific Session error.
			if ( 'expires_at' === $request_expiry_parameter && null === $error->getStripeParam()
				&& 'The `expires_at` timestamp must be at least 30 minutes from Checkout Session creation.' === $error->getMessage() ) { $expiry_error = true; }
			if ( ! $allow_expiry_retry || 400 !== $error->getHttpStatus() || ! $expiry_error || ! $expiry_invalid ) { throw $error; }
			$order->update_meta_data( 'sahcfwc_checkout_expires_at', 0 );
			$order->save();
			return $this->sahcfwc_create_locked_order_checkout_session( $order, $original_params, false );
		}
		$this->sahcfwc_assert_session_amount( $session, $expected, $currency, $mode, $client, $order->get_id() );
		$order->update_meta_data( 'sahcfwc_stripe_checkout_session_id', $session->id );
		$order->save();
		// An idempotent replay can recover a Session that has since expired. It is
		// safe to replace only after Stripe confirms that terminal state.
		if ( 'expired' === $session->status && $allow_expiry_retry ) {
			return $this->sahcfwc_create_locked_order_checkout_session( $order, $original_params, false );
		}
		if ( 'open' !== $session->status ) {
			throw new \RuntimeException( __( 'This payment is being processed. Please check your order before trying again.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		return $session;
	}

	/** Never expose a payable URL unless Stripe confirms the saved order total. */
	private function sahcfwc_assert_session_amount( $session, $expected, $currency, $mode, $client, $order_id ) {
		if ( isset( $session->amount_total, $session->currency, $session->livemode )
			&& (int) $session->amount_total === $expected
			&& strtolower( (string) $session->currency ) === $currency
			&& (bool) $session->livemode === ( 'live' === $mode ) ) { return; }
		if ( isset( $session->id ) && 'open' === $session->status && 'unpaid' === $session->payment_status ) {
			try { $client->checkout->sessions->expire( $session->id, array() ); } catch ( \Exception $error ) {
				wc_get_logger()->error( 'Unable to expire a mismatched checkout session for order ' . absint( $order_id ) . '.', array( 'source' => 'sahcfwc-checkout' ) );
			}
		}
		wc_get_logger()->error( 'Stripe Checkout total, currency or mode did not match order ' . absint( $order_id ) . '.', array( 'source' => 'sahcfwc-checkout' ) );
		throw new \RuntimeException( __( 'The payment total could not be verified. Please try again or contact the store.', 'sa-hosted-checkout-for-woocommerce' ) );
	}
}
