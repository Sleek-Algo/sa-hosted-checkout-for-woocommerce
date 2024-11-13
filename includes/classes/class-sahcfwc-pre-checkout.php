<?php
/**
 * SAHCFWC_Pre_Checkout class.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */

namespace SAHCFWC\Classes;

if ( ! class_exists( '\SAHCFWC\Classes\SAHCFWC_Pre_Checkout' ) ) {
	/**
	 * Load pre stripe functionality.
	 *
	 * This class call all the core functionality.
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class SAHCFWC_Pre_Checkout {
		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\SAHCFWC_Singleton;
		use \SAHCFWC\Traits\SAHCFWC_Helpers;
		/**
		 * Stripe secret key.
		 *
		 * @since 1.0.0
		 *
		 * @var boolean
		 */
		public $sahcfwc_is_valid;

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Filter hook.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			// Disable the coupon usage in WooCommerce.
			update_option( 'woocommerce_enable_coupons', 'no' );
			add_filter(
				'woocommerce_coupon_is_valid',
				array( $this, 'sahcfwc_custom_coupon_validation' ),
				10,
				2
			);
			add_filter(
				'woocommerce_coupon_error',
				array( $this, 'sahcfwc_custom_coupon_error_message' ),
				10,
				2
			);
		}

		/**
		 * Change thank you page to custom page.
		 *
		 * @since 1.0.0
		 *
		 * @param  boolean $is_valid checking.
		 * @param  object  $coupon WooCommerce order coupons.
		 * @return boolean|is_valid boolean value
		 */
		public function sahcfwc_custom_coupon_validation( $is_valid, $coupon ) {
			if ( $coupon->get_usage_limit_per_user() === 1 ) {
				if ( ! is_user_logged_in() ) {
					$is_valid               = false;
					$this->sahcfwc_is_valid = $is_valid;
				}
			}
			return $is_valid;
		}

		/**
		 * Change thank you page to custom page.
		 *
		 * @since 1.0.0
		 *
		 * @param  string $error message.
		 * @param  int    $err_code WooCommerce order coupons.
		 * @return string|error message
		 */
		public function sahcfwc_custom_coupon_error_message( $error, $err_code ) {
			if ( 100 === $err_code && false === $this->sahcfwc_is_valid ) {
				$error = esc_html__( 'Please log in before applying the coupon.', 'sa-hosted-checkout-for-woocommerce' );
			}
			return $error;
		}

	}

}
