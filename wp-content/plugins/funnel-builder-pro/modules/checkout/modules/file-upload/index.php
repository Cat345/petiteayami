<?php
/**
 * File Upload Module Bootstrap
 *
 * Loaded by WFACP_Core::load_modules().
 * No plugin activation code - module runs within checkout.
 *
 * @package FunnelKit\Checkout\Modules\File_Upload
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants for module. Use __DIR__ so we always load from this module path.
if ( ! defined( 'FKFU_PLUGIN_DIR' ) ) {
	define( 'FKFU_PLUGIN_DIR', __DIR__ . '/' );
}
if ( ! defined( 'FKFU_PLUGIN_URL' ) ) {
	define( 'FKFU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'FKFU_VERSION' ) ) {
	define( 'FKFU_VERSION', '1.0.0' );
}

require_once FKFU_PLUGIN_DIR . 'class-main.php';

\FunnelKit\Checkout\Modules\File_Upload\Main::get_instance();
