<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_WooMulti_Curcy' ) ) {
	/**
	 * CURCY - WooCommerce Multi Currency (premium, WOOMULTI_CURRENCY_*) and
	 * CURCY - Multi Currency for WooCommerce (free, WOOMULTI_CURRENCY_F_*).
	 *
	 * The bump discount pipeline (`WFOB_Common::set_product_price()` and the cart
	 * session pipeline) always runs in the store's default currency: it stores the
	 * discounted unit price in `_wfob_price`, the undiscounted one in
	 * `_wfob_regular_price` and the bump settings in `_wfob_options`. This class
	 * converts those default-currency figures into the active currency at the very
	 * end of every price getter (priority 1000), so the result depends neither on
	 * the order in which CURCY and WFOB registered their priority-99 filters nor on
	 * CURCY's own handling of products that carry runtime prop changes.
	 *
	 * CURCY offers two ways to price a product in a secondary currency: converting
	 * the default-currency price by the exchange rate, or a per-product fixed price
	 * stored in `_regular_price_wmcp` / `_sale_price_wmcp` (when "fixed price" is
	 * enabled). The bump must honour both, so the discount is recomputed here from
	 * the product's undiscounted price *in the active currency* (fixed price when
	 * the merchant set one, rate-converted otherwise) using the bump's own discount
	 * settings. Rate mode therefore yields exactly wmc_get_price( _wfob_price ),
	 * and fixed mode yields the discount applied to the fixed price.
	 */
	#[\AllowDynamicProperties]
	class WFOB_Compatibility_With_WooMulti_Curcy {

		/**
		 * @var WOOMULTI_CURRENCY_Data|WOOMULTI_CURRENCY_F_Data|false|null
		 */
		private $settings = null;

		public function __construct() {
			add_action( 'woocommerce_calculate_totals', array( $this, 'remove_actions' ) );
			add_filter( 'wfob_product_raw_data', array( $this, 'product_raw_data' ), 10, 3 );
			add_filter( 'wfob_product_switcher_price_data', array( $this, 'change_price' ), 21, 3 );
			add_filter( 'wfob_show_product_price', array( $this, 'stop_printing_price' ), 10, 2 );
			add_filter( 'wfob_show_product_price_placeholder', array( $this, 'display_price' ), 12, 4 );

			// Runs after WFOB's own price filter (99) and Curcy's (also 99), whichever of
			// the two the site happens to register first. See convert_bump_price().
			$price_hooks = array(
				'woocommerce_product_get_price',
				'woocommerce_product_variation_get_price',
				'woocommerce_product_get_sale_price',
				'woocommerce_product_variation_get_sale_price',
			);
			foreach ( $price_hooks as $hook ) {
				add_filter( $hook, array( $this, 'convert_bump_price' ), 1000, 2 );
			}
			add_filter( 'woocommerce_product_get_regular_price', array( $this, 'convert_bump_regular_price' ), 1000, 2 );
			add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'convert_bump_regular_price' ), 1000, 2 );
		}

		/**
		 * Discounted bump price (price and sale price getters) in the active currency.
		 *
		 * WFOB returns `_wfob_price` verbatim from its own priority-99 filter and
		 * CURCY hooks the same getters at 99, so whether the value ends up converted
		 * used to depend on registration order. Recompute it here, after both, from the
		 * bump meta so the result is deterministic and honours CURCY fixed prices.
		 *
		 * @param mixed      $price
		 * @param WC_Product $product
		 *
		 * @return mixed
		 */
		public function convert_bump_price( $price, $product ) {
			if ( ! $product instanceof WC_Product ) {
				return $price;
			}

			$converted = $this->get_discounted_price( $product );
			if ( ! is_null( $converted ) ) {
				// Price getters are expected to yield a numeric string; currency plugins
				// key their per-product caches on it, so a float leaks a float offset.
				return wc_format_decimal( $converted );
			}

			return $this->maybe_quick_view_price( $price, $product );
		}

		/**
		 * Undiscounted bump price (regular price getters) in the active currency.
		 *
		 * WFOB sets the regular price on the bump product, so the product carries
		 * runtime changes and CURCY rate-converts it even when the merchant configured
		 * a fixed price for the active currency. Return the fixed price when there is
		 * one, the rate-converted default-currency price otherwise.
		 *
		 * @param mixed      $price
		 * @param WC_Product $product
		 *
		 * @return mixed
		 */
		public function convert_bump_regular_price( $price, $product ) {
			if ( ! $product instanceof WC_Product || '' === $product->get_meta( '_wfob_price' ) || ! $this->is_secondary_currency() ) {
				return $price;
			}

			$base = $this->get_base_prices( $product );
			if ( is_array( $base ) ) {
				return wc_format_decimal( $base['regular_price'] );
			}

			$fixed_regular = $this->get_fixed_price( $product, 'regular_price' );
			if ( false !== $fixed_regular ) {
				return wc_format_decimal( $fixed_regular );
			}

			$regular = $product->get_meta( '_wfob_regular_price' );
			if ( '' !== $regular ) {
				return wc_format_decimal( wmc_get_price( floatval( $regular ) ) );
			}

			return $price;
		}

		public function remove_actions() {
			if ( class_exists( 'WOOMULTI_CURRENCY_Plugin_Woofunnels_Order_Bump' ) ) {
				$instance = WFOB_Common::remove_actions( 'wfob_show_product_price', 'WOOMULTI_CURRENCY_Plugin_Woofunnels_Order_Bump', 'wfob_show_product_price' );

			}
			if ( class_exists( 'WOOMULTI_CURRENCY_F_Plugin_Woofunnels_Order_Bump' ) ) {
				WFOB_Common::remove_actions( 'wfob_show_product_price', 'WOOMULTI_CURRENCY_F_Plugin_Woofunnels_Order_Bump', 'wfob_show_product_price' );
			}
		}

		/**
		 * Bump box price data. Both getters are made deterministic for bump products
		 * by convert_bump_price() / convert_bump_regular_price(), so the box shows the
		 * same figures the cart charges.
		 *
		 * @param array      $price_data
		 * @param WC_Product $pro
		 * @param mixed      $cart_key
		 *
		 * @return array
		 */
		public function change_price( $price_data, $pro, $cart_key = '' ) {
			$price_data['regular_org'] = $pro->get_regular_price();
			$price_data['price']       = $pro->get_price();

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
		 * by this class, at getter time.
		 *
		 * For the same reason this compat deliberately hooks neither
		 * `wfob_discount_regular_price_data`/`wfob_discount_price_data` nor
		 * `wfob_discount_amount_data`: a fixed discount amount is entered in the
		 * store's default currency, so it has to be subtracted from a
		 * default-currency price. Converting it while the prices stayed unconverted
		 * used to overshoot the sale price in the quick view popup and clamp the
		 * variation to zero.
		 *
		 * The pipeline overwrites the product's regular/sale price with the discounted
		 * values, so the undiscounted default-currency prices are remembered on the
		 * object here; the getters need them to recompute the discount on top of a
		 * CURCY fixed price.
		 *
		 * @param $raw_data
		 * @param $product WC_Product;
		 *
		 * @return mixed
		 */
		public function product_raw_data( $raw_data, $product, $cart_item_key = '' ) {
			if ( $product instanceof WC_Product && is_array( $raw_data ) && ! is_array( $product->get_meta( '_wfob_wmc_raw' ) ) ) {
				$product->update_meta_data(
					'_wfob_wmc_raw',
					array(
						'regular_price' => isset( $raw_data['regular_price'] ) ? $raw_data['regular_price'] : '',
						'price'         => isset( $raw_data['price'] ) ? $raw_data['price'] : '',
						'sale_price'    => isset( $raw_data['sale_price'] ) ? $raw_data['sale_price'] : '',
					)
				);
			}

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

		/* ------------------------------------------------------------------ */
		/* Helpers                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * @return WOOMULTI_CURRENCY_Data|WOOMULTI_CURRENCY_F_Data|false
		 */
		private function get_settings() {
			if ( is_null( $this->settings ) ) {
				$this->settings = false;
				if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
					$this->settings = WOOMULTI_CURRENCY_Data::get_ins();
				} elseif ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
					$this->settings = WOOMULTI_CURRENCY_F_Data::get_ins();
				}
			}

			return $this->settings;
		}

		/**
		 * Whether the shopper is browsing in a currency other than the store default.
		 *
		 * @return bool
		 */
		private function is_secondary_currency() {
			$settings = $this->get_settings();
			if ( ! $settings || ! function_exists( 'wmc_get_price' ) || ! method_exists( $settings, 'get_current_currency' ) || ! method_exists( $settings, 'get_default_currency' ) ) {
				return false;
			}
			$current = $settings->get_current_currency();

			return ! empty( $current ) && $current !== $settings->get_default_currency();
		}

		/**
		 * CURCY per-product fixed price for the active currency.
		 *
		 * @param WC_Product $product
		 * @param string     $type 'regular_price' or 'sale_price'
		 *
		 * @return float|false False when fixed prices are disabled or none is set.
		 */
		private function get_fixed_price( $product, $type ) {
			$settings = $this->get_settings();
			if ( ! $settings || ! method_exists( $settings, 'check_fixed_price' ) || ! $settings->check_fixed_price() ) {
				return false;
			}
			$current = $settings->get_current_currency();
			$meta    = $product->get_meta( 'sale_price' === $type ? '_sale_price_wmcp' : '_regular_price_wmcp', true );
			if ( is_string( $meta ) ) {
				$meta = json_decode( $meta, true );
			}
			if ( ! is_array( $meta ) ) {
				return false;
			}
			if ( function_exists( 'wmc_adjust_fixed_price' ) ) {
				$meta = wmc_adjust_fixed_price( $meta );
			}
			if ( ! isset( $meta[ $current ] ) || '' === $meta[ $current ] || ! is_numeric( $meta[ $current ] ) ) {
				return false;
			}

			return floatval( $meta[ $current ] );
		}

		/**
		 * Undiscounted regular and selling price of a product in the active currency,
		 * from its default-currency raw data: the CURCY fixed price when the merchant
		 * set one, the rate-converted price otherwise (mirrors CURCY's own getters
		 * for a product without runtime changes).
		 *
		 * @param WC_Product $product
		 * @param array      $raw regular_price / price / sale_price in the default currency
		 *
		 * @return array{regular_price: float, price: float}
		 */
		private function get_base_prices_from_raw( $product, $raw ) {
			$regular = isset( $raw['regular_price'] ) && '' !== $raw['regular_price'] ? floatval( $raw['regular_price'] ) : 0;
			$price   = isset( $raw['price'] ) && '' !== $raw['price'] ? floatval( $raw['price'] ) : $regular;
			$on_sale = isset( $raw['sale_price'] ) && '' !== $raw['sale_price'] && $price < $regular;

			$fixed_regular = $this->get_fixed_price( $product, 'regular_price' );
			$base_regular  = false !== $fixed_regular ? $fixed_regular : wmc_get_price( $regular );

			if ( $on_sale ) {
				$fixed_sale = $this->get_fixed_price( $product, 'sale_price' );
				$base_price = false !== $fixed_sale ? $fixed_sale : wmc_get_price( $price );
			} else {
				$base_price = false !== $fixed_regular ? $fixed_regular : wmc_get_price( $price );
			}

			return array(
				'regular_price' => floatval( $base_regular ),
				'price'         => floatval( $base_price ),
			);
		}

		/**
		 * Undiscounted prices of a bump product in the active currency, or null when
		 * the pipeline did not run through product_raw_data() for this object.
		 *
		 * @param WC_Product $product
		 *
		 * @return array|null
		 */
		private function get_base_prices( $product ) {
			$raw = $product->get_meta( '_wfob_wmc_raw' );
			if ( ! is_array( $raw ) ) {
				return null;
			}

			return $this->get_base_prices_from_raw( $product, $raw );
		}

		/**
		 * Apply the bump's discount settings to prices already in the active currency.
		 *
		 * @param array $base    regular_price / price in the active currency
		 * @param array $options bump product settings (discount_type, discount_amount)
		 *
		 * @return float|null
		 */
		private function apply_discount( $base, $options ) {
			if ( ! is_array( $options ) || empty( $options['discount_type'] ) ) {
				return null;
			}
			$type   = trim( $options['discount_type'] );
			$amount = isset( $options['discount_amount'] ) ? floatval( $options['discount_amount'] ) : 0;

			// Fixed amounts and flat prices are entered in the store's default currency.
			if ( $amount > 0 && in_array( $type, array( 'fixed_discount_reg', 'fixed_discount_sale', 'flat_price' ), true ) ) {
				$amount = wmc_get_price( $amount );
			}

			$new_price = WFOB_Common::calculate_discount(
				array(
					'wfob_product_rp'      => $base['regular_price'],
					'wfob_product_p'       => $base['price'],
					'wfob_discount_amount' => $amount,
					'wfob_discount_type'   => $type,
				)
			);

			return is_null( $new_price ) ? null : floatval( $new_price );
		}

		/**
		 * Discounted price of a bump product in the active currency, or null when the
		 * product is not a discounted bump or the store default currency is active.
		 *
		 * @param WC_Product $product
		 *
		 * @return float|null
		 */
		private function get_discounted_price( $product ) {
			$wfob_price = $product->get_meta( '_wfob_price' );
			if ( '' === $wfob_price || ! $this->is_secondary_currency() ) {
				return null;
			}

			$base = $this->get_base_prices( $product );
			if ( is_array( $base ) ) {
				$new_price = $this->apply_discount( $base, $product->get_meta( '_wfob_options' ) );
				if ( ! is_null( $new_price ) ) {
					return $new_price;
				}
			}

			return floatval( wmc_get_price( floatval( $wfob_price ) ) );
		}

		/**
		 * "Choose an option" popup: the variation the shopper picked is discounted by
		 * WFOB_Apply_Discount_Quick_View at priority 98 on an object that carries no
		 * bump meta. CURCY (99) then replaces that discounted value with the
		 * variation's fixed price when one is set, dropping the discount. Recompute
		 * the discount on top of the fixed price; without a fixed price CURCY's
		 * rate conversion of the discounted value is already right.
		 *
		 * @param mixed      $price
		 * @param WC_Product $product
		 *
		 * @return mixed
		 */
		private function maybe_quick_view_price( $price, $product ) {
			//phpcs:disable WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Front-end quick view for a public checkout page; the values only look up the bump's own product list.
			if ( 'variation' !== $product->get_type() || ! wp_doing_ajax() || ! isset( $_REQUEST['wfob_id'], $_REQUEST['item_key'] ) ) {
				return $price;
			}
			if ( ! $this->is_secondary_currency() || false === $this->get_fixed_price( $product, 'regular_price' ) ) {
				return $price;
			}
			$bump_products = WFOB_Common::get_bump_products( absint( $_REQUEST['wfob_id'] ) );
			$item_key      = sanitize_text_field( wp_unslash( $_REQUEST['item_key'] ) );
			//phpcs:enable WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
			if ( ! is_array( $bump_products ) || ! isset( $bump_products[ $item_key ] ) ) {
				return $price;
			}
			$data = $bump_products[ $item_key ];
			if ( empty( $data['variable'] ) || 'yes' !== $data['variable'] || absint( $data['id'] ) !== $product->get_parent_id() || empty( $data['discount_amount'] ) ) {
				return $price;
			}

			$new_price = $this->apply_discount( $this->get_base_prices_from_raw( $product, $product->get_data() ), $data );

			return is_null( $new_price ) ? $price : wc_format_decimal( $new_price );
		}
	}

	new WFOB_Compatibility_With_WooMulti_Curcy();
}
