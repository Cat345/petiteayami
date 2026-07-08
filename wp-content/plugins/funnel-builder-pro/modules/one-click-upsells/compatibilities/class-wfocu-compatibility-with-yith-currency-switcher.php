<?php
/**
 * YITH Multi Currency Switcher Compatibility Class for One-Click Upsells
 *
 * @package FunnelKit One-Click Upsells
 * @version 1.26.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Compatibility_With_Yith_Currency_Switcher' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Compatibility_With_Yith_Currency_Switcher {

		public function __construct() {
			add_filter( 'wfocu_product_raw_price', array( $this, 'wfocu_product_raw_price' ), 10, 3 );
			add_filter( 'wfocu_product_raw_sale_price', array( $this, 'wfocu_product_raw_sale_price' ), 10, 4 );
		}

		public function is_enable() {
			return defined( 'YITH_WCMCS_VERSION' );
		}

		/**
		 * Get product raw price with YITH manual currency support
		 *
		 * ONLY applies YITH manual prices when enabled for the product.
		 * If manual prices are disabled, returns original price unchanged (uses base currency).
		 *
		 * @param float      $price   Product price.
		 * @param WC_Product $product Product object.
		 * @param object     $options Offer options.
		 *
		 * @return float Modified price with currency-specific prices.
		 */
		public function wfocu_product_raw_price( $price, $product, $options ) {
			if ( ! $this->is_enable() ) {
				return $price;
			}

			if ( ! class_exists( 'YITH_WCMCS' ) || ! YITH_WCMCS::apply_currency_filters() ) {
				return $price;
			}

			$has_manual_prices = $product->get_meta( '_yith_wcmcs_custom_prices', true );

			if ( 'yes' === $has_manual_prices ) {
				$regular_price = $product->get_regular_price();
				if ( ! empty( $regular_price ) && is_object( $options ) && isset( $options->quantity ) ) {
					$price = $regular_price * $options->quantity;
				}
			}

			return $price;
		}

		/**
		 * Get product raw sale price with YITH manual currency support
		 *
		 * ONLY applies YITH manual prices when enabled for the product.
		 * If manual prices are disabled, returns original sale_price unchanged (uses base currency).
		 *
		 * @param float      $sale_price   Product sale price.
		 * @param WC_Product $product      Product object.
		 * @param object     $options      Offer options.
		 * @param float      $regular_price Regular price.
		 *
		 * @return float Modified sale price with currency-specific prices.
		 */
		public function wfocu_product_raw_sale_price( $sale_price, $product, $options, $regular_price ) {
			if ( ! $this->is_enable() ) {
				return $sale_price;
			}

			if ( ! class_exists( 'YITH_WCMCS' ) || ! YITH_WCMCS::apply_currency_filters() ) {
				return $sale_price;
			}

			$has_manual_prices = $product->get_meta( '_yith_wcmcs_custom_prices', true );

			if ( 'yes' === $has_manual_prices ) {
				$product_price = $product->get_price();
				if ( ! empty( $product_price ) && is_object( $options ) && isset( $options->quantity ) ) {
					$sale_price = $product_price * $options->quantity;
				}
			}

			return $sale_price;
		}
	}

	new WFOCU_Compatibility_With_Yith_Currency_Switcher();
}
