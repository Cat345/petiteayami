<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * Class WFFN_Optin_Contacts_Analytics
 */
if ( ! class_exists( 'WFFN_Optin_Contacts_Analytics' ) ) {

	#[\AllowDynamicProperties]
	class WFFN_Optin_Contacts_Analytics {

		/**
		 * instance of class
		 *
		 * @var null
		 */
		private static $ins = null;

		/**
		 * WFFN_Optin_Contacts_Analytics constructor.
		 */
		public function __construct() {
		}

		/**
		 * @return WFFN_Optin_Contacts_Analytics|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function get_contacts_optin_records( $cid, $entry_ids ) {
			global $wpdb;
			$cid             = ! empty( $cid ) ? absint( $cid ) : $cid;
			$entry_ids_array = is_string( $entry_ids ) ? array_map( 'absint', array_filter( explode( ',', $entry_ids ) ) ) : array_map( 'absint', (array) $entry_ids );

			if ( empty( $entry_ids_array ) ) {
				return array();
			}

			$query_args = array_merge( $entry_ids_array, array( $cid ) );
			$data       = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT optin.id, optin.funnel_id as fid, optin.email as email, optin.step_id as 'object_id', optin.data as 'data', DATE_FORMAT(optin.date, '%%Y-%%m-%%d %%T') as 'date', COALESCE( p.post_title, '' ) as 'object_name', 'optin' as 'type' FROM {$wpdb->prefix}bwf_optin_entries as optin LEFT JOIN {$wpdb->prefix}posts as p ON optin.step_id = p.id WHERE optin.id IN ( " . implode( ', ', array_fill( 0, count( $entry_ids_array ), '%d' ) ) . ' ) AND optin.cid=%d order by optin.date asc',
					...$query_args
				)
			);
			$db_error   = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			return $data;
		}

		/**
		 * @param $cids
		 * @param $funnel_id
		 *
		 * @return array|false[]|true
		 */
		public function delete_contact( $cids, $funnel_id = 0 ) {
			global $wpdb;
			$cid_count                = count( $cids );
			$stringPlaceholders       = array_fill( 0, $cid_count, '%s' );
			$placeholdersForFavFruits = implode( ',', $stringPlaceholders );
			$query_args               = $cids;
			$funnel_query             = '';
			if ( absint( $funnel_id ) > 0 ) {
				$funnel_query = ' AND fid = %d ';
				$query_args[] = absint( $funnel_id );
			}

			$e_query = 'DELETE FROM ' . $wpdb->prefix . 'bwf_optin_entries WHERE cid IN (' . $placeholdersForFavFruits . ') ' . $funnel_query;
			$wpdb->query( $wpdb->prepare( $e_query, $query_args ) ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- all values ($cids, $funnel_id) bound through prepare(); IN/fid placeholders built via array_fill()/%d.

			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			return true;
		}

		/**
		 * @param $funnel_id
		 */
		public function reset_analytics( $funnel_id ) {
			global $wpdb;
			$query = $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'bwf_optin_entries WHERE funnel_id=%d', $funnel_id );
			$wpdb->query( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query prepared above.
		}

		/**
		 * @param $entry_ids
		 *
		 * @return array|false[]|void
		 */
		public function delete_optin_entries( $entry_ids ) {
			global $wpdb;
			$entry_ids = is_array( $entry_ids ) ? $entry_ids : explode( ',', (string) $entry_ids );
			$entry_ids = array_values( array_filter( array_map( 'absint', $entry_ids ) ) );
			if ( empty( $entry_ids ) ) {
				return;
			}
			$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
			$e_query      = $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bwf_optin_entries WHERE id IN ($placeholders)", $entry_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a list of %d built via array_fill(); ids bound through prepare().
			$wpdb->query( $e_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return $db_error;
			}

			do_action( 'wffn_delete_optin_entries', implode( ',', $entry_ids ) );
		}
	}
}
