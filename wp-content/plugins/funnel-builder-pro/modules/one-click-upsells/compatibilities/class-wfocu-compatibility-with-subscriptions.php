<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_Subscriptions' ) ) {
	class WFOCU_Compatibility_With_Subscriptions {

		public function __construct() {
			if ( class_exists( 'WC_Subscriptions' ) || class_exists( 'WC_Subscriptions_Core_Plugin' ) ) {
				add_filter( 'wfocu_front_payment_gateway_integration_enabled', array( $this, 'maybe_disable_integration_when_subscription_in_cart' ), 10, 2 );

			}
		}

		public function is_enable() {

			return class_exists( 'WC_Subscriptions' );
		}


		/**
		 * @hooked over `wfocu_front_payment_gateway_integration_enabled`
		 * Check if order contains any subscription & if have subscription then discard integration & allow WooSubscription to take over tokenization
		 * It is such an important hook to prevent any such error during the checkout, as woocommerce subscriptions does almost the same work in tokenizing the user as we do.
		 * It is required to hold back our functionality to prevent such cases.
		 *
		 * @param $is_enable
		 * @param WC_order $order
		 *
		 * @return false when having subscription in the cart
		 * @see wcs_order_contains_subscription()
		 *
		 */
		public function maybe_disable_integration_when_subscription_in_cart( $is_enable, $order ) {
			if ( function_exists( 'wcs_order_contains_subscription' ) && is_a( $order, 'WC_Abstract_Order' ) ) {
				$have_subscription = wcs_order_contains_subscription( $order );
				if ( true === $have_subscription ) {
					return false;
				}
			}

			return $is_enable;
		}


	}


	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Subscriptions(), 'subscriptions' );
}