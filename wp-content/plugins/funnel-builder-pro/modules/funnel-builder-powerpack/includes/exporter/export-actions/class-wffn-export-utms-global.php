<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFFN_Export_UTMs_Global' ) ) {
	/**
	 * Class WFFN_Export_UTMs_Global
	 *
	 * Exports UTM tracking data to a CSV file, including pagination and search functionality.
	 */
	class WFFN_Export_UTMs_Global extends WFFN_Abstract_Exporter {

		protected static $slug = 'global_utms';
		private static $ins = null;
		protected static $ACTION_HOOK = 'bwf_utms';

		public function get_title() {
			return __( 'utms', 'funnel-builder-powerpack' );
		}

		public function action_hook() {
			return self::$ACTION_HOOK;
		}

		public function __construct() {
			parent::__construct();
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * Retrieves the columns to be included in the export.
		 *
		 * @return array
		 */

		public function get_columns() {
			return [
				'utm_campaign' => __( 'UTM Campaign', 'funnel-builder-powerpack' ),
				'utm_source'   => __( 'UTM Source', 'funnel-builder-powerpack' ),
				'utm_medium'   => __( 'UTM Medium', 'funnel-builder-powerpack' ),
				'utm_content'  => __( 'UTM Content', 'funnel-builder-powerpack' ),
				'utm_term'     => __( 'UTM Term', 'funnel-builder-powerpack' ),
				'orders'       => __( 'Orders', 'funnel-builder-powerpack' ),
				'optins'       => __( 'Opt-ins', 'funnel-builder-powerpack' ),
				'revenue'      => __( 'Revenue', 'funnel-builder-powerpack' ),
			];
		}

		/**
		 * Calculates the total number of rows.
		 *
		 * @param array $args Search and filter criteria.
		 *
		 * @return int Total row count after applying filters.
		 */
		public function total_rows( $args ) {
			$filters = isset( $args['filters'] ) ? $args['filters'] : [];
			$search  = '';
			if ( isset( $args['funnel_id'] ) && $args['funnel_id'] > 0 ) {
				array_push( $filters, [ 'filter' => 'funnels', 'rule' => '', 'data' => [ [ 'id' => $args['funnel_id'], 'label' => '' ] ] ] );
			}
			// Extract search term from filters if provided
			foreach ( $filters as $filter ) {
				if ( $filter['filter'] === 's' ) {
					$search = $filter['data'];
					break;
				}
			}

			$request = [
				'filters' => $filters,
				's'       => $search,
			];

			$data  = $this->get_utm_data( $request );
			$count = count( $data );

			return absint( $count );
		}

		/**
		 * Exports the data to a CSV file, applying pagination and filtering.
		 *
		 * @return void
		 */
		public function export_data() {
			$filters = isset( $this->export_meta['filters'] ) ? $this->export_meta['filters'] : [];
			if ( isset( $this->export_meta['fid'] ) && $this->export_meta['fid'] > 0 ) {
				array_push( $filters, [ 'filter' => 'funnels', 'rule' => '', 'data' => [ [ 'id' => $this->export_meta['fid'], 'label' => '' ] ] ] );
			}
			$page_no = isset( $this->export_meta['page_no'] ) ? $this->export_meta['page_no'] : 1;
			$limit   = get_option( 'posts_per_page' );
			$search  = '';

			$is_full_export = empty( $filters ) && empty( $search ) && $page_no === 1;

			foreach ( $filters as $filter ) {
				if ( $filter['filter'] === 's' ) {
					$search = $filter['data'];
					break;
				}
			}
			$request = [
				'filters' => $filters,
				's'       => $search,
				'offset'  => $is_full_export ? null : ( $page_no - 1 ) * $limit,
				'limit'   => $is_full_export ? null : $limit,
			];

			if ( ! empty( $search ) || ! empty( $filters ) ) {
				$request['offset'] = null;
				$request['limit']  = null;
			}

			$data        = $this->get_utm_data( $request );
			$mapped_data = array_map( [ $this, 'map_columns' ], $data );

			$this->data_populated_in_csv( '', $mapped_data );
		}

		/**
		 * Populates the CSV file with provided data.
		 *
		 * @param string $utm_id UTM identifier (not used here but kept for consistency).
		 * @param array $data Array of data rows to write to the CSV.
		 *
		 * @return void
		 */
		public function data_populated_in_csv( $utm_id, $data ) {
			$file  = fopen( WFFN_PRO_EXPORT_DIR . '/' . $this->export_meta['file'], "a" );
			$count = 0;
			foreach ( $data as $subdata ) {
				fputcsv( $file, $subdata );
				$count ++;
			}
			fclose( $file );
			$this->current_pos = $this->current_pos + $count;
		}

		/**
		 * Maps data to match column structure for CSV export.
		 *
		 * @param array $data The row data to map.
		 *
		 * @return array Mapped data for the CSV row.
		 */
		protected function map_columns( $data ) {
			$return_data = [];
			foreach ( $this->get_columns() as $key => $column_name ) {
				$return_data[ $key ] = $data[ $key ] ?? '-';
			}

			return $return_data;
		}

		/**
		 * Retrieves UTM data based on filters, pagination, and search criteria.
		 *
		 * @param array|null $request Array with filters, offset, limit, and search criteria.
		 *
		 * @return array Retrieved records for UTM data.
		 */
		protected function get_utm_data( $request = [] ) {
			$request += [ 'offset' => 0, 'limit' => get_option( 'posts_per_page' ) ];

			return array_values( WFFN_Conversion_Data::get_instance()->get_global_utm_campaigns( $request )['records'] ?? [] );// phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}
}

if ( class_exists( 'WFFN_Pro_Core' ) ) {
	WFFN_Pro_Core()->exporter->register( WFFN_Export_UTMs_Global::get_instance() );
}
