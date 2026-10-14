<?php
/**
 * Reconcile explicitly permitted Checkout quantity changes against a frozen contract.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */
namespace SAHCFWC\Classes;

require_once __DIR__ . '/class-sahcfwc-quantity-inventory.php';

final class SAHCFWC_Quantity_Adjustment {
	const CONTRACT = 'sahcfwc_quantity_contract';
	const FINAL_SESSION = 'sahcfwc_quantity_finalized_session_id';
	const FINAL_HASH = 'sahcfwc_quantity_finalized_hash';
	const FINAL_QUANTITIES = 'sahcfwc_quantity_finalized_quantities';

	private static function value( $object, $key, $default = null ) {
		if ( is_array( $object ) || $object instanceof \ArrayAccess ) { return isset( $object[ $key ] ) ? $object[ $key ] : $default; }
		return is_object( $object ) && isset( $object->$key ) ? $object->$key : $default;
	}

	private static function error( $message = '', $status = 400 ) {
		return new \WP_Error( 'sahcfwc_quantity_mismatch', $message ?: __( 'The checkout quantities or amounts could not be verified for this order.', 'sa-hosted-checkout-for-woocommerce' ), array( 'status' => $status ) );
	}

	private static function decimal( $value ) {
		if ( '' === $value || null === $value ) { $value = 0; }
		return (string) wc_format_decimal( $value, false, true );
	}

	private static function minor( $value, $currency ) {
		return SAHCFWC_Money::to_minor( $value, $currency );
	}

	private static function major( $value, $currency ) {
		$multiplier = SAHCFWC_Money::multiplier( $currency );
		$decimals = 1 === $multiplier ? 0 : ( 1000 === $multiplier ? 3 : 2 );
		return self::decimal( wc_format_decimal( $value / $multiplier, $decimals ) );
	}

	private static function taxes( $taxes ) {
		$result = array();
		foreach ( (array) $taxes as $kind => $values ) {
			$result[ $kind ] = array();
			foreach ( (array) $values as $id => $value ) { $result[ $kind ][ $id ] = self::decimal( $value ); }
			ksort( $result[ $kind ], SORT_STRING );
		}
		ksort( $result, SORT_STRING );
		return $result;
	}

	/** Exclude receipt/customer fields and operational stock metadata from financial identity. */
	private static function financial_state( $order ) {
		$state = array( 'order' => array(), 'items' => array() );
		foreach ( array( 'currency', 'total', 'discount_total', 'discount_tax', 'shipping_total', 'shipping_tax', 'cart_tax' ) as $field ) {
			$getter = 'get_' . $field;
			$state['order'][ $field ] = 'currency' === $field ? strtolower( $order->$getter() ) : self::decimal( $order->$getter() );
		}
		foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping', 'tax', 'coupon' ) ) as $id => $item ) {
			$type = $item->get_type();
			$fields = array( 'type' => $type );
			if ( 'line_item' === $type ) {
				$fields['product_id'] = (int) $item->get_product_id();
				$fields['variation_id'] = (int) $item->get_variation_id();
				foreach ( array( 'quantity', 'subtotal', 'total', 'subtotal_tax', 'total_tax' ) as $field ) {
					$getter = 'get_' . $field; $fields[ $field ] = self::decimal( $item->$getter() );
				}
				$fields['tax_class'] = (string) $item->get_tax_class();
				$fields['taxes'] = self::taxes( $item->get_taxes() );
			} elseif ( 'fee' === $type || 'shipping' === $type ) {
				$fields['total'] = self::decimal( $item->get_total() );
				$fields['total_tax'] = self::decimal( $item->get_total_tax() );
				$fields['taxes'] = self::taxes( $item->get_taxes() );
				if ( 'shipping' === $type ) {
					$fields['method_id'] = (string) $item->get_method_id();
					$fields['instance_id'] = (string) $item->get_instance_id();
				} else {
					$fields['tax_class'] = (string) $item->get_tax_class();
					$fields['tax_status'] = (string) $item->get_tax_status();
				}
			} elseif ( 'tax' === $type ) {
				$fields['rate_id'] = (int) $item->get_rate_id();
				$fields['compound'] = (bool) $item->get_compound();
				$fields['tax_total'] = self::decimal( $item->get_tax_total() );
				$fields['shipping_tax_total'] = self::decimal( $item->get_shipping_tax_total() );
			} elseif ( 'coupon' === $type ) {
				$fields['code'] = (string) $item->get_code();
				$fields['discount'] = self::decimal( $item->get_discount() );
				$fields['discount_tax'] = self::decimal( $item->get_discount_tax() );
			}
			$state['items'][ (string) $id ] = $fields;
		}
		ksort( $state['items'], SORT_STRING );
		return $state;
	}

	/** Build only before Session creation; never infer a contract from a returned payment. */
	public static function build_contract( $order, $params, $discount, $shipping ) {
		$contract = array(
			'version' => 1, 'currency' => strtolower( $order->get_currency() ),
			'initial_total_minor' => self::minor( $order->get_total(), $order->get_currency() ),
			'fixed_shipping_minor' => (int) $shipping, 'fixed_discount_minor' => (int) $discount, 'rows' => array(),
			'financial_state' => self::financial_state( $order ),
		);
		$adjustable = false; $initial_gross = 0; $product_rows = array();
		foreach ( $params['line_items'] as $line ) {
			$price = $line['price_data'];
			$metadata = isset( $price['product_data']['metadata'] ) ? $price['product_data']['metadata'] : array();
			$key = isset( $metadata['sahcfwc_line_key'] ) ? (string) $metadata['sahcfwc_line_key'] : '';
			$quantity = isset( $line['quantity'] ) ? (int) $line['quantity'] : 0;
			$unit = isset( $price['unit_amount'] ) ? $price['unit_amount'] : null;
			if ( ! $key || isset( $contract['rows'][ $key ] ) || $quantity < 1 || ! is_int( $unit ) || $unit < 0 ) {
				throw new \RuntimeException( __( 'The adjustable checkout could not be prepared safely.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$is_product = array_key_exists( 'product_id', $metadata );
			$enabled = ! empty( $line['adjustable_quantity']['enabled'] );
			$minimum = $enabled && isset( $line['adjustable_quantity']['minimum'] ) ? (int) $line['adjustable_quantity']['minimum'] : $quantity;
			$maximum = $enabled && isset( $line['adjustable_quantity']['maximum'] ) ? (int) $line['adjustable_quantity']['maximum'] : $quantity;
			if ( $minimum < 1 || $maximum > 999999 || $minimum > $quantity || $maximum < $quantity || ( $enabled && ( ! $is_product || $unit <= 0 || ! isset( $line['adjustable_quantity']['maximum'] ) ) ) ) {
				throw new \RuntimeException( __( 'The checkout quantity limits are invalid.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$row = array(
				'kind' => $is_product ? 'product' : ( isset( $metadata['order_item_type'] ) ? (string) $metadata['order_item_type'] : '' ),
				'order_item_id' => isset( $metadata['order_item_id'] ) ? (int) $metadata['order_item_id'] : 0,
				'product_id' => isset( $metadata['product_id'] ) ? (int) $metadata['product_id'] : 0,
				'variation_id' => isset( $metadata['variation_id'] ) ? (int) $metadata['variation_id'] : 0,
				'unit_minor' => $unit, 'initial_qty' => $quantity, 'min' => $minimum, 'max' => $maximum, 'adjustable' => $enabled,
				'metadata' => array_map( 'strval', $metadata ),
			);
			$contract['rows'][ $key ] = $row;
			$initial_gross += $unit * $quantity;
			if ( $is_product ) { $product_rows[ $row['order_item_id'] ][] = $row; }
			$adjustable = $adjustable || $enabled;
		}
		if ( ! $adjustable ) { return array(); }
		foreach ( $product_rows as $id => $rows ) {
			if ( ! isset( $contract['financial_state']['items'][ $id ] ) ) { throw new \RuntimeException( __( 'The checkout product does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) ); }
			$original = $contract['financial_state']['items'][ $id ];
			foreach ( $rows as $row ) {
				if ( ! $row['adjustable'] ) { continue; }
				$subtotal = self::minor( $original['subtotal'], $contract['currency'] );
				$item_discount = $subtotal - self::minor( $original['total'], $contract['currency'] );
				if ( 1 !== count( $rows ) || (float) $original['quantity'] !== (float) $row['initial_qty']
					|| $row['unit_minor'] * $row['initial_qty'] !== $subtotal || $item_discount < 0
					|| $row['unit_minor'] * $row['min'] < $item_discount ) {
					throw new \RuntimeException( __( 'This product cannot safely support adjustable quantities.', 'sa-hosted-checkout-for-woocommerce' ) );
				}
			}
		}
		if ( $initial_gross + $contract['fixed_shipping_minor'] - $contract['fixed_discount_minor'] !== $contract['initial_total_minor'] ) {
			throw new \RuntimeException( __( 'The adjustable checkout total does not match the order.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		ksort( $contract['rows'], SORT_STRING );
		return $contract;
	}

	public static function is_enabled( $order ) {
		return $order instanceof \WC_Order && (bool) $order->get_meta( self::CONTRACT, true );
	}

	private static function contract_valid( $contract ) {
		$valid = is_array( $contract ) && isset( $contract['version'], $contract['currency'], $contract['initial_total_minor'], $contract['fixed_shipping_minor'], $contract['fixed_discount_minor'], $contract['rows'], $contract['financial_state'] )
			&& 1 === $contract['version'] && is_array( $contract['rows'] ) && count( $contract['rows'] ) > 0 && count( $contract['rows'] ) <= 100
			&& isset( $contract['financial_state']['order'], $contract['financial_state']['items'] )
			&& is_array( $contract['financial_state']['order'] ) && is_array( $contract['financial_state']['items'] );
		if ( ! $valid ) { return false; }
		foreach ( array( 'initial_total_minor', 'fixed_shipping_minor', 'fixed_discount_minor' ) as $field ) {
			if ( ! is_int( $contract[ $field ] ) || $contract[ $field ] < 0 ) { return false; }
		}
		foreach ( $contract['rows'] as $key => $row ) {
			if ( ! is_string( $key ) || ! is_array( $row ) || ! isset( $row['kind'], $row['order_item_id'], $row['unit_minor'], $row['initial_qty'], $row['min'], $row['max'], $row['adjustable'], $row['metadata'] )
				|| ! is_array( $row['metadata'] ) || ! is_bool( $row['adjustable'] ) ) { return false; }
			foreach ( array( 'order_item_id', 'unit_minor', 'initial_qty', 'min', 'max' ) as $field ) {
				if ( ! is_int( $row[ $field ] ) || $row[ $field ] < 0 ) { return false; }
			}
			if ( $row['min'] < 1 || $row['max'] > 999999 || $row['min'] > $row['initial_qty'] || $row['max'] < $row['initial_qty']
				|| ( ! $row['adjustable'] && ( $row['min'] !== $row['initial_qty'] || $row['max'] !== $row['initial_qty'] ) ) ) { return false; }
		}
		return true;
	}

	/** Cheap, strict original-order integrity check for immutable Session retries. */
	public static function validate_order_snapshot( $order, $contract ) {
		return self::contract_valid( $contract ) && self::financial_state( $order ) === $contract['financial_state'] ? true : self::error();
	}

	/**
	 * Fetch every Stripe line, including expanded immutable product identities.
	 * Open validation is read-only. Finalization is called only under the order lock.
	 */
	public static function validate( $order, $session, $client, $finalize = false ) {
		if ( ! self::is_enabled( $order ) ) { return true; }
		$contract = $order->get_meta( self::CONTRACT, true );
		if ( ! self::contract_valid( $contract ) || $contract['currency'] !== strtolower( $order->get_currency() ) ) { return self::error(); }
		$session_id = (string) self::value( $session, 'id', '' );
		if ( ! $session_id || ! hash_equals( (string) $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true ), $session_id )
			|| $contract['currency'] !== strtolower( (string) self::value( $session, 'currency', '' ) ) ) { return self::error(); }
		if ( $finalize && ( 'complete' !== self::value( $session, 'status' )
			|| ! in_array( self::value( $session, 'payment_status' ), array( 'paid', 'unpaid', 'no_payment_required' ), true ) ) ) { return self::error(); }
		$quantities = array(); $gross = 0; $cursor = ''; $seen_ids = array();
		try {
			do {
				$params = array( 'limit' => 100, 'expand' => array( 'data.price.product' ) );
				if ( $cursor ) { $params['starting_after'] = $cursor; }
				$page = $client->checkout->sessions->allLineItems( $session_id, $params );
				$data = self::value( $page, 'data', array() );
				if ( ! is_array( $data ) || ! $data ) { return self::error(); }
				foreach ( $data as $line ) {
					$id = (string) self::value( $line, 'id', '' );
					$price = self::value( $line, 'price' );
					$metadata = self::value( self::value( $price, 'product' ), 'metadata', array() );
					$key = (string) self::value( $metadata, 'sahcfwc_line_key', '' );
					$quantity = self::value( $line, 'quantity' );
					if ( ! $id || isset( $seen_ids[ $id ] ) || ! isset( $contract['rows'][ $key ] ) || isset( $quantities[ $key ] ) || ! is_int( $quantity ) ) { return self::error(); }
					$row = $contract['rows'][ $key ];
					if ( $quantity < $row['min'] || $quantity > $row['max'] || self::value( $price, 'unit_amount' ) !== $row['unit_minor']
						|| $contract['currency'] !== strtolower( (string) self::value( $price, 'currency', '' ) )
						|| self::value( $line, 'amount_subtotal' ) !== $row['unit_minor'] * $quantity ) { return self::error(); }
					foreach ( $row['metadata'] as $name => $value ) {
						if ( (string) self::value( $metadata, $name, '' ) !== $value ) { return self::error(); }
					}
					$seen_ids[ $id ] = true; $quantities[ $key ] = $quantity; $gross += $row['unit_minor'] * $quantity; $cursor = $id;
				}
				$has_more = (bool) self::value( $page, 'has_more', false );
				if ( count( $quantities ) > count( $contract['rows'] ) || ( $has_more && count( $quantities ) >= count( $contract['rows'] ) ) ) { return self::error(); }
			} while ( $has_more );
		} catch ( \Exception $error ) {
			return self::error( __( 'Unable to verify the final checkout quantities. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
		}
		if ( count( $quantities ) !== count( $contract['rows'] ) ) { return self::error(); }
		ksort( $quantities, SORT_STRING );
		$total = $gross + $contract['fixed_shipping_minor'] - $contract['fixed_discount_minor'];
		$details = self::value( $session, 'total_details' );
		if ( $total < 0 || self::value( $session, 'amount_total' ) !== $total
			|| self::value( $details, 'amount_discount' ) !== $contract['fixed_discount_minor']
			|| self::value( $details, 'amount_shipping' ) !== $contract['fixed_shipping_minor']
			|| 0 !== self::value( $details, 'amount_tax' ) ) { return self::error(); }
		$target = $contract['financial_state'];
		$delta = 0;
		foreach ( $contract['rows'] as $key => $row ) {
			if ( ! $row['adjustable'] ) { continue; }
			$id = $row['order_item_id'];
			if ( ! isset( $target['items'][ $id ] ) ) { return self::error(); }
			$original = $contract['financial_state']['items'][ $id ];
			$discount = self::minor( $original['subtotal'], $contract['currency'] ) - self::minor( $original['total'], $contract['currency'] );
			$subtotal = $row['unit_minor'] * $quantities[ $key ];
			if ( $subtotal < $discount ) { return self::error(); }
			$target['items'][ $id ]['quantity'] = (string) $quantities[ $key ];
			$target['items'][ $id ]['subtotal'] = self::major( $subtotal, $contract['currency'] );
			$target['items'][ $id ]['total'] = self::major( $subtotal - $discount, $contract['currency'] );
			$delta += $row['unit_minor'] * ( $quantities[ $key ] - $row['initial_qty'] );
		}
		if ( $contract['initial_total_minor'] + $delta !== $total ) { return self::error(); }
		$target['order']['total'] = self::major( $total, $contract['currency'] );
		$current = self::financial_state( $order );
		$final_session = (string) $order->get_meta( self::FINAL_SESSION, true );
		$hash = hash( 'sha256', wp_json_encode( array( $session_id, $contract['rows'], $quantities, $target ) ) );
		if ( $final_session ) {
			if ( ! hash_equals( $final_session, $session_id ) || ! hash_equals( (string) $order->get_meta( self::FINAL_HASH, true ), $hash )
				|| $order->get_meta( self::FINAL_QUANTITIES, true ) !== $quantities || $current !== $target
				|| (int) $order->get_meta( 'sahcfwc_checkout_expected_amount', true ) !== $total ) { return self::error(); }
			// A failed delayed payment releases its stock. A later success must not
			// reuse that old finalization as permission to sell unreserved inventory.
			if ( $finalize && in_array( self::value( $session, 'payment_status' ), array( 'paid', 'no_payment_required' ), true )
				&& ! $order->is_paid() && ! $order->get_data_store()->get_stock_reduced( $order->get_id() ) ) {
				return self::require_reservation( $order, $contract, $session_id );
			}
			return true;
		}
		// A failed save may leave a subset of fields at the exact final values.
		// Only complete Sessions may resume that deterministic, idempotent write.
		$recoverable = 'complete' === self::value( $session, 'status' )
			? self::is_original_or_target( $current, $contract['financial_state'], $target )
			: $current === $contract['financial_state'];
		$saved_amount = $order->get_meta( 'sahcfwc_checkout_expected_amount', true );
		if ( ! $recoverable || ! is_numeric( $saved_amount ) || ! in_array( (int) $saved_amount, array( $contract['initial_total_minor'], $total ), true ) ) { return self::error(); }
		$has_changed = $target !== $contract['financial_state'];
		if ( $has_changed && ( $order->is_paid() || $order->get_meta( 'sahcfwc_completed_session_id', true ) || $order->has_status( array( 'refunded', 'trash' ) )
			|| $order->get_data_store()->get_stock_reduced( $order->get_id() ) ) ) {
			return self::error( __( 'This order has already affected stock or fulfillment. Review it before changing its quantities.', 'sa-hosted-checkout-for-woocommerce' ), 409 );
		}
		if ( ! $finalize ) { return true; }
		$reservation = self::require_reservation( $order, $contract, $session_id );
		if ( is_wp_error( $reservation ) ) { return $reservation; }
		try {
			$adjustable_items = array();
			foreach ( $contract['rows'] as $row ) { if ( $row['adjustable'] ) { $adjustable_items[ $row['order_item_id'] ] = true; } }
			foreach ( $target['items'] as $id => $fields ) {
				if ( 'line_item' !== $fields['type'] || ! isset( $adjustable_items[ $id ] ) ) { continue; }
				// Mutate the order-owned instance; get_item() defaults to a detached DB object.
				$item = $order->get_item( $id, false );
				if ( ! $item ) { return self::error(); }
				$item->set_quantity( $fields['quantity'] );
				$item->set_subtotal( $fields['subtotal'] );
				$item->set_total( $fields['total'] );
			}
			// No cart/tax recalculation: this feature explicitly keeps existing charges fixed.
			$order->set_total( $target['order']['total'] );
			$order->update_meta_data( 'sahcfwc_checkout_expected_amount', $total );
			if ( ! $order->save() ) { return self::error( '', 503 ); }
			$fresh_order = self::fresh_order( $order );
			if ( ! $fresh_order || self::financial_state( $fresh_order ) !== $target
				|| (int) $fresh_order->get_meta( 'sahcfwc_checkout_expected_amount', true ) !== $total ) { return self::error( '', 503 ); }
			// Persist and verify details before the final Session acts as the commit marker.
			$order->update_meta_data( self::FINAL_HASH, $hash );
			$order->update_meta_data( self::FINAL_QUANTITIES, $quantities );
			if ( ! $order->save() ) { return self::error( '', 503 ); }
			$fresh_order = self::fresh_order( $order );
			if ( (string) $fresh_order->get_meta( self::FINAL_HASH, true ) !== $hash || $fresh_order->get_meta( self::FINAL_QUANTITIES, true ) !== $quantities ) { return self::error( '', 503 ); }
			$order->update_meta_data( self::FINAL_SESSION, $session_id );
			if ( ! $order->save() ) { return self::error( '', 503 ); }
			$fresh_order = self::fresh_order( $order );
			if ( (string) $fresh_order->get_meta( self::FINAL_SESSION, true ) !== $session_id
				|| (string) $fresh_order->get_meta( self::FINAL_HASH, true ) !== $hash
				|| $fresh_order->get_meta( self::FINAL_QUANTITIES, true ) !== $quantities
				|| (int) $fresh_order->get_meta( 'sahcfwc_checkout_expected_amount', true ) !== $total
				|| self::financial_state( $fresh_order ) !== $target ) { return self::error( '', 503 ); }
			return true;
		} catch ( \Exception $error ) {
			return self::error( __( 'Unable to save the verified checkout quantities. Please try again.', 'sa-hosted-checkout-for-woocommerce' ), 503 );
		}
	}

	/** Bypass Woo's cached order instance and force persisted item/meta reads. */
	private static function fresh_order( $order ) {
		foreach ( $order->get_items( array( 'line_item', 'fee', 'shipping', 'tax', 'coupon' ) ) as $id => $item ) {
			wp_cache_delete( $id, 'order_item_meta' );
		}
		$class = get_class( $order );
		$fresh = new $class( $order->get_id() );
		$fresh->read_meta_data( true );
		return $fresh;
	}

	private static function require_reservation( $order, $contract, $session_id ) {
		$maxima = array();
		foreach ( $contract['rows'] as $row ) { if ( $row['adjustable'] ) { $maxima[ $row['order_item_id'] ] = $row['max']; } }
		try { $reserved = SAHCFWC_Quantity_Inventory::has_current_reservation( $order, $maxima ); }
		catch ( \Exception $error ) { $reserved = false; }
		if ( $reserved ) { return true; }
		if ( $session_id !== $order->get_meta( 'sahcfwc_quantity_stock_review_session', true ) ) {
			$order->add_order_note( __( 'Checkout quantities require manual review because the stock reservation is no longer available. Check the Stripe payment and inventory before fulfilling this order.', 'sa-hosted-checkout-for-woocommerce' ) );
			$order->update_meta_data( 'sahcfwc_quantity_stock_review_session', $session_id );
			$order->save_meta_data();
		}
		return new \WP_Error( 'sahcfwc_quantity_stock_review', __( 'This payment requires a stock review before the order can be fulfilled. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ), array( 'status' => 503 ) );
	}

	private static function is_original_or_target( $current, $original, $target ) {
		if ( ! is_array( $current ) || array_keys( $current ) !== array_keys( $original ) || array_keys( $current ) !== array_keys( $target ) ) { return false; }
		foreach ( $current as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( ! self::is_original_or_target( $value, $original[ $key ], $target[ $key ] ) ) { return false; }
			} elseif ( $value !== $original[ $key ] && $value !== $target[ $key ] ) { return false; }
		}
		return true;
	}
}
