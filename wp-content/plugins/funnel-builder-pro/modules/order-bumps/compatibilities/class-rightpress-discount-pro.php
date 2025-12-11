<?php
if ( ! class_exists( 'WFOB_Rightpress_Discount_Pro' ) ) {
	/**
	 * PLugin https://codecanyon.net/item/woocommerce-dynamic-pricing-discounts/7119279
	 */
	class WFOB_Rightpress_Discount_Pro {
		public function __construct() {
			add_filter( 'rp_wcdpd_product_pricing_cart_items', [ $this, 'disable_discounting' ], 11 );
		}

		function disable_discounting( $cart_items ) {
			if ( empty( $cart_items ) ) {
				return $cart_items;
			}
			foreach ( $cart_items as $cart_item_key => $cart_item ) {
				if ( isset( $cart_item['_wfacp_options'] ) || isset( $cart_item['_wfob_options'] ) ) {
					unset( $cart_items[ $cart_item_key ] );
				}
			}

			return $cart_items;
		}
	}

	new WFOB_Rightpress_Discount_Pro();
}