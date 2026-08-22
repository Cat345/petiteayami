<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * YayPricing - WooCommerce Dynamic Pricing & Discounts by YayCommerce (Free & Pro)
 */
if ( ! class_exists( 'WFACP_YayPricing' ) ) {
	#[AllowDynamicProperties]
	class WFACP_YayPricing {
		public function __construct() {
			add_action( 'wfacp_template_load', array( $this, 'action' ), 8 );
		}

		public function action() {
			add_filter( 'wfacp_saving_price_item_regular_price', array( $this, 'adjust_extra_item_regular_price' ), 10, 2 );
		}

		/**
		 * Free/BOGO gift-aware regular price for the "You save" baseline.
		 *
		 * YayPricing adds gift items as separate zero-priced cart lines
		 * (is_extra, see \YAYDP\Core\YAYDP_Cart::publish), so their DB regular
		 * price inflates the baseline while contributing nothing to the total.
		 * YayPricing's own savings row skips these lines too
		 * (YAYDP_Cart::get_cart_origin_total). Drop them from the baseline.
		 */
		public function adjust_extra_item_regular_price( $regular_price, $cart_item ) {
			if ( ! is_array( $cart_item ) || empty( $cart_item ) ) {
				return $regular_price;
			}

			$is_extra = function_exists( 'yaydp_is_extra_wc_cart_item' ) ? yaydp_is_extra_wc_cart_item( $cart_item ) : ! empty( $cart_item['is_extra'] );
			if ( $is_extra ) {
				return '';
			}

			return $regular_price;
		}
	}


	WFACP_Plugin_Compatibilities::register( new WFACP_YayPricing(), 'yaypricing' );

}
