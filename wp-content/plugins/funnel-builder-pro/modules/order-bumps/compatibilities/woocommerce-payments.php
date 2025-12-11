<?php
/**
 * Official WooCommerce Payments(WooPayments)
 */
if ( ! class_exists( 'WFOB_WooCommerce_payments' ) ) {
	class WFOB_WooCommerce_payments {
		public function __construct() {
			add_filter( 'wfob_product_switcher_price_data', [ $this, 'change_price_data' ], 10, 2 );
		}

		public function change_price_data( $price_data, $pro ) {
			$price_data['regular_org'] = $pro->get_regular_price();
			$price_data['price']       = $pro->get_price();

			return $price_data;
		}
	}

	new WFOB_WooCommerce_payments();
}