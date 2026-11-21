<?php
/**
 * Schema owner for the bundle tables.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and describes `bwf_bundle` and its `bwf_bundle_targets` reverse index.
 *
 * The `config` column holds the bundle payload in full; the promoted columns
 * are a derived index maintained on write, never a replacement. No generated
 * columns and no DEFAULT CURRENT_TIMESTAMP, for MySQL 5.6 and dbDelta.
 */
class Bundles_Table {

	/** Unprefixed table names. */
	/** Width of the `name` column; the longest name that survives a write. */
	const NAME_LENGTH = 191;

	const TABLE         = 'bwf_bundle';
	const TARGETS_TABLE = 'bwf_bundle_targets';

	/** Autoloaded stamp recording that both tables were seen at this schema version. */
	const EXISTS_OPTION = 'wffn_bundles_tables_ready';

	/**
	 * Target row types; there is no wildcard type by design.
	 *
	 * Zero target rows means the bundle matches nothing, never everything.
	 */
	const TARGET_PRODUCT      = 1;
	const TARGET_CATEGORY     = 2;
	const TARGET_EXCL_PRODUCT = 3;
	const TARGET_EXCL_CATEGORY = 4;

	/** Column width for `name`; writes are clamped to it (see Bundle_Repository). */
	const NAME_MAX = 191;

	/** @var bool|null Request-cached answer for exists(). */
	private static $exists = null;

	/**
	 * Prefixed bundles table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Prefixed targets table name.
	 *
	 * @return string
	 */
	public static function targets_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TARGETS_TABLE;
	}

	/**
	 * Create (or diff-upgrade) both tables via dbDelta. Idempotent.
	 *
	 * `menu_order` is signed: a new bundle is written at MIN(menu_order) - 1.
	 * `start_date`/`end_date` hold site wall-clock time, `created_at`/`updated_at`
	 * hold UTC. The targets UNIQUE key backs the INSERT IGNORE rewrite.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$bundles       = self::table_name();
		$targets         = self::targets_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$bundles} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(" . self::NAME_LENGTH . ") NOT NULL DEFAULT '',
			discount_type TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			status TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			schedule_mode TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			start_date DATETIME NULL DEFAULT NULL,
			end_date DATETIME NULL DEFAULT NULL,
			target_mode TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			menu_order INT(11) NOT NULL DEFAULT 0,
			config LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status_order (status, menu_order),
			KEY order_id (menu_order, id),
			KEY discount_type (discount_type),
			KEY name (name)
		) {$charset_collate};";

		$sql .= "CREATE TABLE {$targets} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			bundle_id BIGINT(20) UNSIGNED NOT NULL,
			target_type TINYINT(1) UNSIGNED NOT NULL,
			object_id BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY link (bundle_id, target_type, object_id),
			KEY lookup (target_type, object_id, bundle_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		self::flush_exists_cache();
	}

	/**
	 * Do both tables exist? Cached for the request.
	 *
	 * Every repository read calls this, so a missing table degrades to "no
	 * bundles" instead of fatalling the storefront.
	 *
	 * @return bool
	 */
	public static function exists() {
		if ( null !== self::$exists ) {
			return self::$exists;
		}
		// Autoloaded stamp: avoids two SHOW TABLES on every request.
		if ( (string) WFFN_BUNDLES_DB_VERSION === (string) get_option( self::EXISTS_OPTION ) ) {
			self::$exists = true;
			return self::$exists;
		}
		global $wpdb;
		$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table_name() ) ) );
		$found2 = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::targets_table_name() ) ) );

		self::$exists = ( ! empty( $found ) && ! empty( $found2 ) );
		if ( self::$exists ) {
			update_option( self::EXISTS_OPTION, (string) WFFN_BUNDLES_DB_VERSION, true );
		}
		return self::$exists;
	}

	/**
	 * Forget the cached exists() answer, so the installing request can read at once.
	 *
	 * @return void
	 */
	public static function flush_exists_cache() {
		self::$exists = null;
		delete_option( self::EXISTS_OPTION );
	}
}
