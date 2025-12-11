<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_DB_Tables' ) ) {
	/**
	 * Class BWFABT_DB_Tables
	 */
	#[AllowDynamicProperties]
	class BWFABT_DB_Tables {

		/**
		 * instance of class
		 * @var null
		 */
		private static $ins = null;
		/**
		 * Charector collation
		 *
		 * @since 2.0
		 *
		 * @var string
		 */
		protected $charset_collate;
		/**
		 * Max index length
		 *
		 * @since 2.0
		 *
		 * @var int
		 */
		protected $max_index_length = 191;
		/**
		 * List of missing tables
		 *
		 * @since 2.0
		 *
		 * @var array
		 */
		protected $missing_tables;

		/**
		 * WooFunnels_DB_Tables constructor.
		 */
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'add_if_needed' ) );
		}

		/**
		 * @return WooFunnels_DB_Tables|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Add CF tables if they are missing
		 *
		 * @since 2.0
		 */
		public function add_if_needed() {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$tables_list = $this->get_tables_list();

			if ( empty( $tables_list ) ) {
				return;
			}

			$search = 'bwf_';
			foreach ( $tables_list as $table ) {
				call_user_func( array( $this, str_replace( $search, '', $table ) ) );
			}
		}

		/**
		 * Get the list of woofunnels tables, with wp_db prefix
		 *
		 * @return array
		 * @since 2.0
		 *
		 */
		protected function get_tables_list() {
			$tables = array(
				'bwf_ab_experiments'
			);

			return $tables;
		}

		/**
		 * Add bwf_ab_experiments table
		 *
		 *  Warning: check if it exists first, which could cause SQL errors.
		 */
		public function ab_experiments() {
			$collate = '';
			global $wpdb;
			try {
				if ( $wpdb->has_cap( 'collation' ) ) {
					$collate = $wpdb->get_charset_collate();
				}
				$values_table = "CREATE TABLE `" . $wpdb->prefix . "bwf_ab_experiments` (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`title` text NOT NULL,
					`status` enum('1','2','3','4') NOT NULL DEFAULT '1',
					`desc` text NOT NULL,
					`type` varchar(20) NOT NULL DEFAULT '',
					`date_added` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
					  `date_started` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
					  `last_reset_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
					  `date_completed` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
					`goal` LONGTEXT NULL DEFAULT NULL,
					`control` bigint(20) unsigned NOT NULL,
					`variants` LONGTEXT NULL DEFAULT NULL,
					`activity` LONGTEXT NULL DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `id` (`id`)				
					) " . $collate . ";";
	
				dbDelta( $values_table );
	
				$tables = get_option( '_bwfabt_created_tables', array() );
	
				// Only proceed if we have a valid array, otherwise skip the update
				if ( is_array( $tables ) ) {
					array_push( $tables, $wpdb->prefix . 'bwf_ab_experiments' );
					$tables = array_unique( $tables );
					update_option( '_bwfabt_created_tables', $tables );
				}
			} catch (Exception|Error $e) {
				
			}
		}
			
	}
}