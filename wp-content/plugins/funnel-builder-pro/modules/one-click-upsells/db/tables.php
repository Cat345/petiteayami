<?php

if ( ! class_exists( 'WFOCU_DB_Tables' ) ) {
	/**
	 * Class WFOCU_DB_Tables
	 */
	class WFOCU_DB_Tables {

		/**
		 * Charector collation
		 *
		 * @since 1.5.1
		 *
		 * @var string
		 */
		protected $charset_collate;

		/**
		 * Max index length
		 *
		 * @since 1.5.1
		 *
		 * @var int
		 */
		protected $max_index_length = 191;

		/**
		 * List of missing tables
		 *
		 * @since 1.5.4
		 *
		 * @var array
		 */
		protected $missing_tables;

		/**
		 * WFOCU_DB_Tables constructor.
		 *
		 * @since 1.5.1
		 *
		 */
		public function __construct() {
		}

		/**
		 * Add CF tables if they are missing
		 *
		 * @since 1.5.1
		 */
		public function add_if_needed() {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$this->missing_tables = $this->find_missing_tables();

			if ( empty( $this->missing_tables ) ) {
				return;
			}

			global $wpdb;

			$search = $wpdb->prefix . 'wfocu_';
			foreach ( $this->missing_tables as $table ) {

				call_user_func( array( $this, str_replace( $search, '', $table ) ) );
			}

		}

		/**
		 * Get list of missing tables
		 *
		 * @return array
		 * @since 1.5.4
		 *
		 */
		public function get_missing_tables() {
			return $this->missing_tables;
		}

		/**
		 * Find any missing tables
		 *
		 * @return array
		 */
		protected function find_missing_tables() {


			return $this->get_tables_list();

		}

		/**
		 * Get the list of tables, with wpdb prefix
		 *
		 * @return array
		 * @since 1.5.1
		 *
		 */
		protected function get_tables_list() {

			$tables = array(
				'wfocu_session',
				'wfocu_event',
				'wfocu_event_meta',

			);
			global $wpdb;
			foreach ( $tables as &$table ) {
				$table = $wpdb->prefix . $table;
			}

			return $tables;
		}

		/**
		 * Add wfocu_events table
		 *
		 * Warning: does not check if it exists first, which could cause SQL errors.
		 *
		 * @since 1.5.1
		 */
		public function session() {
			global $wpdb;

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}
			$values_table = "CREATE TABLE `" . $wpdb->prefix . "wfocu_session` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`order_id` bigint(20) NOT NULL,
				`email` varchar(100) NOT NULL,
				`total` longtext NOT NULL,
				`gateway` varchar(100) NOT NULL,
				`cid` bigint(20) unsigned NOT NULL DEFAULT 0,
				`fid` bigint(20) unsigned NOT NULL DEFAULT 0,
				`timestamp` DateTime NOT NULL,
				PRIMARY KEY (`id`),
				KEY `order_id` (`order_id`),
				KEY `email` (`email`)
                ) " . $collate . ";";

			dbDelta( $values_table );
		}

		/**
		 * Add wfocu_events table
		 *
		 * Warning: does not check if it exists first, which could cause SQL errors.
		 *
		 * @since 1.5.1
		 */
		public function event() {
			global $wpdb;

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}
			$values_table = "CREATE TABLE `" . $wpdb->prefix . "wfocu_event` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`sess_id` bigint(20) NOT NULL,
				`object_type` varchar(12) NOT NULL DEFAULT '',
				`object_id` varchar(20) NOT NULL,
				`action_type_id` varchar(10) NOT NULL,
				`value` longtext NOT NULL,
				`timestamp` DateTime NOT NULL,
				PRIMARY KEY (`id`),
				KEY `object_type` (`object_type`),
				KEY `object_id` (`object_id`),
				KEY `action_type_id` (`action_type_id`)
                ) " . $collate . ";";

			dbDelta( $values_table );
		}


		/**
		 * Add wfocu_events table
		 *
		 * Warning: does not check if it exists first, which could cause SQL errors.
		 *
		 * @since 1.5.1
		 */
		public function events() {
			global $wpdb;

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}
			$values_table = "CREATE TABLE `" . $wpdb->prefix . "wfocu_events` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`order_id` bigint(20) NOT NULL,
				`useremail` varchar(200) NOT NULL,
				`object_type` varchar(12) NOT NULL DEFAULT '',
				`object_id` varchar(20) NOT NULL,
				`action_type_id` varchar(10) NOT NULL,
				`value` longtext NOT NULL,
				`timestamp` DateTime NOT NULL,
				PRIMARY KEY (`id`),
				KEY `order_id` (`order_id`),
				KEY `object_type` (`object_type`),
				KEY `object_id` (`object_id`),
				KEY `action_type_id` (`action_type_id`)
                ) " . $collate . ";";

			dbDelta( $values_table );
		}

		/**
		 * Add wfocu_form_entry_meta table
		 *
		 * Warning: does not check if it exists first, which could cause SQL errors.
		 *
		 * @since 1.5.1
		 */
		public function events_meta() {
			global $wpdb;

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}
			$meta_table = 'CREATE TABLE `' . $wpdb->prefix . "wfocu_events_meta` (
			`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`event_id` bigint(20) unsigned NOT NULL DEFAULT '0',
			`meta_key` varchar(50) DEFAULT NULL,    
			`meta_value` longtext,
			PRIMARY KEY (`meta_id`),
			KEY `meta_key` (meta_key(" . $this->max_index_length . ')),
            KEY `event_id` (`event_id`)
            ) ' . $collate . ';';

			dbDelta( $meta_table );

		}


		/**
		 * Add wfocu_form_entry_meta table
		 *
		 * Warning: does not check if it exists first, which could cause SQL errors.
		 *
		 * @since 1.5.1
		 */
		public function event_meta() {
			global $wpdb;

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}
			$meta_table = "CREATE TABLE `" . $wpdb->prefix . "wfocu_event_meta` (
			`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`event_id` bigint(20) unsigned NOT NULL DEFAULT '0',
			`meta_key` varchar(50) DEFAULT NULL,    
			`meta_value` longtext,
			PRIMARY KEY (`meta_id`)
            ) " . $collate . ";";

			dbDelta( $meta_table );

		}


	}
}
