<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WFFN_Export_Contact
 */
if ( ! class_exists( 'WFFN_Export_Campaign' ) ) {
	class WFFN_Export_Campaign extends WFFN_Abstract_Exporter {
		protected static $slug = 'campaigns';
		private static $ins = null;
		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_contact_campaign';


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
				'total_orders'    => __( 'Orders', 'funnel-builder-powerpack' ),
				'total_revenue'   => __( 'Revenue', 'funnel-builder-powerpack' ),
				'type'            => __( 'Type', 'funnel-builder-powerpack' ),
				'average_revenue' => __( 'Average Order Value', 'funnel-builder-powerpack' ),
			];
		}

		/**
		 * @param $args
		 *
		 * @return array
		 */
		public function handle_export( $args ) {

			$response        = array(
				'status'  => false,
				'message' => __( 'Error in exporting Referrers', 'funnel-builder-powerpack' ),
			);
			$data            = [];
			$rest_endpoints  = WFFN_REST_API_EndPoint::get_instance();
			$get_conversions = $rest_endpoints->get_campaign_count( $args['funnel_id'], $args['type'], isset( $args['is_global_export'] ) );

			$count = 0;
			if ( ! is_array( $get_conversions ) || ! isset( $get_conversions['total_count'] ) ) {
				return $response;
			}
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				WFFN_Core()->logger->log( "Get total count Referrer db error # " . print_r( $get_conversions, true ), 'wffn', true );
				$response['message'] = __( 'Error in exporting Referrers check funnel logs', 'funnel-builder-powerpack' );

				return $response;
			}
			if ( isset( $get_conversions['total_count'] ) ) {
				$count = $get_conversions['total_count'];
			}

			if ( abs( $count ) === 0 ) {
				$response['message'] = __( 'No Referrers found', 'funnel-builder-powerpack' );

				return $response;
			}

			$data['count'] = $count;

			return $this->wffn_register_export( $data );
		}

		public function wffn_register_export( $args ) {

			$response = array(
				'status'  => false,
				'message' => __( 'Error in export create export', 'funnel-builder-powerpack' ),
			);

			if ( ! is_array( $args ) ) {
				return $response;
			}


			$export_title     = __( 'wffn export', 'funnel-builder-powerpack' );
			$funnel_id        = isset( $args['funnel_id'] ) ? $args['funnel_id'] : 0;
			$fields           = isset( $args['fields'] ) ? $args['fields'] : '';
			$count            = isset( $args['count'] ) ? $args['count'] : 0;
			$is_global_export = isset( $args['is_global_export'] );
			$csv_header       = ( isset( $args['csv_header'] ) && is_array( $args['csv_header'] ) ) ? $args['csv_header'] : [];

			if ( '' === $fields || abs( $count ) === 0 ) {
				$response['message'] = __( 'No data found', 'funnel-builder-powerpack' );

				return $response;
			}


			if ( ! file_exists( WFFN_PRO_EXPORT_DIR . '/' ) ) {
				wp_mkdir_p( WFFN_PRO_EXPORT_DIR );
			}

			$file_name = 'funnelkit-' . $this->get_slug() . '-' . gmdate( 'm-d-Y' ) . '.csv';
			$file      = fopen( WFFN_PRO_EXPORT_DIR . '/' . $file_name, "wb" );
			fputcsv( $file, $fields );
			fclose( $file );

			$input_data = apply_filters( 'wffn_exporter_insert_post', array(
				'post_type'    => WFFN_Pro_Core()->exporter->get_post_type_slug(),
				'post_title'   => $export_title,
				'post_name'    => sanitize_title( $export_title ),
				'post_status'  => 'publish',
				'post_content' => '',
				'meta_input'   => array(
					'offset'      => 0,
					'type'        => self::$EXPORT,
					'status'      => self::$EXPORT_IN_PROGRESS,
					'count'       => $count,
					'export_type' => $this->get_slug(),
					'fid'         => $funnel_id,
					'meta'        => array(
						'fields'           => $fields,
						'file'             => $file_name,
						'is_global_export' => $is_global_export,
						'export_type'      => $this->get_slug(),
					),
				)
			), $this->get_slug(), $args, $csv_header );

			$input_data['meta_input']['meta'] = wp_json_encode( $input_data['meta_input']['meta'] );
			$export_id                        = wp_insert_post( $input_data );

			if ( absint( $export_id ) > 0 ) {
				$response = true;
			} else {
				wp_delete_file( WFFN_PRO_EXPORT_DIR . '/' . $file_name );
			}
			WFFN_Core()->logger->log( "successfully registered the export to run " . $export_id, 'wffn', true );

			return array(
				'status'    => $response,
				'export_id' => $export_id,
			);
		}

		/**
		 * create contact csv header with maximum columns
		 *
		 * @param $funnel_id
		 * @param $field_data
		 *
		 * @return false[]|void
		 */

		public function contact_csv_header( $funnel_id = 0, $field_data = [], $default_row = false ) {
			/* prepared data for csv header get maximum columns data using funnel data **/
			return [
				'header'   => $field_data,
				'status'   => true,
				'step_ids' => []
			];
		}

		/**
		 * Export data to CSV function
		 */
		public function export_data() {
			$funnel_id = isset( $this->db_export_row['fid'] ) ? absint( $this->db_export_row['fid'] ) : 0;

			$args = array(
				'id'               => $funnel_id,
				'offset'           => $this->current_pos,
				'limit'            => get_option( 'posts_per_page' ),
				'is_global_export' => ( true === $this->export_meta['is_global_export'] ),
			);

			$rest_endpoints  = WFFN_REST_API_EndPoint::get_instance();
			$get_conversions = $rest_endpoints->get_conversion_export_data( $args );
			if ( isset( $get_conversions['db_error'] ) && true === $get_conversions['db_error'] ) {
				WFFN_Core()->logger->log( "db error " . $this->get_slug() . " not exported for export id # {$this->export_id} " . print_r( $get_conversions, true ), 'wffn', true );

				return;
			}
			unset( $get_conversions['total_count'] );
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
			$default_data = $this->contact_csv_header( $funnel_id, $data, true );
			if ( is_array( $default_data ) && isset( $default_data['status'] ) && false === $default_data['status'] ) {
				WFFN_Core()->logger->log( self::$slug . " data not populated in csv for export id # {$this->export_id} funnel id {$funnel_id}", 'wffn', true );

				return;
			}
			$file  = fopen( WFFN_PRO_EXPORT_DIR . '/' . $this->export_meta['file'], "a" );
			$count = 0;

			foreach ( $data as $subdata ) {
				$append_data = $this->map_with_fields( $this->get_columns(), $subdata );
				fputcsv( $file, $append_data );
				$count ++;
			}
			fclose( $file );
			$this->current_pos = $this->current_pos + $count;
		}

		public function map_with_fields( $fields, $row_data ) {
			$output = [];
			foreach ( $fields as $key => $field ) {
				$output[ $key ] = isset( $row_data[ $key ] ) ? $row_data[ $key ] : '';
			}

			return $output;
		}

	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core()->exporter->register( WFFN_Export_Campaign::get_instance() );
	}
}