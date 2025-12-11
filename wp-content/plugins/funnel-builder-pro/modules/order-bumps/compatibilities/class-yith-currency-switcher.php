<?php
/**
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Yith_Currency_Exchange' ) ) {
	class WFOB_Compatibility_With_Yith_Currency_Exchange {

		public function __construct() {
			add_filter( 'wfob_product_switcher_price_data', [ $this, 'wfob_product_switcher_price_data' ], 10, 2 );
		}

		public function is_enable() {
			return defined( 'YITH_WCMCS_VERSION' );
		}


		/**
		 * @param $price_data
		 * @param $pro WC_Product;
		 *
		 * @return mixed
		 */
		public function wfob_product_switcher_price_data( $price_data, $pro ) {
			if ( ! $this->is_enable() || ! function_exists( 'yith_wcmcs_convert_price' ) ) {
				return $price_data;
			}

			$price_data['regular_org'] = yith_wcmcs_convert_price( $pro->get_regular_price( 'edit' ) );
			$price_data['price']       = yith_wcmcs_convert_price( $pro->get_price( 'edit' ) );

			return $price_data;
		}


	}

	new WFOB_Compatibility_With_Yith_Currency_Exchange();
}




