<?php
/**
 * Plugin Name:   WFOCUKirki Toolkit
 * Plugin URI:    http://aristath.github.io/kirki
 * Description:   The ultimate WordPress Customizer Toolkit
 * Author:        Aristeides Stathopoulos
 * Author URI:    http://aristath.github.io
 * Version:       3.0.33
 * Text Domain:   wfocukirki
 *
 * GitHub Plugin URI: aristath/kirki
 * GitHub Plugin URI: https://github.com/aristath/kirki
 *
 * @package     WFOCUKirki
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

// No need to proceed if WFOCUKirki already exists.
if ( class_exists( 'WFOCUKirki' ) ) {
	return;
}

// Include the autoloader.
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'class-wfocukirki-autoload.php';
new WFOCUKirki_Autoload();

if ( ! defined( 'WFOCU_KIRKI_PLUGIN_FILE' ) ) {
	define( 'WFOCU_KIRKI_PLUGIN_FILE', __FILE__ );
}

// Define the WFOCU_KIRKI_VERSION constant.
if ( ! defined( 'WFOCU_KIRKI_VERSION' ) ) {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$data    = get_plugin_data( WFOCU_KIRKI_PLUGIN_FILE );
	$version = ( isset( $data['Version'] ) ) ? $data['Version'] : false;
	define( 'WFOCU_KIRKI_VERSION', $version );
}

// Make sure the path is properly set.
WFOCUKirki::$path = wp_normalize_path( dirname( __FILE__ ) );
WFOCUKirki_Init::set_url();

new WFOCUKirki_Controls();

if ( ! function_exists( 'WFOCUKirki' ) ) {
	/**
	 * Returns an instance of the WFOCUKirki object.
	 */
	function wfocukirki() {
		$wfocukirki = WFOCUKirki_Toolkit::get_instance();
		return $wfocukirki;
	}
}

// Start WFOCUKirki.
global $wfocukirki;
$wfocukirki = wfocukirki();

// Instantiate the modules.
$wfocukirki->modules = new WFOCUKirki_Modules();

WFOCUKirki::$url = plugins_url( '', __FILE__ );

// Instantiate classes.
new WFOCUKirki();
new WFOCUKirki_L10n();

// Include deprecated functions & methods.
require_once wp_normalize_path( dirname( __FILE__ ) . '/deprecated/deprecated.php' );

// Include the ariColor library.
require_once wp_normalize_path( dirname( __FILE__ ) . '/lib/class-aricolor.php' );

// Add an empty config for global fields.
WFOCUKirki::add_config( '' );

$custom_config_path = dirname( __FILE__ ) . '/custom-config.php';
$custom_config_path = wp_normalize_path( $custom_config_path );
if ( file_exists( $custom_config_path ) ) {
	require_once $custom_config_path;
}

// Add upgrade notifications.
require_once wp_normalize_path( dirname( __FILE__ ) . '/upgrade-notifications.php' );

/**
 * To enable tests, add this line to your wp-config.php file (or anywhere alse):
 * define( 'WFOCU_KIRKI_TEST', true );
 *
 * Please note that the example.php file is not included in the wordpress.org distribution
 * and will only be included in dev versions of the plugin in the github repository.
 */
if ( defined( 'WFOCU_KIRKI_TEST' ) && true === WFOCU_KIRKI_TEST && file_exists( dirname( __FILE__ ) . '/example.php' ) ) {
	include_once dirname( __FILE__ ) . '/example.php';
}
