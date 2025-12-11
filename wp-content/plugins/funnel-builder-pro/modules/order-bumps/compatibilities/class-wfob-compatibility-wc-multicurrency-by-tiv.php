<?php
/*
 * Plugin : WooCommerce Multi-currency by TIV.NET INC v(2.14.1)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_WC_Multicurrency_By_TIV' ) ) {
	class WFOB_Compatibility_With_WC_Multicurrency_By_TIV {

		public function __construct() {
			add_filter( 'wfob_product_switcher_price_data', [ $this, 'wfob_product_switcher_price_data' ], 10, 2 );
		}

		public function is_enable() {
			return class_exists( 'WOOMC\Product\Pricing' );
		}

		/**
		 * @param $price_data
		 * @param $pro WC_Product;
		 *
		 * @return mixed
		 */
		public function wfob_product_switcher_price_data( $price_data, $pro ) {
			if ( ! $this->is_enable() ) {
				return $price_data;
			}

			$price_data['regular_org'] = $pro->get_regular_price();
			$price_data['price']       = $pro->get_price();

			return $price_data;
		}
	}


	new WFOB_Compatibility_With_WC_Multicurrency_By_TIV();
}