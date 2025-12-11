<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_WooMulti_Curcy' ) ) {
	class WFOB_Compatibility_With_WooMulti_Curcy {
		private $woo_multi_currency_data = null;

		public function __construct() {
			add_action( 'woocommerce_calculate_totals', [ $this, 'remove_actions' ] );
			add_filter( 'wfob_product_raw_data', [ $this, 'product_raw_data' ], 10, 3 );
			add_filter( 'wfob_product_switcher_price_data', [ $this, 'change_price' ], 21, 3 );
			add_filter( 'wfob_discount_amount_data', [ $this, 'wfob_discount_amount_data' ], 10, 2 );
			add_filter( 'wfob_show_product_price', [ $this, 'stop_printing_price' ], 10, 2 );
			add_filter( 'wfob_show_product_price_placeholder', [ $this, 'display_price' ], 12, 4 );
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

			$price_data['regular_org'] = $pro->get_regular_price();
			$price_data['price']       = $pro->get_price();

			return $price_data;
		}


		/**
		 * @param $raw_data
		 * @param $product WC_Product;
		 *
		 * @return mixed
		 */
		public function product_raw_data( $raw_data, $product ) {
			try {

				$settings = $this->get_currency_instance();

				if ( is_null( $settings ) ) {
					return $raw_data;
				}

				$current_currency = $settings->get_current_currency();
				$fixed_price      = $settings->check_fixed_price();
				$default_currency = $settings->get_default_currency();
				if ( $current_currency == $default_currency ) {
					return $raw_data;
				}

				if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
					if ( isset( $raw_data['price'] ) && ! empty( $raw_data['price'] ) ) {
						$raw_data['price'] = wmc_get_price( $raw_data['price'], $current_currency );
					}
					if ( isset( $raw_data['sale_price'] ) && ! empty( $raw_data['sale_price'] ) ) {
						$raw_data['sale_price'] = wmc_get_price( $raw_data['sale_price'], $current_currency );
					}

				}

				if ( ! $fixed_price ) {
					return $raw_data;
				}

				$regular_price_wmcp = json_decode( get_post_meta( $product->get_id(), '_regular_price_wmcp', true ), true );
				$sale_price_wmcp    = json_decode( get_post_meta( $product->get_id(), '_sale_price_wmcp', true ), true );
				if ( ! isset( $regular_price_wmcp[ $current_currency ] ) || $regular_price_wmcp[ $current_currency ] < 0 ) {
					return $raw_data;
				}
				$raw_data['regular_price'] = $regular_price_wmcp[ $current_currency ];
				if ( $raw_data['regular_price'] > 0 ) {


					$sale_price = ! is_null( $sale_price_wmcp ) && isset( $sale_price_wmcp[ $current_currency ] ) ? $sale_price_wmcp[ $current_currency ] : 0;

					if ( $sale_price > 0 ) {
						$raw_data['price']      = wmc_revert_price( $sale_price );
						$raw_data['sale_price'] = wmc_revert_price( $sale_price );
					} else {
						$raw_data['price'] = wmc_revert_price( $raw_data['regular_price'] );
					}
					$raw_data['regular_price'] = wmc_revert_price( $raw_data['regular_price'] );

				}

				return $raw_data;
			} catch ( Exception $e ) {
				echo $e->getMessage();
				error_log( 'WFACP_Compatibility_With_WooMulti_Curcy::wfacp_product_raw_data - ' . $e->getMessage() );

				return $raw_data;
			}
		}

		/**
		 * @return WOOMULTI_CURRENCY_Data
		 */
		private function get_currency_instance() {
			try {
				if ( is_null( $this->woo_multi_currency_data ) && class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
					$this->woo_multi_currency_data = WOOMULTI_CURRENCY_Data::get_ins();
				}
				if ( is_null( $this->woo_multi_currency_data ) && class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
					$this->woo_multi_currency_data = WOOMULTI_CURRENCY_F_Data::get_ins();
				}

				return $this->woo_multi_currency_data;
			} catch ( Exception $e ) {
				error_log( 'WFACP_Compatibility_With_WooMulti_Curcy::get_currency_instance - ' . $e->getMessage() );

				return null;
			}
		}

		/**
		 * @param $price_data
		 * @param $product WC_Product
		 *
		 * @return mixed
		 */
		public function wfob_discount_amount_data( $discount_amount, $discount_type ) {
			if ( ! did_action( 'wc_ajax_wfob_quick_view_ajax' ) ) {
				return $discount_amount;
			}
			$settings = $this->get_currency_instance();
			if ( is_null( $settings ) ) {
				return $discount_amount;
			}
			switch ( $discount_type ) {
				case 'fixed_discount_reg':
					$discount_amount = wmc_get_price( $discount_amount );
					break;
				case 'fixed_discount_sale':
					$discount_amount = wmc_get_price( $discount_amount );
					break;
			}

			return $discount_amount;
		}

		/**
		 * @param $status boolean
		 * @param $pro WC_Product
		 *
		 * @return bool
		 */
		public function stop_printing_price( $status, $pro ) {
			if ( in_array( $pro->get_type(), WFOB_Common::get_subscription_product_type() ) ) {
				remove_filter( 'wfob_show_product_price_placeholder', [ WFOB_Compatibility_Subscription::getInstance(), 'display_price' ] );
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