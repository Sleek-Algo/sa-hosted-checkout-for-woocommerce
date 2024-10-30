<?php
/**
 * RestAPI trait.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\Traits;

if ( ! trait_exists( '\SAHCFWC\Traits\RestAPI' ) ) {

	/**
	 * RestAPI trait
	 *
	 * This class use for restapi class
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	trait RestAPI {

		/**
		 * Version.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $api_version = 'v1';

		/**
		 * Namespace.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $api_namespace = 'sa/sahcfwc';

		/**
		 * Register integration mode.
		 *
		 * @since 1.0.0
		 *
		 * @return string|api_base_url return URL
		 */
		public function sahcfwc_get_api_base_url() {
			$api_base_url = $this->api_namespace . '/' . $this->api_version;

			return $api_base_url;
		}

		/**
		 * Permission function.
		 *
		 * @since 1.0.0
		 *
		 * @return boolean
		 */
		public function sahcfwc_permissions() {
			return true;
		}

	}
}
