<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WFFN_Export_Contact
 */
if ( ! class_exists( 'WFFN_Export_Referrer' ) ) {
	class WFFN_Export_Referrer extends WFFN_Abstract_Exporter {
		protected static $slug = 'referrers';
		private static $ins = null;
		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_contact_referrers';

		public function get_title() {
			return __( 'Referrers', 'funnel-builder-powerpack' );
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

		public function get_columns() {
			return [
				'referrers'       => __( 'Referring Sites', 'funnel-builder-powerpack' ),
				'total_orders'    => __( 'Orders', 'funnel-builder-powerpack' ),
				'total_optins'    => __( 'Optins', 'funnel-builder-powerpack' ),
				'total_revenue'   => __( 'Gross Sales', 'funnel-builder-powerpack' ),
				'average_revenue' => __( 'Average Order Value', 'funnel-builder-powerpack' ),
			];
		}

		public function total_rows( $args ) {
			$args['total_count'] = true;
			$rest_endpoints      = WFFN_REST_API_EndPoint::get_instance();
			$get_conversions     = $rest_endpoints->get_conversion_count( $args['funnel_id'] ?? '', $args['is_global_export'] ?? false, $args['filters'] );

			if ( isset( $get_conversions['status'] ) && false === $get_conversions['status'] ) {
				return false;
			}
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				return false;
			}
			if ( isset( $get_conversions['total_count'] ) ) {
				$count = $get_conversions['total_count'];
			}

			return absint( $count );
		}


		/**
		 * Export data to CSV function
		 */
		public function export_data() {
			$funnel_id = isset( $this->db_export_row['fid'] ) ? absint( $this->db_export_row['fid'] ) : 0;

			$args = array(
				'id'     => $funnel_id,
				'offset' => $this->current_pos,
				'limit'  => get_option( 'posts_per_page' ),
			);

			$rest_endpoints  = WFFN_REST_API_EndPoint::get_instance();
			$get_conversions = $rest_endpoints->get_conversion_export_data( $args, $this->export_meta['filters'] );
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				WFFN_Core()->logger->log( "db error " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_conversions, true ), 'wffn', true );

				return;
			}
			unset( $get_conversions['total_count'] );



			$get_conversions = array_map( function ( $item ) {
				$return_data = [];
				foreach ( $this->get_columns() as $key => $column_name ) {
					$return_data[ $key ] = $item[ $key ] ?? '';
				}

				return $return_data;
			}, $get_conversions );



			$this->data_populated_in_csv( $funnel_id, $get_conversions );

		}

		/* prepared and import data in csv
		*
		* @param $funnel_id
		* @param $data
		*
		* @return void
		*/
		public function data_populated_in_csv( $funnel_id, $data ) {
			/* prepared data for csv header get maximum columns data using funnel data **/
			$file  = fopen( WFFN_PRO_EXPORT_DIR . '/' . $this->export_meta['file'], "a" );
			$count = 0;
			foreach ( $data as $subdata ) {
				fputcsv( $file, $subdata );
				$count ++;
			}
			fclose( $file );
			$this->current_pos = $this->current_pos + $count;
		}

	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core()->exporter->register( WFFN_Export_Referrer::get_instance() );
	}
}