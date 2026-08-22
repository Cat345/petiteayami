<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_WooMulti_Curcy' ) ) {
	#[\AllowDynamicProperties]
	class WFOB_Compatibility_With_WooMulti_Curcy {

		public function __construct() {
			add_action( 'woocommerce_calculate_totals', array( $this, 'remove_actions' ) );
			add_filter( 'wfob_product_raw_data', array( $this, 'product_raw_data' ), 10, 3 );
			add_filter( 'wfob_product_switcher_price_data', array( $this, 'change_price' ), 21, 3 );
			add_filter( 'wfob_show_product_price', array( $this, 'stop_printing_price' ), 10, 2 );
			add_filter( 'wfob_show_product_price_placeholder', array( $this, 'display_price' ), 12, 4 );
			// Runs after WFOB's own price filter (99) and Curcy's (also 99), whichever of
			// the two the site happens to register first. See convert_bump_price().
			add_filter( 'woocommerce_product_get_price', array( $this, 'convert_bump_price' ), 1000, 2 );
			add_filter( 'woocommerce_product_variation_get_price', array( $this, 'convert_bump_price' ), 1000, 2 );
		}

		/**
		 * Convert a bump's discounted price into the active currency.
		 *
		 * WFOB stores the discounted price in `_wfob_price` in the store's default
		 * currency and returns it verbatim from its own `woocommerce_product_get_price`
		 * filter. Curcy hooks that filter at the same priority (99), so whether the
		 * bump price ends up converted depends purely on which plugin registered
		 * first — the cart shows the correct converted price on one site and the raw
		 * default-currency number on another. Recompute from the meta here, after both,
		 * so the result no longer depends on registration order.
		 *
		 * @param $price
		 * @param $product WC_Product
		 *
		 * @return mixed
		 */
		public function convert_bump_price( $price, $product ) {
			if ( ! $product instanceof WC_Product || ! function_exists( 'wmc_get_price' ) ) {
				return $price;
			}

			$wfob_price = $product->get_meta( '_wfob_price' );
			if ( empty( $wfob_price ) ) {
				return $price;
			}

			return wmc_get_price( floatval( $wfob_price ) );
		}

		public function remove_actions() {
			if ( class_exists( 'WOOMULTI_CURRENCY_Plugin_Woofunnels_Order_Bump' ) ) {
				$instance = WFOB_Common::remove_actions( 'wfob_show_product_price', 'WOOMULTI_CURRENCY_Plugin_Woofunnels_Order_Bump', 'wfob_show_product_price' );

			}
			if ( class_exists( 'WOOMULTI_CURRENCY_F_Plugin_Woofunnels_Order_Bump' ) ) {
				WFOB_Common::remove_actions( 'wfob_show_product_price', 'WOOMULTI_CURRENCY_F_Plugin_Woofunnels_Order_Bump', 'wfob_show_product_price' );
			}
		}

		public function change_price( $price_data, $pro, $cart_key = '' ) {

			// No 'edit' context: Curcy's getter filter converts the regular price to
			// the current currency (WFOB never overrides regular).
			$price_data['regular_org'] = $pro->get_regular_price();

			// The discounted bump price in `_wfob_price` is stored in the store's
			// default currency, and WFOB's getter filter returns it verbatim. The
			// free and premium Curcy plugins register their conversion at the same
			// priority (99) but on opposite sides of WFOB's filter, so relying on
			// the chain converts on one plugin and not the other. Convert the meta
			// value explicitly instead — deterministic for both.
			$wfob_price = $pro->get_meta( '_wfob_price' );
			if ( '' !== $wfob_price && function_exists( 'wmc_get_price' ) ) {
				$price_data['price'] = wmc_get_price( floatval( $wfob_price ) );
			} else {
				$price_data['price'] = $pro->get_price();
			}

			return $price_data;
		}


		/**
		 * Keep the discount pipeline in the store's default currency.
		 *
		 * Converting price/regular_price here (by rate or Curcy's per-currency fixed
		 * meta) poisons the pipeline: the discounted result is stored in _wfob_price
		 * and set on the product, and Curcy's getter filter converts it AGAIN at
		 * display/cart-total time (bump products always carry runtime prop changes,
		 * so Curcy rate-converts them regardless of its fixed-price setting). Feeding
		 * default-currency values through means every price is converted exactly once,
		 * by Curcy, at getter time.
		 *
		 * For the same reason this compat deliberately hooks neither
		 * `wfob_discount_regular_price_data`/`wfob_discount_price_data` nor
		 * `wfob_discount_amount_data`: a fixed discount amount is entered in the
		 * store's default currency, so it has to be subtracted from a
		 * default-currency price. Converting it while the prices stayed unconverted
		 * used to overshoot the sale price in the quick view popup and clamp the
		 * variation to zero.
		 *
		 * @param $raw_data
		 * @param $product WC_Product;
		 *
		 * @return mixed
		 */
		public function product_raw_data( $raw_data, $product ) {
			return $raw_data;
		}

		/**
		 * @param $status boolean
		 * @param $pro WC_Product
		 *
		 * @return bool
		 */
		public function stop_printing_price( $status, $pro ) {
			if ( in_array( $pro->get_type(), WFOB_Common::get_subscription_product_type() ) ) {
				remove_filter( 'wfob_show_product_price_placeholder', array( WFOB_Compatibility_Subscription::getInstance(), 'display_price' ) );
				$status = false;
			}

			return $status;
		}

		/**
		 * @param $price_html String
		 * @param $pro WC_Product
		 * @param $cart_item_key String
		 * @param $price_data []
		 */
		public function display_price( $price_html, $pro, $cart_item_key, $price_data ) {
			/**
			 * @var $pro WC_Product
			 */
			if ( in_array( $pro->get_type(), WFOB_Common::get_subscription_product_type() ) ) {
				$temp = wc_get_product( $pro->get_id() );
				if ( ! $temp instanceof WC_Product ) {
					return $price_html;
				}
				$s_price_data          = $price_data;
				$s_price_data['price'] = $s_price_data['regular_org'];
				if ( '' !== $cart_item_key ) {
					$price_html = $price_data['price'];
				} else {
					$price_html = WFOB_Common::get_subscription_price( $pro, $price_data );
				}
			}

			return $price_html;
		}
	}

	new WFOB_Compatibility_With_WooMulti_Curcy();
}
