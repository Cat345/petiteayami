<?php
namespace ACFWF\Models;

use ACFWF\Abstracts\Abstract_Main_Plugin_Class;
use ACFWF\Abstracts\Base_Model;
use ACFWF\Helpers\Helper_Functions;
use ACFWF\Helpers\Plugin_Constants;
use ACFWF\Interfaces\Activatable_Interface;
use ACFWF\Interfaces\Deactivatable_Interface;
use ACFWF\Interfaces\Initializable_Interface;
use ACFWF\Interfaces\Model_Interface;
use ACFWF\Models\Objects\ACFW_Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Model that houses the logic of 'Bootstraping' the plugin.
 * Private Model.
 *
 * @since 1.0
 */
class Bootstrap extends Base_Model implements Model_Interface {
    /*
    |--------------------------------------------------------------------------
    | Class Properties
    |--------------------------------------------------------------------------
     */

    /**
     * Option name that flags the duplicate advanced coupon meta migration as done.
     *
     * @since 4.7.7
     * @var string
     */
    const COUPON_META_DEDUPE_OPTION = 'acfwf_coupon_meta_dedupe_done';

    /**
     * Option name that locks the duplicate advanced coupon meta migration while it runs.
     *
     * @since 4.7.7
     * @var string
     */
    const COUPON_META_DEDUPE_LOCK = 'acfwf_coupon_meta_dedupe_lock';

    /**
     * Number of seconds after which a lock of the duplicate advanced coupon meta migration is stale.
     *
     * @since 4.7.7
     * @var int
     */
    const COUPON_META_DEDUPE_LOCK_TIMEOUT = 300;

    /**
     * Number of duplicate advanced coupon meta rows that the migration deletes in one batch.
     *
     * @since 4.7.7
     * @var int
     */
    const COUPON_META_DEDUPE_BATCH_SIZE = 500;

    /**
     * Number of seconds that one request can use for the duplicate advanced coupon meta migration.
     *
     * @since 4.7.7
     * @var int
     */
    const COUPON_META_DEDUPE_TIME_LIMIT = 10;

    /**
     * Array of models implementing the ACFWF\Interfaces\Activatable_Interface.
     *
     * @since 1.0
     * @access private
     * @var array
     */
    private $_activatables;

    /**
     * Array of models implementing the ACFWF\Interfaces\Initializable_Interface.
     *
     * @since 1.0
     * @access private
     * @var array
     */
    private $_initializables;

    /**
     * Array of models implementing the ACFWF\Interfaces\Deactivatable_Interface.
     *
     * @since 1.0.1
     * @access private
     * @var array
     */
    private $_deactivatables;

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * Class constructor.
     *
     * @since 1.0
     * @access public
     *
     * @param Abstract_Main_Plugin_Class $main_plugin      Main plugin object.
     * @param Plugin_Constants           $constants        Plugin constants object.
     * @param Helper_Functions           $helper_functions Helper functions object.
     * @param array                      $activatables     Array of models implementing ACFWF\Interfaces\Activatable_Interface.
     * @param array                      $initializables   Array of models implementing ACFWF\Interfaces\Initializable_Interface.
     * @param array                      $deactivatables   Array of models implementing ACFWF\Interfaces\Deactivatable_Interface.
     */
    public function __construct( Abstract_Main_Plugin_Class $main_plugin, Plugin_Constants $constants, Helper_Functions $helper_functions, array $activatables = array(), array $initializables = array(), $deactivatables = array() ) {
        parent::__construct( $main_plugin, $constants, $helper_functions );
        $this->_activatables   = $activatables;
        $this->_initializables = $initializables;
        $this->_deactivatables = $deactivatables;
        $main_plugin->add_to_all_plugin_models( $this );
        $this->_register_custom_database_tables();
    }

    /**
     * Load plugin text domain.
     *
     * @since 1.0
     * @access public
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain( Plugin_Constants::TEXT_DOMAIN, false, $this->_constants->PLUGIN_DIRNAME . '/languages' );
    }

    /**
     * Register custom database tables through WC filter.
     *
     * @since 4.5.1
     * @access public
     */
    private function _register_custom_database_tables() {
        global $wpdb;

        $custom_tables = array(
            Plugin_Constants::STORE_CREDITS_DB_NAME, // store credit entries.
        );

        foreach ( $custom_tables as $table ) {
            $wpdb->$table   = $wpdb->prefix . $table;
            $wpdb->tables[] = $table;
        }
    }

    /**
     * Method that houses the logic relating to activating the plugin.
     *
     * @since 1.0
     * @access public
     *
     * @global wpdb $wpdb Object that contains a set of functions used to interact with a database.
     *
     * @param boolean $network_wide Flag that determines whether the plugin has been activated network wid ( on multi site environment ) or not.
     */
    public function activate_plugin( $network_wide ) {
        global $wpdb;

        if ( is_multisite() ) {

            if ( $network_wide ) {

                // get ids of all sites.
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                foreach ( $blog_ids as $blog_id ) {

                    switch_to_blog( $blog_id );
                    $this->_activate_plugin( $blog_id );

                }

                restore_current_blog();

            } else {
                $this->_activate_plugin( $wpdb->blogid );
            }
            // activated on a single site, in a multi-site.

        } else {
            $this->_activate_plugin( $wpdb->blogid );
        }
        // activated on a single site.
    }

    /**
     * Method to initialize a newly created site in a multi site set up.
     *
     * @since 1.0
     * @access public
     *
     * @param int    $blog_id Blog ID of the created blog.
     * @param int    $user_id User ID of the user creating the blog.
     * @param string $domain Domain used for the new blog.
     * @param string $path Path to the new blog.
     * @param int    $site_id Site ID. Only relevant on multi-network installs.
     * @param array  $meta Meta data. Used to set initial site options.
     */
    public function new_mu_site_init( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
        if ( is_plugin_active_for_network( 'advanced-coupons-for-woocommerce-free/advanced-coupons-for-woocommerce-free.php' ) ) {

            switch_to_blog( $blog_id );
            $this->_activate_plugin( $blog_id );
            restore_current_blog();

        }
    }

    /**
     * Initialize plugin settings options.
     * This is a compromise to my idea of 'Modularity'. Ideally, bootstrap should not take care of plugin settings stuff.
     * However due to how WooCommerce do its thing, we need to do it this way. We can't separate settings on its own.
     *
     * @since 1.0
     * @access private
     */
    private function _initialize_plugin_settings_options() {
        // General settings section options.
        if ( ! get_option( Plugin_Constants::COUPON_ENDPOINT, false ) ) {
            update_option( Plugin_Constants::COUPON_ENDPOINT, 'coupon' );
        }

        // Help settings section options.
        if ( ! get_option( Plugin_Constants::CLEAN_UP_PLUGIN_OPTIONS, false ) ) {
            update_option( Plugin_Constants::CLEAN_UP_PLUGIN_OPTIONS, 'no', false );
        }
    }

    /**
     * Normalize the legacy store credits expiry option value for the number-input UI.
     *
     * The store credits expiry field changed from a select ( 'noexpiry' / '1'-'5' ) to a
     * number input in 4.7.5. Sites upgrading from an older version may still store the legacy
     * 'noexpiry' string, which renders as empty in the number input. Behavior is unchanged
     * either way ( should_store_credits_expire() treats 'noexpiry' and 0 identically ), so this
     * only tidies the display by converting any non-numeric stored value to 0. Numeric year
     * values ( '1'-'5' ) are left untouched. Idempotent and safe to run on every version bump.
     *
     * @since 4.7.5
     * @access private
     */
    private function _migrate_store_credit_expiry_option() {
        $expiry = get_option( Plugin_Constants::STORE_CREDIT_EXPIRY, false );

        if ( false !== $expiry && ! is_numeric( $expiry ) ) {
            update_option( Plugin_Constants::STORE_CREDIT_EXPIRY, 0 );
        }
    }

    /**
     * Remove duplicate advanced coupon meta rows.
     *
     * Before 4.7.7, a coupon save that ran after `rest_api_init` could not see its own empty `_acfw_*` meta rows,
     * so it added a second row for the same key. This keeps one row of each duplicated key: the last row that is not
     * empty (the value the merchant last saved), or the last row when all rows are empty. Advanced_Coupon reads the
     * same row while duplicates exist, so the coupon editor and the advanced coupon props show the value that is kept.
     *
     * Only duplicate groups that have an empty value are changed, because that is the signature of the bug. Keys
     * that store one row per value on purpose (e.g. allowed customers) are skipped. Idempotent. The rows are deleted
     * in batches. The run stops when the time limit is used, so a large table does not time out. Each run deletes at
     * least one row, and the delete time limit starts after the first scan, so a slow scan cannot stall the cleanup.
     *
     * @since 4.7.7
     * @access private
     *
     * @global wpdb $wpdb Object that contains a set of functions used to interact with a database.
     *
     * @return bool True when no duplicate rows are left, false when the run stopped early or a query failed.
     */
    private function _remove_duplicate_advanced_coupon_meta() {
        global $wpdb;

        $not_unique_keys = $this->_helper_functions->get_coupon_meta_not_unique_keys();
        $exclude_sql     = $not_unique_keys ? ' AND pm.meta_key NOT IN (' . implode( ',', array_fill( 0, count( $not_unique_keys ), '%s' ) ) . ')' : '';
        $query_args      = array_merge(
            array( $wpdb->esc_like( Plugin_Constants::META_PREFIX ) . '%' ),
            $not_unique_keys,
            array( self::COUPON_META_DEDUPE_BATCH_SIZE )
        );
        $start_time      = time();
        $delete_start    = 0;
        $deleted         = 0;

        while ( true ) {
            // Do not start another scan when the time limit is used. A later request deletes the rows that are left.
            if ( $deleted > 0 && time() - $start_time >= self::COUPON_META_DEDUPE_TIME_LIMIT ) {
                return false;
            }

            // Get the meta_id of every row other than the kept row of each duplicated group, in one query.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders
            $meta_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT old.meta_id
                    FROM {$wpdb->postmeta} AS old
                    INNER JOIN (
                        SELECT pm.post_id, pm.meta_key,
                            COALESCE(
                                MAX( CASE WHEN pm.meta_value NOT IN ( '', 'a:0:{}' ) THEN pm.meta_id END ),
                                MAX( pm.meta_id )
                            ) AS keep_id
                        FROM {$wpdb->postmeta} AS pm
                        INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
                        WHERE p.post_type = 'shop_coupon'
                        AND pm.meta_key LIKE %s
                        {$exclude_sql}
                        GROUP BY pm.post_id, pm.meta_key
                        HAVING COUNT( pm.meta_id ) > 1
                        AND SUM( pm.meta_value = '' OR pm.meta_value = 'a:0:{}' ) > 0
                    ) AS dup ON dup.post_id = old.post_id AND dup.meta_key = old.meta_key AND old.meta_id <> dup.keep_id
                    LIMIT %d",
                    $query_args
                )
            );
            // phpcs:enable

            // A failed query also returns an empty list, so check the error before the site is marked as done.
            if ( '' !== $wpdb->last_error ) {
                return false;
            }

            /**
             * The delete time limit starts when the first scan returns. A slow scan on a large table then cannot use
             * the full time limit before the first delete, and each run removes rows.
             */
            if ( ! $delete_start ) {
                $delete_start = time();
            }

            /**
             * Delete through the metadata API so the WordPress and WooCommerce object caches of the coupon are
             * flushed (`deleted_post_meta`), including persistent object caches.
             */
            foreach ( $meta_ids as $meta_id ) {
                // Stop when the time limit is used, after at least one delete. A later request deletes the rows that are left.
                if ( $deleted > 0 && time() - $delete_start >= self::COUPON_META_DEDUPE_TIME_LIMIT ) {
                    return false;
                }

                if ( ! delete_metadata_by_mid( 'post', (int) $meta_id ) ) {
                    return false;
                }

                ++$deleted;
            }

            if ( count( $meta_ids ) < self::COUPON_META_DEDUPE_BATCH_SIZE ) {
                return true;
            }
        }
    }

    /**
     * Run the duplicate advanced coupon meta cleanup on the current site until it is done.
     *
     * The done flag is autoloaded, so the check adds no query after the cleanup. A lock stops two requests from
     * running the cleanup at the same time. The flag is set only when the cleanup removed every duplicate row
     * without an error, so a later request continues a run that stopped early, failed or timed out.
     *
     * @since 4.7.7
     * @access private
     */
    private function _maybe_remove_duplicate_advanced_coupon_meta() {
        if ( 'yes' === get_option( self::COUPON_META_DEDUPE_OPTION ) || ! $this->_create_coupon_meta_dedupe_lock() ) {
            return;
        }

        if ( $this->_remove_duplicate_advanced_coupon_meta() ) {
            update_option( self::COUPON_META_DEDUPE_OPTION, 'yes', true );
        }

        delete_option( self::COUPON_META_DEDUPE_LOCK );
    }

    /**
     * Create the lock of the duplicate advanced coupon meta cleanup.
     *
     * The same pattern as WP_Upgrader::create_lock(): `INSERT IGNORE` is atomic, so only one request gets the lock.
     * A lock older than COUPON_META_DEDUPE_LOCK_TIMEOUT is from a request that ended early, so it is replaced once.
     *
     * @since 4.7.7
     * @access private
     *
     * @global wpdb $wpdb Object that contains a set of functions used to interact with a database.
     *
     * @return bool True when this request has the lock.
     */
    private function _create_coupon_meta_dedupe_lock() {
        global $wpdb;

        for ( $attempt = 0; $attempt < 2; $attempt++ ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $created = $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO `{$wpdb->options}` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' )",
                    self::COUPON_META_DEDUPE_LOCK,
                    time()
                )
            );

            if ( $created ) {
                return true;
            }

            // Read the lock from the database, not from the option cache, which can be stale.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $locked_at = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s", self::COUPON_META_DEDUPE_LOCK )
            );

            if ( $locked_at > time() - self::COUPON_META_DEDUPE_LOCK_TIMEOUT ) {
                return false;
            }

            // The lock is stale, or it was released after the insert. Remove it and try again.
            delete_option( self::COUPON_META_DEDUPE_LOCK );
        }

        return false;
    }

    /**
     * Put the kept row of each duplicated advanced coupon meta key first in the meta rows of a coupon.
     *
     * WC_Coupon::get_meta( $key, true ) returns the first row of a key. Before the duplicate meta cleanup ends, the
     * first row can be the empty duplicate, so code that reads the meta directly (e.g. the premium plugin) sees a
     * different value than Advanced_Coupon. This moves the row that the cleanup keeps to the place of the first row of
     * its key. No row is removed, so a save still sees every row and replaces the duplicates. Like the cleanup, it
     * changes only a key that has an empty row.
     *
     * @since 4.7.7
     * @access public
     *
     * @param array  $meta_data Raw meta rows (stdClass with meta_id, meta_key and meta_value), from the database or
     *                          from the WooCommerce meta cache (already in the order that this filter returned).
     * @param object $data      WooCommerce data object that the meta rows are read for.
     * @return array Meta rows.
     */
    public function put_kept_coupon_meta_rows_first( $meta_data, $data ) {
        if ( ! $data instanceof \WC_Coupon || ! is_array( $meta_data ) ) {
            return $meta_data;
        }

        $not_unique_keys = $this->_helper_functions->get_coupon_meta_not_unique_keys();
        $groups          = array();

        foreach ( $meta_data as $index => $meta ) {
            if ( isset( $meta->meta_id, $meta->meta_key, $meta->meta_value ) && str_starts_with( $meta->meta_key, Plugin_Constants::META_PREFIX )
                && ! in_array( $meta->meta_key, $not_unique_keys, true ) ) {
                $groups[ $meta->meta_key ][ (int) $meta->meta_id ] = $index;
            }
        }

        foreach ( $groups as $indexes ) {
            if ( count( $indexes ) < 2 ) {
                continue;
            }

            /**
             * WooCommerce caches the rows that this filter returns and runs the filter again on a cache hit. Pick the
             * kept row by meta ID, not by its place in the list, so every pass gives the same order.
             */
            ksort( $indexes );

            $values = array();
            foreach ( $indexes as $meta_id => $index ) {
                $values[ $meta_id ] = $meta_data[ $index ]->meta_value;
            }

            // The cleanup skips a group that has no empty row, so do not change the order of that group.
            if ( ! array_intersect( $values, array( '', 'a:0:{}' ) ) ) {
                continue;
            }

            $kept_id     = array_search( $this->_helper_functions->get_kept_coupon_meta_value( $values ), array_reverse( $values, true ), true );
            $first_index = min( $indexes );

            if ( false === $kept_id || $indexes[ $kept_id ] === $first_index ) {
                continue;
            }

            $kept_index                = $indexes[ $kept_id ];
            $first_row                 = $meta_data[ $first_index ];
            $meta_data[ $first_index ] = $meta_data[ $kept_index ];
            $meta_data[ $kept_index ]  = $first_row;
        }

        return $meta_data;
    }

    /**
     * Actual function that houses the code to execute on plugin activation.
     *
     * @since 1.0
     * @since 1.7 Refactor support for multisite setup.
     * @access private
     *
     * @global WP_Rewrite $wp_rewrite Core class used to implement a rewrite component API.
     *
     * @param int $blogid Blog ID of the created blog.
     */
    private function _activate_plugin( $blogid ) {
        /**
         * Previously multisite installs site store license options using normal get/add/update_option functions.
         * These stores the option on a per sub-site basis. We need move these options network wide in multisite setup
         * via get/add/update_site_option functions.
         */
        if ( is_multisite() ) {

            $installed_version = get_option( Plugin_Constants::INSTALLED_VERSION );

            if ( $installed_version ) {

                update_site_option( Plugin_Constants::INSTALLED_VERSION, $installed_version );

                delete_option( Plugin_Constants::INSTALLED_VERSION );

            }
        }

        // Initialize settings options.
        $this->_initialize_plugin_settings_options();

        // Normalize the legacy store credits expiry value for the 4.7.5 number-input UI.
        $this->_migrate_store_credit_expiry_option();

        // Reconcile autoload flags for plugin options (handles existing installs).
        $this->_set_plugin_options_autoload();

        // Execute 'activate' contract of models implementing ACFWF\Interfaces\Activatable_Interface.
        foreach ( $this->_activatables as $activatable ) {
            if ( $activatable instanceof Activatable_Interface ) {
                $activatable->activate();
            }
        }

        // Update current installed plugin version.
        if ( is_multisite() ) {
            update_site_option( Plugin_Constants::INSTALLED_VERSION, Plugin_Constants::VERSION );
        } else {
            update_option( Plugin_Constants::INSTALLED_VERSION, Plugin_Constants::VERSION );
        }

        // This is brute force rewriting of rules.
        global $wp_rewrite;
        $wp_rewrite->flush_rules();

        update_option( 'acfwf_activation_code_triggered', 'yes' );

        // Store the current date as the installation date.
        if ( false === get_option( Plugin_Constants::INSTALLATION_DATE ) ) {
            $now = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
            update_option( Plugin_Constants::INSTALLATION_DATE, $now->format( 'Y-m-d H:i:s' ) );
        }
    }

    /**
     * Reconcile the autoload flag on plugin options that should not autoload.
     *
     * The $autoload arg of update_option() is honored only on insert, so existing
     * installs need an explicit migration. Runs on activation and on every version
     * bump (via Bootstrap::initialize() -> activate_plugin()).
     *
     * @since 4.7.3
     * @access private
     *
     * @global wpdb $wpdb
     */
    private function _set_plugin_options_autoload() {
        global $wpdb;

        $option_names = array(
            Plugin_Constants::SHOW_GETTING_STARTED_NOTICE,
            Plugin_Constants::SHOW_UPGRADE_NOTICE,
            Plugin_Constants::SHOW_ALLOW_USAGE_NOTICE,
            Plugin_Constants::SAVETO_NOTICE_SHOW_AFTER,
            Plugin_Constants::SAVETO_NOTICE_DISMISSED,
            Plugin_Constants::CLEAN_UP_PLUGIN_OPTIONS,
            Plugin_Constants::STORE_CREDITS_DB_CREATED,
            Plugin_Constants::USAGE_CRON_CONFIG,
            Plugin_Constants::USAGE_LAST_CHECKIN,
            Plugin_Constants::TOTAL_CREATED_WITH_COUPON_TEMPLATES,
            Plugin_Constants::MOST_POPULAR_TEMPLATES,
            Plugin_Constants::TOTAL_CREATED_WITH_AI_TEMPLATES,
            Plugin_Constants::TOTAL_SAVED_AI_TEMPLATES,
            Plugin_Constants::ALLOW_FETCH_CONTENT_REMOTE,
            Plugin_Constants::DEFAULT_COUPON_CATEGORY,
            Plugin_Constants::STORE_CREDITS_EXPIRY_CHECK_DATE,
            $this->_constants->RECENT_COUPON_TEMPLATES,
            'acfwf_feature_counts_initialized',
            'acfwf_bogo_migrate_coupon_type',
        );

        // Per-term feature counts: enumerate dynamically. Option name format from
        // Feature_Custom_Taxonomy::_get_feature_count_option_key() is
        // "{$prefix}_feature_taxonomy_count_{$slug}", where $prefix defaults to 'acfwf'.
        // Sites/plugins that filter `acfw_feature_count_option_prefix` to a different
        // value must reconcile those rows themselves.
        $feature_count_keys = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'acfwf_feature_taxonomy_count_%'" );
        $option_names       = array_merge( $option_names, (array) $feature_count_keys );

        // WP 6.7+: canonical bulk API; accepts boolean autoload directly.
        if ( function_exists( 'wp_set_options_autoload' ) ) {
            wp_set_options_autoload( $option_names, false );
            return;
        }

        // WP 6.4 - 6.6: pre-`wp_set_options_autoload()` bulk API.
        if ( function_exists( 'wp_set_option_autoload_values' ) ) {
            wp_set_option_autoload_values( array_fill_keys( $option_names, false ) );
            return;
        }

        // WP < 6.4: direct UPDATE with the only autoload value the column accepts pre-6.7.
        foreach ( $option_names as $option_name ) {
            $wpdb->update(
                $wpdb->options,
                array( 'autoload' => 'no' ),
                array( 'option_name' => $option_name ),
                array( '%s' ),
                array( '%s' )
            );
        }
        wp_cache_delete( 'alloptions', 'options' );
    }

    /**
     * Method that houses the logic relating to deactivating the plugin.
     *
     * @since 1.0
     * @access public
     *
     * @global wpdb $wpdb Object that contains a set of functions used to interact with a database.
     *
     * @param boolean $network_wide Flag that determines whether the plugin has been activated network wid ( on multi site environment ) or not.
     */
    public function deactivate_plugin( $network_wide ) {
        global $wpdb;

        // check if it is a multisite network.
        if ( is_multisite() ) {

            // check if the plugin has been activated on the network or on a single site.
            if ( $network_wide ) {

                // get ids of all sites.
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

                foreach ( $blog_ids as $blog_id ) {

                    switch_to_blog( $blog_id );
                    $this->_deactivate_plugin( $wpdb->blogid );

                }

                restore_current_blog();

            } else {
                $this->_deactivate_plugin( $wpdb->blogid );
            }
            // activated on a single site, in a multi-site.

        } else {
            $this->_deactivate_plugin( $wpdb->blogid );
        }
        // activated on a single site.
    }

    /**
     * Actual method that houses the code to execute on plugin deactivation.
     *
     * @since 1.0
     * @access private
     *
     * @param int $blogid Blog ID of the created blog.
     */
    private function _deactivate_plugin( $blogid ) {
        // Execute 'deactivate' contract of models implementing ACFWF\Interfaces\Deactivatable_Interface.
        foreach ( $this->_deactivatables as $deactivatable ) {
            if ( $deactivatable instanceof Deactivatable_Interface ) {
                $deactivatable->deactivate();
            }
        }

        flush_rewrite_rules();
    }

    /**
     * Method that houses codes to be executed on init hook.
     *
     * @since 1.0
     * @access public
     */
    public function initialize() {
        // Execute activation codebase if not yet executed on plugin activation ( Mostly due to plugin dependencies ).
        $installed_version = is_multisite() ? get_site_option( Plugin_Constants::INSTALLED_VERSION, false ) : get_option( Plugin_Constants::INSTALLED_VERSION, false );

        if ( version_compare( $installed_version, Plugin_Constants::VERSION, '!=' ) || get_option( 'acfwf_activation_code_triggered', false ) !== 'yes' ) {

            if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
                require_once ABSPATH . '/wp-admin/includes/plugin.php';
            }

            $network_wide = is_plugin_active_for_network( 'advanced-coupons-for-woocommerce/advanced-coupons-for-woocommerce.php' );
            $this->activate_plugin( $network_wide );

        }

        // Collapse duplicate advanced coupon meta rows left by the bug fixed in 4.7.7. Checked on every site, outside
        // the version gate, because on multisite the version gate only fires on the first site that loads. It runs
        // before the initializables, so the upgrade request cleans the rows before the coupons are read.
        $this->_maybe_remove_duplicate_advanced_coupon_meta();

        // Execute 'initialize' contract of models implementing ACFWF\Interfaces\Initializable_Interface.
        foreach ( $this->_initializables as $initializable ) {
            if ( $initializable instanceof Initializable_Interface ) {
                $initializable->initialize();
            }
        }
    }

    /**
     * Maybe add support for the HTML5 script to the theme so it will allow the `type="module"` attribute in the script tag.
     *
     * @since 4.5.9.2
     * @access public
     */
    public function maybe_add_html5_script_support() {
        if ( current_theme_supports( 'html5', 'script' ) ) {
            return;
        }

        add_theme_support( 'html5', array( 'script' ) );
    }

    /**
     * Add settings link to plugin actions links.
     *
     * @since 1.0
     * @access public
     *
     * @param array $links Plugin action links.
     * @return array Filtered plugin action links
     */
    public function plugin_settings_action_link( $links ) {
        $href = admin_url( 'admin.php?page=acfw-settings' );

        if ( $this->_helper_functions->is_plugin_active( Plugin_Constants::PREMIUM_PLUGIN ) && version_compare( ACFWP()->Plugin_Constants->VERSION, '2.2', '<' ) ) {
            $href = admin_url( 'admin.php?page=wc-settings&tab=acfw_settings' );
        }

        $settings_link = '<a href="' . $href . '">' . __( 'Settings', 'advanced-coupons-for-woocommerce-free' ) . '</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Declare high performance order storage compatibility.
     *
     * @since 4.5.6
     * @access public
     */
    public function declare_woocommerce_features_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            // Declare that the plugin is compatible with hpos feature.
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                $this->_constants->MAIN_PLUGIN_FILE_PATH,
                true
            );

            // Declare that the plugin is compatible with the cart and checkout blocks feature.
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $this->_constants->MAIN_PLUGIN_FILE_PATH, true );

            // Declare that the plugin is compatible with the product instance caching feature.
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_instance_caching', $this->_constants->MAIN_PLUGIN_FILE_PATH, true );
        }
    }

    /**
     * Filter to force enable WooCommerce coupons.
     *
     * @since 4.6.1
     * @access public
     *
     * @return string
     */
    public function woocommerce_enable_coupons_pre_filter_option() {
        return 'yes';
    }

    /**
     * Execute plugin bootstrap code.
     *
     * @inherit ACFWF\Interfaces\Model_Interface
     *
     * @since 1.0
     * @access public
     */
    public function run() {
        // Internationalization.
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        // Execute plugin activation/deactivation.
        register_activation_hook( $this->_constants->MAIN_PLUGIN_FILE_PATH, array( $this, 'activate_plugin' ) );
        register_deactivation_hook( $this->_constants->MAIN_PLUGIN_FILE_PATH, array( $this, 'deactivate_plugin' ) );

        // Execute plugin initialization ( plugin activation ) on every newly created site in a multi site set up.
        add_action( 'wpmu_new_blog', array( $this, 'new_mu_site_init' ), 10, 6 );

        // Execute codes that need to run on 'init' hook.
        add_action( 'init', array( $this, 'initialize' ) );

        add_action( 'after_setup_theme', array( $this, 'maybe_add_html5_script_support' ) );

        // Add settings link to plugin action links.
        add_filter( 'plugin_action_links_' . $this->_constants->PLUGIN_BASENAME, array( $this, 'plugin_settings_action_link' ), 10 );

        // Declare HPOS compatibility with WooCommerce.
        add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_features_compatibility' ) );

        // Add filter to force enable WooCommerce coupons.
        add_filter( 'pre_option_woocommerce_enable_coupons', array( $this, 'woocommerce_enable_coupons_pre_filter_option' ) );

        // Until the duplicate coupon meta cleanup ends, make WC_Coupon::get_meta() read the row that the cleanup keeps.
        if ( 'yes' !== get_option( self::COUPON_META_DEDUPE_OPTION ) ) {
            add_filter( 'woocommerce_data_store_wp_post_read_meta', array( $this, 'put_kept_coupon_meta_rows_first' ), 10, 2 );
        }
    }
}
