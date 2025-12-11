<?php

/**
 * WooCommerce Price Based on Country (Basic)
 * Author: Oscar Gare
 * #[AllowDynamicProperties]
 * class WFACP_Product_Price_Based_Country
 */
if ( ! class_exists( 'WFOB_Product_Price_Based_Country' ) ) {
	#[AllowDynamicProperties]
	class WFOB_Product_Price_Based_Country {
		public function __construct() {
			add_filter( 'wfob_product_raw_data', [ $this, 'change_price_data' ], 10, 2 );
			add_filter( 'wfob_product_switcher_price_data', [ $this, 'wfob_product_switcher_price_data' ], 10, 2 );

		}

		public function change_price_data( $raw_data, $product ) {
			if ( ! class_exists( 'WC_Product_Price_Based_Country' ) ) {
				return $raw_data;
			}
			$product = wc_get_product( $product );
			add_filter( 'wfob_set_bump_product_price_params', '__return_false' );
			/**
			 * @var $product WC_Product
			 * return $raw_data;
			 */
			$raw_data['regular_price'] = $product->get_regular_price();
			$raw_data['price']         = $product->get_price();

			return $raw_data;
		}

		public function wfob_product_switcher_price_data( $price_data, $pro ) {
			if ( ! class_exists( 'WC_Product_Price_Based_Country' ) ) {
				return $price_data;
			}
			$price_data['regular_org'] = $pro->get_regular_price();
			$price_data['price']       = $pro->get_price();

			return $price_data;
		}

	}

	new WFOB_Product_Price_Based_Country();
}