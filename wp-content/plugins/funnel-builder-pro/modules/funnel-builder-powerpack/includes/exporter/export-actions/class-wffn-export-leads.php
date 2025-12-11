<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WFFN_Export_Contact
 */
if ( ! class_exists( 'WFFN_Export_Leads' ) ) {
	class WFFN_Export_Leads extends WFFN_Abstract_Exporter {
		protected static $slug = 'leads';
		private static $ins = null;
		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_leads';

		public function get_title() {
			return __( 'Leads', 'funnel-builder-powerpack' );
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
				'order_id'     => __( 'Order Id', 'funnel-builder-powerpack' ),
				'email'        => __( 'Email', 'funnel-builder-powerpack' ),
				'name'         => __( 'Name', 'funnel-builder-powerpack' ),
				'phone'        => __( 'Phone', 'funnel-builder-powerpack' ),
				'others'       => __( 'Others', 'funnel-builder-powerpack' ),
				'date'         => __( 'Date', 'funnel-builder-powerpack' ),
				'device'       => __( 'Device', 'funnel-builder-powerpack' ),
				'referrers'    => __( 'Referrers', 'funnel-builder-powerpack' ),
				'utm_campaign' => __( 'Utm Campaign', 'funnel-builder-powerpack' ),
				'utm_source'   => __( 'Utm Source', 'funnel-builder-powerpack' ),
				'utm_medium'   => __( 'Utm Medium', 'funnel-builder-powerpack' ),
				'utm_term'     => __( 'Utm Term', 'funnel-builder-powerpack' ),
				'contact_id'   => __( 'Contact ID', 'funnel-builder-powerpack' ),
				'funnel'       => __( 'Funnel', 'funnel-builder-powerpack' ),
				'convert_time' => __( 'Time to Convert', 'funnel-builder-powerpack' )
			];
		}

		public function total_rows( $args ) {

			$args['total_count'] = true;
			$rest_endpoints      = WFFN_Funnel_Orders::get_instance();
			$get_conversions     = $rest_endpoints->get_leads( [ 'total_count' => 'yes', 'funnel_id' => $args['funnel_id'] ?? 0, 'filters' => $args['filters'] ], true );

			if ( isset( $get_conversions['status'] ) && false === $get_conversions['status'] ) {
				return false;
			}
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				return false;
			}
			if ( isset( $get_conversions['total_count'] ) ) {
				return $get_conversions['total_count'];
			}

			return false;
		}


		/**
		 * Export data to CSV function
		 */
		public function export_data() {
			$funnel_id = isset( $this->db_export_row['fid'] ) ? absint( $this->db_export_row['fid'] ) : 0;

			$args = array(
				'funnel_id' => $funnel_id,
				'offset'    => $this->current_pos,
				'limit'     => get_option( 'posts_per_page' ),
				'filters'   => $this->export_meta['filters']
			);

			$rest_endpoints  = WFFN_Funnel_Orders::get_instance();
			$get_conversions = $rest_endpoints->get_leads( $args, true );


			if ( isset( $get_conversions['status'] ) && false === $get_conversions['status'] ) {
				WFFN_Core()->logger->log( "Something Went Wrong " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_conversions, true ), 'wffn', true );

				return;
			}
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				WFFN_Core()->logger->log( "db error " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_conversions, true ), 'wffn', true );

				return;
			}
			unset( $get_conversions['total_count'] );
			$this->data_populated_in_csv( $funnel_id, $get_conversions['records'] );

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
		WFFN_Pro_Core()->exporter->register( WFFN_Export_Leads::get_instance() );
	}
}