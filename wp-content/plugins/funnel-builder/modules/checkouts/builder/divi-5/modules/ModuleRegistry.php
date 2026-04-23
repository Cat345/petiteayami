<?php
/**
 * Module Registry for WFACP Divi 5 Modules.
 *
 * This class provides a centralized way to register and manage all WFACP modules.
 * It automatically discovers and loads modules based on configuration.
 *
 * @package WFACP\Modules
 * @since 1.0.0
 */

namespace WFACP\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

/**
 * Module Registry Class.
 *
 * Manages registration of all WFACP Divi 5 modules.
 *
 * @since 1.0.0
 */
class ModuleRegistry {

	/**
	 * Module configuration array.
	 *
	 * Format:
	 * [
	 *   'module_name' => [
	 *     'namespace' => 'WFACP\Modules\ModuleName',
	 *     'class'     => 'ModuleName',
	 *     'dir'       => 'ModuleName', // Relative to modules folder
	 *   ],
	 * ]
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $modules = array(
		'CheckoutForm' => array(
			'namespace' => 'WFACP\Modules\CheckoutForm',
			'class'     => 'CheckoutForm',
			'dir'       => 'CheckoutForm',
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
		if ( ! interface_exists( 'ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
			return;
		}

		// Only register on checkout post type pages.
		$post_type = self::get_current_post_type();
		if ( $post_type && 'wfacp_checkout' !== $post_type ) {
			return;
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
					if ( $dependency_tree && method_exists( $dependency_tree, 'add_dependency' ) ) {
						$dependency_tree->add_dependency( $module_instance );
					}

					// Call load() to register the module with ModuleRegistration
					if ( method_exists( $module_instance, 'load' ) ) {
						$module_instance->load();
					}
				} catch ( \Exception $e ) {
					// Silent fail - module registration error
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

	/**
	 * Detect the post type of the page being edited.
	 *
	 * Returns empty string if post type cannot be determined (modules
	 * should still register in that case to avoid false negatives).
	 *
	 * @since 1.0.0
	 * @return string Post type slug or empty string.
	 */
	private static function get_current_post_type(): string {
		global $post;

		if ( $post instanceof \WP_Post ) {
			return $post->post_type;
		}

		foreach ( array( 'post', 'postId', 'post_id', 'et_post_id' ) as $param ) {
			if ( ! empty( $_REQUEST[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				$pid = absint( wp_unslash( $_REQUEST[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				if ( $pid ) {
					$type = get_post_type( $pid );
					if ( $type ) {
						return $type;
					}
				}
			}
		}

		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			if ( preg_match( '/[?&](?:post|post_id|et_post_id|p)=(\d+)/', $referer, $m ) ) {
				$type = get_post_type( absint( $m[1] ) );
				if ( $type ) {
					return $type;
				}
			}

			if ( function_exists( 'url_to_postid' ) ) {
				$base_url = strtok( $referer, '?' );
				if ( $base_url ) {
					$resolved = url_to_postid( $base_url );
					if ( $resolved ) {
						$type = get_post_type( $resolved );
						if ( $type ) {
							return $type;
						}
					}
				}
			}
		}

		return '';
	}
}
