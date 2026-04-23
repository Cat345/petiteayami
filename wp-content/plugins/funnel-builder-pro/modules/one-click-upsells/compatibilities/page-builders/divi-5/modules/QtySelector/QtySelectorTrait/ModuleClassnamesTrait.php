<?php
/**
 * QtySelector::module_classnames().
 *
 * @package WFOCU\Modules\QtySelector
 * @since 1.0.0
 */

namespace WFOCU\Modules\QtySelector\QtySelectorTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

use ET\Builder\Packages\Module\Options\Text\TextClassnames;

trait ModuleClassnamesTrait {

	/**
	 * Module classnames function for Qty Selector module.
	 *
	 * This function is equivalent of JS function moduleClassnames located in
	 * src/components/qty-selector/module-classnames.ts.
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

		// Add stacked/inline class based on stackedEnabled attribute
		// Handle toggle values: "on" = enabled, "off" = disabled (matching Product Images pattern)
		$stacked_enabled = false;
		if ( isset( $attrs['stackedEnabled']['desktop']['value'] ) ) {
			$value           = $attrs['stackedEnabled']['desktop']['value'];
			$stacked_enabled = $value === true || $value === 'on' || $value === '1' || $value === 'yes';
		}

		if ( $stacked_enabled ) {
			$classnames_instance->add( 'wfocu_proqty_block', true );
		} else {
			$classnames_instance->add( 'wfocu_proqty_inline', true );
		}
	}
}
