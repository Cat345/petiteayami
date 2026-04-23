<?php
/**
 * Register all modules with dependency tree.
 *
 * This file uses ModuleRegistry to automatically register all modules.
 * To add a new module, simply add it to ModuleRegistry::$modules array.
 *
 * @package WFACP\Modules
 * @since 1.0.0
 */

namespace WFACP\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// CRITICAL: Register filter hook OUTSIDE the constant check to ensure it's always registered
// This filter must be registered as early as possible, even if the file is loaded multiple times
if ( ! has_filter( 'wfacp_wc_photoswipe_enable', __NAMESPACE__ . '\\disable_photoswipe_in_divi5_vb' ) ) {
	add_filter(
		'wfacp_wc_photoswipe_enable',
		__NAMESPACE__ . '\\disable_photoswipe_in_divi5_vb',
		999, // High priority to ensure it runs after other filters and can override them
		1
	);
}

// Register custom value expansion functions for D4→D5 conversion.
add_filter(
	'divi.moduleLibrary.conversion.valueExpansionFunctionMap',
	function ( $map ) {
		$map['convertBorderWidth'] = __NAMESPACE__ . '\\convert_border_width';
		return $map;
	}
);

// Prevent multiple registrations using a constant
if ( ! defined( 'WFACP_DIVI5_MODULES_LOADED' ) ) {
	// CRITICAL: Define constant AFTER hook registration to ensure hook registration code runs
	// The constant prevents multiple loads, but we need the hook registration to happen first

	// Load ModuleRegistry
	$module_registry_path = plugin_dir_path( __FILE__ ) . 'ModuleRegistry.php';

	if ( file_exists( $module_registry_path ) ) {
		require_once $module_registry_path;
	}

	// CRITICAL: Hook into Divi 5 module registration
	// This hook fires when Divi 5 builds its module dependency tree
	// Use a closure to ensure it only registers once
	add_action(
		'divi_module_library_modules_dependency_tree',
		function ( $dependency_tree ) {
			if ( ! is_object( $dependency_tree ) ) {
				return;
			}

			// Register all WFACP modules
			ModuleRegistry::register_modules( $dependency_tree );
		},
		10,
		1
	);

	// CRITICAL: Register REST API routes independently of the dependency tree.
	// The dependency tree hook only fires during Divi page rendering, but REST
	// requests to /wp-json/wfacp/v1/* need routes registered via rest_api_init.
	// Loading the module files directly ensures the traits are available.
	add_action(
		'rest_api_init',
		function () {
			if ( ! interface_exists( 'ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
				return;
			}

			$modules_dir = plugin_dir_path( __FILE__ );

			// CheckoutForm REST routes.
			$cf_file = $modules_dir . 'CheckoutForm/CheckoutForm.php';
			if ( file_exists( $cf_file ) ) {
				require_once $cf_file;
				if ( class_exists( 'WFACP\\Modules\\CheckoutForm\\CheckoutForm' ) ) {
					\WFACP\Modules\CheckoutForm\CheckoutForm::register_rest_routes_direct();
				}
			}
		}
	);

	// Fallback: when this file is loaded after init:0 (where the dependency tree hook fires),
	// the above action is too late. Register modules via load() -> register_module() instead.
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

	// CRITICAL: Define constant AFTER hook registration to prevent multiple loads
	// but ensure hook registration happens first
	define( 'WFACP_DIVI5_MODULES_LOADED', true );
}

/**
 * Disable photoswipe in Divi 5 Visual Builder editor mode.
 *
 * This filter prevents photoswipe scripts/styles from loading in the Visual Builder
 * to avoid conflicts and improve editor performance. Only applies when Divi 5 is enabled
 * and Visual Builder is active.
 *
 * @since 1.0.0
 * @param bool $enable Whether photoswipe should be enabled.
 * @return bool False if Visual Builder is active in Divi 5, otherwise returns original value.
 */
function disable_photoswipe_in_divi5_vb( $enable ) {
	// Check if Visual Builder is enabled using multiple detection methods
	$is_vb = false;

	// Method 1: Check function (most reliable for direct page loads)
	if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
		$is_vb = true;
	}
	// Method 2: Check constant
	elseif ( defined( 'ET_FB_ENABLED' ) && ET_FB_ENABLED ) {
		$is_vb = true;
	}
	// Method 3: Check request parameters (GET/POST) for et_fb or et_wfacp_id
	elseif ( ( isset( $_REQUEST['et_fb'] ) && sanitize_text_field( wp_unslash( $_REQUEST['et_fb'] ) ) === '1' ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
			( isset( $_REQUEST['et_wfacp_id'] ) && ! empty( sanitize_text_field( wp_unslash( $_REQUEST['et_wfacp_id'] ) ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
		$is_vb = true;
	}
	// Method 4: Check HTTP referrer for Visual Builder parameters (for REST/AJAX requests)
	elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$referer        = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		$parsed_referer = wp_parse_url( $referer );
		if ( ! empty( $parsed_referer['query'] ) ) {
			parse_str( $parsed_referer['query'], $referer_params );
			if ( ( isset( $referer_params['et_fb'] ) && $referer_params['et_fb'] === '1' ) ||
				( isset( $referer_params['et_wfacp_id'] ) && ! empty( $referer_params['et_wfacp_id'] ) ) ) {
				$is_vb = true;
			}
		}
	}
	// Method 5: Check REST API headers
	elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_SERVER['HTTP_X_ET_FB'] ) ) {
		$is_vb = true;
	}

	// If Visual Builder is not active, return original value
	if ( ! $is_vb ) {
		return $enable;
	}

	// Visual Builder is active - check if Divi 5 is enabled
	if ( function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled() ) {
		return false;
	}

	// Fallback: Check if we're in Divi 5 module context (handles cases where et_builder_d5_enabled() isn't available yet)
	if ( defined( 'WFACP_DIVI5_MODULES_LOADED' ) && WFACP_DIVI5_MODULES_LOADED ) {
		return false;
	}

	// Not in Divi 5 context, return original value
	return $enable;
}

/**
 * Convert D4 border-width value to D5 format by appending 'px' unit if missing.
 *
 * D4 stores border-width as unitless numbers (e.g., "4"), but D5 requires units (e.g., "4px").
 *
 * @since 1.0.0
 * @param string $value The D4 border-width value.
 * @param array  $context Conversion context with attrs, desktopName, moduleName, viewport, state.
 * @return string The value with 'px' unit appended if it was a bare number.
 */
function convert_border_width( $value, $context = array() ) {
	if ( is_numeric( $value ) ) {
		return $value . 'px';
	}
	return $value;
}
