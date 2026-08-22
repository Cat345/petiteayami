<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFFN_Report_Views
 *
 * Storage for the page-view counters this plugin records.
 *
 * Replaces WFCO_Model_Report_views, which lived in the shared WooFunnels core.
 * Nothing about these counters is shared -- only this plugin writes them and
 * only this plugin reads them -- so the class belongs here, and carrying it in
 * the free plugin's bundled core meant shipping premium storage code in the
 * .org build.
 *
 * Deliberately standalone: the old class extended WFCO_Model and inherited
 * sixteen methods to use three. Only the three that are actually called are
 * kept. Renamed so it can coexist with the old class while callers migrate.
 */
if ( ! class_exists( 'WFFN_Report_Views' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Report_Views {

		/**
		 * Counter table. Unchanged from the old class, so existing rows are read
		 * and written exactly as before.
		 *
		 * @return string
		 */
		public static function table() {
			global $wpdb;

			return $wpdb->prefix . 'wfco_report_views';
		}

		/**
		 * Records one view: increments the day's counter or starts it at one.
		 *
		 * @param string     $date      Date in Y-m-d. Defaults to today.
		 * @param string|int $object_id Post id, or empty for a counter with no object.
		 * @param int        $type      View type.
		 *
		 * @return void
		 */
		public static function update_data( $date = '', $object_id = '', $type = 1 ) {
			global $wpdb;

			$has_object_id = ( '' !== $object_id );
			$object_id     = absint( $object_id );
			$type          = absint( $type );
			$date          = ( '' !== $date ) ? sanitize_text_field( $date ) : gmdate( 'Y-m-d' );
			$table         = self::table();

			if ( $has_object_id ) {
				$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . $table . '` WHERE `date` = %s AND `object_id` = %d AND `type` = %d', $date, $object_id, $type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . $table . '` WHERE `date` = %s AND `type` = %d', $date, $type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}

			if ( ! empty( $existing ) ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE `' . $table . '` SET no_of_sessions = no_of_sessions + 1 WHERE id = %d', absint( $existing ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				return;
			}

			$insert = array(
				'date' => $date,
				'type' => $type,
			);
			if ( $has_object_id ) {
				$insert['object_id'] = $object_id;
			}

			$wpdb->insert( $table, $insert ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		/**
		 * Runs a read query. {table_name} in the query is replaced with the
		 * counter table, matching the contract the previous class used.
		 *
		 * @param string $query  Query containing {table_name}.
		 * @param array  $params Values to bind.
		 *
		 * @return array
		 */
		public static function get_results( $query, $params = array() ) {
			global $wpdb;

			$query = str_replace( '{table_name}', self::table(), $query );
			if ( ! empty( $params ) ) {
				$query = $wpdb->prepare( $query, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return is_array( $results ) ? $results : array();
		}

		/**
		 * Deletes rows. {table_name} is replaced as above.
		 *
		 * @param string $query Query containing {table_name}.
		 *
		 * @return void
		 */
		public static function delete_multiple( $query ) {
			global $wpdb;

			$query = str_replace( '{table_name}', self::table(), $query );
			$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
}
