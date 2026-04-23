<?php
/**
 * VariationSelector::module_classnames().
 *
 * @package WFOCU\Modules\VariationSelector
 * @since 1.0.0
 */

namespace WFOCU\Modules\VariationSelector\VariationSelectorTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Text\TextClassnames;

trait ModuleClassnamesTrait {

	/**
	 * Module classnames function for Variation Selector module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * src/components/variation-selector/module-classnames.ts.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     An array of arguments.
	 *
	 *     @type object $classnamesInstance Instance of ET\Builder\Packages\Module\Layout\Components\Classnames.
	 *     @type array  $attrs              Block attributes data that being rendered.
	 * }
	 *
	 * @return void
	 */
	public static function module_classnames( array $args ): void {
		$classnames_instance = $args['classnamesInstance'];
		$attrs               = $args['attrs'];

		// Text Options.
		$text_options_classnames = TextClassnames::text_options_classnames( $attrs['module']['advanced']['text'] ?? array() );

		if ( $text_options_classnames ) {
			$classnames_instance->add( $text_options_classnames, true );
		}

		// Add stacked/inline class based on attr_value_block attribute (Divi 4 key)
		// Similar to Quantity Selector: add class to module wrapper for CSS targeting
		$stacked_enabled = false;
		$value           = '';

		// Check attr_value_block first (Divi 4 key)
		if ( isset( $attrs['attr_value_block']['desktop']['value'] ) ) {
			$value = $attrs['attr_value_block']['desktop']['value'];
		} elseif ( isset( $attrs['attr_value_block']['desktop'] ) && is_string( $attrs['attr_value_block']['desktop'] ) ) {
			$value = $attrs['attr_value_block']['desktop'];
		} elseif ( isset( $attrs['attr_value_block'] ) && is_string( $attrs['attr_value_block'] ) ) {
			$value = $attrs['attr_value_block'];
		}

		// Handle toggle values: "on"/"Yes" = enabled, "off"/"No" = disabled (matching Divi 4)
		$stacked_enabled = $value === true || $value === 'on' || $value === 'Yes' || $value === '1' || $value === 'yes';

		// Add class to module wrapper (similar to Quantity Selector pattern)
		// This class will be used in CSS to target the form wrapper
		if ( $stacked_enabled ) {
			$classnames_instance->add( 'wfocu-variation-block', true );
		} else {
			$classnames_instance->add( 'wfocu-variation-inline', true );
		}
	}
}
