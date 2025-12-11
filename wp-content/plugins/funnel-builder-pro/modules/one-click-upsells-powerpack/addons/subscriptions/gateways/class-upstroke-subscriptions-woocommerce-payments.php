<?php
/**
 * Author PhpStorm.
 */
if ( ! class_exists( 'UpStroke_Subscriptions_WooCommerce_Payments' ) ) {
	class UpStroke_Subscriptions_WooCommerce_Payments extends WFOCU_Gateway_Integration_WooCommerce_Payments {

		public function __construct() {

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_to_subscription' ), 10, 3 );
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_keys_to_copy' ), 10, 1 );
		}

		/**
		 * Save Subscription details
		 *
		 * @param WC_Subscription $subscription
		 * @param $key
		 * @param WC_Order $order
		 */
		public function save_to_subscription( $subscription, $key, $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( $this->get_key() !== $order->get_payment_method() ) {
				return;
			}
			$payment_tokens = $order->get_payment_tokens();
			$customer_id    = $order->get_meta( '_stripe_customer_id', true );
			$user           = $order->get_user();


			$global = WC_Payments::is_network_saved_cards_enabled();
			$gateway = WC_Payments::get_gateway();
			$is_test_mode = method_exists( $gateway, 'is_in_test_mode' ) ? $gateway->is_in_test_mode() : WC_Payments::mode()->is_test();

			$get_id = $is_test_mode ? WC_Payments_Customer_Service::WCPAY_TEST_CUSTOMER_ID_OPTION : WC_Payments_Customer_Service::WCPAY_LIVE_CUSTOMER_ID_OPTION;
			update_user_option( $user->ID, $get_id, $customer_id, $global );
			if ( ! empty( $customer_id ) && ! empty( $payment_tokens ) ) {

				if ( is_array( $payment_tokens ) && count( $payment_tokens ) > 0 ) {
					foreach ( $payment_tokens as $token ) {
						if ( ! $token instanceof WC_Payment_Token ) {
							$token = WC_Payment_Tokens::get( $token );

						}
						$subscription->add_payment_token( $token );
					}
				}


				$subscription->update_meta_data( '_stripe_customer_id', $customer_id );
				$subscription->save_meta_data();
			}

		}



		public function set_keys_to_copy( $meta_keys ) {
			array_push( $meta_keys, '_payment_tokens', '_stripe_customer_id' );

			return $meta_keys;
		}

	}

	if ( class_exists( 'WC_Subscriptions' ) ) {
		new UpStroke_Subscriptions_WooCommerce_Payments();
	}
}