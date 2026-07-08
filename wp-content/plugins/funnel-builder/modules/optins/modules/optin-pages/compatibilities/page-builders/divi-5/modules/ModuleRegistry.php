<?php
/**
 * Module Registry for WFOP Divi 5 Modules.
 *
 * This class provides a centralized way to register and manage all WFOP modules.
 * It automatically discovers and loads modules based on configuration.
 *
 * @package WFOP\Modules
 * @since 1.0.0
 */

namespace WFOP\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module Registry Class.
 *
 * Manages registration of all WFOP Divi 5 modules.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class ModuleRegistry {

	/**
	 * Module configuration array.
	 *
	 * Format:
	 * [
	 *   'module_name' => [
	 *     'namespace' => 'WFOP\Modules\ModuleName',
	 *     'class'     => 'ModuleName',
	 *     'dir'       => 'ModuleName', // Relative to modules folder
	 *   ],
	 * ]
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $modules = array(
		'OptinForm' => array(
			'namespace' => 'WFOP\Modules\OptinForm',
			'class'     => 'OptinForm',
			'dir'       => 'OptinForm',
		),
	);

	/**
	 * Register all modules with dependency tree.
	 *
	 * @since 1.0.0
	 * @param object $dependency_tree Dependency tree instance.
	 * @return void
	 */
	public static function register_modules( $dependency_tree ): void {
		// CRITICAL: Check if Divi classes are available before loading module files
		// Match upsell pattern - check for DependencyInterface
		if ( ! interface_exists( 'ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
			// Still try to load modules and call load() - registration happens via init hook in BaseModule::load()
			// This ensures modules are registered even if dependency tree hook doesn't fire
		}

		$modules_dir = plugin_dir_path( __FILE__ );

		// Load and register each module
		foreach ( self::$modules as $module_name => $module_config ) {
			$module_file = $modules_dir . $module_config['dir'] . '/' . $module_config['class'] . '.php';

			// Load module file
			if ( file_exists( $module_file ) ) {
				require_once $module_file;
			} else {
				continue;
			}

			// Register module with dependency tree
			$module_class = $module_config['namespace'] . '\\' . $module_config['class'];

			if ( class_exists( $module_class ) ) {
				try {
					$module_instance = new $module_class();

					// Only add to dependency tree if DependencyInterface exists (match upsell pattern)
					if ( interface_exists( 'ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
						if ( $dependency_tree && method_exists( $dependency_tree, 'add_dependency' ) ) {
							$dependency_tree->add_dependency( $module_instance );
						}
					}

					// CRITICAL: Always call load() - this registers the module with ModuleRegistration via init hook
					// This works independently of the dependency tree hook
					if ( method_exists( $module_instance, 'load' ) ) {
						$module_instance->load();
					}
				} catch ( \Exception $e ) {
					// Silently fail - error handling is done by Divi framework
				}
			}
		}
	}

	/**
	 * Get all registered modules.
	 *
	 * @since 1.0.0
	 * @return array Module configuration array.
	 */
	public static function get_modules(): array {
		return self::$modules;
	}

	/**
	 * Add a module to the registry.
	 *
	 * @since 1.0.0
	 * @param string $module_name Module name (key).
	 * @param array  $config      Module configuration.
	 * @return void
	 */
	public static function add_module( string $module_name, array $config ): void {
		self::$modules[ $module_name ] = $config;
	}
}
