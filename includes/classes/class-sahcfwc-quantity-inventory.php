<?php
/**
 * Bounded stock reservations for adjustable Stripe Checkout quantities.
 *
 * Call reserve() under the same per-order lock used for Checkout creation.
 * Order item quantities and purchased stock are never changed by this helper.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */
namespace SAHCFWC\Classes;

final class SAHCFWC_Quantity_Inventory {
	const GATEWAY = 'sahcfwc_stripe_checkout';
	const GRACE_SECONDS = 300;

	/** Explicit bootstrap registration; merely loading this class has no effects. */
	public static function register_hooks() {
		add_filter( 'woocommerce_cancel_unpaid_order', array( __CLASS__, 'should_cancel_unpaid_order' ), PHP_INT_MAX, 2 );
	}

	/** Keep only this plugin's active, bounded adjustable checkout out of unpaid-order cancellation. */
	public static function should_cancel_unpaid_order( $should_cancel, $order ) {
		if ( ! $should_cancel || ! $order instanceof \WC_Order || self::GATEWAY !== $order->get_payment_method()
			|| ! $order->has_status( 'pending' ) || $order->get_meta( 'sahcfwc_completed_session_id', true ) ) {
			return $should_cancel;
		}
		$expires = (int) $order->get_meta( 'sahcfwc_checkout_expires_at', true );
		$contract = $order->get_meta( 'sahcfwc_quantity_contract', true );
		if ( $expires <= 0 || $expires + self::GRACE_SECONDS <= time() || ! is_array( $contract )
			|| ! isset( $contract['version'], $contract['rows'] ) || 1 !== (int) $contract['version'] || ! is_array( $contract['rows'] ) ) {
			return $should_cancel;
		}
		foreach ( $contract['rows'] as $row ) {
			if ( ! is_array( $row ) || empty( $row['adjustable'] ) || ! isset( $row['kind'], $row['order_item_id'], $row['min'], $row['max'] )
				|| 'product' !== $row['kind'] || ! self::whole( $row['min'], true ) || ! self::whole( $row['max'] )
				|| (int) $row['max'] < (int) $row['min'] ) { continue; }
			$item = $order->get_item( (int) $row['order_item_id'] );
			if ( $item instanceof \WC_Order_Item_Product && (int) $item->get_order_id() === $order->get_id() ) { return false; }
		}
		return $should_cancel;
	}

	/**
	 * Cap requested item maxima against purchase limits and shared stock pools.
	 * Fixed/unrequested lines still consume baseline stock in their shared pool.
	 *
	 * @param \WC_Order $order Saved source order.
	 * @param array $requested Order-item ID => requested maximum.
	 * @return array Order-item ID => deterministic allowed maximum.
	 */
	public static function limits( $order, $requested ) {
		self::assert_order( $order );
		$items = self::items( $order );
		$maxima = self::normalize( $items, $requested );
		$pools = array();
		foreach ( $items as $id => $data ) {
			$product = $data['product'];
			if ( ! $product->is_in_stock() ) { throw self::error( __( 'An item in this order is no longer in stock.', 'sa-hosted-checkout-for-woocommerce' ) ); }
			if ( isset( $maxima[ $id ] ) ) {
				$purchase_max = $product->get_max_purchase_quantity();
				if ( is_numeric( $purchase_max ) && (float) $purchase_max >= 0 ) {
					$maxima[ $id ] = min( $maxima[ $id ], (int) floor( (float) $purchase_max ) );
					if ( $maxima[ $id ] < $data['quantity'] ) { throw self::error( __( 'The order exceeds an item purchase limit.', 'sa-hosted-checkout-for-woocommerce' ) ); }
				}
			}
			if ( ! $product->managing_stock() || $product->backorders_allowed() ) { continue; }
			$stock_id = (int) $product->get_stock_managed_by_id();
			if ( ! isset( $pools[ $stock_id ] ) ) {
				$stock_product = wc_get_product( $stock_id );
				if ( ! $stock_product ) { throw self::error( __( 'Stock for an order item could not be verified.', 'sa-hosted-checkout-for-woocommerce' ) ); }
				$reserver = self::reserver();
				$pools[ $stock_id ] = array(
					'available' => max( 0, (int) floor( (float) $stock_product->get_stock_quantity() - (float) $reserver->get_reserved_stock( $stock_product, $order->get_id() ) ) ),
					'baseline' => 0, 'items' => array(),
				);
			}
			$pools[ $stock_id ]['baseline'] += $data['quantity'];
			$pools[ $stock_id ]['items'][ $id ] = $data['quantity'];
		}
		foreach ( $pools as $pool ) {
			$remaining = $pool['available'] - $pool['baseline'];
			if ( $remaining < 0 ) { throw self::error( __( 'Not enough stock is available for this order. Please update your cart.', 'sa-hosted-checkout-for-woocommerce' ) ); }
			ksort( $pool['items'], SORT_NUMERIC );
			foreach ( $pool['items'] as $id => $baseline ) {
				if ( ! isset( $maxima[ $id ] ) ) { continue; }
				$extra = min( $remaining, $maxima[ $id ] - $baseline );
				$maxima[ $id ] = $baseline + $extra;
				$remaining -= $extra;
			}
		}
		ksort( $maxima, SORT_NUMERIC );
		return $maxima;
	}

	/**
	 * Read-only proof that every managed stock pool remains held through Checkout expiry plus grace.
	 */
	public static function has_reservation( $order, $maxima, $expires ) {
		self::assert_order( $order );
		$until = self::until( $expires );
		$required = self::required( $order, $maxima );
		return self::rows_cover( $order->get_id(), $required, $until );
	}

	/** Read-only settlement gate: all managed maximum quantities must still be held now. */
	public static function has_current_reservation( $order, $maxima ) {
		self::assert_order( $order );
		return self::rows_cover( $order->get_id(), self::required( $order, $maxima ), time() + 1 );
	}

	/**
	 * Reserve the maximum without saving altered order item quantities.
	 * Never replace insufficient holds after an outbound request may have been sent.
	 */
	public static function reserve( $order, $maxima, $expires ) {
		self::assert_order( $order );
		if ( ! $order->has_status( 'pending' ) ) { throw self::error( __( 'Stock can only be reserved for an unpaid checkout.', 'sa-hosted-checkout-for-woocommerce' ) ); }
		$until = self::until( $expires );
		$items = self::items( $order );
		$maxima = self::normalize( $items, $maxima );
		$required = self::required( $order, $maxima );
		if ( self::rows_cover( $order->get_id(), $required, $until ) ) {
			self::record( $order, $maxima, $until );
			return;
		}
		if ( $order->get_meta( 'sahcfwc_stripe_checkout_session_id', true ) || $order->get_meta( 'sahcfwc_checkout_payload_hash', true ) ) {
			throw self::error( __( 'Stock for this checkout needs verification. Check the existing payment session before trying again.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		$reserver = self::reserver();
		$order_id = $order->get_id();
		$quantity_filter = function ( $quantity, $filtered_order, $item ) use ( $order_id, $maxima ) {
			if ( $filtered_order instanceof \WC_Order && $filtered_order->get_id() === $order_id && isset( $maxima[ $item->get_id() ] ) ) {
				return $maxima[ $item->get_id() ];
			}
			return $quantity;
		};
		add_filter( 'woocommerce_order_item_quantity', $quantity_filter, PHP_INT_MAX, 3 );
		try {
			$reserver->reserve_stock_for_order( $order, (int) ceil( ( $until - time() ) / 60 ) );
		} finally {
			remove_filter( 'woocommerce_order_item_quantity', $quantity_filter, PHP_INT_MAX );
		}
		if ( ! self::rows_cover( $order_id, $required, $until ) ) {
			throw self::error( __( 'Stock could not be reserved for the full checkout period. Please contact the store.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		self::record( $order, $maxima, $until );
	}

	private static function assert_order( $order ) {
		if ( ! $order instanceof \WC_Order || ! $order->get_id() || self::GATEWAY !== $order->get_payment_method() ) {
			throw self::error( __( 'The checkout order could not be verified.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
	}

	private static function error( $message ) { return new \RuntimeException( $message ); }

	private static function whole( $value, $allow_zero = false ) {
		return ( is_int( $value ) || is_float( $value ) || is_string( $value ) )
			&& is_numeric( $value ) && is_finite( (float) $value ) && (float) $value === floor( (float) $value )
			&& (float) $value >= ( $allow_zero ? 0 : 1 ) && (float) $value <= PHP_INT_MAX;
	}

	private static function until( $expires ) {
		if ( ! self::whole( $expires ) || (int) $expires <= time() || (int) $expires > PHP_INT_MAX - self::GRACE_SECONDS ) {
			throw self::error( __( 'The checkout expiration time is invalid.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		return (int) $expires + self::GRACE_SECONDS;
	}

	private static function items( $order ) {
		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $id => $item ) {
			$product = $item->get_product();
			if ( ! $product || ! self::whole( $item->get_quantity() ) ) {
				throw self::error( __( 'This order cannot use adjustable checkout quantities.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$items[ (int) $id ] = array( 'product' => $product, 'quantity' => (int) $item->get_quantity() );
		}
		if ( ! $items ) { throw self::error( __( 'The checkout order has no purchasable items.', 'sa-hosted-checkout-for-woocommerce' ) ); }
		return $items;
	}

	private static function normalize( $items, $requested ) {
		if ( ! is_array( $requested ) || ! $requested ) { throw self::error( __( 'Checkout quantity limits are missing.', 'sa-hosted-checkout-for-woocommerce' ) ); }
		$result = array();
		foreach ( $requested as $id => $maximum ) {
			if ( ! self::whole( $id ) || ! isset( $items[ (int) $id ] ) || ! self::whole( $maximum ) || (int) $maximum < $items[ (int) $id ]['quantity'] ) {
				throw self::error( __( 'Checkout quantity limits are invalid.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
			$result[ (int) $id ] = (int) $maximum;
		}
		ksort( $result, SORT_NUMERIC );
		return $result;
	}

	private static function required( $order, $maxima ) {
		$items = self::items( $order );
		$maxima = self::normalize( $items, $maxima );
		$required = array();
		foreach ( $items as $id => $data ) {
			$product = $data['product'];
			if ( ! $product->managing_stock() || $product->backorders_allowed() ) { continue; }
			$stock_id = (int) $product->get_stock_managed_by_id();
			$required[ $stock_id ] = ( isset( $required[ $stock_id ] ) ? $required[ $stock_id ] : 0 ) + ( isset( $maxima[ $id ] ) ? $maxima[ $id ] : $data['quantity'] );
		}
		return $required;
	}

	private static function rows_cover( $order_id, $required, $until ) {
		if ( ! $required ) { return true; }
		self::reserver();
		global $wpdb;
		$table = $wpdb->prefix . 'wc_reserved_stock';
		$hpos = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$orders_table = $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts;
		$order_id_column = $hpos ? 'id' : 'ID';
		$status_column = $hpos ? 'status' : 'post_status';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT stock.product_id, stock.stock_quantity FROM {$table} stock INNER JOIN {$orders_table} orders ON stock.order_id = orders.{$order_id_column} WHERE stock.order_id = %d AND stock.expires >= FROM_UNIXTIME(%d) AND orders.{$status_column} IN ('wc-pending','wc-checkout-draft')",
			$order_id, $until
		), ARRAY_A );
		$held = array();
		foreach ( (array) $rows as $row ) { $held[ (int) $row['product_id'] ] = (float) $row['stock_quantity']; }
		foreach ( $required as $product_id => $quantity ) {
			if ( ! isset( $held[ $product_id ] ) || $held[ $product_id ] < $quantity ) { return false; }
		}
		return true;
	}

	private static function reserver() {
		if ( ! apply_filters( 'woocommerce_hold_stock_for_checkout', wc_string_to_bool( get_option( 'woocommerce_manage_stock', 'yes' ) ) ) ) {
			throw self::error( __( 'Stock reservations are disabled. Enable stock reservations before using adjustable checkout quantities.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		if ( ! class_exists( '\Automattic\WooCommerce\Checkout\Helpers\ReserveStock' ) || (int) get_option( 'woocommerce_schema_version', 0 ) < 430 ) {
			throw self::error( __( 'WooCommerce stock reservations are unavailable. Update the store database before enabling adjustable quantities.', 'sa-hosted-checkout-for-woocommerce' ) );
		}
		return new \Automattic\WooCommerce\Checkout\Helpers\ReserveStock();
	}

	private static function record( $order, $maxima, $until ) {
		if ( $maxima === $order->get_meta( 'sahcfwc_quantity_stock_maxima', true ) && $until === (int) $order->get_meta( 'sahcfwc_quantity_stock_expires', true ) ) { return; }
		$order->update_meta_data( 'sahcfwc_quantity_stock_maxima', $maxima );
		$order->update_meta_data( 'sahcfwc_quantity_stock_reserved_at', time() );
		$order->update_meta_data( 'sahcfwc_quantity_stock_expires', $until );
		$order->save_meta_data();
	}
}
