<?php

if ( ! class_exists( 'FKCART_DB_Migrator' ) ) {
	#[AllowDynamicProperties]
	class FKCART_DB_Migrator extends WooFunnels_Background_Updater {
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
			return array();
		}

		public function manage_last_offsets() {

		}

		protected function complete() {
			$migrate_total = get_option( '_bwf_fkcart_offset', 0 );
			$this->set_upgrade_state( 3 );
			WFFN_Core()->logger->log( 'FKcart migration process complete for total number of entry ' . $migrate_total, 'fkcart_migration', true );

			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fk_cart_stats" );

            update_option( '_bwf_fkcart_offset', 0 );
			delete_option( 'fkcart_order_maxid' );
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

