<?php
/**
 * YITH Multi Currency Switcher Compatibility Class
 *
 * @package FunnelKit Order Bumps
 * @version 1.26.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Yith_Currency_Exchange' ) ) {
	class WFOB_Compatibility_With_Yith_Currency_Exchange {

		public function __construct() {
			add_filter( 'wfob_product_switcher_price_data', array( $this, 'wfob_product_switcher_price_data' ), 10, 2 );
			add_filter( 'wfob_product_raw_data', array( $this, 'wfob_product_raw_data' ), 10, 2 );
		}

		public function is_enable() {
			return defined( 'YITH_WCMCS_VERSION' );
		}


		/**
		 * Get product prices with YITH manual currency support
		 *
		 * ONLY applies YITH manual prices when enabled for the product.
		 * If manual prices are disabled, returns original price_data unchanged (uses base currency).
		 *
		 * @param array      $price_data Price data array.
		 * @param WC_Product $pro        Product object.
		 *
		 * @return array Modified price data with currency-specific prices.
		 */
		public function wfob_product_switcher_price_data( $price_data, $pro ) {
			if ( ! $this->is_enable() ) {
				return $price_data;
			}

			// Check if YITH should apply currency filters (not in admin context)
			if ( ! class_exists( 'YITH_WCMCS' ) || ! YITH_WCMCS::apply_currency_filters() ) {
				return $price_data;
			}

			// Check if manual prices are enabled for this specific product
			$has_manual_prices = $pro->get_meta( '_yith_wcmcs_custom_prices', true );

			// ONLY apply YITH manual prices if explicitly enabled
			if ( 'yes' === $has_manual_prices ) {
				// Manual prices enabled - get manual prices from YITH
				$price_data['regular_org'] = $pro->get_regular_price();
				$price_data['price']       = $pro->get_price();
			}
			// If manual prices NOT enabled, return unchanged (use base prices)

			return $price_data;
		}

		/**
		 * Get product raw data with YITH manual currency support
		 *
		 * ONLY applies YITH manual prices when enabled for the product.
		 * If manual prices are disabled, returns original raw_data unchanged (uses base currency).
		 *
		 * @param array      $raw_data Raw product data array.
		 * @param WC_Product $pro      Product object.
		 *
		 * @return array Modified raw data with currency-specific prices.
		 */
		public function wfob_product_raw_data( $raw_data, $pro ) {
			if ( ! $this->is_enable() ) {
				return $raw_data;
			}

			// Check if YITH should apply currency filters (not in admin context)
			if ( ! class_exists( 'YITH_WCMCS' ) || ! YITH_WCMCS::apply_currency_filters() ) {
				return $raw_data;
			}

			// Check if manual prices are enabled for this specific product
			$has_manual_prices = $pro->get_meta( '_yith_wcmcs_custom_prices', true );

			// ONLY apply YITH manual prices if explicitly enabled
			if ( 'yes' === $has_manual_prices ) {
				// Manual prices enabled - get manual prices from YITH
				$raw_data['regular_price'] = $pro->get_regular_price();
				$raw_data['price']         = $pro->get_price();
			}
			// If manual prices NOT enabled, return unchanged (use base prices)

			return $raw_data;
		}
	}

	new WFOB_Compatibility_With_Yith_Currency_Exchange();
}
