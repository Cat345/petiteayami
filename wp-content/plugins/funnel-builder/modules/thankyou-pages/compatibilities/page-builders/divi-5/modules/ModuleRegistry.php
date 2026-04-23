<?php
/**
 * Module Registry for Thank You Page Divi 5 Modules.
 *
 * @package WFTY\Modules
 * @since 1.0.0
 */

namespace WFTY\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

class ModuleRegistry {

	/** @var array */
	private static $modules = array(
		'CustomerDetails' => array(
			'namespace' => 'WFTY\Modules\CustomerDetails',
			'class'     => 'CustomerDetails',
			'dir'       => 'CustomerDetails',
		),
		'OrderDetails'    => array(
			'namespace' => 'WFTY\Modules\OrderDetails',
			'class'     => 'OrderDetails',
			'dir'       => 'OrderDetails',
		),
	);

	/**
	 * Register all modules with dependency tree.
	 *
	 * @param object|null $dependency_tree Dependency tree instance.
	 * @return void
	 */
	public static function register_modules( $dependency_tree ): void {
		$modules_dir = plugin_dir_path( __FILE__ );
		foreach ( self::$modules as $module_name => $module_config ) {
			$module_file = $modules_dir . $module_config['dir'] . '/' . $module_config['class'] . '.php';
			if ( ! file_exists( $module_file ) ) {
				continue;
			}
			require_once $module_file;
			$module_class = $module_config['namespace'] . '\\' . $module_config['class'];
			if ( ! class_exists( $module_class ) ) {
				continue;
			}
			try {
				$module_instance = new $module_class();
				if ( interface_exists( 'ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
					if ( $dependency_tree && method_exists( $dependency_tree, 'add_dependency' ) ) {
						$dependency_tree->add_dependency( $module_instance );
					}
				}
				if ( method_exists( $module_instance, 'load' ) ) {
					$module_instance->load();
				}
			} catch ( \Exception $e ) {
				// Silently fail
			}
		}
	}

	/** @return array */
	public static function get_modules(): array {
		return self::$modules;
	}

	/**
	 * @param string $module_name
	 * @param array  $config
	 * @return void
	 */
	public static function add_module( string $module_name, array $config ): void {
		self::$modules[ $module_name ] = $config;
	}
}
