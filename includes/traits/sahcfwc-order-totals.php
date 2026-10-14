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

require_once dirname( __DIR__ ) . '/classes/class-sahcfwc-payment-methods.php';

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
		if ( $order && $order->has_status( 'on-hold' ) && 'sahcfwc_stripe_checkout' === $order->get_payment_method()
			&& (int) $order->get_customer_id() === get_current_user_id()
			&& $order->get_meta( 'sahcfwc_pending_session_id', true )
			&& $order->get_cart_hash() && hash_equals( $order->get_cart_hash(), $cart->get_cart_hash() ) ) {
			throw new \RuntimeException( __( 'Payment for this cart is awaiting confirmation. Check your order before paying again.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
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
	private function sahcfwc_order_line_items( $order, $adjust_quantities = false, $maxima = array() ) {
		$currency = strtolower( $order->get_currency() );
		$woo_quantum = max( 1, (int) round( \SAHCFWC\Classes\SAHCFWC_Money::multiplier( $currency ) / pow( 10, wc_get_price_decimals() ) ) );
		$lines = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			// Adjustable checkouts keep the original discount AMOUNT, not a discounted
			// unit price which would multiply a fixed coupon when quantities increase.
			$amount = $this->sahcfwc_order_minor_amount( $adjust_quantities ? $item->get_subtotal() : $item->get_total(), $currency );
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
				// A rounding split is not two independently adjustable products. Keep
				// those rows (and fractional/custom products) fixed in both editions.
				if ( $adjust_quantities && 0 === $remainder && $base_amount > 0 && 0 === $base_amount % $woo_quantum
					&& (float) $item->get_quantity() === (float) $quantity && $product
					&& $product->is_type( array( 'simple', 'variation' ) ) && ! $product->is_sold_individually() ) {
					$item_discount = max( 0, $amount - $this->sahcfwc_order_minor_amount( $item->get_total(), $currency ) );
					$minimum = max( 1, (int) ceil( $item_discount / $base_amount ) );
					$maximum = isset( $maxima[ $item->get_id() ] ) ? (int) $maxima[ $item->get_id() ] : $quantity;
					if ( $minimum <= $quantity && $maximum >= $quantity && $minimum < $maximum ) {
						$line['adjustable_quantity'] = array( 'enabled' => true, 'minimum' => $minimum, 'maximum' => $maximum );
					}
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

	/** Immutable maximum quantities, bounded by each shared stock pool. */
	private function sahcfwc_quantity_limits( $order ) {
		require_once dirname( __DIR__ ) . '/classes/class-sahcfwc-quantity-inventory.php';
		$maximum = max( 1, min( 999999, (int) get_option( 'sahcfwc_stripe_quantity_maximum', 10 ) ) );
		$requested = array();
		$currency = strtolower( $order->get_currency() );
		$quantum = in_array( $currency, array( 'isk', 'ugx' ), true ) ? 100 : 1;
		$quantum = max( $quantum, (int) round( \SAHCFWC\Classes\SAHCFWC_Money::multiplier( $currency ) / pow( 10, wc_get_price_decimals() ) ) );
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$quantity = (float) $item->get_quantity();
			$requested[ $item->get_id() ] = $quantity;
			$product = $item->get_product();
			$amount = $this->sahcfwc_order_minor_amount( $item->get_subtotal(), $currency );
			if ( $product && $product->is_type( array( 'simple', 'variation' ) ) && ! $product->is_sold_individually()
				&& $quantity >= 1 && floor( $quantity ) === $quantity && $amount > 0
				&& 0 === ( $amount % ( (int) $quantity * $quantum ) ) ) {
				$requested[ $item->get_id() ] = max( (int) $quantity, $maximum );
			}
		}
		return \SAHCFWC\Classes\SAHCFWC_Quantity_Inventory::limits( $order, $requested );
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
		$saved_contract = $order->get_meta( 'sahcfwc_quantity_contract', true );
		$saved_contract = is_array( $saved_contract ) && 1 === ( $saved_contract['version'] ?? 0 ) ? $saved_contract : array();
		$saved_session_id = (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id' );
		if ( $saved_contract && $saved_session_id ) {
			// A customer may already have edited this hosted Session. Its contract,
			// not the current admin settings or the starting amount, is authoritative.
			$existing_client = \SAHCFWC\Classes\SAHCFWC_Payment_Completion::client_for_order( $order );
			if ( is_wp_error( $existing_client ) ) { throw new \RuntimeException( $existing_client->get_error_message() ); }
			$existing_session = $existing_client->checkout->sessions->retrieve( $saved_session_id, array() );
			$valid = \SAHCFWC\Classes\SAHCFWC_Payment_Completion::validate( $order, $existing_session, $existing_client );
			if ( is_wp_error( $valid ) ) { throw new \RuntimeException( $valid->get_error_message() ); }
			if ( 'open' === $existing_session->status ) {
				if ( (int) $existing_session->expires_at > time() + 30 ) { return $existing_session; }
				throw new \RuntimeException( __( 'This checkout is about to expire. Please wait a moment before trying again.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			if ( 'expired' !== $existing_session->status || 'unpaid' !== $existing_session->payment_status ) {
				throw new \RuntimeException( __( 'This payment is being processed. Please check your order before trying again.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			// Stripe has positively confirmed that the old checkout is no longer
			// payable. A fresh contract/reservation is now safe for this pending order.
			foreach ( array( 'sahcfwc_quantity_contract', 'sahcfwc_stripe_checkout_session_id', 'sahcfwc_checkout_payload_hash', 'sahcfwc_checkout_expires_at' ) as $key ) { $order->delete_meta_data( $key ); }
			// Even a same-second replacement must have a distinct immutable request
			// key; otherwise Stripe replays the checkout we just proved expired.
			$order->update_meta_data( 'sahcfwc_quantity_generation', (int) $order->get_meta( 'sahcfwc_quantity_generation' ) + 1 );
			$order->save();
			$saved_contract = array();
		}
		$replay_contract = $saved_contract && ! $saved_session_id && $order->get_meta( 'sahcfwc_checkout_payload_hash' ) ? $saved_contract : array();
		$quantity_contract = array();
		$quantity_maxima = array();
		if ( $replay_contract ) {
			// Never change a lost-response request, even if the merchant changes
			// quantity/payment settings in the meantime. Replay the exact request.
			$intact = \SAHCFWC\Classes\SAHCFWC_Quantity_Adjustment::validate_order_snapshot( $order, $replay_contract );
			if ( is_wp_error( $intact ) || empty( $replay_contract['request_params'] ) || $expected !== (int) $replay_contract['initial_total_minor'] ) {
				throw new \RuntimeException( __( 'This order changed while checkout was being prepared. Verify the existing payment before trying again.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			if ( empty( $replay_contract['credential_scope'] ) || ! hash_equals( $replay_contract['credential_scope'], hash_hmac( 'sha256', 'sahcfwc-quantity-checkout', $this->sahcfwc_stripe_secret ) ) ) {
				throw new \RuntimeException( __( 'Stripe credentials changed while checkout was being prepared. Restore the original credentials and verify this order before retrying.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$params = $replay_contract['request_params'];
			$discount = (int) $replay_contract['fixed_discount_minor'];
			$shipping = (int) $replay_contract['fixed_shipping_minor'];
			$discount_name = $replay_contract['discount_name'];
			$mode = $replay_contract['mode'];
			$binding = $params['metadata'];
			$quantity_contract = $replay_contract;
		} else {
		$adjust_quantities = 'yes' === get_option( 'sahcfwc_stripe_adjustment_quantity', 'no' );
		if ( $adjust_quantities ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$quantity = (float) $item->get_quantity();
				// Extensions selling weights/fractions need their own quantity rules.
				// Preserve the normal checkout instead of blocking their purchase.
				if ( $quantity < 1 || floor( $quantity ) !== $quantity || $quantity > 999999 || ! $item->get_product() ) { $adjust_quantities = false; break; }
			}
		}
		if ( $adjust_quantities ) {
			if ( 0 !== strpos( trim( (string) get_option( 'sahcfwc_stripe_webhook_key', '' ) ), 'whsec_' ) ) {
				throw new \RuntimeException( __( 'Quantity changes require a configured Stripe webhook. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$quantity_maxima = $this->sahcfwc_quantity_limits( $order );
		}
		$params['line_items'] = $this->sahcfwc_order_line_items( $order, $adjust_quantities, $quantity_maxima );
		$adjustable_lines = array_filter( $params['line_items'], function ( $line ) { return ! empty( $line['adjustable_quantity']['enabled'] ); } );
		if ( $adjust_quantities && ! $adjustable_lines ) {
			$adjust_quantities = false;
			$params['line_items'] = $this->sahcfwc_order_line_items( $order );
		}
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
		if ( $adjust_quantities ) {
			$minimum_subtotal = 0;
			foreach ( $params['line_items'] as $line ) {
				$minimum_subtotal += $line['price_data']['unit_amount'] * ( $line['adjustable_quantity']['minimum'] ?? $line['quantity'] );
			}
			// An amount-off adjustment cannot exceed the minimum Stripe subtotal.
			// With large negative fees, preserve starting minima (increases only).
			if ( $discount > $minimum_subtotal ) {
				foreach ( $params['line_items'] as &$line ) {
					if ( ! empty( $line['adjustable_quantity']['enabled'] ) ) { $line['adjustable_quantity']['minimum'] = $line['quantity']; }
				}
				unset( $line );
			}
			foreach ( $params['line_items'] as $index => &$line ) { $line['price_data']['product_data']['metadata']['sahcfwc_line_key'] = 'row_' . $index; }
			unset( $line );
		}
		$binding = array( 'sahcfwc_order_id' => (string) $order->get_id(), 'sahcfwc_order_key' => $order->get_order_key() );
		if ( $adjust_quantities ) { $binding['sahcfwc_quantity_generation'] = (string) (int) $order->get_meta( 'sahcfwc_quantity_generation' ); }
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
		if ( $adjust_quantities ) { foreach ( $order->get_coupon_codes() as $code ) { $discount_names[] = $code; } }
		foreach ( $order->get_items( 'fee' ) as $fee ) { if ( (float) $fee->get_total() < 0 ) { $discount_names[] = $fee->get_name(); } }
		$discount_name = $discount_names ? implode( ', ', $discount_names ) : __( 'Order adjustment', 'sa-hosted-checkout-for-woocommerce' );
		$mode = preg_match( '/^(sk|rk)_live_/', $this->sahcfwc_stripe_secret ) ? 'live' : 'test';
		$params = \SAHCFWC\Classes\SAHCFWC_Payment_Methods::configure( $params, $order );
		$locale = $this->sahcfwc_get_stripe_checkout_locale();
		unset( $params['locale'] );
		if ( 'auto' !== $locale ) { $params['locale'] = $locale; }
		if ( $adjust_quantities ) {
			$quantity_contract = \SAHCFWC\Classes\SAHCFWC_Quantity_Adjustment::build_contract( $order, $params, $discount, $shipping );
			$quantity_contract['request_params'] = $params;
			$quantity_contract['discount_name'] = $discount_name;
			$quantity_contract['mode'] = $mode;
			$quantity_contract['credential_scope'] = hash_hmac( 'sha256', 'sahcfwc-quantity-checkout', $this->sahcfwc_stripe_secret );
		}
		}
		$hash_params = $params;
		unset( $hash_params['expires_at'] );
		$fingerprint = hash( 'sha256', wp_json_encode( array( $hash_params, $discount, $discount_name, $mode ) ) );
		$previous_hash = (string) $order->get_meta( 'sahcfwc_checkout_payload_hash' );
		$session_id = (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id' );
		if ( $previous_hash && ! $session_id && ! hash_equals( $previous_hash, $fingerprint ) ) {
			// A lost response may already have produced a payable Session. Changing
			// settings must not mint a second one, including unmanaged-stock orders.
			throw new \RuntimeException( __( 'Checkout settings changed while payment was being prepared. Restore the previous settings and verify the existing payment before trying again.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$customer_scope = (string) $order->get_meta( 'sahcfwc_checkout_customer_scope' );
		if ( $previous_hash && ! $session_id && $customer_scope
			&& ! hash_equals( $customer_scope, hash_hmac( 'sha256', 'sahcfwc-order-customer', $this->sahcfwc_stripe_secret ) ) ) {
			// A lost response may already have created a payable Session using the
			// original Customer. Never retry that request with a different Customer.
			throw new \RuntimeException( __( 'Stripe credentials changed while checkout was being prepared. Restore the original credentials and verify this order before retrying.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
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
			if ( $quantity_contract ) {
				$order->delete_meta_data( 'sahcfwc_stripe_checkout_session_id' );
				$order->delete_meta_data( 'sahcfwc_checkout_payload_hash' );
				$order->save();
			}
		}
		$expires = (int) $order->get_meta( 'sahcfwc_checkout_expires_at' );
		// An uncertain request must retain its exact parameters and idempotency key,
		// even when its saved expiry is now too near for a brand-new Session.
		if ( $previous_hash !== $fingerprint || $expires <= 0 || $session_id ) { $expires = time() + 3600; }
		$params['expires_at'] = $expires;
		if ( $quantity_contract && ! $replay_contract ) {
			// Hold the allowed maximum before any payable Stripe request. Do not
			// re-reserve after an uncertain request: Woo releases holds on failure.
			\SAHCFWC\Classes\SAHCFWC_Quantity_Inventory::reserve( $order, $quantity_maxima, $expires );
			$order->update_meta_data( 'sahcfwc_quantity_contract', $quantity_contract );
		}
		$order->set_payment_method( 'sahcfwc_stripe_checkout' );
		$order->update_meta_data( 'sahcfwc_checkout_expected_amount', $expected );
		$order->update_meta_data( 'sahcfwc_checkout_currency', $currency );
		$order->update_meta_data( 'sahcfwc_checkout_mode', $mode );
		$order->update_meta_data( 'sahcfwc_checkout_payload_hash', $fingerprint );
		$order->update_meta_data( 'sahcfwc_checkout_expires_at', $expires );
		$order->save();
		$idempotency = 'sahcfwc-' . hash( 'sha256', $order->get_order_key() . $fingerprint . $expires );
		$params = \SAHCFWC\Classes\SAHCFWC_Payment_Methods::prepare_customer( $params, $order, $client, $this->sahcfwc_stripe_secret );
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
			if ( $quantity_contract ) {
				// This specific rejection proves there is no payable request to replay.
				// Rebuild the quantity contract and renew its inventory hold safely.
				$order->delete_meta_data( 'sahcfwc_quantity_contract' );
				$order->delete_meta_data( 'sahcfwc_checkout_payload_hash' );
			}
			$order->update_meta_data( 'sahcfwc_checkout_expires_at', 0 );
			$order->save();
			return $this->sahcfwc_create_locked_order_checkout_session( $order, $original_params, false );
		}
		if ( $quantity_contract && $replay_contract ) {
			// An idempotent response can contain the customer's latest quantities.
			// Verify the frozen allowed changes, not just the initial amount.
			$order->update_meta_data( 'sahcfwc_stripe_checkout_session_id', $session->id );
			$order->save();
			$valid = \SAHCFWC\Classes\SAHCFWC_Payment_Completion::validate( $order, $session, $client );
			if ( is_wp_error( $valid ) ) { throw new \RuntimeException( $valid->get_error_message() ); }
		} else {
			$this->sahcfwc_assert_session_amount( $session, $expected, $currency, $mode, $client, $order->get_id() );
		}
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
