<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility shim for the view-counter store.
 *
 * @deprecated Storage lives in the premium plugin as WFFN_Report_Views.
 *
 * The implementation moved out of this shared core: only the premium plugin
 * writes these counters and only it reads them, so the free build has no reason
 * to carry the storage code. This class stays behind purely so premium builds
 * released before that move keep working -- several of their call sites invoke
 * it without a class_exists guard, and two of those run on the storefront
 * during checkout, so dropping the name outright would fatal a customer's
 * checkout rather than merely degrade reporting.
 *
 * It holds no storage logic. Each method forwards to WFFN_Report_Views when the
 * premium plugin provides it, and otherwise returns a correctly typed empty
 * value so callers can keep going. Forwarding rather than no-oping means a call
 * site we missed still records its data instead of silently dropping it.
 *
 * Keeping the name also keeps one released path alive: the checkout module's
 * reporting guards on class_exists() and, when the class is missing, requires
 * woofunnels/connector/db/class-wfco-model-report-views.php -- a path that no
 * longer exists. Because this shim satisfies the guard, that require is never
 * reached.
 *
 * Delete once the minimum supported premium release is the one that calls
 * WFFN_Report_Views directly.
 */
if ( ! class_exists( 'WFCO_Model_Report_views' ) ) {
	#[AllowDynamicProperties]
	class WFCO_Model_Report_views {

		/**
		 * Whether the premium plugin's store is loaded.
		 *
		 * @return bool
		 */
		private static function has_store() {
			return class_exists( 'WFFN_Report_Views' );
		}

		/**
		 * Records one view.
		 *
		 * @param string     $date      Date in Y-m-d.
		 * @param string|int $object_id Post id, or empty.
		 * @param int        $type      View type.
		 *
		 * @return void
		 */
		public static function update_data( $date = '', $object_id = '', $type = 1 ) {
			if ( self::has_store() ) {
				WFFN_Report_Views::update_data( $date, $object_id, $type );
			}
		}

		/**
		 * Reads rows.
		 *
		 * @param string $query  Query containing {table_name}.
		 * @param array  $params Values to bind.
		 *
		 * @return array Empty when nothing records views -- callers iterate this.
		 */
		public static function get_results( $query, $params = array() ) {
			if ( self::has_store() ) {
				return WFFN_Report_Views::get_results( $query, $params );
			}

			return array();
		}

		/**
		 * Deletes rows.
		 *
		 * @param string $query Query containing {table_name}.
		 *
		 * @return void
		 */
		public static function delete_multiple( $query ) {
			if ( self::has_store() ) {
				WFFN_Report_Views::delete_multiple( $query );
			}
		}
	}
}
