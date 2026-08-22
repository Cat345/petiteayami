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

		public static function getInstance() { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct() {
			/** Updates */
			add_action( 'admin_init', array( $this, 'db_update' ), 20 );
		}

		/**
		 * Perform DB update
		 *
		 * @return void
		 */
		public function db_update() {
			$db_changes = array(
				'1.0.1' => '1_0_1',
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

			if ( $this->column_exists( $table_name, 'onumber' ) ) {
				$this->update_db_version( $version_key );

				return;
			}

			/** Back off for an hour so a database that keeps refusing is not retried on every load */
			if ( false !== get_transient( 'fkcart_pro_onumber_lock' ) ) {
				return;
			}
			set_transient( 'fkcart_pro_onumber_lock', 1, HOUR_IN_SECONDS );

			$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `onumber` varchar(100) NOT NULL DEFAULT '' AFTER `oid`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			/** Leave the version unset when the column did not land, so this runs again later */
			if ( ! $this->column_exists( $table_name, 'onumber' ) ) {
				$this->log( 'Unable to add fk_cart.onumber column: ' . $wpdb->last_error );

				return;
			}

			delete_transient( 'fkcart_pro_onumber_lock' );
			$this->log( 'Added missing fk_cart.onumber column.' );

			$this->update_db_version( $version_key );
		}

		/**
		 * @param string $table_name Fully prefixed table name.
		 * @param string $column Column name.
		 *
		 * @return bool
		 */
		private function column_exists( $table_name, $column ) {
			global $wpdb;

			$found = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

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
