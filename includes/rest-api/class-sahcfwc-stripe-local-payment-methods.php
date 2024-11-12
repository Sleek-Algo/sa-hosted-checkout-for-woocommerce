<?php
/**
 * SAHCFWC_Stripe_Local_Payment_Methods class.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\RestApi;

if ( ! class_exists( '\SAHCFWC\RestApi\SAHCFWC_Stripe_Local_Payment_Methods' ) ) {

	/**
	 * Api for payment methods
	 *
	 * This class use for api
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class SAHCFWC_Stripe_Local_Payment_Methods {

		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\SAHCFWC_Singleton;
		use \SAHCFWC\Traits\SAHCFWC_Helpers;
		use \SAHCFWC\Traits\SAHCFWC_RestAPI;
		use \SAHCFWC\Traits\SAHCFWC_Stripe_Local_Payment_Methods_List;

		/**
		 * Api url.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private static $sahcfwc_api_route_base_url = '/stripe-local-payment-methods';

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Hooks
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'sahcfwc_register_routes_local_payment_methods' ) );
		}

		/**
		 * Register routes.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_register_routes_local_payment_methods() {
			register_rest_route(
				$this->sahcfwc_get_api_base_url(),
				self::$sahcfwc_api_route_base_url,
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'sahcfwc_get_stripe_local_payment_methods_handle' ),
					'permission_callback' => array( $this, 'sahcfwc_permissions' ),
				)
			);

		}

		/**
		 * Handle local payment methods.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_get_stripe_local_payment_methods_handle() {
			$store_raw_country            = sanitize_text_field( get_option( 'woocommerce_default_country' ) );
			$split_country                = explode( ':', $store_raw_country );
			$stripe_local_payment_methods = $this->sahcfwc_get_stripe_local_payment_methods( strtolower( $split_country[0] ) );
			$sanitized_payment_methods    = $this->sahcfwc_esc_payment_methods( $stripe_local_payment_methods );
			wp_send_json( $sanitized_payment_methods );
		}

		/**
		 * Sanitized methods.
		 *
		 * @since 1.0.0
		 *
		 * @param  array $payment_methods Stripe payment methods.
		 * @return array|sanitized_methods list of paymet methods.
		 */
		private function sahcfwc_esc_payment_methods( $payment_methods ) {
			$sanitized_methods = array();

			foreach ( $payment_methods as $method ) {
				$sanitized_method = array(
					'id'          => esc_html( $method['id'] ),
					'title'       => esc_html( $method['title'] ),
					'icon_url'    => esc_url( $method['icon_url'] ),
					'countries'   => $this->sahcfwc_esc_countries( $method['countries'] ),
					'description' => esc_html( $method['description'] ),
					'help'        => esc_html( $method['help'] ),
					'legal'       => esc_html( $method['legal'] ),
					'created_at'  => esc_html( $method['created_at'] ),
					'updated_at'  => esc_html( $method['updated_at'] ),
					'status'      => esc_html( $method['status'] ),
				);

				$sanitized_methods[] = $sanitized_method;
			}

			return $sanitized_methods;
		}

		/**
		 * Sanitized countries.
		 *
		 * @since 1.0.0
		 *
		 * @param  array $countries Stripe supported countries.
		 * @return array|sanitized_methods list of countries.
		 */
		private function sahcfwc_esc_countries( $countries ) {
			$sanitized_countries = array();
			foreach ( $countries as $country ) {
				foreach ( $country as $code => $name ) {
					$sanitized_countries[ $code ] = esc_html( $name );
				}
			}

			return $sanitized_countries;
		}

	}
}
