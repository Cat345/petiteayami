<?php
/**
 * OfferPrice::custom_css().
 *
 * @package WFOCU\Modules\OfferPrice
 * @since 1.0.0
 */

namespace WFOCU\Modules\OfferPrice\OfferPriceTrait;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

trait CustomCssTrait {

	public static function custom_css() {
		$registry = \WP_Block_Type_Registry::get_instance()->get_registered( 'wfocu/offer-price' );
		return $registry ? ( $registry->customCssFields ?? array() ) : array();
	}
}
