<?php
/**
 * License_Manager class.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\RestApi;

if ( ! class_exists( '\SAHCFWC\RestApi\License_Manager' ) ) {

	/**
	 * Api for License
	 *
	 * This class use for api
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class License_Manager {
		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\Singleton;
		use \SAHCFWC\Traits\Helpers;
		use \SAHCFWC\Traits\RestAPI;

		/**
		 * Response.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $response_obj;

		/**
		 * License Message.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $license_message;

		/**
		 * Api url.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private static $api_route_base_url = '/license';

		/**
		 * Request data.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $request_data;

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Hooks
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Register routes.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function register_routes() {
			register_rest_route(
				$this->sahcfwc_get_api_base_url(),
				self::$api_route_base_url,
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'sa_wc_sahcfwc_get_license_handler' ),
					'permission_callback' => array( $this, 'sahcfwc_permissions' ),
				)
			);
			register_rest_route(
				$this->sahcfwc_get_api_base_url(),
				self::$api_route_base_url,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'sa_wc_sahcfwc_co_license_request_handler' ),
					'permission_callback' => array( $this, 'sahcfwc_permissions' ),
				)
			);
		}

		/**
		 * Get license data.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sa_wc_sahcfwc_get_license_handler() {
			$result = array(
				'action'        => sanitize_text_field( get_option( 'sahcfwc_checkout_license_action' ) ),
				'license_key'   => sanitize_text_field( get_option( 'sahcfwc_checkout_license_key' ) ),
				'license_email' => sanitize_email( get_option( 'SleekCheckout_License_email' ) ),
			);

			// Escape values for JSON output.
			$result = array_map( 'esc_html', $result );
			wp_send_json( $result );
		}

		/**
		 * Set license data.
		 *
		 * @since 1.0.0
		 *
		 * @param   \WP_REST_Request|object $request license data.
		 * @return void
		 */
		public function sa_wc_sahcfwc_co_license_request_handler( \WP_REST_Request $request ) {
			$data               = json_decode( $request->get_body() );
			$this->request_data = $data;
			$license_action     = isset( $data->action ) && ! empty( $data->action ) ? sanitize_text_field( $data->action ) : '';
			if ( $license_action ) {
				switch ( $data->action ) {
					case 'activate':
						$this->activate_license( $data );

						break;
					case 'deactivate':
						$this->deactivate_license( $data );
						break;
					default:
						break;
				}
			}
		}

		/**
		 * Deacitvate license API.
		 *
		 * @since 1.0.0
		 *
		 * @param  string $license_key WooCommerce order id.
		 * @return boolean
		 */
		public function deactivate_freemius_license( $license_key ) {
			$fs          = freemius( 16167 );
			$license_key = sanitize_text_field( wp_unslash( $license_key ) );
			try {
				// Reflect to make the protected _deactivate_license method accessible.
				$reflection_method = new \ReflectionMethod( $fs, '_deactivate_license' );
				$reflection_method->setAccessible( true );

				// Invoke the method.
				$reflection_method->invoke( $fs, true );

				// Access the protected _site property to check the license status.
				$reflection_property = new \ReflectionProperty( $fs, '_site' );
				$reflection_property->setAccessible( true );
				$site = $reflection_property->getValue( $fs );

				// Check if the license ID is still valid.
				if ( ! \FS_Plugin_License::is_valid_id( $site->license_id ) ) {
					return true;
				} else {
					return false;
				}
			} catch ( \Exception $e ) {
				return false;
			}
		}

		/**
		 * Acitvate license API.
		 *
		 * @since 1.0.0
		 *
		 * @param  string $license_key WooCommerce order id.
		 * @return boolean
		 */
		public function activate_freemius_license( $license_key ) {
			$fs          = freemius( 16167 );
			$license_key = sanitize_text_field( wp_unslash( $license_key ) );
			// Check if network admin.
			$sites = fs_is_network_admin() ? fs_request_get( 'sites', array(), 'post' ) : array();

			try {
				// Reflect to make the private activate_license method accessible.
				$reflection_method = new \ReflectionMethod( $fs, 'activate_license' );
				$reflection_method->setAccessible( true );

				// Invoke the method with the required arguments.
				$result = $reflection_method->invoke(
					$fs,
					$license_key,
					$sites,
					fs_request_get_bool( 'is_marketing_allowed', null ),
					fs_request_get( 'blog_id', null ),
					fs_request_get( 'module_id', null, 'post' ),
					fs_request_get( 'user_id', null ),
					fs_request_get_bool( 'is_extensions_tracking_allowed', null ),
					fs_request_get_bool( 'is_diagnostic_tracking_allowed', null )
				);

				// Handle bundle license auto-activation if enabled.
				if ( $result['success'] && $fs->is_bundle_license_auto_activation_enabled() ) {
					$license             = new \FS_Plugin_License();
					$license->secret_key = $license_key;
					$fs->maybe_activate_bundle_license( $license, $sites );
				}
				return $result;
			} catch ( \Exception $e ) {
				return false;
			}
		}

		/**
		 * Activate license.
		 *
		 * @since 1.0.0
		 *
		 * @param  object $data WooCommerce order id.
		 * @return void
		 */
		public function activate_license( $data ) {
			$license_key       = isset( $data->license_key ) && ! empty( $data->license_key ) ? sanitize_text_field( wp_unslash( $data->license_key ) ) : '';
			$result_data       = array();
			$activation_result = $this->activate_freemius_license( $license_key );
			if ( true === $activation_result['success'] ) {
				$license_message = 'License has been activated';
				$license_details = true;
				$license_action  = 'activate';
				update_option( 'sahcfwc_checkout_license_action', $license_action );
				update_option( 'sahcfwc_checkout_license_key', $license_key );
			} else {
				$license_details = false;
				$license_action  = 'deactivate';
				$license_message = $activation_result['error'];
			}
			$result_data = array(
				'message'          => esc_html( $license_message ),
				'action'           => esc_html( $license_action ),
				'license_response' => $license_details,
			);
			wp_send_json( $result_data );
		}

		/**
		 * Deactivate license.
		 *
		 * @since 1.0.0
		 *
		 * @param  object $data WooCommerce order id.
		 * @return void
		 */
		public function deactivate_license( $data ) {
			$license_key         = isset( $data->license_key ) && ! empty( $data->license_key ) ? sanitize_text_field( wp_unslash( $data->license_key ) ) : '';
			$deactivation_result = $this->deactivate_freemius_license( $license_key );

			$result_data    = array();
			$license_action = 'activate';
			if ( true === $deactivation_result ) {
				$license_details = true;
				$license_action  = 'deactivate';
				$license_message = 'License has been deactivated';
				update_option( 'sahcfwc_checkout_license_action', $license_action );
				update_option( 'sahcfwc_checkout_license_key', $license_key );
			}
			$result_data = array(
				'action'           => esc_html( $license_action ),
				'message'          => esc_html( $license_message ),
				'license_response' => $license_details,
			);
			update_option( 'sahcfwc_checkout_license_action', $license_action );

			wp_send_json( $result_data );

		}

	}
}
