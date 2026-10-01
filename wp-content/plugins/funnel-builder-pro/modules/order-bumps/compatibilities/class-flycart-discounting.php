<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Discount_Rule_fly_Cart' ) ) {
	/**
	 * Discount Rules Core Plugin by Fly Cart.
	 */
	#[\AllowDynamicProperties]
	class WFOB_Compatibility_With_Discount_Rule_fly_Cart {

		public function __construct() {
			add_filter( 'wfob_product_switcher_price_data', array( $this, 'wfob_product_switcher_price_data' ), 20, 3 );
		}

		/**
		 * @param $price_data
		 * @param $pro WC_Product;
		 *
		 * @return mixed
		 */
		public function wfob_product_switcher_price_data( $price_data, $pro, $qty = 1 ) {
			if ( ! $pro instanceof WC_Product || ! class_exists( '\Wdr\App\Router', false ) ) {
				return $price_data;
			}

			// Discount on top of the price already resolved by earlier-priority filters (e.g. Price Based
			// on Country's zone price at priority 10, multicurrency, B2B pricing) instead of re-fetching
			// with 'edit' context. 'edit' skips WC_Data::get_prop()'s apply_filters(), so it discarded
			// those plugins' price and reset the bump card to the raw base price even when no discount
			// rule applied. get_price()/get_regular_price() (default 'view') is a filter-aware fallback
			// when no earlier hook set the value.
			$regular_org = isset( $price_data['regular_org'] ) ? $price_data['regular_org'] : $pro->get_regular_price();
			$base_price  = isset( $price_data['price'] ) ? $price_data['price'] : $pro->get_price();

			$discountedPrice = apply_filters( 'advanced_woo_discount_rules_get_product_discount_price_from_custom_price', $base_price, $pro, $qty, $regular_org, 'discounted_price', true, false );

			$price_data['regular_org'] = $regular_org;
			$price_data['price']       = ( false !== $discountedPrice ) ? $discountedPrice : $base_price;

			return $price_data;
		}
	}

	new WFOB_Compatibility_With_Discount_Rule_fly_Cart();
}
