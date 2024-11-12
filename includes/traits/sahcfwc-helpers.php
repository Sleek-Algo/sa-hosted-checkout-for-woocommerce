<?php
/**
 * SAHCFWC_Helpers trait.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\Traits;

if ( ! trait_exists( '\SAHCFWC\Traits\SAHCFWC_Helpers' ) ) {

	/**
	 * Helper trait
	 *
	 * This class use for common function
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	trait SAHCFWC_Helpers {

		/**
		 * Register integration mode.
		 *
		 * @since 1.0.0
		 *
		 * @return string|result return mode
		 */
		public function sahcfwc_get_stripe_integration_mode() {
			$current_user                  = wp_get_current_user();
			$stripe_integration_mode       = sanitize_text_field( get_option( 'sahcfwc_stripe_integration_mode' ) );
			$stripe_admin_test_mode_status = sanitize_text_field( get_option( 'sahcfwc_stripe_admin_test_mode_status' ) );
			$result                        = $stripe_integration_mode;
			if ( 'yes' === $stripe_admin_test_mode_status && ( 0 !== $current_user->ID && in_array( 'administrator', $current_user->roles, true ) ) ) {
				$result = 'test-mode';
			}

			return $result;
		}

		/**
		 * Get stripe secret key.
		 *
		 * @since 1.0.0
		 *
		 * @return string|result return secret key.
		 */
		public function sahcfwc_get_stripe_secret_key() {
			$stripe_integration_mode = $this->sahcfwc_get_stripe_integration_mode();
			$stripe_test_secret_key  = sanitize_text_field( get_option( 'sahcfwc_stripe_test_secret_key' ) );
			$stripe_live_secret_key  = sanitize_text_field( get_option( 'sahcfwc_stripe_live_secret_key' ) );
			$reslut                  = '';
			if ( 'live-mode' === $stripe_integration_mode ) {
				$reslut = $stripe_live_secret_key;
			} else {
				$reslut = $stripe_test_secret_key;
			}

			return $reslut;
		}

		/**
		 * Get stripe account detail.
		 *
		 * @since 1.0.0
		 *
		 * @return array|stripe_account_detail return account detail.
		 */
		public function sahcfwc_get_stripe_account_detail() {
			$stripe_account_detail = array();
			// Retrieve your Stripe account details.
			if ( ! empty( $this->sahcfwc_get_stripe_secret_key() ) ) {
				try {
					if( class_exists('\SAHCFWC\Libraries\Stripe\Account') ){
						$stripe_account_detail = \SAHCFWC\Libraries\Stripe\Account::retrieve( null, array( 'api_key' => $this->sahcfwc_get_stripe_secret_key() ) );
					}
				} catch ( \SAHCFWC\Libraries\Stripe\Exception\AuthenticationException $e ) {
					$error = $e;
				} catch ( \Exception $e ) {
					$error = $e;
				}
			}

			return $stripe_account_detail;
		}

		/**
		 * Get match country WooCommerce and stripe.
		 *
		 * @since 1.0.0
		 *
		 * @return boolean
		 */
		public function sahcfwc_is_match_country_stripe_wc() {
			$store_raw_country   = sanitize_text_field( get_option( 'woocommerce_default_country' ) );
			$split_country       = explode( ':', $store_raw_country );
			$stripe_country_name = ( isset( $this->sahcfwc_get_stripe_account_detail()->country ) ) ? $this->sahcfwc_get_stripe_account_detail()->country : '';
			if ( $stripe_country_name === $split_country[0] ) {
				return true;
			}

			return false;
		}

		/**
		 * Get shipping method data.
		 *
		 * @since 1.0.0
		 *
		 * @return array shipping method detail.
		 */
		public function sahcfwc_chosen_shipping_method_data() {
			// Ensure packages are available by recalculating shipping if needed.
			WC()->cart->calculate_totals();
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			$packages                = WC()->shipping()->get_packages();
			foreach ( $packages as $package_index => $package ) {
				$available_methods = $package['rates'];
				foreach ( $available_methods as $method ) {
					if ( in_array( $method->get_id(), $chosen_shipping_methods, true ) ) {
						return array(
							'id'          => $method->get_id(),
							'method_id'   => $method->get_method_id(),
							'instance_id' => $method->get_instance_id(),
							'label'       => $method->get_label(),
							'cost'        => $method->get_cost(),
							'taxes'       => $method->get_taxes(),
							'meta_data'   => $method->get_meta_data(),
						);
					}
				}
			}

			// If no matching shipping method is found, return null or handle as needed.
			return null;
		}

		/**
		 * Create stripe customer.
		 *
		 * @since 1.0.0
		 *
		 * @param  object $order WooCommerce order object.
		 * @param  object $user_obj user object.
		 * @return object|response page redirect.
		 */
		public function sahcfwc_create_stripe_customer( $order, $user_obj ) {
			if ( ! empty( $order ) ) {
				$order_no = $order->get_order_number();
				$params   = array(
					'description' => 'Customer for Order #' . $order_no,
					'email'       => ( ( WC()->version < '2.7.0' ) ? $order->billing_email : $order->get_billing_email() ),
					'address'     => array(
						'city'        => ( method_exists( $order, 'get_billing_city' ) ) ? $order->get_billing_city() : $order->billing_city,
						'country'     => ( method_exists( $order, 'get_billing_country' ) ) ? $order->get_billing_country() : $order->billing_country,
						'line1'       => ( method_exists( $order, 'get_billing_address_1' ) ) ? $order->get_billing_address_1() : $order->billing_address_1,
						'line2'       => ( method_exists( $order, 'get_billing_address_2' ) ) ? $order->get_billing_address_2() : $order->billing_address_2,
						'postal_code' => ( method_exists( $order, 'get_billing_postcode' ) ) ? $order->get_billing_postcode() : $order->billing_postcode,
						'state'       => ( method_exists( $order, 'get_billing_state' ) ) ? $order->get_billing_state() : $order->billing_state,
					),

					'name'        => ( method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name ) . ' ' . ( method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name ),
				);
			} else {
				$fname  = ( isset( $user_obj->user_firstname ) ? $user_obj->user_firstname : '' );
				$lname  = ( isset( $user_obj->user_lastname ) ? $user_obj->user_lastname : '' );
				$params = array(
					'description' => 'Added manually',
					'name'        => $fname . ' ' . $lname,
					'email'       => $user_obj->user_email,
				);
			}
			if( class_exists('\SAHCFWC\Libraries\Stripe\Customer') ){
				$response = \SAHCFWC\Libraries\Stripe\Customer::create( $params );
			}

			if ( empty( $response->id ) ) {
				return false;
			}

			return $response;
		}

	}
}
