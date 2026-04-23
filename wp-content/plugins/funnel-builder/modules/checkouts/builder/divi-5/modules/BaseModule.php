<?php
/**
 * Base Module Class for WFACP Divi 5 Modules.
 *
 * This abstract class provides a reusable pattern for all WFACP modules,
 * reducing code duplication and making it easy to add new modules.
 *
 * @package WFACP\Modules
 * @since 1.0.0
 */



namespace WFACP\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

/**
 * Base Module Abstract Class.
 *
 * All WFACP Divi 5 modules should extend this class.
 * It provides common functionality for module registration and trait loading.
 *
 * @since 1.0.0
 */
abstract class BaseModule implements DependencyInterface {

	/**
	 * Track which modules have been registered to prevent duplicates.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $registered_modules = array();

	/**
	 * Module name (e.g., 'CheckoutForm', 'MiniCart').
	 *
	 * @since 1.0.0
	 * @var string
	 */
	abstract protected function get_module_name(): string;

	/**
	 * Module namespace (e.g., 'WFACP\Modules\MiniCart').
	 *
	 * @since 1.0.0
	 * @var string
	 */
	abstract protected function get_module_namespace(): string;

	/**
	 * Module directory path relative to modules folder.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	abstract protected function get_module_dir(): string;

	/**
	 * List of trait files to load (relative to module directory).
	 *
	 * @since 1.0.0
	 * @return array Array of trait file names.
	 */
	protected function get_trait_files(): array {
		return array(
			'CustomCssTrait.php',
			'RenderCallbackTrait.php',
			'ModuleClassnamesTrait.php',
			'ModuleStylesTrait.php',
			'ModuleScriptDataTrait.php',
		);
	}

	/**
	 * Get the render callback method name.
	 *
	 * @since 1.0.0
	 * @return string Method name for render callback.
	 */
	protected function get_render_callback_method(): string {
		return 'render_callback';
	}

	/**
	 * Get module dependencies (required by DependencyInterface).
	 *
	 * @since 1.0.0
	 * @return array Empty array - modules don't have external dependencies.
	 */
	public function getDependencies(): array {
		return array();
	}

	/**
	 * Load module traits.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function load_traits(): void {
		$modules_dir = __DIR__;
		$module_dir  = $modules_dir . '/' . $this->get_module_dir();
		$trait_dir   = $module_dir . '/' . $this->get_module_name() . 'Trait/';

		$traits = $this->get_trait_files();

		foreach ( $traits as $trait_file ) {
			$trait_path = $trait_dir . $trait_file;
			if ( file_exists( $trait_path ) ) {
				require_once $trait_path;
			}
		}
	}

	/**
	 * Loads module and registers Front-End render callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load(): void {
		$modules_dir             = __DIR__;
		$module_dir              = $modules_dir . '/' . $this->get_module_dir();
		$module_json_folder_path = $module_dir . '/module-json';

		// Validate module JSON folder exists
		if ( ! is_dir( $module_json_folder_path ) ) {
			return;
		}

		// Validate module.json file exists
		$module_json_file = $module_json_folder_path . '/module.json';
		if ( ! file_exists( $module_json_file ) ) {
			return;
		}

		// Get module identifier to prevent duplicate registration
		$module_identifier = $this->get_module_namespace() . '\\' . $this->get_module_name();

		// Check if module is already registered to prevent duplicate registration
		if ( isset( self::$registered_modules[ $module_identifier ] ) ) {
			return;
		}

		// Validate ModuleRegistration class exists
		if ( ! class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			return;
		}

		// Get render callback class and method
		$render_callback_class  = $this->get_module_namespace() . '\\' . $this->get_module_name();
		$render_callback_method = $this->get_render_callback_method();

		// Verify render callback method exists
		if ( ! method_exists( $render_callback_class, $render_callback_method ) ) {
			return;
		}

		// Register the module directly (same pattern as native Divi 5 modules).
		// Native modules call ModuleRegistration::register_module() directly in load(),
		// without wrapping in add_action('init'). This is critical because the BlockParser
		// calls load() during template rendering (after init has already fired), so
		// deferring to init would cause the render_callback to never be registered.
		try {
			ModuleRegistration::register_module(
				$module_json_folder_path,
				array(
					'render_callback' => array( $render_callback_class, $render_callback_method ),
				)
			);
			// Mark as successfully registered
			self::$registered_modules[ $module_identifier ] = true;
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FunnelKit Divi5: Module registration failed for ' . $module_identifier . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
}
