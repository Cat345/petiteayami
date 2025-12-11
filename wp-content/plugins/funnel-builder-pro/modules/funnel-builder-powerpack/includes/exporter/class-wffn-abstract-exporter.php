<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WFFN_Abstract_Exporter
 */
if ( ! class_exists( 'WFFN_Abstract_Exporter' ) ) {
	class WFFN_Abstract_Exporter {
		/** Export type */
		public static $EXPORT = 2;
		/** Export Status */
		public static $EXPORT_IN_PROGRESS = 1;
		public static $EXPORT_FAILED = 2;
		public static $EXPORT_SUCCESS = 3;
		protected static $slug = '';
		private static $ins = null;
		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_export';
		protected $start_time = 0;
		protected $current_pos = 0;

		protected $db_export_row = array();
		protected $export_meta = array();
		protected $export_fields = array();
		protected $export_id = 0;

		protected $args = [];

		public function __construct() {
		}

		public function should_register() {
			return true;
		}

		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * @param $args
		 *
		 * @return array
		 */
		public function handle_export( $args ) {
			$this->args = $args;

			return $this->wffn_register_export( $args );
		}

		public function get_title() {
			return __( 'wffn export', 'funnel-builder-powerpack' );
		}

		public function get_columns() {
			return [];
		}

		public function total_rows( $args ) {
			return 0;
		}

		/**
		 * @param $args
		 * @param $csv_header
		 * @param $type
		 *
		 * Add scheduler action for export data
		 *
		 * @return array
		 */
		public function wffn_register_export( $args ) {


			$response = array(
				'status'  => false,
				'message' => __( 'Error in export create export', 'funnel-builder-powerpack' ),
			);

			if ( ! is_array( $args ) ) {
				return $response;
			}

			$export_title = isset( $args['title'] ) ? $args['title'] : $this->get_title();
			$funnel_id    = isset( $args['funnel_id'] ) ? $args['funnel_id'] : 0;
			$filters      = isset( $args['filters'] ) ? $args['filters'] : '';
			$header       = $this->get_columns();
			$count        = $this->total_rows( $args );
			if ( false === $count ) {
				$response['message'] = __( 'No Data Found', 'funnel-builder-powerpack' );

				return $response;
			}

			if ( ! file_exists( WFFN_PRO_EXPORT_DIR . '/' ) ) {
				wp_mkdir_p( WFFN_PRO_EXPORT_DIR );
			}

			$this->delete_all_export( $funnel_id );
			$file_name = 'funnelkit-' . $this->get_slug() . '-' . gmdate( 'm-d-Y' ) . '.csv';
			$file      = fopen( WFFN_PRO_EXPORT_DIR . '/' . $file_name, "wb" );
			fputcsv( $file, $header );
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
					'export_type' => $this->get_slug(),
					'count'       => $count,
					'fid'         => $funnel_id,
					'meta'        => array(
						'fields'      => $header,
						'file'        => $file_name,
						'filters'     => $filters,
						'export_type' => $this->get_slug(),
					),
				)
			), $this->get_slug(), $args, $header );


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

		public function delete_all_export( $funnel_id ) {
			$args = [
				'post_type'   => 'fk_export',
				'post_status' => 'any',
				'fields'      => 'ids',
			];

			$args['meta_query'] = [
				[
					'key'     => 'export_type',
					'value'   => $this->get_slug(),
					'compare' => '='
				],
			];
			if ( $funnel_id > 0 ) {
				$args['meta_query']['relation'] = 'AND';
				$args['meta_query'][]           = [
					'key'     => 'fid',
					'value'   => $funnel_id,
					'compare' => '='
				];
			}

			$query = new WP_Query( $args );
			$ids   = $query->get_posts();
			if ( ! empty( $ids ) ) {
				foreach ( $ids as $id ) {
					wp_delete_post( $id );
				}
			}
		}

		/**
		 * Get dynamic string
		 *
		 * @param $count
		 *
		 * @return string
		 */
		public static function get_dynamic_string( $count = 8 ) {
			$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

			return substr( str_shuffle( $chars ), 0, $count );
		}

		/**
		 * Add Exporter Action
		 */
		public function wffn_exporter_action() {
			add_action( $this->action_hook(), array( $this, 'wffn_export' ) );
		}

		/**
		 * Export Action callback
		 */
		public function wffn_export( $export_id ) {
			wp_cache_flush();

			if ( $this->is_process_running( $export_id ) ) {
				return;
			}

			$this->lock_process( $export_id );

			WFFN_Core()->logger->log( "entering " . $export_id . " " . __FUNCTION__, 'wffn', true );
			if ( ! $this->maybe_get_export( $export_id ) ) {
				$this->end_export( self::$EXPORT_FAILED, 'Unable to get Export ID: ' . $export_id );

				return;
			}

			if ( $this->is_recently_exported() ) {
				$this->end_export( self::$EXPORT_FAILED, $this->get_slug() . " Recent Export attempt: " . $this->db_export_row['last_modified'] );

				return;
			}
			$this->export_fields = isset( $this->export_meta['fields'] ) ? $this->export_meta['fields'] : [];

			if ( ! is_array( $this->export_fields ) || empty( $this->export_fields ) ) {
				$this->end_export( self::$EXPORT_FAILED, 'Export Fields Empty' );

				return;
			}
			$this->current_pos = absint( $this->db_export_row['offset'] );

			$end_export = false;

			try {
				while ( ( ( time() - $this->start_time ) < 10 ) && ! self::memory_exceeded() ) {
					$this->export_data();
					$this->update_offset();
					$end_export = ( $this->get_percent_completed() >= 100 );
					if ( true === $end_export ) {
						$this->unlock_process( $export_id );
						break;
					}
				}
			} catch ( Error $e ) {
				$this->end_export( self::$EXPORT_FAILED, ' something wrong error comes in Export ID: #' . $export_id . ' error ' . print_r( $e->getMessage(), true ) );
			}

			if ( true === $end_export ) {
				$this->end_export( self::$EXPORT_SUCCESS, '' );
			}

			$this->unlock_process( $export_id );

		}

		/**
		 * Check for export id
		 *
		 * @param $export_id
		 *
		 * @return bool
		 */
		public function maybe_get_export( $export_id ) {
			if ( is_array( $this->db_export_row ) && ! empty( $this->db_export_row ) && absint( $this->db_export_row['id'] ) === absint( $export_id ) ) {
				return true;
			}

			$this->export_id     = absint( $export_id );
			$this->db_export_row = WFFN_Pro_Core()->exporter->get_export_post_meta( $this->export_id );
			if ( empty( $this->db_export_row ) ) {
				WFFN_Core()->logger->log( "wffn empty export data export_id # {$this->export_id}", 'wffn', true );

				return true;
			}
			$export_post                          = get_post( $this->export_id );
			$this->db_export_row['last_modified'] = $export_post->post_modified;
			$this->export_meta                    = ! empty( $this->db_export_row['meta'] ) ? json_decode( $this->db_export_row['meta'], true ) : array();

			return is_array( $this->db_export_row ) && ! empty( $this->db_export_row );
		}

		/**
		 * Finish exporting to file
		 *
		 * @param int $status
		 * @param string $status_message
		 */
		public function end_export( $status = 3, $status_message = '' ) {
			if ( empty( $this->export_id ) || ! isset( $this->db_export_row['status'] ) ) {
				return;
			}

			WFFN_Core()->logger->log( "wffn end export " . $this->get_slug() . " export_id # {$this->export_id}", 'wffn', true );
			if ( ! empty( $status_message ) ) {
				WFFN_Core()->logger->log( $this->get_slug() . "  not export " . $status_message, 'wffn', true );
			} else if ( 3 === $status ) {
				$status_message = $this->get_slug() . " exported. Export ID: " . $this->export_id;
			}

			$this->db_export_row['status']   = $status;
			$this->export_meta['status_msg'] = $status_message;
			update_post_meta( absint( $this->export_id ), 'status', $status );
			update_post_meta( absint( $this->export_id ), 'meta', wp_json_encode( $this->export_meta ) );

			$this->unlock_process( $this->export_id );
		}

		/**
		 * unscheduled Running Export
		 *
		 * @param $export_id
		 *
		 * @return void
		 */
		public function un_schedule_export( $export_id ) {
			if ( $export_id || ! function_exists( 'as_has_scheduled_action' ) ) {
				return;
			}
			as_unschedule_action( $this->action_hook(), array( 'export_id' => $export_id ), 'bwf_funnel' );
		}

		/**
		 * Check last modified time
		 *
		 * @return bool
		 */
		public function is_recently_exported() {
			if ( ! isset( $this->db_export_row['status'] ) || ! isset( $this->db_export_row['last_modified'] ) ) {
				return true;
			}
			$status                = absint( $this->db_export_row['status'] );
			$last_modified_seconds = time() - strtotime( $this->db_export_row['last_modified'] );

			return self::$EXPORT_IN_PROGRESS != $status && $last_modified_seconds <= 5;
		}

		/**
		 * Export data to CSV function
		 */
		public function export_data() {

		}

		/**
		 * Update DB offset
		 */
		public function update_offset() {
			$this->db_export_row['offset'] = $this->current_pos;

			update_post_meta( absint( $this->export_id ), 'offset', $this->current_pos );
			if ( $this->get_percent_completed() >= 100 ) {
				$this->end_export( self::$EXPORT_SUCCESS, '' );
			}
		}

		/**
		 * Return percent completed
		 *
		 * @return int
		 */
		public function get_percent_completed() {
			$start_pos = isset( $this->db_export_row['offset'] ) && ! empty( absint( $this->db_export_row['offset'] ) ) ? absint( $this->db_export_row['offset'] ) : 1;

			return absint( min( round( ( ( $start_pos / $this->db_export_row['count'] ) * 100 ) ), 100 ) );
		}

		public static function memory_exceeded() {
			$memory_limit    = self::get_memory_limit() * 0.75;
			$current_memory  = memory_get_usage( true );
			$memory_exceeded = $current_memory >= $memory_limit;

			return $memory_exceeded;
		}

		public static function get_memory_limit() {
			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				$memory_limit = '128M'; // Sensible default, and minimum required by WooCommerce
			}

			if ( ! $memory_limit || - 1 === $memory_limit || '-1' === $memory_limit ) {
				// Unlimited, set to 32GB.
				$memory_limit = '32G';
			}

			return self::convert_hr_to_bytes( $memory_limit );
		}

		public static function convert_hr_to_bytes( $value ) {
			if ( function_exists( 'wp_convert_hr_to_bytes' ) ) {
				return wp_convert_hr_to_bytes( $value );
			}

			$value = strtolower( trim( $value ) );
			$bytes = (int) $value;

			if ( false !== strpos( $value, 'g' ) ) {
				$bytes *= GB_IN_BYTES;
			} elseif ( false !== strpos( $value, 'm' ) ) {
				$bytes *= MB_IN_BYTES;
			} elseif ( false !== strpos( $value, 'k' ) ) {
				$bytes *= KB_IN_BYTES;
			}

			// Deal with large (float) values which run into the maximum integer size.
			return min( $bytes, PHP_INT_MAX );
		}

		/**
		 * prepared and import data in csv
		 *
		 * @param $funnel_id
		 * @param $data
		 *
		 * @return void
		 */
		public function data_populated_in_csv( $funnel_id, $data ) {
			$count = 0;
			/* prepared data for csv header get maximum columns data using funnel data **/
			$default_data = $this->contact_csv_header( $funnel_id, $data, true );
			if ( is_array( $default_data ) && isset( $default_data['status'] ) && false === $default_data['status'] ) {
				WFFN_Core()->logger->log( $this->get_slug() . " data not populated in csv for export id # {$this->export_id} funnel id {$funnel_id}", 'wffn', true );

				return;
			}


			$file = fopen( WFFN_PRO_EXPORT_DIR . '/' . $this->export_meta['file'], "a" );
			foreach ( $data as $prepared_step ) {
				$csvData = [];

				foreach ( $default_data as $f_key => $filters ) {
					foreach ( $filters as $key => $filter ) {
						if ( is_array( $filter ) && count( $filter ) > 0 ) {
							foreach ( $filter as $k => $v ) {
								if ( isset( $prepared_step[ $f_key ][ $key ][ $k ] ) ) {
									$filter[ $k ] = $prepared_step[ $f_key ][ $key ][ $k ];
									$csvData[]    = $prepared_step[ $f_key ][ $key ][ $k ];

								} else {
									$filter[ $k ] = $v;
									$csvData[]    = $v;
								}
							}
						}
					}
				}
				$csvData = apply_filters( 'wffn_export_csv_row_before_insert', $csvData, $this->export_fields );
				fputcsv( $file, $csvData );
				$count ++;
			}
			fclose( $file );
			$this->current_pos = $this->current_pos + $count;

		}

		public function contact_csv_header( $funnel_id = 0, $field_data = [], $default_row = false ) {
			$result = array(
				'status' => false,
			);

			return $result;
		}

		/**
		 * @param $export_id
		 *
		 * Lock the process so that multiple instances can't run simultaneously.
		 *
		 * @return void
		 */
		protected function lock_process( $export_id ) {
			$this->start_time = time();
			update_post_meta( $export_id, 'start_time', $this->start_time );
		}

		/**
		 * @param $export_id
		 *
		 * Check whether the current process is already running
		 * in a background process.
		 *
		 * @return bool
		 */
		protected function is_process_running( $export_id ) {

			$start_time = get_post_meta( $export_id, 'start_time', true );

			if ( empty( $start_time ) ) {
				return false;
			}

			if ( ( time() - absint( $start_time ) ) < 60 ) {
				return true;
			}

			return false;

		}

		/**
		 * @param $export_id
		 * Unlock the process so that other instances can spawn.
		 *
		 * @return void
		 */
		protected function unlock_process( $export_id ) {
			delete_post_meta( $export_id, 'start_time' );
		}

		public function action_hook() {
			return self::$ACTION_HOOK;
		}

		protected function map_columns( $data ) {
			$return_data = [];
			foreach ( $this->get_columns() as $key => $column_name ) {
				$return_data[ $key ] = $data[ $key ] ?? '';
			}

			return $return_data;
		}
	}
}