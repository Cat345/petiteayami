<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_WooMultiCurrency' ) ) {
	class WFOCU_Compatibility_With_WooMultiCurrency {

		public function __construct() {
			if ( defined( 'WOOMULTI_CURRENCY_VERSION' ) ) {
				add_filter( 'woocommerce_get_checkout_order_received_url', array( $this, 'maybe_add_currency_converter_url' ), 999, 2 );

				/**
				 * reset currency change price hook for upsell offer
				 */
				add_action( 'init', function () {
					if ( ! class_exists( 'WOOMULTI_CURRENCY_Frontend_Price' ) ) {
						return;
					}
					if ( WFOCU_Core()->template_loader->is_valid_state_for_data_setup() ) {
						$obj = new WOOMULTI_CURRENCY_Frontend_Price();
						add_action( 'init', array( $obj, 'add_change_price_hooks' ), 14 );
						remove_action( 'init', array( $obj, 'add_change_price_hooks' ), 100 );
					}
				}, 10 );

			}

		}

		public function is_enable() {
			if ( defined( 'WOOMULTI_CURRENCY_VERSION' ) ) {
				return true;
			}

			return false;
		}

		/**
		 *
		 * @param $url
		 * @param WC_Order $order
		 *
		 * @return string
		 */
		public function maybe_add_currency_converter_url( $url, $order ) {

			if ( ! $order instanceof WC_Order ) {
				return $url;
			}

			return add_query_arg( array( 'wmc-currency' => strtoupper( $order->get_currency() ) ), $url );
		}


		/**
		 *
		 * Modifies the amount for the fixed discount given by the admin in the currency selected.
		 *
		 * @param integer|float $price
		 *
		 * @return float
		 */
		public function alter_fixed_amount( $price, $currency = null ) {
			return wmc_get_price( $price, $currency );
		}

		function get_fixed_currency_price_reverse( $price, $from = null, $base = null ) {
			$data = new WOOMULTI_CURRENCY_Data();
			$from = ( is_null( $from ) ) ? $data->get_current_currency() : $from;
			$base = ( is_null( $base ) ) ? get_option( 'woocommerce_currency' ) : $base;

			$rates = $data->get_exchange( $from, $base );
			if ( is_array( $rates ) && isset( $rates[ $base ] ) ) {
				$price = $price * $rates[ $base ];
			}

			return $price;
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_WooMultiCurrency(), 'woomulticurrency' );


}