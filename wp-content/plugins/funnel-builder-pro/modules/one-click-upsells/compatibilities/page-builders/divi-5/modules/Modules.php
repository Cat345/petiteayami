<?php
/**
 * Register all modules with dependency tree.
 *
 * This file uses ModuleRegistry to automatically register all modules.
 * To add a new module, simply add it to ModuleRegistry::$modules array.
 *
 * @package WFOCU\Modules
 * @since 1.0.0
 */

namespace WFOCU\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

// Load ModuleRegistry
require_once plugin_dir_path( __FILE__ ) . 'ModuleRegistry.php';

// Register custom value expansion functions for D4→D5 conversion.
// D4 stores border-width as unitless numbers (e.g., "4"), but D5 requires units (e.g., "4px").
add_filter(
	'divi.moduleLibrary.conversion.valueExpansionFunctionMap',
	function ( $map ) {
		$map['convertBorderWidth'] = __NAMESPACE__ . '\\convert_border_width';
		return $map;
	}
);

// Inject D4 shortcode defaults before conversion.
// D4 modules define border defaults in PHP (via add_border()), but these
// defaults are not stored in shortcode markup. This injects the missing
// defaults so conversion mappings can transform them correctly.
// Uses et_pb_module_shortcode_attributes which fires during D4→D5 conversion.
add_filter(
	'et_pb_module_shortcode_attributes',
	__NAMESPACE__ . '\\inject_d4_shortcode_defaults',
	10,
	6
);

add_action(
	'divi_module_library_modules_dependency_tree',
	function ( $dependency_tree ) {
		ModuleRegistry::register_modules( $dependency_tree );
	}
);

// Fallback: when this file is loaded after init:0 (where the dependency tree hook fires),
// the above action is too late. Register modules via load() -> register_block_type() instead.
// Priority 200 fires after wfocu_offer post type (init:100) and the dispatcher (init:110).
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
	200
);

// Normalize legacy label attributes for OfferPrice module.
// D4 conversion stores reg_label/offer_label as plain strings (e.g., "Offer Price: ").
// D5 expects { innerContent: { desktop: { value: "..." } } }.
// This filter converts old format to new before Divi processes the block.
add_filter(
	'render_block_data',
	function ( $parsed_block ) {
		if ( 'wfocu/offer-price' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}

		$label_keys = array( 'regLabel', 'offerLabel', 'signupLabel', 'recurringLabel' );
		foreach ( $label_keys as $key ) {
			if ( ! isset( $parsed_block['attrs'][ $key ] ) ) {
				continue;
			}
			$val = $parsed_block['attrs'][ $key ];
			// Plain string — wrap in innerContent format.
			if ( is_string( $val ) ) {
				$parsed_block['attrs'][ $key ] = array(
					'innerContent' => array(
						'desktop' => array( 'value' => $val ),
					),
				);
			}
			// Old desktop.value format — convert to innerContent.
			if ( is_array( $val ) && isset( $val['desktop']['value'] ) && ! isset( $val['innerContent'] ) ) {
				$parsed_block['attrs'][ $key ] = array(
					'innerContent' => array(
						'desktop' => array( 'value' => $val['desktop']['value'] ),
					),
				);
			}
		}

		return $parsed_block;
	}
);

/**
 * Convert D4 border-width value to D5 format by appending 'px' unit if missing.
 *
 * D4 stores border-width as unitless numbers (e.g., "4"), but D5 requires units (e.g., "4px").
 *
 * @since 1.0.0
 * @param string $value The D4 border-width value.
 * @param array  $context Conversion context.
 * @return string The value with 'px' unit appended if it was a bare number.
 */
function convert_border_width( $value, $context = array() ) {
	if ( is_numeric( $value ) ) {
		return $value . 'px';
	}
	return $value;
}

/**
 * Inject D4 shortcode defaults before conversion.
 *
 * D4 modules define field defaults in PHP (via add_border()), but these
 * defaults are not stored in the shortcode markup. This injects the missing
 * D4 defaults so the standard conversion mappings can transform them into
 * the correct D5 attribute paths.
 *
 * @param array  $attrs       D4 shortcode attributes.
 * @param string $module_name D5 module name.
 *
 * @return array Attributes with D4 defaults merged in.
 */
function inject_d4_shortcode_defaults( $attrs, $unprocessed_attrs = array(), $module_slug = '', $module_address = '', $content = '', $maybe_global_presets = false ) {
	// Set default line-height for text modules on offer pages if not explicitly set.
	// D4 text modules inherit line-height from the theme, but D5 uses a tighter default.
	if ( 'et_pb_text' === $module_slug && ! isset( $attrs['text_line_height'] ) ) {
		$attrs['text_line_height'] = '1.5em';
	}

	// Map D4 shortcode slugs to border groups.
	$border_groups = array();

	switch ( $module_slug ) {
		// AcceptLink/RejectLink: add_border( ..., 'border', ..., ['border_type' => 'none'] )
		case 'et_wfocu_accept_link':
		case 'et_wfocu_reject_link':
			$border_groups = array(
				'border' => array(
					'border_type'  => 'none',
					'border_color' => '#dddddd',
				),
			);
			break;

		// AcceptButton: add_border( ..., 'wfocu_accept_button_border', ..., ['border_type' => 'solid', widths => '0', radius => '3'] )
		case 'et_wfocu_accept_button':
			$border_groups = array(
				'wfocu_accept_button_border' => array(
					'border_color'  => '#dddddd',
					'border_width'  => '0px',
					'border_radius' => '3px',
				),
			);
			break;

		// RejectButton: add_border( ..., 'wfocu_reject_button_border', ..., ['border_type' => 'solid', widths => '0', radius => '3'] )
		case 'et_wfocu_reject_button':
			$border_groups = array(
				'wfocu_reject_button_border' => array(
					'border_color'  => '#dddddd',
					'border_width'  => '0px',
					'border_radius' => '3px',
				),
			);
			break;
	}

	if ( empty( $border_groups ) ) {
		return $attrs;
	}

	foreach ( $border_groups as $prefix => $overrides ) {
		$type_key     = $prefix . '_border_type';
		$default_type = $overrides['border_type'] ?? 'solid';
		$border_color = $overrides['border_color'] ?? '#dddddd';

		// Resolve effective border type: shortcode value or D4 default.
		$border_type = $attrs[ $type_key ] ?? $default_type;

		if ( 'none' === $border_type ) {
			continue;
		}

		// Inject missing D4 defaults for visible borders.
		$default_width  = $overrides['border_width'] ?? '1px';
		$default_radius = $overrides['border_radius'] ?? '0';
		$defaults       = array(
			$prefix . '_border_color'         => $border_color,
			$prefix . '_border_width_top'     => $default_width,
			$prefix . '_border_width_bottom'  => $default_width,
			$prefix . '_border_width_left'    => $default_width,
			$prefix . '_border_width_right'   => $default_width,
			$prefix . '_border_radius_top'    => $default_radius,
			$prefix . '_border_radius_bottom' => $default_radius,
			$prefix . '_border_radius_left'   => $default_radius,
			$prefix . '_border_radius_right'  => $default_radius,
		);

		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $attrs[ $key ] ) ) {
				$attrs[ $key ] = $value;
			}
		}

		// Convert unitless border width/radius values to px.
		// D4 stores these as bare numbers (e.g., "1"), D5 needs units ("1px").
		$numeric_keys = array(
			$prefix . '_border_width_top',
			$prefix . '_border_width_bottom',
			$prefix . '_border_width_left',
			$prefix . '_border_width_right',
			$prefix . '_border_width_all',
			$prefix . '_border_radius_top',
			$prefix . '_border_radius_bottom',
			$prefix . '_border_radius_left',
			$prefix . '_border_radius_right',
		);
		foreach ( $numeric_keys as $key ) {
			if ( isset( $attrs[ $key ] ) && is_numeric( $attrs[ $key ] ) ) {
				$attrs[ $key ] = $attrs[ $key ] . 'px';
			}
		}
	}

	return $attrs;
}
