<?php
/**
 * Bundles: product bundles for WooCommerce, loaded by its host plugin as a module.
 *
 * Carries no plugin header on purpose: WordPress must not list or activate it on its own.
 *
 * @package WFFN_Bundles
 */

defined( 'ABSPATH' ) || exit;

// Booted only by its host plugin, which defines WFFN_PLUGIN_FILE first; required any other way, it stays off.
if ( ! defined( 'WFFN_PLUGIN_FILE' ) ) {
	return;
}

define( 'WFFN_BUNDLES_FILE', __FILE__ );
define( 'WFFN_BUNDLES_DIR', __DIR__ );
define( 'WFFN_BUNDLES_URL', plugins_url( '/', __FILE__ ) );
define( 'WFFN_BUNDLES_VERSION', '1.0.0' );

/**
 * Schema version, decoupled from the plugin version.
 *
 * Install::maybe_upgrade() gates table creation on this constant. Bump it on
 * any DDL change in includes/storage/*-table.php.
 */
define( 'WFFN_BUNDLES_DB_VERSION', '9' );
define( 'WFFN_BUNDLES_FULL_NAME', 'Bundles For WooCommerce' );
define( 'WFFN_BUNDLES_ADMIN_NAMESPACE', 'wffn-bundles-admin' );
define( 'WFFN_BUNDLES_API_VERSION', 'v1' );

require_once WFFN_BUNDLES_DIR . '/plugin.php';
WFFN\Bundles\Plugin::get_instance();

if ( ! WFFN\Bundles\Plugin::is_woocommerce_active() ) {
	return;
}

// A module never gets activation hooks of its own, so its lifecycle follows the host plugin's.
register_activation_hook( WFFN_PLUGIN_FILE, array( 'WFFN\Bundles\Includes\Storage\Install', 'activate' ) );
register_deactivation_hook( WFFN_PLUGIN_FILE, array( 'WFFN\Bundles\Includes\Storage\Install', 'deactivate' ) );

// Admin app (non-namespaced class, loaded explicitly): in every state, as its build decides where the Marketing entry opens.
if ( is_admin() ) {
	require_once WFFN_BUNDLES_DIR . '/admin/app.php';
}
