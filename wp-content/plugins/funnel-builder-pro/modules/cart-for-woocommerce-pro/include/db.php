<?php

namespace FKCart\Pro;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( '\FKCart\Pro\DB' ) ) {

	/**
	 * Class DB
	 */
	#[\AllowDynamicProperties]
	class DB {

		private static $instance = null;

		const BACKFILL_HOOK  = 'fkcart_pro_backfill_missed_orders';
		const BACKFILL_BATCH = 50;
		/** Cart Lite 1.8.3 added `onumber` here; orders before it were recorded without the column */
		const BACKFILL_SINCE = '2025-07-01';

		public static function getInstance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct() {
			/** Updates */
			add_action( 'admin_init', array( $this, 'db_update' ), 20 );
			add_action( self::BACKFILL_HOOK, array( $this, 'backfill_missed_orders' ) );
		}

		/**
		 * Perform DB update
		 *
		 * @return void
		 */
		public function db_update() {
			$db_changes = array(
				'1.0.1' => '1_0_1',
				'1.0.2' => '1_0_2',
			);

			$db_options = get_option( 'fkcart_pro_db_options', array() );
			$db_version = $db_options['db_version'] ?? '0.1';

			/** Checking if current db version is greater than then the saved version */
			if ( false === version_compare( FKCART_PRO_DB_VERSION, $db_version, '>' ) ) {
				return;
			}

			foreach ( $db_changes as $version_key => $version_value ) {
				if ( version_compare( $db_version, $version_key, '<' ) ) {
					$function_name = 'db_update_' . $version_value;
					$this->$function_name( $version_key );
				}
			}
		}

		/**
		 * Update db option key with version
		 *
		 * @param string $version Version key.
		 *
		 * @return void
		 */
		private function update_db_version( $version ) {
			$db_options               = get_option( 'fkcart_pro_db_options', array() );
			$db_options['db_version'] = $version;

			$db_options[ 'db_' . $version ] = current_time( 'mysql' );

			if ( ! isset( $db_options['id'] ) || empty( $db_options['id'] ) ) {
				$db_options['id'] = current_time( 'mysql' ); // Install date
			}

			/** Updating version */
			update_option( 'fkcart_pro_db_options', $db_options, true );
		}

		/**
		 * 1.0.1
		 *
		 * Add the `onumber` column to fk_cart where it is missing, otherwise every order
		 * fails with "Unknown column 'onumber' in 'field list'".
		 *
		 * @param string $version_key Version key.
		 *
		 * @return void
		 */
		protected function db_update_1_0_1( $version_key ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'fk_cart';

			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
			if ( $table_exists !== $table_name ) {
				$this->update_db_version( $version_key );

				return;
			}

			if ( $this->column_exists( 'onumber' ) ) {
				$this->update_db_version( $version_key );

				return;
			}

			/** Back off for an hour so a database that keeps refusing is not retried on every load */
			if ( false !== get_transient( 'fkcart_pro_onumber_lock' ) ) {
				return;
			}
			set_transient( 'fkcart_pro_onumber_lock', 1, HOUR_IN_SECONDS );

			/**
			 * Installs made before the foreign key was taken out of the fk_cart_products schema still
			 * have it, because dbDelta adds the replacement index but never drops a constraint. It
			 * holds the index on fk_cart.oid, so rebuilding fk_cart fails with "Cannot drop index '':
			 * needed in a foreign key constraint". Remove it first.
			 */
			$this->drop_stale_foreign_key();

			/**
			 * Inserts name their columns, so the position is only a preference: when the rebuild for
			 * AFTER `oid` is refused, add it at the end, then with foreign key checks off.
			 */
			$errors = array();
			foreach ( array( 'after_oid', 'at_end', 'checks_off' ) as $attempt ) {
				if ( 'checks_off' === $attempt ) {
					$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
				}
				if ( 'after_oid' === $attempt ) {
					$wpdb->query( "ALTER TABLE {$wpdb->prefix}fk_cart ADD COLUMN `onumber` varchar(100) NOT NULL DEFAULT '' AFTER `oid`" );
				} else {
					$wpdb->query( "ALTER TABLE {$wpdb->prefix}fk_cart ADD COLUMN `onumber` varchar(100) NOT NULL DEFAULT ''" );
				}
				/** Read the error now: the next query clears it */
				if ( '' !== $wpdb->last_error ) {
					$errors[] = $wpdb->last_error;
				}
				if ( 'checks_off' === $attempt ) {
					$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
				}
				if ( $this->column_exists( 'onumber' ) ) {
					break;
				}
			}

			/** Leave the version unset when the column did not land, so this runs again later */
			if ( ! $this->column_exists( 'onumber' ) ) {
				$this->log( 'Unable to add fk_cart.onumber column: ' . implode( ' | ', $errors ) );

				return;
			}

			delete_transient( 'fkcart_pro_onumber_lock' );
			$this->log( 'Added missing fk_cart.onumber column.' );

			$this->update_db_version( $version_key );
		}

		/**
		 * 1.0.2
		 *
		 * Paid orders placed while fk_cart was missing `onumber` were never recorded. Record them once,
		 * in the background. Waits for the column: saving this version first would leave 1.0.1 skipped.
		 *
		 * @param string $version_key Version key.
		 *
		 * @return void
		 */
		protected function db_update_1_0_2( $version_key ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'fk_cart';

			if ( $table_name !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) ) {
				$this->update_db_version( $version_key );

				return;
			}

			if ( ! $this->column_exists( 'onumber' ) ) {
				return;
			}

			$this->schedule_backfill( 1 );
			$this->update_db_version( $version_key );
		}

		/**
		 * Queue one batch of the missed-orders backfill.
		 *
		 * @param int $page Page of orders to process.
		 *
		 * @return void
		 */
		private function schedule_backfill( $page ) {
			$args = array( 'page' => (int) $page );
			/** Two requests finishing the migration together must not queue the same batch twice */
			if ( function_exists( 'as_has_scheduled_action' ) ? as_has_scheduled_action( self::BACKFILL_HOOK, $args, 'fkcart' ) : wp_next_scheduled( self::BACKFILL_HOOK, $args ) ) {
				return;
			}
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( self::BACKFILL_HOOK, $args, 'fkcart' );

				return;
			}
			wp_schedule_single_event( time() + 30, self::BACKFILL_HOOK, $args );
		}

		/**
		 * Record paid orders that carry cart view data but have no fk_cart row, using Lite's own insert.
		 * Runs in the background, one batch at a time.
		 *
		 * @param int $page Page of orders to process.
		 *
		 * @return void
		 */
		public function backfill_missed_orders( $page = 1 ) {
			try {
				global $wpdb;
				$cart = class_exists( '\FKCart\Includes\Cart' ) && method_exists( '\FKCart\Includes\Cart', 'get_instance' ) ? \FKCart\Includes\Cart::get_instance() : null;
				if ( ! $cart || ! method_exists( $cart, 'insert_data' ) || ! function_exists( 'wc_get_orders' ) ) {
					return;
				}

				$page   = max( 1, (int) $page );
				$orders = wc_get_orders(
					array(
						'limit'        => self::BACKFILL_BATCH,
						'paged'        => $page,
						'status'       => wc_get_is_paid_statuses(),
						'meta_key'     => '_fkcart_upsell_views',
						'meta_compare' => 'EXISTS',
						'date_created' => '>=' . self::BACKFILL_SINCE,
						'orderby'      => 'ID',
						'order'        => 'ASC',
					)
				);

				$recorded = 0;
				foreach ( $orders as $order ) {
					$order_id = $order->get_id();
					if ( 0 < (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}fk_cart WHERE oid = %d", $order_id ) ) ) {
						continue;
					}
					if ( method_exists( $cart, 'is_order_renewal' ) && $cart->is_order_renewal( $order ) ) {
						continue;
					}

					/** No fk_cart row means no product rows either; clear leftovers so nothing doubles */
					$wpdb->delete( $wpdb->prefix . 'fk_cart_products', array( 'oid' => $order_id ), array( '%d' ) );
					$cart->insert_data( $order );

					/** insert_data() stamps the current time; keep the order's own date in the report */
					$created = $order->get_date_created();
					if ( $created && 0 < (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}fk_cart WHERE oid = %d", $order_id ) ) ) {
						$wpdb->update( $wpdb->prefix . 'fk_cart', array( 'date_created' => $created->date( 'Y-m-d H:i:s' ) ), array( 'oid' => $order_id ), array( '%s' ), array( '%d' ) );
						++$recorded;
					}
				}

				if ( 0 < $recorded ) {
					$this->log( 'Backfill page ' . $page . ': recorded ' . $recorded . ' missed orders.' );
				}

				if ( self::BACKFILL_BATCH === count( $orders ) ) {
					$this->schedule_backfill( $page + 1 );
				}
			} catch ( \Exception | \Error $e ) {
				$this->log( 'Backfill page ' . $page . ' stopped: ' . $e->getMessage() );
			}
		}

		/**
		 * Drop the fk_cart_products foreign key where an install still has one.
		 *
		 * @return void
		 */
		private function drop_stale_foreign_key() {
			global $wpdb;

			/** The old schema declared it unnamed, so InnoDB named it <table>_ibfk_1 */
			$schema = (string) $wpdb->get_var( "SHOW CREATE TABLE {$wpdb->prefix}fk_cart_products", 1 );
			if ( false === strpos( $schema, 'CONSTRAINT `' . $wpdb->prefix . 'fk_cart_products_ibfk_1` FOREIGN KEY' ) ) {
				return;
			}

			$wpdb->query( "ALTER TABLE {$wpdb->prefix}fk_cart_products DROP FOREIGN KEY {$wpdb->prefix}fk_cart_products_ibfk_1" );

			if ( '' !== $wpdb->last_error ) {
				$this->log( 'Unable to drop foreign key ' . $wpdb->prefix . 'fk_cart_products_ibfk_1: ' . $wpdb->last_error );

				return;
			}

			$this->log( 'Dropped foreign key ' . $wpdb->prefix . 'fk_cart_products_ibfk_1 from fk_cart_products.' );
		}

		/**
		 * @param string $column Column name in fk_cart.
		 *
		 * @return bool
		 */
		private function column_exists( $column ) {
			global $wpdb;

			$found = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->prefix}fk_cart LIKE %s", $column ) );

			return ! empty( $found );
		}

		/**
		 * @param string $message Message to record.
		 *
		 * @return void
		 */
		private function log( $message ) {
			if ( function_exists( 'WFFN_Core' ) && is_object( WFFN_Core()->logger ) ) {
				WFFN_Core()->logger->log( $message, 'fkcart_migration', true );
			}
		}
	}
}
