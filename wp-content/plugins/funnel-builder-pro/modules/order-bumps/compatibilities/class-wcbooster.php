<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Active_WCJ' ) ) {
	/**
	 * Booster For Woocommerce by booster.io
	 * Class WFOB_Compatibility_With_Active_WCJ
	 */
	class WFOB_Compatibility_With_Active_WCJ {

		public function __construct() {

			add_filter( 'wfob_product_raw_data', [ $this, 'product_raw_data' ], 10, 2 );
		}


		public function price_by_country_enabled() {
			return function_exists( 'wcj_get_option' ) && ( 'yes' == wcj_get_option( 'wcj_price_by_country_enabled', 'no' ) );
		}


		public function product_raw_data( $raw_data, $pro ) {
			if ( $this->price_by_country_enabled() ) {
				$raw_data['regular_price'] = $pro->get_regular_price();
				$raw_data['price']         = $pro->get_price();
			}

			return $raw_data;
		}

		public static function is_enable() {
			return class_exists( 'WC_Jetpack' );
		}
	}

	new WFOB_Compatibility_With_Active_WCJ();
}