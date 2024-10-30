<?php
/**
 * Stripe_Session_Api class.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\RestApi;

if ( ! class_exists( '\SAHCFWC\RestApi\Stripe_Session_Api' ) ) {

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
	class Stripe_Session_Api {

		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\Singleton;
		use \SAHCFWC\Traits\Helpers;
		use \SAHCFWC\Traits\RestAPI;
		use \SAHCFWC\Traits\Stripe_Local_Payment_Methods;

		/**
		 * Api url.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		private static $sahcfwc_api_route_base_url = '/stripe-sessions';

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @see Hooks
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'sahcfwc_register_routes_get_stripe_session' ) );

		}

		/**
		 * Register routes.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function sahcfwc_register_routes_get_stripe_session() {
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
		 * @param   \WP_REST_Request|object $request session filter data.
		 * @return void
		 */
		public function sahcfwc_get_stripe_session( \WP_REST_Request $request ) {
			$data                    = $request->get_query_params();
			$stripe_api_limit        = ( isset( $data['limit'] ) && ! empty( $data['limit'] ) ) ? intval( $data['limit'] ) : 10;
			$stripe_api_limit        = ( $stripe_api_limit > 0 ) ? $stripe_api_limit : 10;
			$stripe_api_current_page = ( isset( $data['current_page'] ) && ! empty( $data['current_page'] ) ) ? intval( $data['current_page'] ) : 1;
			$stripe_api_current_page = ( $stripe_api_current_page > 0 ) ? $stripe_api_current_page : 1;
			$filter_customer_email   = ( isset( $data['filter_customer_email'] ) && ! empty( $data['filter_customer_email'] ) ) ? sanitize_email( $data['filter_customer_email'] ) : '';
			$filter_start_date       = ( isset( $data['filter_start_date'] ) && ! empty( $data['filter_start_date'] ) ) ? sanitize_text_field( $data['filter_start_date'] ) : '';
			$filter_end_date         = ( isset( $data['filter_end_date'] ) && ! empty( $data['filter_end_date'] ) ) ? sanitize_text_field( $data['filter_end_date'] ) : '';
			global $wpdb;

			$offset          = ( $stripe_api_current_page - 1 ) * $stripe_api_limit;
			$table_name      = $wpdb->prefix . 'sahcfwc_stripe_sessions';
			$check_run_query = true;

			if ( '' !== $filter_customer_email && '' === $filter_start_date && '' === $filter_end_date ) {

				$check_run_query = false;
				// @codingStandardsIgnoreStart
				$sql             = $wpdb->prepare(
					"SELECT id,stripe_session_id, customer_email, expires_at, livemode, mode, payment_status, `status`, created_at, updated_at FROM $table_name 
					 WHERE customer_email LIKE %s 
					 ORDER BY created_at DESC",
					'%' . $wpdb->esc_like( $filter_customer_email ) . '%'
				);
				// @codingStandardsIgnoreEnd

			} elseif ( '' !== $filter_start_date && '' !== $filter_end_date && '' === $filter_customer_email ) {

				// @codingStandardsIgnoreStart
				$check_run_query = false;
				$sql             = $wpdb->prepare(
					"SELECT id,stripe_session_id, customer_email, expires_at, livemode, mode, payment_status, `status`, created_at, updated_at FROM $table_name 
					 WHERE created_at BETWEEN %s AND %s 
					 ORDER BY created_at DESC",
					$filter_start_date,
					$filter_end_date
				);
				// @codingStandardsIgnoreEnd

			} elseif ( '' !== $filter_start_date && '' !== $filter_end_date && '' !== $filter_customer_email ) {

				$check_run_query = false;
				// @codingStandardsIgnoreStart
				$sql             = $wpdb->prepare(
					"SELECT id,stripe_session_id, customer_email, expires_at, livemode, mode, payment_status, `status`, created_at, updated_at FROM $table_name 
					 WHERE customer_email LIKE %s 
					 AND created_at BETWEEN %s AND %s 
					 ORDER BY created_at DESC",
					'%' . $wpdb->esc_like( $filter_customer_email ) . '%',
					$filter_start_date,
					$filter_end_date
				);
				// @codingStandardsIgnoreEnd

			} else {

				$check_run_query = true;
				// @codingStandardsIgnoreStart
				$sql             = $wpdb->prepare(
					"SELECT id,stripe_session_id, customer_email, expires_at, livemode, mode, payment_status, `status`, created_at, updated_at FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$stripe_api_limit,
					$offset
				);
				// @codingStandardsIgnoreEnd

			}

			$new_responses = array();
			$db_response   = array();
			// @codingStandardsIgnoreStart
			$results       = $wpdb->get_results( $sql );
			// @codingStandardsIgnoreEnd
			if ( is_array( $results ) && count( $results ) > 0 ) {
				foreach ( $results as $row ) {
					$meta_data  = \SAHCFWC\Classes\Meta_Table_Session::sahcfwc_get_session_meta( $row->id );
					$meta_array = array(
						'name'                         => isset( $meta_data['name'] ) ? esc_html( $meta_data['name'] ) : '',
						'phone'                        => isset( $meta_data['phone'] ) ? esc_html( $meta_data['phone'] ) : '',
						'line1'                        => isset( $meta_data['line1'] ) ? esc_html( $meta_data['line1'] ) : '',
						'state'                        => isset( $meta_data['state'] ) ? esc_html( $meta_data['state'] ) : '',
						'city'                         => isset( $meta_data['city'] ) ? esc_html( $meta_data['city'] ) : '',
						'country'                      => isset( $meta_data['country'] ) ? esc_html( $meta_data['country'] ) : '',
						'postal_code'                  => isset( $meta_data['postal_code'] ) ? esc_html( $meta_data['postal_code'] ) : '',
						'shipping_details_line1'       => isset( $meta_data['shipping_details_line1'] ) ? esc_html( $meta_data['shipping_details_line1'] ) : '',
						'shipping_details_state'       => isset( $meta_data['shipping_details_state'] ) ? esc_html( $meta_data['shipping_details_state'] ) : '',
						'shipping_details_city'        => isset( $meta_data['shipping_details_city'] ) ? esc_html( $meta_data['shipping_details_city'] ) : '',
						'shipping_details_country'     => isset( $meta_data['shipping_details_country'] ) ? esc_html( $meta_data['shipping_details_country'] ) : '',
						'shipping_details_postal_code' => isset( $meta_data['shipping_details_postal_code'] ) ? esc_html( $meta_data['shipping_details_postal_code'] ) : '',
						'amount_subtotal'              => isset( $meta_data['amount_subtotal'] ) ? esc_html( $meta_data['amount_subtotal'] ) : '',
						'amount_total'                 => isset( $meta_data['amount_total'] ) ? esc_html( $meta_data['amount_total'] ) : '',
						'url'                          => isset( $meta_data['url'] ) ? esc_html( $meta_data['url'] ) : '',
					);

					$session_entry = array(
						'id'                => isset( $row->id ) ? intval( $row->id ) : '',
						'stripe_session_id' => isset( $row->stripe_session_id ) ? esc_html( $row->stripe_session_id ) : '',
						'customer_email'    => isset( $row->customer_email ) ? esc_html( $row->customer_email ) : '',
						'expires_at'        => isset( $row->expires_at ) ? esc_html( $row->expires_at ) : '',
						'livemode'          => isset( $row->livemode ) ? esc_html( $row->livemode ) : '',
						'mode'              => isset( $row->mode ) ? esc_html( $row->mode ) : '',
						'payment_status'    => isset( $row->payment_status ) ? esc_html( $row->payment_status ) : '',
						'status'            => isset( $row->status ) ? esc_html( $row->status ) : '',
						'created_at'        => isset( $row->created_at ) ? esc_html( $row->created_at ) : '',
						'updated_at'        => isset( $row->updated_at ) ? esc_html( $row->updated_at ) : '',
						'meta_data'         => ( is_array( $meta_array ) && count( $meta_array ) > 0 ) ? $meta_array : '',
					);
					$db_response[] = $session_entry;
				}
			}

			$count = 10;
			if ( $check_run_query ) {
				// @codingStandardsIgnoreStart
				$count_query        = $wpdb->prepare( "SELECT COUNT(*) AS total_count FROM $table_name" );
				$count_query_result = $wpdb->get_results( $count_query );
				// @codingStandardsIgnoreEnd
				$count = $count_query_result[0]->total_count;
			} else {
				$count = count( $results );
			}
			$new_responses['data'] = $db_response;

			$new_responses['page']  = $stripe_api_current_page;
			$new_responses['total'] = $count;
			wp_send_json( array( 'new_response' => $new_responses ) );
		}

	}
}
