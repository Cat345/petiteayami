<?php

if ( ! class_exists( 'FKCART_DB_Migrator' ) ) {
	#[AllowDynamicProperties]
	class FKCART_DB_Migrator extends WooFunnels_Background_Updater {
		const MAX_SAME_OFFSET_THRESHOLD = 5;
		
		public static $_instance = null;
		protected $prefix = 'bwf_fkcart_1';
		protected $action = 'migrator';

		public function __construct() {
			parent::__construct();
			add_action( 'wffn_conversion_migration_complete_batch', [ $this, 'db_migrator' ] );
		}

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}


		public function maybe_re_dispatch_background_process() {
			if ( $this->is_queue_empty() ) {
				return;
			}
			if ( $this->is_process_running() ) {
				return;
			}

			// Check for stuck process - same offset for 5 consecutive runs
			$offsets = $this->get_last_offsets();
			if ( self::MAX_SAME_OFFSET_THRESHOLD === count( $offsets ) ) {
				$unique = array_unique( $offsets );
				if ( 1 === count( $unique ) ) {
					$this->kill_process();
					WFFN_Core()->logger->log( sprintf( 'FKcart migration offset is stuck from last %d attempts, terminating the process.', self::MAX_SAME_OFFSET_THRESHOLD ), 'fkcart_migration', true );
					return;
				}
			}

			$this->manage_last_offsets();
			$this->dispatch();
		}

		public function get_action() {
			return $this->action;
		}

		/**
		 * Kill process.
		 *
		 * Stop processing queue items, clear cronjob and delete all batches.
		 */
		public function kill_process() {
			$this->kill_process_safe();
		}

		public function get_last_offsets() {
			return get_option( '_bwf_fkcart_last_offsets', array() );
		}

		/**
		 * Manage last 5 offsets for stuck process detection
		 */
		public function manage_last_offsets() {
			$offsets        = $this->get_last_offsets();
			$current_offset = get_option( '_bwf_fkcart_offset', 0 );
			
			if ( self::MAX_SAME_OFFSET_THRESHOLD === count( $offsets ) ) {
				$offsets = array_map( function ( $key ) use ( $offsets ) {
					return isset( $offsets[ $key + 1 ] ) ? $offsets[ $key + 1 ] : 0;
				}, array_keys( $offsets ) );

				$offsets[ self::MAX_SAME_OFFSET_THRESHOLD - 1 ] = $current_offset;
			} else {
				$offsets[ count( $offsets ) ] = $current_offset;
			}

			$this->update_last_offsets( $offsets );
		}

		public function update_last_offsets( $offsets ) {
			update_option( '_bwf_fkcart_last_offsets', $offsets );
		}

		protected function complete() {
			$migrate_total = get_option( '_bwf_fkcart_offset', 0 );
			$this->set_upgrade_state( 3 );
			WFFN_Core()->logger->log( 'FKcart migration process complete for total number of entry ' . $migrate_total, 'fkcart_migration', true );

			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fk_cart_stats" );

            update_option( '_bwf_fkcart_offset', 0 );
			delete_option( 'fkcart_order_maxid' );
			delete_option( '_bwf_fkcart_last_offsets' );
		}

		public function get_upgrade_state() {

			/**
			 * 0: default state, nothing set
			 * 1: upgrade is available
			 * 2: upgrade is in process
			 * 3: upgrade is completed successfully
			 * 4: upgrade is unavailable
			 */
			return absint( get_option( '_fkcart_upgrade', '0' ) );
		}

		public function set_upgrade_state( $state ) {
			update_option( '_fkcart_upgrade', $state );
		}

		/**
		 * Prepare data for db migrate
		 * @return bool
		 */
		public function db_migrator() {
			global $wpdb;
			$per_page = 100;
			$offset = absint( get_option( '_bwf_fkcart_offset', 0 ) );
			$max_id = absint( get_option( 'fkcart_order_maxid', 0 ) );
			
			// Check for stuck process - same offset for 5 consecutive runs
			$offsets = $this->get_last_offsets();
			if ( self::MAX_SAME_OFFSET_THRESHOLD === count( $offsets ) ) {
				$unique = array_unique( $offsets );
				if ( 1 === count( $unique ) ) {
					$this->kill_process();
					WFFN_Core()->logger->log( sprintf( 'FKcart migration offset is stuck from last %d attempts, terminating the process.', self::MAX_SAME_OFFSET_THRESHOLD ), 'fkcart_migration', true );
					return false;
				}
			}
			
			// Update offset tracking
			$this->manage_last_offsets();
			
			if ( 0 === $max_id ) {
				return true; 
			}

			$entries = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT oid FROM {$wpdb->prefix}fk_cart WHERE id <= %d ORDER BY id ASC LIMIT %d OFFSET %d",
					$max_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);

			if ( empty( $entries ) ) {
				// Migration complete - no more records to process
				$this->complete();
				return true;
			}

			foreach ( $entries as $item ) {
				$onumber = $item['oid'];
				if ( function_exists( 'wc_get_order' ) ) {
					$order = wc_get_order( $item['oid'] );
					if ( $order && method_exists( $order, 'get_order_number' ) ) {
						$onumber = $order->get_order_number();
					}
				}
				$wpdb->update(
					"{$wpdb->prefix}fk_cart",
					[ 'onumber' => $onumber ],
					[ 'oid' => $item['oid'] ]
				);
				$offset++;
				update_option( '_bwf_fkcart_offset', $offset );
			}

			// Check if we've processed all records up to max_id
			$total_records_to_process = $wpdb->get_var( $wpdb->prepare( 
				"SELECT COUNT(*) FROM {$wpdb->prefix}fk_cart WHERE id <= %d", 
				$max_id 
			) );
			
			if ( $offset >= $total_records_to_process ) {
				$this->complete();
				return true;
			}

			return true;
		}

		/**
		 * Insert data in new tables
		 *
		 * @param $data
		 * @param $table_name
		 *
		 * @return void
		 */
		public function insert_data( $data, $table_name ) {
			/***
			 * prepare process order array
			 */
			if ( ! empty( $data ) ) {
				global $wpdb;
				$first_row     = reset( $data );
				$columns       = array_keys( $first_row );
				$placeholders  = array_fill( 0, count( $data ), '(' . rtrim( str_repeat( '%s, ', count( $columns ) ), ', ' ) . ')' );
				$query         = "INSERT INTO $table_name (" . implode( ', ', $columns ) . ") VALUES " . implode( ', ', $placeholders );
				$insert_values = [];
				foreach ( $data as $row ) {
					$insert_values = array_merge( $insert_values, array_values( $row ) );
				}
				$sql = $wpdb->prepare( $query, $insert_values );//phpcs:ignore
				$wpdb->query( $sql );//phpcs:ignore

				if ( ! empty( $wpdb->last_error ) ) {
					WFFN_Core()->logger->log( 'fkcart migration process insert data error ' . $wpdb->last_error . ' last query ' . $wpdb->last_query, 'fkcart_migration', true );
				}
			}
		}

		public function update_data( $data, $table_name ) {
			global $wpdb;
			if ( ! is_array( $data ) || empty( $data ) ) {
				return;
			}

			foreach ( $data as $item ) {
				$sql = '';
				unset( $item['id'] );

				$update_data  = $item;
				$oid_value    = $item['oid'];
				$placeholders = array();
				$update_query = "UPDATE $table_name SET ";
				foreach ( $update_data as $key => $value ) {
					$placeholders[] = "$key = %s";
				}
				$update_query       .= implode( ', ', $placeholders );
				$update_query       .= " WHERE oid = %d;";
				$update_data_values = array_merge( array_values( $update_data ), array( $oid_value ) );
				$sql                .= $wpdb->prepare( $update_query, $update_data_values ); //phpcs:ignore
				$wpdb->query( $sql );//phpcs:ignore
				if ( ! empty( $wpdb->last_error ) ) {
					WFFN_Core()->logger->log( 'fkcart migration process update data error ' . $wpdb->last_error . ' last query ' . $wpdb->last_query, 'fkcart_migration', true );
				}
			}

		}

	}

	if ( ! function_exists( 'FKCART_DB_Migrator' ) ) {
		function fkcart_db_migrator() {  //@codingStandardsIgnoreLine
			return FKCART_DB_Migrator::get_instance();
		}
	}

	fkcart_db_migrator();
}

if ( ! function_exists( 'fkcart_run_db_migrator' ) ) {
	function fkcart_run_db_migrator() {
		return fkcart_db_migrator()->db_migrator();
	}
}

