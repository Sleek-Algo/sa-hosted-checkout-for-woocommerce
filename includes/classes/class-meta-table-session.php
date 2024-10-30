<?php
/**
 * Meta_Table_Session class.
 *
 * @package Sleek_Checkout_for_WooCommerce
 */

namespace SAHCFWC\Classes;

if ( ! class_exists( '\SAHCFWC\Classes\Meta_Table_Session' ) ) {

	/**
	 * Load metadata function.
	 *
	 * This class content metadata functions.
	 *
	 * @copyright  sleekalgo
	 * @version    Release: 1.0.0
	 * @link       https://www.sleekalgo.com
	 * @package    SA Hosted Checkout for WooCommerce
	 * @since      Class available since Release 1.0.0
	 */
	class Meta_Table_Session {

		/**
		 * Traits used inside class
		 */
		use \SAHCFWC\Traits\Singleton;
		use \SAHCFWC\Traits\Helpers;

		/**
		 * A constructor to prevent this class from being loaded more than once.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function __construct() {
		}

		/**
		 * Get session meta values.
		 *
		 * @since 1.0.0
		 *
		 * @param  int     $id session id.
		 * @param  string  $meta_key session meta key.
		 * @param  boolean $single true or false.
		 * @return array meta_data meta values.
		 */
		public static function sahcfwc_get_session_meta( $id, $meta_key = '', $single = true ) {

			$cache_key = ( ! empty( $meta_key ) ) ? 'sahcfwc_session_' . $id . '_meta_' . $meta_key . '_value' : 'sahcfwc_session_' . $id . '_meta_values';

			/**
			 * Get Data from Chache if exist
			 */
			$results = wp_cache_get( $cache_key );

			if ( false === $results ) {

				// @codingStandardsIgnoreStart
				global $wpdb;

				if ( ! empty( $meta_key ) ) {
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT `meta_key`, `meta_value` FROM `{$wpdb->prefix}sahcfwc_stripe_sessionsmeta` WHERE `stripe_session_record_id` = %d AND `meta_key` = %s",
							$id,
							$meta_key
						),
						ARRAY_A
					);
				} else {
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT `meta_key`, `meta_value` FROM `{$wpdb->prefix}sahcfwc_stripe_sessionsmeta` WHERE `stripe_session_record_id` = %d",
							$id
						),
						ARRAY_A
					);
				}
				// @codingStandardsIgnoreEnd

				/**
				* Update Data into Chache just for 10 minute
				*/
				wp_cache_set( $cache_key, $results, 'sahcfwc_sessions', ( 10 * MINUTE_IN_SECONDS ) );

			}

			return wp_list_pluck( $results, 'meta_value', 'meta_key' );
		}

		/**
		 * Update session meta values.
		 *
		 * @since 1.0.0
		 *
		 * @param  int    $id session id.
		 * @param  string $meta_key session meta key.
		 * @param  string $value true or false.
		 * @return void
		 */
		public static function sahcfwc_update_session_meta( $id, $meta_key, $value = '' ) {
			$cache_key = 'sahcfwc_session_' . $id . '_meta_' . $meta_key . '_value';
			// @codingStandardsIgnoreStart
			global $wpdb;
			$table_name = $wpdb->prefix . 'sahcfwc_stripe_sessionsmeta';
			$wpdb->update(
				$table_name,
				array( 'meta_value' => $value ),
				array(
					'stripe_session_record_id' => $id,
					'meta_key'                 => $meta_key,
				)
			);
			// @codingStandardsIgnoreEnd
			wp_cache_set( $cache_key, $value, 'sahcfwc_sessions', ( 10 * MINUTE_IN_SECONDS ) );
		}

		/**
		 * Add session meta values.
		 *
		 * @since 1.0.0
		 *
		 * @param  int    $id session id.
		 * @param  string $meta_key session meta key.
		 * @param  string $value true or false.
		 * @return void
		 */
		public static function sahcfwc_add_session_meta( $id, $meta_key = 'sccp', $value = '' ) {
			$cache_key = 'sahcfwc_session_' . $id . '_meta_' . $meta_key . '_value';
			// @codingStandardsIgnoreStart
			global $wpdb;
			$table_name = $wpdb->prefix . 'sahcfwc_stripe_sessionsmeta';
			$wpdb->insert(
				$table_name,
				array(
					'stripe_session_record_id' => $id,
					'meta_key'                 => $meta_key,
					'meta_value'               => $value,
				)
			);
			// @codingStandardsIgnoreEnd
			wp_cache_set( $cache_key, $value, 'sahcfwc_sessions', ( 10 * MINUTE_IN_SECONDS ) );
		}

		/**
		 * Delete session meta values.
		 *
		 * @since 1.0.0
		 *
		 * @param  int    $id session id.
		 * @param  string $meta_key session meta key.
		 * @return void
		 */
		public static function sahcfwc_delete_session_meta( $id, $meta_key = '' ) {
			$cache_key = 'sahcfwc_session_' . $id . '_meta_' . $meta_key . '_value';
			// @codingStandardsIgnoreStart
			global $wpdb;
			$table_name = $wpdb->prefix . 'sahcfwc_stripe_sessionsmeta';
			$wpdb->delete(
				$table_name,
				array(
					'stripe_session_record_id' => $id,
					'meta_key'                 => $meta_key,
				)
			);
			// @codingStandardsIgnoreEnd
			wp_cache_delete( $cache_key, 'sahcfwc_sessions' );
		}

	}
}
