<?php
if ( ! class_exists( 'WFOB_Apply_Discount_Quick_View' ) ) {
	class WFOB_Apply_Discount_Quick_View {
		private $item_key = '';
		private $item_data = [];
		private $wfob_id = '';
		private $hook_priority = 98;

		public function __construct() {
			add_action( 'wfob_qv_images', [ $this, 'prepare_data' ] );
			add_filter( 'woocommerce_product_variation_get_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority, 2 );
			add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority, 2 );
		}


		public function prepare_data() {
			if ( isset( $_REQUEST['wfob_id'] ) ) {
				$this->wfob_id  = absint( $_REQUEST['wfob_id'] );
				$this->item_key = $_REQUEST['item_key'];
				$bump_products  = WFOB_Common::get_bump_products( $this->wfob_id );

				if ( isset( $bump_products[ $this->item_key ] ) ) {
					$this->item_data = $bump_products[ $this->item_key ];
				}

			}

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
				$new_price = $this->get_price( $product_global, $this->item_data, $get_price );
				if ( ! is_null( $new_price ) ) {
					$get_price = $new_price;
				}
			}
			add_filter( 'woocommerce_product_variation_get_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority, 2 );
			add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'wcct_trigger_get_price' ), $this->hook_priority, 2 );

			return $get_price;

		}

		private function get_price( $pro, $data, $get_price ) {
			if ( ! $pro instanceof WC_Product ) {
				return null;
			}
			$qty      = 1;
			$raw_data = $pro->get_data();
			if ( empty( $raw_data['regular_price'] ) || 0 == $data['discount_amount'] ) {
				return null;
			}
			$discount_type   = trim( $data['discount_type'] );
			$price           = (float) $get_price;
			$regular_price   = (float) $pro->get_regular_price();
			$discount_amount = (float) ( apply_filters( 'wfob_discount_amount_data', $data['discount_amount'], $discount_type ) );
			$discount_data   = [
				'wfob_product_rp'      => $regular_price * $qty,
				'wfob_product_p'       => $price * $qty,
				'wfob_discount_amount' => $discount_amount,
				'wfob_discount_type'   => $discount_type,
			];
			if ( 'fixed_discount_sale' == $discount_type || 'fixed_discount_reg' == $discount_type ) {
				$discount_data['wfob_discount_amount'] = $discount_amount * $qty;
			}
			$new_price = WFOB_Common::calculate_discount( $discount_data );
			if ( ! is_null( $new_price ) ) {
				$parse_data = apply_filters( 'wfob_discounted_price_data', [ 'regular_price' => $regular_price, 'price' => $new_price ], '', $pro, $raw_data, );

				return $parse_data['price'];
			}

			return null;
		}
	}

	new WFOB_Apply_Discount_Quick_View();
}