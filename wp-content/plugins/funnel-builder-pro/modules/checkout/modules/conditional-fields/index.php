<?php
/**
 * Conditional Fields Module Bootstrap
 *
 * Loaded by WFACP_Core::load_modules().
 * No plugin activation code - module runs within checkout.
 *
 * @package FunnelKit\Checkout\Modules\Conditional_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants for module. Use __DIR__ so we always load from this module path
// (avoids missing-slash bug: WFACP_PLUGIN_DIR . 'modules/...' → .../checkoutmodules/...).
if ( ! defined( 'FKCF_PLUGIN_DIR' ) ) {
	define( 'FKCF_PLUGIN_DIR', __DIR__ . '/' );
}
if ( ! defined( 'FKCF_PLUGIN_URL' ) ) {
	define( 'FKCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'FKCF_VERSION' ) ) {
	define( 'FKCF_VERSION', '2.3.1' );
}

require_once FKCF_PLUGIN_DIR . 'class-main.php';

\FunnelKit\Checkout\Modules\Conditional_Fields\Main::get_instance();
