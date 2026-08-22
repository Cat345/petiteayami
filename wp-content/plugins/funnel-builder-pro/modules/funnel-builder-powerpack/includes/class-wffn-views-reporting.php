<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFFN_Views_Reporting
 *
 * Reads the page-view counters this plugin records.
 *
 * Recording moved here earlier; the queries that read it back followed, because
 * the free plugin writes no views and every one of those queries returned zero
 * there. The free plugin now asks on a filter and defaults to nothing, so its
 * analytics screens render zeroes instead of running dead SQL.
 *
 * Answers three seams:
 *   wffn_report_views_rows  -> array of rows
 *   wffn_report_views_row   -> single aggregate row
 *   wffn_report_views_count -> single integer
 */
if ( ! class_exists( 'WFFN_Views_Reporting' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Views_Reporting {

		/**
		 * @var WFFN_Views_Reporting|null
		 */
		private static $ins = null;

		/**
		 * Hooks are registered by WFFN_Pro_Public so this class is only loaded
		 * when something actually asks for view data. Nothing to do here.
		 */
		public function __construct() {
		}

		/**
		 * @return WFFN_Views_Reporting|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Whether the counters are available to read.
		 *
		 * @return bool
		 */
		private function can_read() {
			global $wpdb;

			return ( $wpdb instanceof wpdb );
		}

		/**
		 * Positive integer ids only -- these are interpolated into IN() lists.
		 *
		 * @param mixed $ids Raw ids from the caller.
		 *
		 * @return array
		 */
		private function clean_ids( $ids ) {
			if ( ! is_array( $ids ) ) {
				return array();
			}

			return array_values( array_filter( array_map( 'absint', $ids ) ) );
		}

		/**
		 * Table holding the counters.
		 *
		 * @return string
		 */
		private function table() {
			return WFFN_Report_Views::table();
		}

		/**
		 * Multiple rows: the canvas step grid, the funnels list and the dashboard.
		 *
		 * @param array $rows Default (empty) rows.
		 * @param array $args Context and structured parameters.
		 *
		 * @return array
		 */
		public function get_rows( $rows, $args = array() ) {
			global $wpdb;

			if ( ! is_array( $args ) || ! $this->can_read() ) {
				return $rows;
			}

			$ids = $this->clean_ids( isset( $args['object_ids'] ) ? $args['object_ids'] : array() );
			if ( empty( $ids ) ) {
				return $rows;
			}

			$context     = isset( $args['context'] ) ? $args['context'] : '';
			$placeholder = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
			$table       = $this->table();

			if ( 'canvas_steps' === $context ) {
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT object_id, SUM(CASE WHEN type = 2 OR type = 4 OR type = 5 OR type = 8 OR type = 10 THEN `no_of_sessions` END) AS `views` ,SUM(CASE WHEN type = 3 OR type = 11 THEN `no_of_sessions` END) AS `converted` FROM ' . $table . ' WHERE object_id IN ( ' . $placeholder . ' ) GROUP BY object_id ORDER BY object_id ASC', $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				return is_array( $result ) ? $result : $rows;
			}

			/** Funnel session totals, optionally windowed by date. */
			$start = isset( $args['start_date'] ) ? $args['start_date'] : '';
			$end   = isset( $args['end_date'] ) ? $args['end_date'] : '';

			if ( '' !== $start && '' !== $end ) {
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT object_id as fid , SUM(COALESCE(no_of_sessions, 0)) AS views FROM ' . $table . ' WHERE type = 7 AND object_id IN ( ' . $placeholder . ' ) AND date >= %s AND date < %s GROUP BY object_id', array_merge( $ids, array( $start, $end ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$result = $wpdb->get_results( $wpdb->prepare( 'SELECT object_id as fid , SUM(COALESCE(no_of_sessions, 0)) AS views FROM ' . $table . ' WHERE type = 7 AND object_id IN ( ' . $placeholder . ' ) GROUP BY object_id', $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}

			return is_array( $result ) ? $result : $rows;
		}

		/**
		 * Single aggregate row for one step's stats panel.
		 *
		 * @param array $row  Default (empty) row.
		 * @param array $args Context and structured parameters.
		 *
		 * @return array
		 */
		public function get_row( $row, $args = array() ) {
			global $wpdb;

			if ( ! is_array( $args ) || ! $this->can_read() ) {
				return $row;
			}

			$ids = $this->clean_ids( isset( $args['object_ids'] ) ? $args['object_ids'] : array() );
			if ( empty( $ids ) ) {
				return $row;
			}

			$view_type = isset( $args['view_type'] ) ? absint( $args['view_type'] ) : 0;
			if ( $view_type < 1 ) {
				return $row;
			}

			$placeholder = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
			$select      = 'SUM( CASE WHEN type = %d THEN `no_of_sessions` END ) AS viewed';
			$params      = array( $view_type );

			if ( isset( $args['convert_type'] ) && absint( $args['convert_type'] ) > 0 ) {
				$select  .= ' ,SUM( CASE WHEN type = %d THEN `no_of_sessions` END ) AS `converted`';
				$params[] = absint( $args['convert_type'] );
			}

			$result = $wpdb->get_row( $wpdb->prepare( 'SELECT ' . $select . ' FROM ' . $this->table() . ' WHERE object_id IN ( ' . $placeholder . ' ) ORDER BY object_id ASC', array_merge( $params, $ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return is_array( $result ) ? $result : $row;
		}

		/**
		 * Single total, used by usage reporting.
		 *
		 * @param int   $count Default (zero) count.
		 * @param array $args  Context and structured parameters.
		 *
		 * @return int
		 */
		public function get_count( $count, $args = array() ) {
			global $wpdb;

			if ( ! is_array( $args ) || ! $this->can_read() ) {
				return $count;
			}

			$view_type = isset( $args['view_type'] ) ? absint( $args['view_type'] ) : 0;
			if ( $view_type < 1 ) {
				return $count;
			}

			$start     = isset( $args['start_date'] ) ? $args['start_date'] : '';
			$end       = isset( $args['end_date'] ) ? $args['end_date'] : '';
			$post_type = isset( $args['post_type'] ) ? $args['post_type'] : '';
			$table     = $this->table();

			$where  = 'rv.type = %d';
			$params = array( $view_type );
			$join   = '';

			if ( '' !== $post_type ) {
				$join    = ' INNER JOIN ' . $wpdb->posts . ' p ON rv.object_id = p.ID';
				$where  .= ' AND p.post_type = %s';
				$params[] = $post_type;
			}

			if ( '' !== $start && '' !== $end ) {
				$where   .= ' AND rv.date >= %s AND rv.date < %s';
				$params[] = $start;
				$params[] = $end;
			}

			$result = $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(rv.no_of_sessions), 0) FROM ' . $table . ' rv' . $join . ' WHERE ' . $where, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return null === $result ? $count : (int) $result;
		}
	}

}
