<?php
if ( ! class_exists( 'WFOB_Apply_Discount_Quick_View' ) ) {
	#[\AllowDynamicProperties]
	class WFOB_Apply_Discount_Quick_View {
		private $item_key      = '';
		private $item_data     = array();
		private $wfob_id       = '';
		private $hook_priority = 98;

		public function __construct() {
			add_action( 'wfob_qv_images', array( $this, 'prepare_data' ) );
			add_filter( 'woocommerce_product_variation_get_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority, 2 );
			add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority, 2 );
		}


		public function prepare_data() {
			//phpcs:disable WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Front-end quick view for a public checkout page; both values are only used to look up the bump's own product list.
			if ( isset( $_REQUEST['wfob_id'] ) ) {
				$this->wfob_id  = absint( $_REQUEST['wfob_id'] );
				$this->item_key = isset( $_REQUEST['item_key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['item_key'] ) ) : '';
				$bump_products  = WFOB_Common::get_bump_products( $this->wfob_id );

				if ( isset( $bump_products[ $this->item_key ] ) ) {
					$this->item_data = $bump_products[ $this->item_key ];
				}
			}
			//phpcs:enable WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
		}

		public function wcct_trigger_get_price( $get_price, $product_global ) {
			if ( ! $product_global instanceof WC_Product ) {
				return $get_price;
			}
			if ( empty( $this->item_data ) ) {
				return $get_price;
			}

			remove_filter( 'woocommerce_product_variation_get_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority );
			remove_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority );
			$id = $product_global->get_parent_id();
			if ( isset( $this->item_data['variable'] ) && 'yes' == $this->item_data['variable'] && $this->item_data['id'] == $id ) {
				$new_price = $this->get_price( $product_global, $this->item_data );
				if ( ! is_null( $new_price ) ) {
					// Hand a numeric string back to the rest of the getter chain. Price
					// getters normally yield the meta value as a string, and currency
					// plugins cache on it (CURCY keys its per-product price cache by the
					// incoming price), so a raw float triggers "Implicit conversion from
					// float ... to int loses precision" on any non-whole result.
					$get_price = wc_format_decimal( $new_price );
				}
			}
			add_filter( 'woocommerce_product_variation_get_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority, 2 );
			add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority, 2 );

			return $get_price;
		}

		/**
		 * Discount the variation the shopper picked in the quick view popup.
		 *
		 * Every input comes from the product's raw data and goes through the same
		 * compatibility filters `WFOB_Common::set_product_price()` uses, so the popup
		 * computes the discount in exactly the currency space the cart will. Reading
		 * `$pro->get_regular_price()` here instead ran the *whole* regular-price getter
		 * chain while `$get_price` had only travelled as far as this filter's priority.
		 * With a currency plugin that converts after us (CURCY hooks the price getters
		 * at 99) that mixed a converted regular price into an unconverted sale price,
		 * and the currency plugin then converted the result a second time: a 50%-off
		 * bump on a ₹600 variation showed ₹3,000 instead of ₹300, and a fixed discount
		 * larger than the unconverted sale price clamped the popup price to zero.
		 *
		 * @param $pro  WC_Product
		 * @param $data array Bump product settings (discount type and amount).
		 *
		 * @return float|null
		 */
		private function get_price( $pro, $data ) {
			if ( ! $pro instanceof WC_Product ) {
				return null;
			}
			$qty      = 1;
			$raw_data = $pro->get_data();
			if ( empty( $raw_data['regular_price'] ) || 0 == $data['discount_amount'] ) {
				return null;
			}
			$discount_type   = trim( $data['discount_type'] );
			$raw_data        = apply_filters( 'wfob_product_raw_data', $raw_data, $pro, '' );
			$regular_price   = (float) apply_filters( 'wfob_discount_regular_price_data', $raw_data['regular_price'], '' );
			$price           = (float) apply_filters( 'wfob_discount_price_data', $raw_data['price'], '' );
			$discount_amount = (float) ( apply_filters( 'wfob_discount_amount_data', $data['discount_amount'], $discount_type, '' ) );
			$discount_data   = array(
				'wfob_product_rp'      => $regular_price * $qty,
				'wfob_product_p'       => $price * $qty,
				'wfob_discount_amount' => $discount_amount,
				'wfob_discount_type'   => $discount_type,
			);
			if ( 'fixed_discount_sale' == $discount_type || 'fixed_discount_reg' == $discount_type ) {
				$discount_data['wfob_discount_amount'] = $discount_amount * $qty;
			}
			$new_price = WFOB_Common::calculate_discount( $discount_data );
			if ( ! is_null( $new_price ) ) {
				$parse_data = apply_filters(
					'wfob_discounted_price_data',
					array(
						'regular_price' => $regular_price,
						'price'         => $new_price,
					),
					'',
					$pro,
					$raw_data,
				);

				return $parse_data['price'];
			}

			return null;
		}
	}

	new WFOB_Apply_Discount_Quick_View();
}
