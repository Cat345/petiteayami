<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Data_Store' ) ) {
	/**
	 * This class contain data for experiments
	 * Class BWFABT_Data_Store
	 */
	#[AllowDynamicProperties]
	class BWFABT_Data_Store {

		static $primary_key = 'id';
		static $count = 20;
		static $query_res = [];
		const ACTIVIY_STARTED = 1;
		const ACTIVIY_PAUSED = 2;
		const ACTIVIY_RESET = 3;
		const ACTIVIY_COMPLETE = 4;

		static function init() {
		}

		public function get( $value ) {
			global $wpdb;

			return $wpdb->get_row( self::_fetch_sql( $value ), ARRAY_A );
		}

		private static function _fetch_sql( $value ) {
			global $wpdb;

			$sql = sprintf( 'SELECT * FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );

			return $wpdb->prepare( $sql, $value );
		}

		private static function _table() {
			global $wpdb;
			$table_name = 'bwf_ab_experiments';

			return $wpdb->prefix . $table_name;
		}

		public function insert( $data ) {
			global $wpdb;
			$wpdb->insert( self::_table(), $data );
		}

		public function update( $data, $where ) {
			global $wpdb;

			return $wpdb->update( self::_table(), $data, $where );
		}

		public function delete( $value ) {
			global $wpdb;
			$sql = sprintf( 'DELETE FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );

			return $wpdb->query( $wpdb->prepare( $sql, $value ) );
		}

		public function insert_id() {
			global $wpdb;

			return $wpdb->insert_id;
		}

		public function now() {
			return current_time( 'mysql' );
		}

		public function time_to_date( $time ) {
			return gmdate( 'Y-m-d H:i:s', $time );
		}

		public function date_to_time( $date ) {
			return strtotime( $date . ' GMT' );
		}

		public function num_rows() {
			global $wpdb;

			return $wpdb->num_rows;
		}

		public function get_specific_rows( $where_key, $where_value ) {
			global $wpdb;
			$query = "SELECT * FROM " . self::_table() . " WHERE $where_key = '$where_value'";

			if ( isset( self::$query_res[ md5( $query ) ] ) ) {
				return self::$query_res[ md5( $query ) ];
			}
			$results                          = $wpdb->get_results( "SELECT * FROM " . self::_table() . " WHERE $where_key = '$where_value'", ARRAY_A );
			self::$query_res[ md5( $query ) ] = $results;

			return $results;
		}

		public function get_specific_columns( $column_names, $where_pairs ) {
			global $wpdb;
			$sql_query = "SELECT ";

			if ( is_array( $column_names ) && count( $column_names ) > 0 ) {
				foreach ( $column_names as $column_name => $column_alias ) {
					$sql_query .= "$column_name as $column_alias ";
				}
			}

			$sql_query .= "FROM " . self::_table();

			if ( is_array( $where_pairs ) && count( $where_pairs ) > 0 ) {
				$sql_query .= " WHERE 1 = 1";
				foreach ( $where_pairs as $where_key => $where_value ) {
					$sql_query .= " AND " . $where_key . " = '$where_value'";
				}
			}

			$results = $wpdb->get_row( $sql_query, ARRAY_A );

			return $results;
		}

		public function get_specific_column( $column_name, $where_pairs ) {
			$key       = is_array( $where_pairs ) ? implode( '_', $where_pairs ) : $where_pairs;
			$cache_key = 'bwfabt_get_specific_column' . $key;
			$get_id    = $this->get_cache_data( $cache_key );

			if ( false !== $get_id ) {
				return $get_id;
			}

			$sql_query = "SELECT $column_name FROM " . self::_table();

			if ( is_array( $where_pairs ) && count( $where_pairs ) > 0 ) {
				$sql_query .= " WHERE 1 = 1";
				foreach ( $where_pairs as $where_key => $where_value ) {
					$sql_query .= " AND " . $where_key . " = '$where_value'";
				}
			}
			global $wpdb;
			$get_id = $wpdb->get_var( $sql_query );
			if ( ! empty( $key ) ) {
				$id = ( null === $get_id ) ? 0 : $get_id;
				$this->set_cache_data( $cache_key, $id );
			}

			return $get_id;

		}

		public function get_results( $query ) {
			global $wpdb;
			$query   = str_replace( '{table_name}', self::_table(), $query );
			$results = $wpdb->get_results( $query, ARRAY_A );

			return $results;
		}

		public function get_row( $query ) {
			global $wpdb;
			$query   = str_replace( '{table_name}', self::_table(), $query );
			$results = $wpdb->get_row( $query, ARRAY_A );

			return $results;
		}

		public function get_active_experiment_for_control( $control_id ) {
			$sql_query = "SELECT * FROM {table_name} WHERE `status` != " . BWFABT_Experiment::STATUS_COMPLETE . " AND `control`=" . $control_id;

			$active_test = self::get_results( $sql_query );

			return empty( $active_test ) ? [] : $active_test;
		}

		public function get_experiment_by_control_id( $control_id, $order_by = 'ASC' ) {
			$sql_query = "SELECT * FROM {table_name} WHERE 1 = 1 AND `control`=" . $control_id . " ORDER BY id " . $order_by;

			$active_test = self::get_results( $sql_query );

			return empty( $active_test ) ? [] : $active_test;
		}

		public function get_active_experiments_for_type( $type ) {
			global $wpdb;
			$sql_query = $wpdb->prepare( "SELECT * FROM {table_name} WHERE `status` = 2 AND `type`=%s", $type );

			$active_test = self::get_results( $sql_query );

			return empty( $active_test ) ? [] : $active_test;
		}


		public function delete_multiple( $query ) {
			global $wpdb;
			$query = str_replace( '{table_name}', self::_table(), $query );
			$wpdb->query( $query );
		}

		public function update_multiple( $query ) {
			global $wpdb;
			$query = str_replace( '{table_name}', self::_table(), $query );
			$wpdb->query( $query );
		}

		public function get_last_error() {
			global $wpdb;

			return $wpdb->last_error;
		}

		/**
		 * @param $args
		 * @param $return_data
		 *
		 * @return array|void
		 */
		public function experiment_status_time( $args, $return_data = false ) {

			$experiment    = [];
			$update_data   = [];
			$experiment_id = $args['entity_id'];
			$query         = "SELECT activity, control FROM {table_name} WHERE id = " . $experiment_id;
			$results       = $this->get_results( $query );
			$get_data      = isset( $results[0] ) ? $results[0] : [];
			/**
			 * Update experiment status in control meta for restricted query on frontend
			 */
			$control_id = 0;
			if ( isset( $get_data['control'] ) ) {
				$control_id = $get_data['control'];
				unset( $get_data['control'] );
			}
			if ( isset( $args['type'] ) && absint( $control_id ) > 0 ) {
				$experiment_status = ( 1 === absint( $args['type'] ) ) ? '' : 'not_active';
				update_post_meta( $control_id, '_experiment_status', $experiment_status );
			}

			unset( $args['entity_id'] );

			if ( is_array( $get_data ) && count( $get_data ) > 0 ) {
				$new_data = [];

				/** if experiment exists before version 1.4.0 add default start date*/
				if ( isset( $get_data['activity'] ) && $get_data['activity'] === '' ) {
					$exp_data               = BWFABT_Core()->get_dataStore()->get( $experiment_id );
					$exists_experiment      = [
						'type' => 1,
						'date' => isset( $exp_data['date_started'] ) ? $exp_data['date_started'] : BWFABT_Core()->get_dataStore()->now(),
					];
					$new_data['activity'][] = $exists_experiment;
				}

				if ( isset( $get_data['activity'] ) && $get_data['activity'] !== '' ) {
					$new_data               = json_decode( $get_data['activity'], true );
					$new_data['activity'][] = $args;
				} else {
					$new_data['activity'][] = $args;
				}
				$update_data = $new_data;
			} else {
				$update_data['activity'][] = $args;
			}

			$experiment['activity'] = wp_json_encode( $update_data, true );
			$this->update( $experiment, array( 'id' => $experiment_id ) );

			if ( $return_data ) {
				return $experiment;
			}

		}

		/**
		 * Retrieves experiment activity time chunks.
		 *
		 * This method gets the time periods during which an experiment was active,
		 * by analyzing the experiment's activity log. It handles various states like
		 * started, paused, reset, and completed.
		 *
		 * @param int $experiment_id The ID of the experiment to retrieve time chunks for
		 *
		 * @return array An array of time periods with start_date and end_date for each active period
		 */
		public function get_experiment_time_chunk( $experiment_id ) {
			// Query the database to get experiment activity data
			$query    = 'SELECT activity FROM {table_name} WHERE `id` = ' . $experiment_id;
			$get_data = $this->get_row( $query );
			$result   = [];
			$default  = [];
			$data     = [];

			// Create experiment object to get default date ranges
			$experiment_objet         = new BWFABT_Experiment( $experiment_id );
			$default[0]               = [];
			$default[0]['start_date'] = $experiment_objet->get_report_start_date( 'mysql' );
			$default[0]['end_date']   = $experiment_objet->get_report_end_date( 'mysql' );

			// Parse activity data from JSON if available
			if ( isset( $get_data['activity'] ) ) {
				$get_data = json_decode( $get_data['activity'], true );
				$data     = ( is_array( $get_data ) && isset( $get_data['activity'] ) ) ? $get_data['activity'] : [];
			}

			// Return default if no activity data exists
			if ( ! is_array( $data ) || count( $data ) === 0 ) {
				return $default;
			}

			// Find the key for reset events in activity data
			$reset_key = 0;
			$get_types = wp_list_pluck( $data, 'type' );

			// Add start event for experiments created before v1.4.0
			if ( isset( $get_types[0] ) && ( $get_types[0] !== 1 ) ) {
				$exists_experiment = [
					'type' => self::ACTIVIY_STARTED,
					'date' => $experiment_objet->get_report_start_date( 'mysql' ),
				];
				array_unshift( $data, $exists_experiment );
			}

			// Find the latest reset key if any reset events exist
			foreach ( $get_types as $key => $value ) {
				if ( 3 === intval( $value ) ) {
					$reset_key = $key;
				}
			}

			// If reset event found, slice data to include only events after reset
			if ( intval( $reset_key ) > 0 ) {
				$data = array_slice( $data, $reset_key );
			}

			// Return current timestamp if no valid data after processing
			if ( ! is_array( $data ) || count( $data ) === 0 ) {
				$result[] = [
					'start_date' => BWFABT_Core()->get_dataStore()->now(),
					'end_date'   => BWFABT_Core()->get_dataStore()->now(),
				];

				return $result;
			}

			// Process each activity event to build time chunks
			foreach ( $data as $key => $value ) {
				$items = [];

				// Skip if experiment is paused with single reset event
				if ( self::ACTIVIY_RESET === intval( $value['type'] ) && count( $data ) === 1 && $experiment_objet->is_paused() ) {
					$result[] = $items;
					continue;
				}

				// Handle start event - set start date
				if ( self::ACTIVIY_STARTED === intval( $value['type'] ) ) {
					$items['start_date'] = $value['date'];
				}

				// Handle reset event - set start date if not followed by start event
				if ( self::ACTIVIY_RESET === intval( $value['type'] ) && ( ! isset( $data[ $key + 1 ] ) || ( isset( $data[ $key + 1 ] ) && self::ACTIVIY_STARTED !== intval( $data[ $key + 1 ]['type'] ) ) ) ) {
					$items['start_date'] = $value['date'];
				}

				// Handle pause event - set end date for previous chunk
				if ( self::ACTIVIY_PAUSED === intval( $value['type'] ) ) {
					if ( isset( $result[ $key - 1 ]['start_date'] ) && ! isset( $result[ $key - 1 ]['end_date'] ) ) {
						$result[ $key - 1 ]['end_date'] = $value['date'];
					}
				}

				// Handle complete event - set appropriate end dates
				if ( self::ACTIVIY_COMPLETE === intval( $value['type'] ) ) {
					if ( self::ACTIVIY_PAUSED !== intval( $data[ $key - 1 ]['type'] ) ) {
						if ( isset( $result[ $key - 1 ]['start_date'] ) ) {
							$result[ $key - 1 ]['end_date'] = $value['date'];
						} else {
							$items['end_date'] = $value['date'];
						}

						if ( ! isset( $result[ $key - 1 ]['start_date'] ) && intval( $data[ $key - 1 ]['type'] ) === self::ACTIVIY_RESET ) {
							$items['end_date'] = $data[ $key - 1 ]['date'];
						}
					}
				}

				// Add this item to results
				$result[] = $items;
			}

			// Filter out empty chunks
			$result = array_merge( array_filter( $result, function ( $v ) {
				return array_filter( $v ) !== array();
			} ) );

			// Return current timestamp if no valid chunks after filtering
			if ( count( $result ) === 0 ) {
				$result[] = [
					'start_date' => BWFABT_Core()->get_dataStore()->now(),
					'end_date'   => BWFABT_Core()->get_dataStore()->now(),
				];

				return $result;
			}

			// Ensure the last chunk has both start and end dates
			$last_key = intval( array_key_last( $result ) );
			if ( ! empty( ( $result[ $last_key ] ) ) && empty( ( $result[ $last_key ]['start_date'] ) ) ) {
				$result[ $last_key ]['start_date'] = BWFABT_Core()->get_dataStore()->now();
			}
			if ( ! empty( ( $result[ $last_key ] ) ) && empty( ( $result[ $last_key ]['end_date'] ) ) ) {
				$result[ $last_key ]['end_date'] = BWFABT_Core()->get_dataStore()->now();
			}

			return $result;
		}

		public function set_cache_data( $cache_key, $data ) {
			if ( class_exists( 'WooFunnels_Cache' ) ) {
				$woofunnels_cache_object = WooFunnels_Cache::get_instance();
				$woofunnels_cache_object->set_cache( $cache_key, $data );
			}
		}

		public function get_cache_data( $cache_key ) {
			if ( class_exists( 'WooFunnels_Cache' ) ) {
				$woofunnels_cache_object = WooFunnels_Cache::get_instance();

				return $woofunnels_cache_object->get_cache( $cache_key );
			}

			return false;
		}

	}

	BWFABT_Data_Store::init();
}