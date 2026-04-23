<?php
/**
 * Register all modules with dependency tree.
 *
 * This file uses ModuleRegistry to automatically register all modules.
 * To add a new module, simply add it to ModuleRegistry::$modules array.
 *
 * @package WFOP\Modules
 * @since 1.0.0
 */

namespace WFOP\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// Load ModuleRegistry
require_once plugin_dir_path( __FILE__ ) . 'ModuleRegistry.php';

// Register modules via dependency tree hook (if it fires)
add_action(
	'divi_module_library_modules_dependency_tree',
	function ( $dependency_tree ) {
		ModuleRegistry::register_modules( $dependency_tree );
	}
);

// CRITICAL: Also ensure modules are loaded even if dependency tree hook doesn't fire
// This ensures modules are registered via BaseModule::load() -> init hook -> ModuleRegistration
add_action(
	'init',
	function () {
		// Only load if dependency tree hook hasn't fired yet
		// Check if any modules are already registered
		static $modules_loaded = false;
		if ( $modules_loaded ) {
			return;
		}

		// Check if we're in a context where modules should be registered
		if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
			return;
		}

		// Call register_modules with null dependency tree - modules will still register via load()
		ModuleRegistry::register_modules( null );
		$modules_loaded = true;
	},
	20 // Higher priority to run after dependency tree hook if it fires
);
