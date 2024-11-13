<?php
/**
 * SAHCFWC_RestAPI trait.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */

namespace SAHCFWC\Traits;

if ( ! trait_exists( '\SAHCFWC\Traits\SAHCFWC_RestAPI' ) ) {

	/**
	 * SAHCFWC_RestAPI trait
	 *
	 * This class use for SAHCFWC_restapi class
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	trait SAHCFWC_RestAPI {

		/**
		 * Version.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_api_version = 'v1';

		/**
		 * Namespace.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_api_namespace = 'sahcfwc';

		/**
		 * Register integration mode.
		 *
		 * @since 1.0.0
		 *
		 * @return string|api_base_url return URL
		 */
		public function sahcfwc_get_api_base_url() {
			$api_base_url = $this->sahcfwc_api_namespace . '/' . $this->sahcfwc_api_version;

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
