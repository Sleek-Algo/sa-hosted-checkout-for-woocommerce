<?php
/**
 * SAHCFWC_Helpers trait.
 *
 * @package sa-hosted-checkout-for-woocommerce
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
		 * Stripe Checkout languages, shared by settings validation and the admin UI.
		 *
		 * @return array Supported locale labels and values.
		 */
		public function sahcfwc_get_stripe_locale_options() {
			$locales = array(
				'auto'  => __( 'Automatic (browser language)', 'sa-hosted-checkout-for-woocommerce' ),
				'bg'    => __( 'Bulgarian', 'sa-hosted-checkout-for-woocommerce' ),
				'cs'    => __( 'Czech', 'sa-hosted-checkout-for-woocommerce' ),
				'da'    => __( 'Danish', 'sa-hosted-checkout-for-woocommerce' ),
				'de'    => __( 'German', 'sa-hosted-checkout-for-woocommerce' ),
				'el'    => __( 'Greek', 'sa-hosted-checkout-for-woocommerce' ),
				'en'    => __( 'English', 'sa-hosted-checkout-for-woocommerce' ),
				'en-GB' => __( 'English (United Kingdom)', 'sa-hosted-checkout-for-woocommerce' ),
				'es'    => __( 'Spanish', 'sa-hosted-checkout-for-woocommerce' ),
				'es-419' => __( 'Spanish (Latin America)', 'sa-hosted-checkout-for-woocommerce' ),
				'et'    => __( 'Estonian', 'sa-hosted-checkout-for-woocommerce' ),
				'fi'    => __( 'Finnish', 'sa-hosted-checkout-for-woocommerce' ),
				'fil'   => __( 'Filipino', 'sa-hosted-checkout-for-woocommerce' ),
				'fr'    => __( 'French', 'sa-hosted-checkout-for-woocommerce' ),
				'fr-CA' => __( 'French (Canada)', 'sa-hosted-checkout-for-woocommerce' ),
				'hr'    => __( 'Croatian', 'sa-hosted-checkout-for-woocommerce' ),
				'hu'    => __( 'Hungarian', 'sa-hosted-checkout-for-woocommerce' ),
				'id'    => __( 'Indonesian', 'sa-hosted-checkout-for-woocommerce' ),
				'it'    => __( 'Italian', 'sa-hosted-checkout-for-woocommerce' ),
				'ja'    => __( 'Japanese', 'sa-hosted-checkout-for-woocommerce' ),
				'ko'    => __( 'Korean', 'sa-hosted-checkout-for-woocommerce' ),
				'lt'    => __( 'Lithuanian', 'sa-hosted-checkout-for-woocommerce' ),
				'lv'    => __( 'Latvian', 'sa-hosted-checkout-for-woocommerce' ),
				'ms'    => __( 'Malay', 'sa-hosted-checkout-for-woocommerce' ),
				'mt'    => __( 'Maltese', 'sa-hosted-checkout-for-woocommerce' ),
				'nb'    => __( 'Norwegian Bokmal', 'sa-hosted-checkout-for-woocommerce' ),
				'nl'    => __( 'Dutch', 'sa-hosted-checkout-for-woocommerce' ),
				'pl'    => __( 'Polish', 'sa-hosted-checkout-for-woocommerce' ),
				'pt'    => __( 'Portuguese', 'sa-hosted-checkout-for-woocommerce' ),
				'pt-BR' => __( 'Portuguese (Brazil)', 'sa-hosted-checkout-for-woocommerce' ),
				'ro'    => __( 'Romanian', 'sa-hosted-checkout-for-woocommerce' ),
				'ru'    => __( 'Russian', 'sa-hosted-checkout-for-woocommerce' ),
				'sk'    => __( 'Slovak', 'sa-hosted-checkout-for-woocommerce' ),
				'sl'    => __( 'Slovenian', 'sa-hosted-checkout-for-woocommerce' ),
				'sv'    => __( 'Swedish', 'sa-hosted-checkout-for-woocommerce' ),
				'th'    => __( 'Thai', 'sa-hosted-checkout-for-woocommerce' ),
				'tr'    => __( 'Turkish', 'sa-hosted-checkout-for-woocommerce' ),
				'vi'    => __( 'Vietnamese', 'sa-hosted-checkout-for-woocommerce' ),
				'zh'    => __( 'Chinese (Simplified)', 'sa-hosted-checkout-for-woocommerce' ),
				'zh-HK' => __( 'Chinese (Hong Kong)', 'sa-hosted-checkout-for-woocommerce' ),
				'zh-TW' => __( 'Chinese (Traditional)', 'sa-hosted-checkout-for-woocommerce' ),
			);
			$options = array();
			foreach ( $locales as $value => $label ) {
				$options[] = array( 'label' => $label, 'value' => $value );
			}
			return $options;
		}

		/**
		 * Reject unsupported or legacy locale values before sending them to Stripe.
		 *
		 * @param mixed $input Saved or submitted locale.
		 * @return string Supported locale, or automatic browser detection.
		 */
		public function sahcfwc_sanitize_stripe_locale( $input ) {
			if ( ! is_string( $input ) ) {
				return 'auto';
			}
			foreach ( $this->sahcfwc_get_stripe_locale_options() as $option ) {
				if ( $input === $option['value'] ) {
					return $input;
				}
			}
			return 'auto';
		}

		/**
		 * Resolve the language for a new Stripe Checkout Session.
		 *
		 * @return string Supported locale.
		 */
		public function sahcfwc_get_stripe_checkout_locale() {
			if ( 'custom' !== get_option( 'sahcfwc_stripe_locale_status', 'default' ) ) {
				return 'auto';
			}
			return $this->sahcfwc_sanitize_stripe_locale( get_option( 'sahcfwc_stripe_locale', 'en' ) );
		}


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
		 * Check if restricted keys are being used.
		 *
		 * @since 1.0.0
		 *
		 * @return bool True if using restricted keys, false otherwise.
		 */
		public function sahcfwc_is_using_restricted_keys() {
			$api_key_type = sanitize_text_field(get_option('sahcfwc_api_key_type', 'standard'));
			return 'restricted' === $api_key_type;
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
			$api_key_type 			 = sanitize_text_field( get_option( 'sahcfwc_api_key_type', 'standard' ) );
			$reslut                  = '';
			if ( 'live-mode' === $stripe_integration_mode ) {
				if ('restricted' === $api_key_type) {
					return $this->sahcfwc_validate_stripe_key( get_option( 'sahcfwc_restricted_live_key', '' ), 'rk_live_' );
				}
				return $this->sahcfwc_validate_stripe_key( get_option( 'sahcfwc_stripe_live_secret_key', '' ), 'sk_live_' );
			} else {
				if ('restricted' === $api_key_type) {
					return $this->sahcfwc_validate_stripe_key( get_option( 'sahcfwc_restricted_test_key', '' ), 'rk_test_' );
				}
				return $this->sahcfwc_validate_stripe_key( get_option( 'sahcfwc_stripe_test_secret_key', '' ), 'sk_test_' );
			}

			return $reslut;
		}

		/** Do not let a key from the other mode silently take a real payment. */
		private function sahcfwc_validate_stripe_key( $key, $prefix ) {
			$key = trim( sanitize_text_field( $key ) );
			return 0 === strpos( $key, $prefix ) ? $key : '';
		}

		/** Only a mode-matched publishable key may reach the browser. */
		public function sahcfwc_get_stripe_public_key() {
			require_once dirname( __DIR__ ) . '/classes/class-sahcfwc-checkout-experience.php';
			$mode = 'live-mode' === $this->sahcfwc_get_stripe_integration_mode() ? 'live' : 'test';
			return \SAHCFWC\Classes\SAHCFWC_Checkout_Experience::public_key( $mode );
		}

		/**
		 * Get stripe account detail.
		 *
		 * @since 1.0.0
		 *
		 * @return array|stripe_account_detail return account detail.
		 */
		public function sahcfwc_get_stripe_account_detail() {
			static $account_cache = array();
			$key = $this->sahcfwc_get_stripe_secret_key();
			if ( '' === $key ) {
				return array();
			}
			$cache_key = hash( 'sha256', $key );
			if ( array_key_exists( $cache_key, $account_cache ) ) {
				return $account_cache[ $cache_key ];
			}
			$stripe_account_detail = array();
			// Retrieve your Stripe account details.
			if ( ! empty( $this->sahcfwc_get_stripe_secret_key() ) ) {
				try {
					if ( class_exists( '\SAHCFWC\Libraries\Stripe\Account' ) ) {
						$stripe_account_detail = \SAHCFWC\Libraries\Stripe\Account::retrieve( null, array( 'api_key' => $this->sahcfwc_get_stripe_secret_key() ) );
					}
				} catch ( \SAHCFWC\Libraries\Stripe\Exception\AuthenticationException $e ) {
					$error = $e;
				} catch ( \Exception $e ) {
					$error = $e;
				}
			}

			$account_cache[ $cache_key ] = $stripe_account_detail;
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
			$stripe_account      = $this->sahcfwc_get_stripe_account_detail();
			if ( ! isset( $stripe_account->country ) || ! $stripe_account->country ) {
				// Missing credentials or a failed API request is not a country mismatch.
				return null;
			}
			$stripe_country_name = $stripe_account->country;
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
			if ( ! WC()->cart || ! WC()->session ) {
				return null;
			}
			// Ensure packages are available by recalculating shipping if needed.
			WC()->cart->calculate_totals();
			$chosen_shipping_methods = (array) WC()->session->get( 'chosen_shipping_methods', array() );
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
					'email'       => $order->get_billing_email(),
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
			if ( class_exists( '\SAHCFWC\Libraries\Stripe\Customer' ) ) {
				$response = \SAHCFWC\Libraries\Stripe\Customer::create( $params );
			}

			if ( empty( $response->id ) ) {
				return false;
			}

			return $response;
		}
	}
}
