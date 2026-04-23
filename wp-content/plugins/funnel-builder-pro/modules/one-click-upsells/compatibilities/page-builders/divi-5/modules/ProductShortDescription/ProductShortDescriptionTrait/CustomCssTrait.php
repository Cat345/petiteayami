<?php
/**
 * ProductShortDescription::custom_css().
 *
 * @package WFOCU\Modules\ProductShortDescription
 * @since 1.0.0
 */

namespace WFOCU\Modules\ProductShortDescription\ProductShortDescriptionTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait CustomCssTrait {

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * src/components/product-short-description/custom-css.ts.
	 *
	 * @since 1.0.0
	 *
	 * @return array Custom CSS fields configuration.
	 */
	public static function custom_css() {
		return \WP_Block_Type_Registry::get_instance()->get_registered( 'wfocu/product-short-description' )->customCssFields;
	}
}
