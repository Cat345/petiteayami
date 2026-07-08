<?php
if ( ! class_exists( 'WFACP_YayCurrency' ) ) {
	/**
	 * YayCurrency – WooCommerce Multi-Currency Switcher
	 * https://wordpress.org/plugins/yaycurrency/
	 */
	#[\AllowDynamicProperties]
	class WFACP_YayCurrency {
		public function __construct() {

			add_filter( 'wfacp_product_switcher_price_data', array( $this, 'change_price' ), 20, 2 );
		}

		public function change_price( $price_data, $pro ) {
			$price_data['regular_org'] = $pro->get_regular_price();
			$price_data['price']       = $pro->get_price();

			return $price_data;
		}
	}

	new WFACP_YayCurrency();
}
