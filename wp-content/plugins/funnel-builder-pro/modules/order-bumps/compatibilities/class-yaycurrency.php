<?php

use Yay_Currency\Helpers\FixedPriceHelper;

use Yay_Currency\Helpers\SupportHelper;

if ( ! class_exists( 'WFOB_YayCurrency' ) ) {
	/**
	 *
	 * • YayCurrency – WooCommerce Multi-Currency Switcher
	 *
	 * • https://wordpress.org/plugins/yaycurrency/
	 */
	class WFOB_YayCurrency {

		private $is_edit = 'view';


		public function construct() {

// Only apply for products with a fixed price setting

			if ( ! class_exists( '\Yay_Currency\Helpers\FixedPriceHelper' ) || ! get_option( 'yay_currency_set_fixed_price', 0 ) ) {

				return;

			}


// Calculate regular and final price, applying product discount only

			add_action( 'yay_currency_set_cart_contents', array( $this, 'product_addons_set_cart_contents' ), 10, 4 );


// Retrieve regular price in cart and checkout, applying product discount only

			$regular_prices = [ 'product_get_regular_price', 'product_variation_get_regular_price', 'variation_prices_regular_price' ];

			foreach ( $regular_prices as $regular_price ) {

				add_filter( 'woocommerce_' . $regular_price, array( $this, 'get_regular_price' ), 20, 2 );

			}


// Retrieve final price in cart and checkout, applying product discount only

			add_filter( 'yay_currency_product_price_3rd_with_condition', array( $this, 'get_final_price' ), 9, 2 );


			add_filter( 'wfob_set_bump_product_price_params', [ $this, 'do_not_set_bump_prices' ] );

			add_filter( 'wfob_product_raw_data', [ $this, 'raw_data' ], 10, 2 );

			add_filter( 'wfob_product_switcher_price_data', [ $this, 'change_price' ], 20, 2 );


		}


		protected function get_fixed_product_data( $data, $product, $apply_currency ) {

			$custom_fixed_prices = $product->get_meta( 'yay_currency_custom_fixed_prices', true );

			if ( ! empty( $custom_fixed_prices ) && isset( $custom_fixed_prices[ $apply_currency['currency'] ] ) ) {

				if ( ! empty( $custom_fixed_prices[ $apply_currency['currency'] ]['price'] ) ) {


					$regular_price = isset( $custom_fixed_prices[ $apply_currency['currency'] ]['regular_price'] ) && ! empty( $custom_fixed_prices[ $apply_currency['currency'] ]['regular_price'] ) ? $custom_fixed_prices[ $apply_currency['currency'] ]['regular_price'] : $data['regular_price'];


					if ( ! empty( $product->get_data()['sale_price'] ) ) {

						$price = $custom_fixed_prices[ $apply_currency['currency'] ]['sale_price'] ?: $data['price'];

					} else {

						$price = $regular_price;

					}


					$data['price'] = $price;

					$data['regular_price'] = $regular_price;

				}

			}

			return $data;

		}


// Calculate regular and final price, applying product discount only

		public function product_addons_set_cart_contents( $cart_contents, $cart_item_key, $cart_item, $apply_currency ) {


			if ( isset( $cart_item['_wfob_options']['discount_amount'], $cart_item['_wfob_options']['discount_type'] ) ) {

				$product = $cart_item['data'];

				$fixed_product_price = FixedPriceHelper::product_is_set_fixed_price_by_currency( $product, $apply_currency );


				$fixed_product_data = self::get_fixed_product_data( $product->get_data(), $product, $apply_currency );

				$fixed_product_regular_price = $fixed_product_data['regular_price'];

				$discount_data = [

					'wfob_product_rp' => $fixed_product_regular_price,

					'wfob_product_p' => $fixed_product_price,

					'wfob_discount_amount' => $cart_item['_wfob_options']['discount_amount'],

					'wfob_discount_type' => $cart_item['_wfob_options']['discount_type'],

				];


				$new_price = WFOB_Common::calculate_discount( $discount_data );

				SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_wfob_regular_price', $fixed_product_regular_price );

				SupportHelper::set_cart_item_objects_property( $cart_contents[ $cart_item_key ]['data'], 'yay_wfob_discounted_price', $new_price );

			}

		}


// Retrieve regular price in cart and checkout, applying product discount only

		public function get_regular_price( $price, $product ) {

			$regular_price = SupportHelper::get_cart_item_objects_property( $product, 'yay_wfob_regular_price' );

			return $regular_price ? $regular_price : $price;

		}


// Retrieve final price in cart and checkout, applying product discount only

		public function get_final_price( $price, $product ) {

			$discounted_price = SupportHelper::get_cart_item_objects_property( $product, 'yay_wfob_discounted_price' );

			return $discounted_price ? $discounted_price : $price;

		}


		public function do_not_set_bump_prices() {

			return false;

		}


		public function raw_data( $data, $product ) {

			try {

				$apply_currency = \Yay_Currency\Helpers\YayCurrencyHelper::get_current_currency();

				$fixed_product_price = FixedPriceHelper::product_is_set_fixed_price_by_currency( $product, $apply_currency );


				if ( ! $fixed_product_price ) {

					return $data;

				}


				$this->is_edit = 'edit';

				$data = self::get_fixed_product_data( $data, $product, $apply_currency );


			} catch ( \Exception|\Error $e ) {


			}


			return $data;


		}


		public function change_price( $price_data, $pro ) {

			$price_data['regular_org'] = $pro->get_regular_price( $this->is_edit );

			$price_data['price'] = $pro->get_price( $this->is_edit );


			return $price_data;

		}

	}

	new WFOB_YayCurrency();
}