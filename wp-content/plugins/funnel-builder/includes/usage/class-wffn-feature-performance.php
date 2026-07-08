<?php
/**
 * Performance Metrics Tracking
 *
 * Collects conversion and views metrics for total, checkout, and optin across 3 time periods
 *
 * @package FunnelKit Funnel Builder
 * @since 3.13.1.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFFN_Feature_Performance' ) ) {

	/**
	 * Class WFFN_Feature_Performance
	 */
	#[\AllowDynamicProperties]
	class WFFN_Feature_Performance {

		/**
		 * Collect performance metrics data
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
		 * Uses the same query logic as the REST API conversion class to ensure data consistency
		 * Database stores timestamps using current_time('mysql') which is local timezone
		 *
		 * @param string $period Period name
		 * @param int    $interval Number of hours/days (0 for all time)
		 * @param string $unit 'hours' or 'days' (default: 'days')
		 *
		 * @return array
		 */
		private function get_metrics_for_period( $period, $interval, $unit = 'days' ) {
			global $wpdb;

			$prefix   = $period . '/';
			$table    = $wpdb->prefix . 'bwf_conversion_tracking';
			$date_col = 'timestamp';

			// Build where conditions matching REST API logic
			$where_conditions = array( '1=1' );

			// Date condition - use current_time('mysql') to match database timezone (local, not UTC)
			// Database stores timestamps using current_time('mysql'), so we must use local timezone
			if ( $interval > 0 ) {
				// Calculate from X hours/days ago to now in local timezone
				$current_time = current_time( 'timestamp' );
				if ( 'hours' === $unit ) {
					$after_timestamp = $current_time - ( $interval * HOUR_IN_SECONDS );
				} else {
					$after_timestamp = $current_time - ( $interval * DAY_IN_SECONDS );
				}
				$after_date         = date( 'Y-m-d H:i:s', $after_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$before_date        = current_time( 'mysql' ); // Current time in local timezone
				$where_conditions[] = $wpdb->prepare( "{$date_col} >= %s AND {$date_col} < %s", $after_date, $before_date ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			$where_query = implode( ' AND ', $where_conditions );

			// Build date condition for views table (wfco_report_views uses 'date' column, not 'timestamp')
			// Match REST API format: date >= start_date AND date < end_date (both in Y-m-d format)
			$views_date_condition = '';
			if ( $interval > 0 ) {
				$current_time = current_time( 'timestamp' );
				if ( 'hours' === $unit ) {
					// For hours, calculate date from X hours ago
					$after_timestamp = $current_time - ( $interval * HOUR_IN_SECONDS );
					$after_date      = date( 'Y-m-d', $after_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					// End date is today (exclusive, so use tomorrow)
					$before_date = date( 'Y-m-d', strtotime( 'tomorrow', $current_time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				} else {
					// For days, calculate date from X days ago
					$after_timestamp = $current_time - ( $interval * DAY_IN_SECONDS );
					$after_date      = date( 'Y-m-d', $after_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					// End date is today + 1 day (exclusive)
					$before_date = date( 'Y-m-d', strtotime( 'tomorrow', $current_time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				}
				$views_date_condition = $wpdb->prepare( 'AND date >= %s AND date < %s', $after_date, $before_date );
			}

			$views_table = $wpdb->prefix . 'wfco_report_views';

			// Total: Conversions (orders through funnels) - type = 2 means wc_order (purchase)
			$query             = "SELECT COUNT(*) FROM {$table} WHERE {$where_query} AND type = 2"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_conversions = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// Total: Views - Sum of funnel sessions from wfco_report_views (type 7 = funnel session)
			// This matches the REST API logic which uses type = 7 for "unique_visits" / "visitors"
			$query       = "SELECT COALESCE(SUM(no_of_sessions), 0) FROM {$views_table} WHERE type = 7 {$views_date_condition}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_views = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// Checkout: Conversions - Orders from checkout pages (type = 2 and step is checkout page)
			$query                = "SELECT COUNT(*) FROM {$table} ct INNER JOIN {$wpdb->prefix}posts p ON ct.step_id = p.ID WHERE {$where_query} AND ct.type = 2 AND p.post_type = 'wfacp_checkout'"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$checkout_conversions = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// Checkout: Views - Sum views from wfco_report_views where type = 4 (checkout visited) and object_id is a checkout page
			$query          = "SELECT COALESCE(SUM(rv.no_of_sessions), 0) FROM {$views_table} rv INNER JOIN {$wpdb->prefix}posts p ON rv.object_id = p.ID WHERE rv.type = 4 AND p.post_type = 'wfacp_checkout' {$views_date_condition}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$checkout_views = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// Optin: Conversions - Opt-in submissions (type = 1 means optin)
			$query             = "SELECT COUNT(*) FROM {$table} WHERE {$where_query} AND type = 1"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$optin_conversions = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// Optin: Views - Sum views from wfco_report_views where type = 8 (optin visited) and object_id is an optin page
			$query       = "SELECT COALESCE(SUM(rv.no_of_sessions), 0) FROM {$views_table} rv INNER JOIN {$wpdb->prefix}posts p ON rv.object_id = p.ID WHERE rv.type = 8 AND p.post_type = 'wffn_optin' {$views_date_condition}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$optin_views = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			return array(
				$prefix . 'total_conversions'    => $total_conversions,
				$prefix . 'total_views'          => $total_views,
				$prefix . 'checkout_conversions' => $checkout_conversions,
				$prefix . 'checkout_views'       => $checkout_views,
				$prefix . 'optin_conversions'    => $optin_conversions,
				$prefix . 'optin_views'          => $optin_views,
			);
		}
	}
}
