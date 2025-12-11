<?php
if ( ! class_exists( 'WFOB_Compatibility_With_Discount_Rule_fly_Cart' ) ) {
	/**
	 * Discount Rules Core Plugin by Fly Cart.
	 */
	class WFOB_Compatibility_With_Discount_Rule_fly_Cart {

		public function __construct() {
			add_filter( 'wfob_product_switcher_price_data', [ $this, 'wfob_product_switcher_price_data' ], 20, 3 );
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

			$price_data['regular_org'] = $pro->get_regular_price( 'edit' );
			$discountedPrice           = apply_filters( 'advanced_woo_discount_rules_get_product_discount_price_from_custom_price', $pro->get_price( 'edit' ), $pro, $qty, $pro->get_regular_price( 'edit' ), 'discounted_price', true, false );
			if ( $discountedPrice !== false ) {
				$price_data['price'] = $discountedPrice;
			} else {
				$price_data['price'] = $pro->get_price( 'edit' );
			}

			return $price_data;
		}

	}

	new WFOB_Compatibility_With_Discount_Rule_fly_Cart();
}