<?php
/**
 * SAHCFWC_App class.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */

namespace SAHCFWC\Bootstrap;

if ( ! class_exists( '\SAHCFWC\Bootstrap\SAHCFWC_App' ) ) {
	/**
	 * Load Plugin functionality
	 *
	 * This class call all the core functionality
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class SAHCFWC_App {
		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\SAHCFWC_Singleton;
		use \SAHCFWC\Traits\SAHCFWC_Helpers;
		/**
		 * The Stripe status.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $sahcfwc_stripe_checkout_status;

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Plugin::instance()
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			$this->sahcfwc_stripe_checkout_status = get_option( 'sahcfwc_stripe_checkout_status' );
			add_action( 'sahcfwc_plugin_loaded', array( $this, 'sahcfwc_init_webhooks' ), 9999 );
			add_action( 'init', array( $this, 'sahcfwc_init_localization' ), 10 );
			add_action( 'init', array( $this, 'sahcfwc_init_classes' ), 10 );
			add_action( 'init', array( $this, 'sahcfwc_init_rest_api_end_points' ), 10 );
			add_action( 'init', array( $this, 'sahcfwc_init_admin_pages' ), 10 );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'sahcfwc_add_gateways' ), 9999 );
			add_action(
				'woocommerce_email',
				array( $this, 'sahcfwc_disable_emails_new_orders' ),
				20,
				1
			);
		}

		/**
		 * Manage activation plugin
		 *
		 * @return void
		 */
		public function sahcfwc_init_localization() {
			load_plugin_textdomain( SAHCFWC_TEXT_DOMAIN, false, SAHCFWC_TEXT_DOMAIN_PATH );
		}

		/**
		 * Manage activation plugin
		 *
		 * @uses remove_action To perform an email notification dyncamically
		 *
		 * @since 1.0.0
		 * @param  string $email_class The email class instance used to manage email notifications.
		 * @return void
		 */
		public function sahcfwc_disable_emails_new_orders( $email_class ) {
			remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_pending_to_completed_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_failed_to_processing_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_failed_to_completed_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_failed_to_on-hold_notification', array( $email_class->emails['WC_Email_New_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_cancelled_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_failed_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_on-hold_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
			remove_action( 'woocommerce_order_status_completed_notification', array( $email_class->emails['WC_Email_Customer_Completed_Order'], 'trigger' ) );
		}

		/**
		 * Inint webhooks.
		 *
		 * @since 1.0.0
		 * @return void.
		 */
		public function sahcfwc_init_webhooks() {
			\SAHCFWC\Webhooks\SAHCFWC_Stripe_Listener::get_instance();
		}

		/**
		 * Inint Stripe Intsance.
		 *
		 * @since 1.0.0
		 *
		 * @see Stripe::instance()
		 * @return void.
		 */
		public function sahcfwc_init_classes() {
			\SAHCFWC\Classes\SAHCFWC_Checkout_Button_Url_Ajax::get_instance();
			\SAHCFWC\Classes\SAHCFWC_Pre_Checkout::get_instance();
			\SAHCFWC\Classes\SAHCFWC_Post_Checkout::get_instance();
			\SAHCFWC\Settings\SAHCFWC_Stripe_Checkout::get_instance();
		}

		/**
		 * Inint API Intsance.
		 *
		 * @since 1.0.0
		 *
		 * @see API::instance()
		 * @return void.
		 */
		public function sahcfwc_init_rest_api_end_points() {
			\SAHCFWC\RestApi\SAHCFWC_Stripe_Country_And_Key_Status::get_instance();
			// This condition block will be auto removed from the Free version.
			\SAHCFWC\RestApi\SAHCFWC_Stripe_Local_Payment_Methods::get_instance();
		}

		/**
		 * Inint Dashboard Pages Intsance.
		 *
		 * @since 1.0.0
		 *
		 * @see Stripe Lister::instance()
		 * @return void.
		 */
		public function sahcfwc_init_admin_pages() {
			if ( current_user_can( 'manage_options' ) ) {
				\SAHCFWC\Pages\SAHCFWC_Dashboard::get_instance();
			}
		}

		/**
		 * Inint Payement Gateway Intsance.
		 *
		 * @since 1.0.0
		 *
		 * @see Stripe Lister::instance()
		 * @param  array $methods Payement Gateways, inlude stripe getway.
		 * @return array|methods The Payment Gateways.
		 */
		public function sahcfwc_add_gateways( $methods ) {
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				$is_match_country = $this->sahcfwc_is_match_country_stripe_wc();
				$stripe_secret    = $this->sahcfwc_get_stripe_secret_key();
				if ( false !== $stripe_secret ) {
					if ( $is_match_country ) {
						if ( 'yes' === $this->sahcfwc_stripe_checkout_status ) {
							$methods[] = \SAHCFWC\Classes\SAHCFWC_Payment_Gateway::get_instance();
						}
					}
				}
			}
			return $methods;
		}

	}

}
