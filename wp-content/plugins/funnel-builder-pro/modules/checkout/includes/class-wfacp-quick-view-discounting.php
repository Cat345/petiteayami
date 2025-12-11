<?php
if ( ! class_exists( 'WFACP_Discount_At_Quick_view' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Discount_At_Quick_view {
		private $item_key = '';
		private $item_data = '';
		private $wfob_id = '';
		private $product_id = '';

		public function __construct() {
			add_action( 'wfac_qv_images', [ $this, 'prepare_data' ] );
			add_filter( 'woocommerce_product_variation_get_price', array( $this, 'wcct_trigger_get_price' ), 999, 2 );
			add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'wcct_trigger_get_price' ), 999, 2 );
		}


		public function prepare_data() {

			if ( isset( $_REQUEST['wfacp_id'] ) ) {
				$this->wfob_id  = absint( $_REQUEST['wfacp_id'] );
				$this->item_key = $_REQUEST['data']['item_key'];
				$bump_products  = WFACP_Common::get_page_product( $this->wfob_id );

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
			$id = $product_global->get_parent_id();
			if ( isset( $this->item_data['variable'] ) && 'yes' == $this->item_data['variable'] && $this->item_data['id'] == $id ) {
				$new_price = $this->get_price( $product_global, $this->item_data, $get_price );
				if ( ! is_null( $new_price ) ) {
					$get_price = $new_price;
				}
			}


			return $get_price;

		}

		private function get_price( $pro, $data, $get_price ) {
			if ( ! $pro instanceof WC_Product ) {
				return null;
			}

			$qty             = 1;
			$raw_data        = $pro->get_data();
			$discount_type   = trim( $data['discount_type'] );
			$discount_amount = $data['discount_amount'];

			if ( $discount_amount == 0 ) {
				return null;
			}
			$discount_amount = (float) ( apply_filters( 'wfacp_discount_amount_data', $discount_amount, $discount_type ) );
			$price           = (float) $get_price;
			$regular_price   = (float) $pro->get_regular_price();


			$discount_data = [
				'wfacp_product_rp'      => $regular_price * $qty,
				'wfacp_product_p'       => $price * $qty,
				'wfacp_discount_amount' => $discount_amount,
				'wfacp_discount_type'   => $discount_type,
			];

			if ( 'fixed_discount_sale' == $discount_type || 'fixed_discount_reg' == $discount_type ) {
				$discount_data['wfacp_discount_amount'] = $discount_amount * $qty;
			}

			$new_price = WFACP_Common::calculate_discount( $discount_data );
			if ( ! is_null( $new_price ) ) {
				return $new_price;
			}
			return null;
		}
	}

	new WFACP_Discount_At_Quick_view();
}
