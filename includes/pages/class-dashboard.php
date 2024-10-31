<?php
/**
 * Dashboard class.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\Pages;

if ( ! class_exists( '\\SAHCFWC\\Pages\\Dashboard' ) ) {
	/**
	 * Load Admin dashboard
	 *
	 * This class render dashboard
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class Dashboard {
		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\Singleton;
		use \SAHCFWC\Traits\Helpers;
		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Hooks
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'sahcfwc_dashboard_menu' ), 10 );
			add_action(
				'admin_enqueue_scripts',
				array( $this, 'sahcfwc_dashboard_scripts' ),
				10,
				1
			);
		}

		/**
		 * Add manu.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_dashboard_menu() {
			// Add second submenu for "Session".
			add_submenu_page(
				'woocommerce',
				esc_html__( 'Sleek Checkout', 'sa-hosted-checkout-for-woocommerce' ),
				esc_html__( 'Sleek Checkout', 'sa-hosted-checkout-for-woocommerce' ),
				'manage_options',
				'sahcfwc-dashboard',
				array( $this, 'sahcfwc_dashboard_page' )
			);
		}

		/**
		 * Dashboard page.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_dashboard_page() {
			?>
			<div id="sahcfwc-app"></div>
			<?php
		}

		/**
		 * Load admin script.
		 *
		 * @since 1.0.0
		 *
		 * @param  string $hook temp.
		 * @return void
		 * @throws \Error If there is an error loading the script.
		 */
		public function sahcfwc_dashboard_scripts( $hook ) {
			if ( 'woocommerce_page_sahcfwc-dashboard' === $hook ) {
				$script_asset_path = SAHCFWC_PATH . '/assets/backend/free-app.asset.php';
				$script_url        = SAHCFWC_URL_ASSETS_BACKEND . '/free-app.js';
				$style_url         = SAHCFWC_URL_ASSETS_BACKEND . '/free-app.css';
				if ( ! file_exists( $script_asset_path ) ) {
					return;
				}
				$script_asset = ( require $script_asset_path );
				wp_register_script(
					'sahcfwc-app-script',
					$script_url,
					$script_asset['dependencies'],
					$script_asset['version'],
					array(
						'strategy'  => 'async',
						'in_footer' => true,
					)
				);
				$license_status                           = ( get_option( 'sahcfwc_checkout_license_action' ) === false ? 'deactivate' : get_option( 'sahcfwc_checkout_license_action' ) );
				$url                                      = get_site_url( null, 'wp-admin/admin.php?page=sahcfwc-dashboard-pricing' );
				$sahcfwc_customizations_localized_objects = array(
					'language'             => get_user_locale(),
					'webhook_URL'          => get_site_url() . '/wp-json/sa/sahcfwc/v1/webhooks/stripe-lister',
					'language_dir'         => ( is_rtl() ? 'rtl' : 'ltr' ),
					'license_status'       => $license_status,
					'purchase_premium_url' => $url,
					'text_domain'          => 'sa-hosted-checkout-for-woocommerce',
				);
				wp_localize_script( 'sahcfwc-app-script', 'sahcfwc_customizations_localized_objects', apply_filters( 'sahcfwc_customizations_localized_objects', $sahcfwc_customizations_localized_objects ) );
				wp_enqueue_script( 'sahcfwc-app-script' );
				/**
				 * Set JS Translations
				 */
				wp_set_script_translations( 'sahcfwc-app-script', 'sa-hosted-checkout-for-woocommerce', SAHCFWC_DIR . 'languages' );
				wp_enqueue_style(
					'sahcfwc-app-style',
					$style_url,
					array( 'wp-components' ),
					$script_asset['version']
				);
			}
		}

	}

}
