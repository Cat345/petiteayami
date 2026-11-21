<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * WooCommerce Quantity Discounts, Rules & Swatches (Quantity Manager Pro) by StudioWombat
 * https://www.studiowombat.com/plugin/woocommerce-quantity-discounts-rules-swatches/
 */
if ( ! class_exists( 'WFACP_Compatibility_WQM_Pro' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_WQM_Pro {

		public function __construct() {
			/**
			 * Quantity Manager re-prices every tiered product on `woocommerce_before_calculate_totals` (priority 10)
			 * from the product's stored price, overwriting the checkout product discount applied at priority 1.
			 */
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'restore_checkout_discount' ), 11 );
			add_filter( 'woocommerce_cart_item_price', array( $this, 'restore_cart_item_price_display' ), 21, 2 );
		}

		/**
		 * Put the checkout discount back on items that carry one, and clear Quantity Manager's discount flags so
		 * its order line item subtotal adjustment does not run with its own tier price.
		 *
		 * @param WC_Cart $cart Cart.
		 *
		 * @return void
		 */
		public function restore_checkout_discount( $cart ) {
			if ( 0 === absint( WFACP_Common::get_id() ) || apply_filters( 'wfacp_disabled_discounting', false, $this ) ) {
				return;
			}

			$currency = get_woocommerce_currency();
			foreach ( $cart->get_cart_contents() as $key => $item ) {
				if ( empty( $item['_wfacp_item_discount'][ $currency ] ) || ! $item['data'] instanceof WC_Product ) {
					continue;
				}

				$item['data']->set_price( $item['_wfacp_item_discount'][ $currency ]['sale_price'] );

				$cart->cart_contents[ $key ]['_wqm_has_discount'] = false;
				unset( $cart->cart_contents[ $key ]['_wqm_original_price'], $cart->cart_contents[ $key ]['_wqm_discounted_price'] );
			}
		}

		/**
		 * Quantity Manager prints its own tier price for the item; show the checkout discounted price instead.
		 *
		 * @param string $price     Price HTML.
		 * @param array  $cart_item Cart item.
		 *
		 * @return string
		 */
		public function restore_cart_item_price_display( $price, $cart_item ) {
			if ( 0 === absint( WFACP_Common::get_id() ) || empty( $cart_item['_wfacp_item_discount'][ get_woocommerce_currency() ] ) || ! $cart_item['data'] instanceof WC_Product ) {
				return $price;
			}

			return WC()->cart->get_product_price( $cart_item['data'] );
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_WQM_Pro(), 'wqm_pro' );
}
