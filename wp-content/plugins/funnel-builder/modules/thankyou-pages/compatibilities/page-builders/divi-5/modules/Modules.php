<?php
/**
 * Register all Thank You Page Divi 5 modules with dependency tree.
 *
 * @package WFTY\Modules
 * @since 1.0.0
 */

namespace WFTY\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

require_once plugin_dir_path( __FILE__ ) . 'ModuleRegistry.php';

add_action(
	'divi_module_library_modules_dependency_tree',
	function ( $dependency_tree ) {
		ModuleRegistry::register_modules( $dependency_tree );
	}
);

add_action(
	'init',
	function () {
		static $modules_loaded = false;
		if ( $modules_loaded ) {
			return;
		}
		if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
			return;
		}
		ModuleRegistry::register_modules( null );
		$modules_loaded = true;
	},
	20
);
