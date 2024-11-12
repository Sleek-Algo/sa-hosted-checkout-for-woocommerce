<?php
/**
 * SAHCFWC_Stripe_Country_And_Key_Status class.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\RestApi;

if ( ! class_exists( '\SAHCFWC\RestApi\SAHCFWC_Stripe_Country_And_Key_Status' ) ) {

	/**
	 * Api for checking country
	 *
	 * This class use for api
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class SAHCFWC_Stripe_Country_And_Key_Status {

		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\SAHCFWC_Singleton;
		use \SAHCFWC\Traits\SAHCFWC_Helpers;
		use \SAHCFWC\Traits\SAHCFWC_RestAPI;

		/**
		 * Api url.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private static $sahcfwc_api_route_base_url = '/stripe-check-data-exist';

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Hooks
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'sahcfwc_register_routes_country' ) );
		}

		/**
		 * Register routes.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_register_routes_country() {
			register_rest_route(
				$this->sahcfwc_get_api_base_url(),
				self::$sahcfwc_api_route_base_url,
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'sahcfwc_get_stripe_session' ),
					'permission_callback' => array( $this, 'sahcfwc_permissions' ),
				)
			);
		}

		/**
		 * Get session data.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_get_stripe_session() {
			$check_counry = $this->sahcfwc_is_match_country_stripe_wc();
			$test_key     = sanitize_text_field( get_option( 'sahcfwc_stripe_test_secret_key', true ) );
			$live_key     = sanitize_text_field( get_option( 'sahcfwc_stripe_live_secret_key', true ) );
			$stripe_key   = ! empty( $test_key ) || ! empty( $live_key );
			$data         = array(
				'is_stripe_wc_country_match' => is_bool( $check_counry ) ? $check_counry : '',
				'stripe_key'                 => $stripe_key,
			);
			$data         = ( is_array( $data ) && count( $data ) > 0 ) ? $data : '';
			wp_send_json( $data );
		}

	}
}
