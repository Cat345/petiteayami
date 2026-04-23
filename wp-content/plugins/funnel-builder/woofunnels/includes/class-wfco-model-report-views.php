<?php
/*if ( ! class_exists( 'WFCO_Model' ) ) {
	require_once __DIR__ . '/class-wfco-model.php';
}*/
if ( ! class_exists( 'WFCO_Model_Report_views' ) ) {
	#[AllowDynamicProperties]
	class WFCO_Model_Report_views extends WFCO_Model {
		static $primary_key = 'ID';

		public static function count_rows( $dependency = null ) {
			global $wpdb;
			$table_name = self::_table();
			$sql        = 'SELECT COUNT(*) FROM ' . $table_name;

			if ( 'all' !== filter_input( INPUT_GET, 'status', FILTER_UNSAFE_RAW ) ) {
				$status = filter_input( INPUT_GET, 'status', FILTER_UNSAFE_RAW );
				$status = ( 'active' === $status ) ? 1 : 2;
				$sql    = $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE status = %d", $status ); //phpcs:ignore WordPress.DB.PreparedSQL
			}

			return $wpdb->get_var( $sql ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL
		}

		private static function _table() {
			global $wpdb;
			$table_name = strtolower( get_called_class() );
			$table_name = str_replace( 'wfco_model_', 'wfco_', $table_name );

			return $wpdb->prefix . $table_name;
		}

		/**
		 * @param string $date Date(Y-m-d)
		 * @param string $object_id post_id or unique_id
		 * @param int $type 1=abandoned,2=upstroke,3=aero,4=bump
		 */

		public static function update_data( $date = '', $object_id = '', $type = 1 ) {
			global $wpdb;
			$has_object_id = ( '' !== $object_id );
			$object_id     = absint( $object_id );
			$type          = absint( $type );
			$insert        = [];

			if ( $date !== '' ) {
				$date = sanitize_text_field( $date );
			} else {
				$date = date( 'Y-m-d' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			}
			$insert['date'] = $date;

			if ( $has_object_id ) {
				$insert['object_id'] = $object_id;
			}
			$insert['type'] = $type;

			$table = self::_table();

			if ( $has_object_id ) {
				$get_sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE `date` = %s AND `object_id` = %d AND `type` = %d", $date, $object_id, $type ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$get_sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE `date` = %s AND `type` = %d", $date, $type ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$result = $wpdb->get_results( $get_sql, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL

			if ( ! empty( $result ) ) {
				$primary_id = absint( $result[0]['id'] );
				$wpdb->query( $wpdb->prepare( "UPDATE `$table` SET no_of_sessions = no_of_sessions + 1 WHERE id = %d", $primary_id ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL
			} else {
				$wpdb->insert( $table, $insert ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $wpdb->insert() parameterizes internally; safe.
			}
		}

		public static function get_data( $date = '', $object_id = '', $type = 1, $interval = false ) {
			global $wpdb;
			$has_object_id = ( '' !== $object_id );
			$object_id     = absint( $object_id );
			$type          = absint( $type );
			$table         = self::_table();

			if ( $date !== '' ) {
				if ( true === $interval ) {
					// $date is an internally-generated SQL date-range fragment, not user input.
					if ( $has_object_id ) {
						$sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE {$date} AND `object_id` = %d AND `type` = %d", $object_id, $type ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					} else {
						$sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE {$date} AND `type` = %d", $type ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					}
				} else {
					$date = sanitize_text_field( $date );
					if ( $has_object_id ) {
						$sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE `date` = %s AND `object_id` = %d AND `type` = %d", $date, $object_id, $type ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					} else {
						$sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE `date` = %s AND `type` = %d", $date, $type ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					}
				}
			} else {
				$date = date( 'Y-m-d' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				if ( $has_object_id ) {
					$sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE `date` = %s AND `object_id` = %d AND `type` = %d", $date, $object_id, $type ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				} else {
					$sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE `date` = %s AND `type` = %d", $date, $type ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			}

			return $wpdb->get_results( $sql, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL
		}


	}
}