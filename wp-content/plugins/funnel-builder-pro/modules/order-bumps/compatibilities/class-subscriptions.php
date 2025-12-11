<?php
if ( ! class_exists( 'WFOB_Compatibility_Subscription' ) ) {
	class WFOB_Compatibility_Subscription {
		private static $instance;

		/**
		 * @return mixed
		 */
		public static function getInstance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct() {
			add_filter( 'wfob_show_product_price', [ $this, 'stop_printing_price' ], 10, 2 );
			add_filter( 'wfob_show_product_price_placeholder', [ $this, 'display_price' ], 10, 4 );
		}

		/**
		 * @param $status boolean
		 * @param $pro WC_Product
		 *
		 * @return bool
		 */
		public function stop_printing_price( $status, $pro ) {
			if ( in_array( $pro->get_type(), WFOB_Common::get_subscription_product_type() ) ) {
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
				$main_product_price    = WFOB_Common::get_subscription_price( $temp, $s_price_data );
				if ( '' !== $cart_item_key ) {
					$price_html = $price_data['price'];
				} else {
					$price_html = WFOB_Common::get_subscription_price( $pro, $price_data );
				}
				if ( $main_product_price == $price_html ) {
					$price_html = wc_price( $price_html );
				} else {
					$price_html = wc_format_sale_price( $main_product_price, $price_html );
				}

			}

			return $price_html;
		}
	}

	WFOB_Compatibility_Subscription::getInstance();
}