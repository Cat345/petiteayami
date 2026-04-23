<?php
/**
 * QtySelector::custom_css().
 *
 * @package WFOCU\Modules\QtySelector
 * @since 1.0.0
 */

namespace WFOCU\Modules\QtySelector\QtySelectorTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait CustomCssTrait {

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * src/components/qty-selector/custom-css.ts.
	 *
	 * @since 1.0.0
	 *
	 * @return array Custom CSS fields configuration.
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'wfocu/qty-selector' )->customCssFields;
	}
}
