<?php
/**
 * Pro Performance Metrics Tracking
 *
 * Collects conversion and views metrics for bump and upsell across 3 time periods
 *
 * @package FunnelKit Funnel Builder Pro
 * @since 3.13.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Feature_Performance_Pro' ) ) {

	/**
	 * Class WFFN_Feature_Performance_Pro
	 */
	#[\AllowDynamicProperties]
	class WFFN_Feature_Performance_Pro {

		/**
		 * Collect Pro performance metrics data
		 *
		 * @return array
		 */
		public function collect() {
			$data = array();

			// Last 24 hours (use hours, not days)
			$data = array_merge( $data, $this->get_metrics_for_period( 'last_24_hours', 24, 'hours' ) );

			// Last 30 days
			$data = array_merge( $data, $this->get_metrics_for_period( 'last_30_days', 30, 'days' ) );

			// All time
			$data = array_merge( $data, $this->get_metrics_for_period( 'all_time', 0, 'days' ) );

			return $data;
		}

		/**
		 * Get metrics for a specific time period
		 * Uses local timezone to match database storage (current_time('mysql'))
		 *
		 * @param string $period Period name
		 * @param int    $interval Number of hours/days (0 for all time)
		 * @param string $unit 'hours' or 'days' (default: 'days')
		 *
		 * @return array
		 */
		private function get_metrics_for_period( $period, $interval, $unit = 'days' ) {
			global $wpdb;

			$prefix = $period . '/';

			// Build date condition for bumps (wfob_stats uses 'date' column which is datetime type)
			// Use local timezone to match how data is stored
			$bump_date_condition = '';
			if ( $interval > 0 ) {
				$current_time = current_time( 'timestamp' );
				if ( 'hours' === $unit ) {
					$after_timestamp = $current_time - ( $interval * HOUR_IN_SECONDS );
				} else {
					$after_timestamp = $current_time - ( $interval * DAY_IN_SECONDS );
				}
				$after_date          = date( 'Y-m-d H:i:s', $after_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$before_date         = current_time( 'mysql' );
				$bump_date_condition = $wpdb->prepare( 'AND date >= %s AND date < %s', $after_date, $before_date );
			}

			// Build date condition for upsells (wfocu_event uses 'timestamp' column)
			$upsell_date_condition = '';
			if ( $interval > 0 ) {
				$current_time = current_time( 'timestamp' );
				if ( 'hours' === $unit ) {
					$after_timestamp = $current_time - ( $interval * HOUR_IN_SECONDS );
				} else {
					$after_timestamp = $current_time - ( $interval * DAY_IN_SECONDS );
				}
				$after_date            = date( 'Y-m-d H:i:s', $after_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$before_date           = current_time( 'mysql' );
				$upsell_date_condition = $wpdb->prepare( 'AND timestamp >= %s AND timestamp < %s', $after_date, $before_date );
			}

			// Bump: Conversions (times accepted)
			$bump_conversions = (int) $wpdb->get_var(
				"
				SELECT COUNT(*)
				FROM {$wpdb->prefix}wfob_stats
				WHERE converted = 1
				$bump_date_condition
			"
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// Bump: Views (times bumps displayed)
			$bump_views = (int) $wpdb->get_var(
				"
				SELECT COUNT(*)
				FROM {$wpdb->prefix}wfob_stats
				WHERE 1=1
				$bump_date_condition
			"
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// Upsell: Conversions (times accepted)
			// object_type = 'offer' and action_type_id = 4 for 'accepted' (OFFER_ACCEPTED_ACTION_ID)
			$upsell_conversions = (int) $wpdb->get_var(
				"
				SELECT COUNT(*)
				FROM {$wpdb->prefix}wfocu_event
				WHERE object_type = 'offer'
				AND action_type_id = '4'
				$upsell_date_condition
			"
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// Upsell: Views (times upsells displayed)
			// object_type = 'offer' and action_type_id = 2 for 'viewed' (OFFER_VIEWED_ACTION_ID)
			$upsell_views = (int) $wpdb->get_var(
				"
				SELECT COUNT(*)
				FROM {$wpdb->prefix}wfocu_event
				WHERE object_type = 'offer'
				AND action_type_id = '2'
				$upsell_date_condition
			"
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			return array(
				$prefix . 'bump_conversions'   => $bump_conversions,
				$prefix . 'bump_views'         => $bump_views,
				$prefix . 'upsell_conversions' => $upsell_conversions,
				$prefix . 'upsell_views'       => $upsell_views,
			);
		}
	}
}
