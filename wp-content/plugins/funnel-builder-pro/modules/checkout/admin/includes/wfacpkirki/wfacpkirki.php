<?php
/**
 * Plugin Name:   WFACPKirki Toolkit
 * Plugin URI:    http://aristath.github.io/wfacpkirki
 * Description:   The ultimate WordPress Customizer Toolkit
 * Author:        Aristeides Stathopoulos
 * Author URI:    http://aristath.github.io
 * Version:       3.0.33
 * Text Domain:   wfacpkirki
 *
 * GitHub Plugin URI: aristath/wfacpkirki
 * GitHub Plugin URI: https://github.com/aristath/wfacpkirki
 *
 * @package     WFACPKirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// No need to proceed if WFACPKirki already exists.
if ( class_exists( 'WFACPKirki' ) ) {
	return;
}

// Include the autoloader.
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'class-kirki-autoload.php';
new WFACPKirki_Autoload();

if ( ! defined( 'WFACP_KIRKI_PLUGIN_FILE' ) ) {
	define( 'WFACP_KIRKI_PLUGIN_FILE', __FILE__ );
}

// Define the WFACP_KIRKI_VERSION constant.
if ( ! defined( 'WFACP_KIRKI_VERSION' ) ) {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$data    = get_plugin_data( WFACP_KIRKI_PLUGIN_FILE );
	$version = ( isset( $data['Version'] ) ) ? $data['Version'] : false;
	define( 'WFACP_KIRKI_VERSION', $version );
}

// Make sure the path is properly set.
WFACPKirki::$path = wp_normalize_path( dirname( __FILE__ ) );
WFACPKirki_Init::set_url();

new WFACPKirki_Controls();

if ( ! function_exists( 'wfacpkirki' ) ) {
	/**
	 * Returns an instance of the WFACPKirki object.
	 */
	function wfacpkirki() {
		return WFACPKirki_Toolkit::get_instance();
	}
}

// Start WFACPKirki.
global $wfacpkirki;
$wfacpkirki = wfacpkirki();

// Instantiate the modules.
$wfacpkirki->modules = new WFACPKirki_Modules();

WFACPKirki::$url = plugins_url( '', __FILE__ );

// Instantiate classes.
new WFACPKirki();
new WFACPKirki_L10n();

// Include deprecated functions & methods.
require_once wp_normalize_path( dirname( __FILE__ ) . '/deprecated/deprecated.php' );

// Include the ariColor library.
require_once wp_normalize_path( dirname( __FILE__ ) . '/lib/class-aricolor.php' );

// Add an empty config for global fields.
WFACPKirki::add_config( '' );

$custom_config_path = dirname( __FILE__ ) . '/custom-config.php';
$custom_config_path = wp_normalize_path( $custom_config_path );
if ( file_exists( $custom_config_path ) ) {
	require_once $custom_config_path;
}

// Add upgrade notifications.
require_once wp_normalize_path( dirname( __FILE__ ) . '/upgrade-notifications.php' );

/**
 * To enable tests, add this line to your wp-config.php file (or anywhere alse):
 * define( 'KIRKI_TEST', true );
 *
 * Please note that the example.php file is not included in the wordpress.org distribution
 * and will only be included in dev versions of the plugin in the github repository.
 */
if ( defined( 'KIRKI_TEST' ) && true === KIRKI_TEST && file_exists( dirname( __FILE__ ) . '/example.php' ) ) {
	include_once dirname( __FILE__ ) . '/example.php';
}
