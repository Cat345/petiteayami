<?php
if ( ! class_exists( 'WFOCU_DB_Base' ) ) {
	/**
	 * Base class for database interactions via custom table.
	 *
	 */
	abstract class WFOCU_DB_Base {

		/**
		 * Primary fields
		 *
		 * @since 1.3.5
		 *
		 * @var array
		 */
		protected $primary_fields = array();

		/**
		 * Meta fields
		 *
		 * @since 1.3.5
		 *
		 * @var array
		 */
		protected $meta_fields = array();

		/**
		 * Meta keys
		 *
		 * @since 1.3.5
		 *
		 * @var array
		 */
		protected $meta_keys = array();

		/**
		 * Name of primary index
		 *
		 * @since 1.3.5
		 *
		 * @var string
		 */
		protected $index;

		/**
		 * Name of table
		 *
		 * NOTE: Don't call this, ever. Use $this->get_table_name() so prefix and possible suffix are extended.
		 *
		 * @since 1.3.5
		 *
		 * @var string
		 */
		protected $table_name;

		/**
		 * The start date of the report.
		 *
		 * @var int timestamp
		 */
		public $start_date;

		/**
		 * The end date of the report.
		 *
		 * @var int timestamp
		 */
		public $end_date;

		/**
		 * Flag to designate that there is a meta table
		 *
		 * @since 1.5.0
		 *
		 * @var bool
		 */
		protected $has_meta = true;

		/**
		 * Constructor -- protected to force singleton upon subclasses.
		 */
		protected function __construct() {
		}

		/**
		 * Get name of table with prefix
		 *
		 * @param bool|false $meta Whether primary or meta table name is desired. Default is false, which returns primary table
		 *
		 * @return string
		 * @since 1.3.5
		 *
		 */
		public function get_table_name( $meta = false ) {
			global $wpdb;

			$table_name = $wpdb->prefix . $this->table_name;
			if ( $meta ) {
				$table_name .= '_meta';
			}

			return $table_name;
		}

		/**
		 * Get names of fields for this collection
		 *
		 * @return array
		 * @since 1.3.5
		 *
		 */
		public function get_fields() {

			$fields['primary'] = array_keys( $this->primary_fields );

			if ( $this->has_meta ) {
				$fields['meta_keys'] = array_keys( $this->meta_keys );

				/**
				 * Filter the allowed meta keys that can be saved
				 *
				 * @param array $fields Allowed fields
				 * @param string $table_name Name of table
				 *
				 * @since 1.4.0
				 *
				 */
				$fields['meta_fields'] = apply_filters( 'wfocu_db_meta_fields', array_keys( $this->meta_fields ), $this->get_table_name( true ) );

			}

			return $fields;
		}

		/**
		 * Create a new entry with meta (if supported)
		 *
		 * @param array $data Data to save
		 *
		 * @return bool|int|null
		 * @since 1.3.5
		 *
		 */
		public function create( array $data ) {

			$_data = $_meta = array(
				'fields'  => array(),
				'formats' => array(),
			);

			foreach ( $data as $field => $datum ) {
				if ( is_null( $datum ) || is_object( $datum ) ) {
					$datum = '';
				}

				if ( $this->valid_field( $field, 'primary' ) ) {
					$_data['fields'][ $field ] = call_user_func( $this->primary_fields[ $field ][1], $datum );
					$_data['formats'][]        = $this->primary_fields[ $field ][0];
				}

				if ( $this->has_meta && $this->valid_field( $field, 'meta_key' ) ) {
					$_meta['fields'][ $field ]['value']  = call_user_func( $this->meta_keys[ $field ][1], $datum );
					$_meta['fields'][ $field ]['format'] = $this->meta_keys[ $field ][0];
				} elseif ( $this->has_meta && $field === 'meta' ) {

					foreach ( $datum as $key => $meta ) {
						if ( empty( $meta ) ) {
							continue;
						}
						$_meta['fields'][ $key ]['value']  = $meta;
						$_meta['fields'][ $key ]['format'] = '%s';
					}
				}
			}

			$id = $this->save( $_data );

			if ( is_numeric( $id ) && $this->has_meta && ! empty( $_meta['fields'] ) ) {

				foreach ( $_meta['fields'] as $key => $meta ) {

					$_meta_row = array();

					$_meta_row['fields']['meta_key'] = $key;
					$_meta_row['formats'][]          = '%s';

					$_meta_row['fields']['meta_value'] = $meta['value'];
					$_meta_row['formats'][]            = '%s';

					$_meta_row['fields'][ $this->index ] = $id;
					$_meta_row['formats'][]              = '%d';

					$this->save( $_meta_row, true );
				}
			}

			return $id;
		}

		/**
		 *  Save a row
		 *
		 * @param array $data Row data to save
		 * @param bool|false $meta
		 *
		 * @return bool|int|null
		 * @since 1.3.5
		 *
		 */
		protected function save( array $data, $meta = false ) {
			if ( $meta && ! $this->has_meta ) {
				return false;
			}

			if ( ! isset( $data['formats'] ) ) {
				foreach ( $this->primary_fields as $args ) {
					$data['formats'][] = $args[0];
				}
			}
			if ( ! empty( $data ) ) {
				global $wpdb;
				$inserted = $wpdb->insert( $this->get_table_name( $meta ), $data['fields'], $data['formats'] );
				if ( $inserted ) {
					return $wpdb->insert_id;
				} else {
					return false;
				}
			} else {
				return null;
			}
		}

		/**
		 * Delete an entry from DB
		 *
		 * @param int $id ID of entry
		 *
		 * @return bool
		 * @since 1.3.5
		 *
		 */
		public function delete( $id ) {
			global $wpdb;
			$deleted = $wpdb->delete( $this->get_table_name(), array( 'ID' => $id ) );
			if ( false != $deleted ) {
				return true;
			}
		}

		public function query_results( $args = array() ) {

			global $wpdb;

			$default_args = array(
				'data'        => array(),
				'where'       => array(),
				'where_meta'  => array(),
				'query_type'  => 'get_row',
				'group_by'    => '',
				'order_by'    => '',
				'order'       => '',
				'limit'       => '',
				'offset'      => '',
				'event_range' => false,
				'nocache'     => false,
				'debug'       => false,
			);

			$args = $args;
			$args = wp_parse_args( $args, $default_args );

			$data           = [];
			$where          = [];
			$where_meta     = [];
			$query_type     = 'get_row';
			$group_by       = '';
			$order_by       = '';
			$order          = '';
			$limit          = '';
			$offset         = '';
			$event_range    = false;
			$nocache        = false;
			$debug          = false;
			$session_table  = false;
			$join_object_id = false;
			$session_join   = false;
			$event_join     = false;
			$meta_join      = false;
			extract( $args );

			$this->start_date = isset( $args['start_date'] ) ? $args['start_date'] : '';
			$this->end_date   = isset( $args['end_date'] ) ? $args['end_date'] : '';

			$query  = array();
			$select = array();

			foreach ( $data as $raw_key => $value ) {
				$key      = sanitize_key( $raw_key );
				$distinct = '';

				if ( isset( $value['distinct'] ) ) {
					$distinct = 'DISTINCT';
				}
				$continue = false;
				switch ( $value['type'] ) {
					case 'col':
						$get_key = "events.{$key}";
						break;
					case 'meta':
						$get_key = "events_meta_{$key}.meta_value";
						break;
					case 'post_data':
						$get_key = "posts.{$key}";
						break;
					case 'order_item_meta':
						$get_key = "order_item_meta_{$key}.meta_value";
						break;
					case 'order_item':
						$get_key = "order_items.{$key}";
						break;
					default:
						$continue = true;
						break;

				}

				if ( $continue ) {
					continue;
				}
				if ( $value['function'] ) {
					$get = "{$value['function']}({$distinct} {$get_key})";
				} else {
					$get = "{$distinct} {$get_key}";
				}

				$select[] = "{$get} as {$value['name']}";
			}

			if ( empty( $select ) ) {
				$query['select'] = 'SELECT * ';
			} else {
				$query['select'] = 'SELECT ' . implode( ',', $select );
			}

			$query['from'] = "FROM {$wpdb->prefix}wfocu_event AS events";

			if ( isset( $args['session_table'] ) && $session_table ) {
				$query['from'] = "FROM {$wpdb->prefix}wfocu_session AS events";
			}

			// Joins
			$joins = array();

			if ( isset( $args['join_object_id'] ) && $join_object_id ) {
				$joins['post_key'] = "LEFT JOIN {$wpdb->prefix}posts AS posts ON ( events.object_id = posts.ID )";
			}

			if ( isset( $args['session_join'] ) && $session_join ) {
				$joins['session_key'] = "LEFT JOIN {$wpdb->prefix}wfocu_session AS session ON ( events.sess_id = session.id )";
			}

			if ( isset( $args['event_join'] ) && $event_join ) { //To join event table with session table
				$joins['event_key'] = "LEFT JOIN {$wpdb->prefix}wfocu_event AS event ON ( event.sess_id = events.id )";
			}

			if ( isset( $args['meta_join'] ) && $meta_join ) { //To join event meta table with join of session table and event table
				$joins['emeta_key'] = "LEFT JOIN {$wpdb->prefix}wfocu_event_meta AS event_meta ON ( event.id = event_meta.event_id )";
			}

			foreach ( ( $data + $where ) as $raw_key => $value ) {
				$join_type = isset( $value['join_type'] ) ? $value['join_type'] : 'INNER';
				$type      = isset( $value['type'] ) ? $value['type'] : false;
				$key       = sanitize_key( $raw_key );

				switch ( $type ) {
					case 'meta':
						$joins["meta_{$key}"] = "{$join_type} JOIN {$wpdb->prefix}wfocu_event_meta AS events_meta_{$key} ON ( events.ID = events_meta_{$key}.event_id AND events_meta_{$key}.meta_key = '{$raw_key}' )";
						break;

				}
			}

			if ( ! empty( $where_meta ) ) {
				foreach ( $where_meta as $value ) {
					if ( ! is_array( $value ) ) {
						continue;
					}
					$join_type = isset( $value['join_type'] ) ? $value['join_type'] : 'INNER';
					$key       = sanitize_key( is_array( $value['meta_key'] ) ? $value['meta_key'][0] . '_array' : $value['meta_key'] );

					// If we have a where clause for meta, join the postmeta table
					$joins["meta_{$key}"] = "{$join_type} JOIN {$wpdb->prefix}wfocu_event_meta AS events_meta_{$key} ON ( events.ID = events_meta_{$key}.event_id )";

				}
			}

			$query['join'] = implode( ' ', $joins );

			$query['where'] = '';
			if ( ! empty( $where ) ) {
				$query['where'] = ' WHERE 1=1 ';
			}

			if ( $event_range ) {
				$query['where'] .= "
				AND 	events.timestamp >= '" . date( 'Y-m-d H:i:s', $this->start_date ) . "'
				AND 	events.timestamp < '" . date( 'Y-m-d H:i:s', $this->end_date ) . "'
			";
			}

			if ( ! empty( $where_meta ) ) {

				$relation = isset( $where_meta['relation'] ) ? $where_meta['relation'] : 'AND';

				$query['where'] .= ' AND (';

				foreach ( $where_meta as $index => $value ) {

					if ( ! is_array( $value ) ) {
						continue;
					}

					$key = sanitize_key( is_array( $value['meta_key'] ) ? $value['meta_key'][0] . '_array' : $value['meta_key'] );

					if ( strtolower( $value['operator'] ) === 'in' || strtolower( $value['operator'] ) === 'not in' ) {

						if ( is_array( $value['meta_value'] ) ) {
							$value['meta_value'] = implode( "','", $value['meta_value'] );  //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						}

						if ( ! empty( $value['meta_value'] ) ) {
							$where_value = "{$value['operator']} ('{$value['meta_value']}')";
						}
					} else {
						$where_value = "{$value['operator']} '{$value['meta_value']}'";
					}

					if ( ! empty( $where_value ) ) {
						if ( $index > 0 ) {
							$query['where'] .= ' ' . $relation;
						}

						if ( is_array( $value['meta_key'] ) ) {
							$query['where'] .= " ( events_meta_{$key}.meta_key   IN ('" . implode( "','", $value['meta_key'] ) . "')";
						} else {
							$query['where'] .= " ( events_meta_{$key}.meta_key   = '{$value['meta_key']}'";
						}

						$query['where'] .= " AND events_meta_{$key}.meta_value {$where_value} )";

					}
				}

				$query['where'] .= ')';
			}

			if ( ! empty( $where ) ) {

				foreach ( $where as $value ) {

					if ( strtolower( $value['operator'] ) === 'in' || strtolower( $value['operator'] ) === 'not in' ) {

						if ( is_array( $value['value'] ) ) {
							$value['value'] = implode( "','", $value['value'] );
						}

						if ( ! empty( $value['value'] ) ) {
							$where_value = "{$value['operator']} ('{$value['value']}')";
						}
					} else {
						$where_value = "{$value['operator']} '{$value['value']}'";
					}

					if ( ! empty( $where_value ) ) {
						$query['where'] .= " AND {$value['key']} {$where_value}";
					}
				}
			}

			if ( $group_by ) {
				$query['group_by'] = "GROUP BY {$group_by}";
			}

			if ( $order_by ) {
				$query['order_by'] = "ORDER BY {$order_by}";
				if ( $order ) {
					$query['order_by'] .= " {$order}";
				}
			}

			if ( $limit ) {
				$query['limit'] = "LIMIT {$limit}";
				if ( $offset ) {
					$query['limit'] = "LIMIT $offset, {$limit}";
				}
			}

			$query      = $query;
			$query      = implode( ' ', $query );
			$query_hash = md5( $query_type . $query );

			if ( $debug ) {
				WFOCU_Common::pr( $query );
			}

			$woofunnels_cache_object  = WooFunnels_Cache::get_instance();
			$woofunnels_transient_obj = WooFunnels_Transient::get_instance();

			$cache_key = strtolower( get_class( $this ) );

			$query_results = array();


			$cached_results = $woofunnels_cache_object->get_cache( $cache_key, 'upstroke-reports' );

			if ( isset( $cached_results[ $query_hash ] ) && ! $debug && ! $nocache ) {
				$query_results = $cached_results[ $query_hash ];
			} else {
				if ( ! $debug && ! $nocache ) {
					$cached_results = $woofunnels_transient_obj->get_transient( $cache_key, 'upstroke-reports' );
				}
				if ( isset( $cached_results[ $query_hash ] ) ) {
					$query_results = $cached_results[ $query_hash ];
				}

				if ( $debug || $nocache || ( ( is_array( $query_results ) || is_object( $query_results ) ) && count( $query_results ) === 0 ) || ! isset( $cached_results[ $query_hash ] ) ) {
					$wpdb->query( 'SET SESSION SQL_BIG_SELECTS=1' );  //db call ok; no-cache ok; WPCS: unprepared SQL ok.
					$query_results = $wpdb->$query_type( $query );
					if ( ! is_array( $cached_results ) ) {
						$cached_results = [];
					}
					$cached_results[ $query_hash ] = $query_results;
					$woofunnels_transient_obj->set_transient( $cache_key, $cached_results, 21600, 'upstroke-reports' );
				}
				$woofunnels_cache_object->set_cache( $cache_key, $cached_results, 'upstroke-reports' );
			}

			$result = $query_results;

			return $result;

		}


		/**
		 * Get meta rows from DB
		 *
		 * @param int|array $id ID of entry, or an array of IDs.
		 * @param string|bool $key Optional. If false, the default all the metas are returned.  Use name of key to get one specific key.
		 *
		 * @return array|null|object
		 * @since 1.3.5
		 *
		 */
		public function get_meta( $id, $key = false ) {
			if ( ! $this->has_meta ) {
				return null;
			}

			global $wpdb;
			$table_name = $this->get_table_name( true );
			if ( is_array( $id ) ) {
				$sql = "SELECT * FROM $table_name WHERE`$this->index` IN(" . $this->escape_array( $id ) . ')';  //db call ok; no-cache ok; WPCS: unprepared SQL ok.
			} else {

				$sql = $wpdb->prepare( "SELECT * FROM $table_name WHERE `event_id` = %d", absint( $id ) );  //db call ok; no-cache ok; WPCS: unprepared SQL ok.
			}

			$results = $wpdb->get_results( $sql, ARRAY_A );

			if ( ! empty( $results ) && is_string( $key ) ) {
				return $this->reduce_meta( $results, $key );

			}

			return $results;
		}

		/**
		 * @param array $results
		 * @param $key
		 *
		 * @return array|null
		 */
		protected function reduce_meta( array $results, $key ) {
			if ( $this->valid_field( $key, 'meta_key' ) ) {
				$values = array_combine( wp_list_pluck( $results, 'meta_key' ), wp_list_pluck( $results, 'meta_value' ) );
				if ( isset( $values[ $key ] ) ) {
					return $values[ $key ];
				}
			}

			return null;
		}


		/**
		 * Check if a field is valid
		 *
		 * @param string $field Name of field to check
		 * @param string $type Type of field. Options: primary|meta|meta_key
		 *
		 * @return bool
		 * @since 1.3.5
		 *
		 */
		protected function valid_field( $field, $type = 'primary' ) {
			switch ( $type ) {
				case 'primary':
					return array_key_exists( $field, $this->primary_fields );
					break;
				case 'meta':
					if ( ! $this->has_meta ) {
						return false;
					}

					return array_key_exists( $field, $this->meta_fields );
					break;
				case 'meta_key':
					if ( ! $this->has_meta ) {
						return false;
					}

					return array_key_exists( $field, $this->meta_keys );
					break;
				default:
					return false;
					break;
			}

		}

		/**
		 * Prepare an array for use with IN() or NOT IN()
		 *
		 * Creates comma separated string with numeric values of the all keys.
		 *
		 * @param array $array
		 *
		 * @return string
		 * @since 1.3.5
		 *
		 */
		protected function escape_array( array $array ) {
			global $wpdb;
			$escaped = array();
			foreach ( $array as $v ) {
				if ( is_numeric( $v ) ) {
					$escaped[] = $wpdb->prepare( '%d', $v );
				}
			}

			return implode( ',', $escaped );
		}

		/**
		 * Add meta value to record by key
		 *
		 * @param array $meta Meta data
		 * @param array $data Record
		 *
		 * @return mixed
		 * @since 1.3.5
		 *
		 */
		protected function add_meta_to_record( array $meta, array $data ) {
			if ( ! $this->has_meta ) {
				return false;
			}
			if ( ! empty( $meta ) ) {
				$arr_keys = array_keys( $this->meta_keys );
				foreach ( $arr_keys as $key ) {
					$data[ $key ] = $this->reduce_meta( $meta, $key );
				}

				return $data;
			}
		}

		/**
		 * @return int|null
		 */
		public function highest_id() {
			global $wpdb;
			$table_name = $this->get_table_name();
			$results    = $wpdb->get_results( "SELECT max(ID) FROM {$table_name}", ARRAY_N );  //db call ok; no-cache ok; WPCS: unprepared SQL ok.
			if ( is_array( $results ) && isset( $results[0], $results[0][0] ) ) {
				return $results[0][0];
			}

		}

		/**
		 * Query by meta key
		 *
		 * @param string $key Meta key to query by
		 * @param string $value Meta value to query for
		 *
		 * @return array|null
		 * @since 1.4.5
		 *
		 */
		protected function query_meta( $key, $value ) {
			if ( ! $this->has_meta ) {
				return null;
			}

			global $wpdb;
			$table = $this->get_table_name( true );
			$sql   = $wpdb->prepare( "SELECT * FROM {$table} WHERE  `meta_key` = %s AND `meta_value` = %s ", $key, $value );  //db call ok; no-cache ok; WPCS: unprepared SQL ok.
			$r     = $wpdb->get_results( $sql, ARRAY_A );  //db call ok; no-cache ok; WPCS: unprepared SQL ok.

			return $r;

		}

		public function add_meta( $id, $key, $value ) {
			$_meta_row = array();

			$_meta_row['fields']['meta_key'] = $key;
			$_meta_row['formats'][]          = '%s';

			$_meta_row['fields']['meta_value'] = $value;
			$_meta_row['formats'][]            = '%s';

			$_meta_row['fields'][ $this->index ] = $id;
			$_meta_row['formats'][]              = '%d';

			$this->save( $_meta_row, true );
		}

	}
}