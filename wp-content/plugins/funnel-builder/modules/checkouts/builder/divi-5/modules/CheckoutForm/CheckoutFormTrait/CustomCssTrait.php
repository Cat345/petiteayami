<?php
/**
 * CheckoutForm::custom_css().
 *
 * @package WFACP\Modules\CheckoutForm
 * @since 1.0.0
 */

namespace WFACP\Modules\CheckoutForm\CheckoutFormTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait CustomCssTrait {

	/**
	 * Custom CSS fields
	 *
	 * This function is equivalent of JS const cssFields located in
	 * src/components/checkout-form/custom-css.ts.
	 *
	 * @since 1.0.0
	 *
	 * @return array Custom CSS fields configuration.
	 */
	public static function custom_css() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'wfacp/checkout-form' );
		if ( $block_type && isset( $block_type->customCssFields ) ) {
			return $block_type->customCssFields;
		}
		return array();
	}
}
