<?php
if ( ! class_exists( 'WFOb_Compatibility_WCS_Att_Product' ) ) {
	/**
	 * Name WooCommerce All Products For Subscriptions
	 * https://woocommerce.com/products/all-products-for-woocommerce-subscriptions/
	 * Author SomewhereWarm
	 * Class WFOb_Compatibility_WCS_Att_Product
	 */
	class WFOb_Compatibility_WCS_Att_Product {
		public function __construct() {
			add_action( 'wfob_before_add_to_cart', [ $this, 'remove_discounting' ] );
		}

		public function is_enabled() {
			return class_exists( 'WCS_ATT_Cart' );
		}

		public function remove_discounting( $post ) {
			if ( ! $this->is_enabled() ) {
				return $post;
			}
			$product_key = $post['product_key'];
			$wfob_id     = $post['wfob_id'];
			$products    = WFOB_Common::get_bump_products( absint( $wfob_id ) );
			if ( isset( $products[ $product_key ] ) && floatval( $products[ $product_key ]['discount_amount'] ) > 0 ) {
				remove_filter( 'woocommerce_add_cart_item_data', 'WCS_ATT_Cart::add_cart_item_data' );
			}

			return $post;
		}
	}

	new WFOb_Compatibility_WCS_Att_Product();
}