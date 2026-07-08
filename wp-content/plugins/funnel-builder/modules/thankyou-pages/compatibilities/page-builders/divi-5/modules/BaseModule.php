<?php
/**
 * Base Module Class for WFTY Divi 5 Modules.
 *
 * @package WFTY\Modules
 * @since 1.0.0
 */

namespace WFTY\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;

/**
 * Base Module Abstract Class.
 *
 * All Thank You Page Divi 5 modules extend this class.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
abstract class BaseModule implements DependencyInterface {

	/** @var array */
	private static $registered_modules = array();

	/** @var array */
	private static $init_actions_added = array();

	abstract protected function get_module_name(): string;
	abstract protected function get_module_namespace(): string;
	abstract protected function get_module_dir(): string;

	protected function get_trait_files(): array {
		return array(
			'RenderCallbackTrait.php',
			'ModuleClassnamesTrait.php',
			'ModuleStylesTrait.php',
			'ModuleScriptDataTrait.php',
		);
	}

	protected function get_render_callback_method(): string {
		return 'render_callback';
	}

	public function getDependencies(): array {
		return array();
	}

	protected function load_traits(): void {
		$modules_dir = __DIR__;
		$module_dir  = $modules_dir . '/' . $this->get_module_dir();
		$trait_dir   = $module_dir . '/' . $this->get_module_name() . 'Trait/';
		$traits      = $this->get_trait_files();
		foreach ( $traits as $trait_file ) {
			$trait_path = $trait_dir . $trait_file;
			if ( file_exists( $trait_path ) ) {
				require_once $trait_path;
			}
		}
	}

	public function load(): void {
		$modules_dir             = __DIR__;
		$module_dir              = $modules_dir . '/' . $this->get_module_dir();
		$module_json_folder_path = $module_dir . '/module-json';
		if ( ! is_dir( $module_json_folder_path ) || ! file_exists( $module_json_folder_path . '/module.json' ) ) {
			return;
		}
		$module_identifier = $this->get_module_namespace() . '\\' . $this->get_module_name();
		if ( isset( self::$init_actions_added[ $module_identifier ] ) ) {
			return;
		}
		self::$init_actions_added[ $module_identifier ] = true;
		$registration_function                          = function () use ( $module_json_folder_path, $module_identifier ) {
			if ( isset( self::$registered_modules[ $module_identifier ] ) ) {
				return;
			}
			if ( ! class_exists( 'ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
				return;
			}
			$render_callback_class  = $this->get_module_namespace() . '\\' . $this->get_module_name();
			$render_callback_method = $this->get_render_callback_method();
			try {
				ModuleRegistration::register_module(
					$module_json_folder_path,
					array(
						'render_callback' => array( $render_callback_class, $render_callback_method ),
					)
				);
				self::$registered_modules[ $module_identifier ] = true;
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FunnelKit Divi5: Module registration failed for ' . $module_identifier . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		};
		$current_hook                                   = current_action();
		if ( did_action( 'init' ) || $current_hook === 'init' ) {
			if ( $current_hook === 'init' ) {
				$registration_function();
			} else {
				add_action( 'wp_loaded', $registration_function, 10 );
			}
		} else {
			add_action( 'init', $registration_function, 10 );
		}
	}
}
