<?php
/**
 * SAHCFWC_Stripe_Checkout class.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */

namespace SAHCFWC\Settings;

if ( ! class_exists( '\SAHCFWC\Settings\SAHCFWC_Stripe_Checkout' ) ) {
	/**
	 * Regsiter stripe settings
	 *
	 * This class use Register stripe settings
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class SAHCFWC_Stripe_Checkout {
		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\SAHCFWC_Singleton;
		use \SAHCFWC\Traits\SAHCFWC_Helpers;
		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see function
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			$this->sahcfwc_stripe_checkout_register_settings();
		}

		/**
		 * Register setting function.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_stripe_checkout_register_settings() {
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_integration_mode',
				array(
					'default'           => 'test-mode',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_integration_mode' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_test_secret_key',
				array(
					'default'           => '',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_secret_key' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_live_secret_key',
				array(
					'default'           => '',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_live_secret_key' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_checkout_status',
				array(
					'default'           => 'no',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_checkout_status' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_shipping_address_status',
				array(
					'default'           => 'no',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_shipping_address_status' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_terms_condition_status',
				array(
					'default'           => 'no',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_terms_condition_status' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_phone_num_status',
				array(
					'default'           => 'no',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_phone_num_status' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_cancel_url',
				array(
					'default'           => '',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_cancel_url' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_delete_temp_order_status',
				array(
					'default'           => 'yes',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_delete_temp_order_status' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_stripe_webhook_key',
				array(
					'default'           => '',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_webhook_key' ),
				)
			);
			// Add these new settings for Restricted API Keys
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_use_restricted_keys',
				array(
					'default'           => 'no',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_restricted_keys_status' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_restricted_test_key',
				array(
					'default'           => '',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_restricted_test_key' ),
				)
			);
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_restricted_live_key',
				array(
					'default'           => '',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_restricted_live_key' ),
				)
			);
			// Add this to your register_settings function
			register_setting(
				'sahcfwc_stripe_checkout_settings',
				'sahcfwc_api_key_type',
				array(
					'default'           => 'standard',
					'show_in_rest'      => true,
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sahcfwc_sanitize_api_key_type' ),
				)
			);
		}

		/**
		 * Define your sanitization function.
		 *
		 * @since 1.0.0
		 *
		 * @param  string $input sanizie field value.
		 * @return boolean|input string value
		 */
		public function sahcfwc_sanitize_integration_mode( $input ) {
			// Allow only 'test-mode' or 'live-mode' as valid inputs.
			return ( 'test-mode' === $input || 'live-mode' === $input ? $input : 'test-mode' );
		}

		/**
		 * Define a sanitization function for the Stripe test secret key.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized string value, or an empty string if invalid.
		 */
		public function sahcfwc_sanitize_secret_key( $input ) {
			// Strip out any unwanted characters (e.g., white spaces or special characters).
			$sanitized_key = sanitize_text_field( $input );
			// Ensure the key matches the expected pattern for Stripe secret keys.
			if ( preg_match( '/^sk_test_[a-zA-Z0-9]{32,}$/', $sanitized_key ) ) {
				return $sanitized_key;
			} else {
				// Return an empty string or a default value if the input is invalid.
				return '';
			}
		}

		/**
		 * Define a sanitization function for the Stripe live secret key.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized string value, or an empty string if invalid.
		 */
		public function sahcfwc_sanitize_live_secret_key( $input ) {
			// Strip out any unwanted characters (e.g., white spaces or special characters).
			$sanitized_key = sanitize_text_field( $input );
			// Ensure the key matches the expected pattern for Stripe secret keys.
			if ( preg_match( '/^sk_live_[a-zA-Z0-9]{32,}$/', $sanitized_key ) ) {
				return $sanitized_key;
			} else {
				// Return an empty string or a default value if the input is invalid.
				return '';
			}
		}

		/**
		 * Define a sanitization function for the Stripe checkout status.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized string value, defaulting to 'no' if invalid.
		 */
		public function sahcfwc_sanitize_checkout_status( $input ) {
			// Allow only 'yes' or 'no' as valid inputs.
			return ( 'yes' === $input || 'no' === $input ? $input : 'no' );
		}

		/**
		 * Define a sanitization function for the Stripe shipping address status.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized string value, defaulting to 'no' if invalid.
		 */
		public function sahcfwc_sanitize_shipping_address_status( $input ) {
			// Allow only 'yes' or 'no' as valid inputs.
			return ( 'yes' === $input || 'no' === $input ? $input : 'no' );
		}

		/**
		 * Define a sanitization function for the Stripe terms and conditions status.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized string value, defaulting to 'no' if invalid.
		 */
		public function sahcfwc_sanitize_terms_condition_status( $input ) {
			// Allow only 'yes' or 'no' as valid inputs.
			return ( 'yes' === $input || 'no' === $input ? $input : 'no' );
		}

		/**
		 * Define a sanitization function for the Stripe phone number status.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized string value, defaulting to 'no' if invalid.
		 */
		public function sahcfwc_sanitize_phone_num_status( $input ) {
			// Allow only 'yes' or 'no' as valid inputs.
			return ( 'yes' === $input || 'no' === $input ? $input : 'no' );
		}

		/**
		 * Define a sanitization function for the Stripe cancel URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized URL, or an empty string if invalid.
		 */
		public function sahcfwc_sanitize_cancel_url( $input ) {
			// Sanitize the input and check if it is a valid URL.
			$sanitized_url = esc_url_raw( $input );
			// Return the sanitized URL if valid; otherwise, return an empty string.
			return ( ! empty( $sanitized_url ) ? $sanitized_url : '' );
		}

		/**
		 * Define a sanitization function for the Stripe delete temporary order status.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized status, defaulting to 'yes' if invalid.
		 */
		public function sahcfwc_sanitize_delete_temp_order_status( $input ) {
			// Allow only 'yes' or 'no' as valid inputs.
			return ( 'yes' === $input || 'no' === $input ? $input : 'no' );
		}

		/**
		 * Define a sanitization function for the Stripe webhook key.
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string The sanitized webhook key, or an empty string if invalid.
		 */
		public function sahcfwc_sanitize_webhook_key( $input ) {
			// Sanitize the input by stripping out unwanted characters.
			$sanitized_key = sanitize_text_field( $input );
			// Ensure the key matches the expected pattern for Stripe webhook keys.
			// Stripe webhook keys start with 'whsec_' followed by alphanumeric characters.
			if ( preg_match( '/^whsec_[a-zA-Z0-9]+$/', $sanitized_key ) ) {
				return $sanitized_key;
			} else {
				// Return an empty string if the input is invalid.
				return '';
			}
		}

		/**
		* Sanitize restricted keys status
		*
		* @since 1.0.0
		*
		* @param string $input The value to sanitize.
		* @return string 'yes' or 'no'
		*/
		public function sahcfwc_sanitize_restricted_keys_status($input) {
			return ($input === 'yes' || $input === 'no') ? $input : 'no';
		}

		/**
		 * Sanitize restricted test key
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string Sanitized key or empty string if invalid
		 */
		public function sahcfwc_sanitize_restricted_test_key($input) {
			$sanitized_key = sanitize_text_field($input);
			// Restricted keys have different patterns than regular keys
			if (preg_match('/^rk_test_[a-zA-Z0-9]{24,}$/', $sanitized_key)) {
				return $sanitized_key;
			}
			return '';
		}

		/**
		 * Sanitize restricted live key
		 *
		 * @since 1.0.0
		 *
		 * @param string $input The value to sanitize.
		 * @return string Sanitized key or empty string if invalid
		 */
		public function sahcfwc_sanitize_restricted_live_key($input) {
			$sanitized_key = sanitize_text_field($input);
			// Restricted keys have different patterns than regular keys
			if (preg_match('/^rk_live_[a-zA-Z0-9]{24,}$/', $sanitized_key)) {
				return $sanitized_key;
			}
			return '';
		}

		/**
		* Sanitize api key Type
		*
		* @since 1.0.0
		*
		* @param string $input The value to sanitize.
		* @return string 'standard' or 'restricted'
		*/
		public function sahcfwc_sanitize_api_key_type($input) {
			return ($input === 'standard' || $input === 'restricted') ? $input : 'standard';
		}
	}
}
