<?php
/**
 * Author PhpStorm.
 */

if ( class_exists( 'WFOCU_Paypal_For_WC_Gateway_Express_Checkout' ) ) {

	class UpStroke_Subscriptions_PayPal_Checkout extends WFOCU_Paypal_For_WC_Gateway_Express_Checkout {

		public function __construct() {

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_meta_to_subscription' ), 10, 3 );
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_keys_to_copy' ), 10, 1 );

		}

		/**
		 * Save Subscription Details
		 *
		 * @param WC_Subscription $subscription
		 * @param $key
		 * @param WC_Order $order
		 */
		public function save_meta_to_subscription( $subscription, $key, $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$get_customer_id = $order->get_meta( '_payment_tokens_id', true );

			if ( ! empty( $get_customer_id ) ) {
				$subscription->update_meta_data( '_payment_tokens_id', $get_customer_id );
				$subscription->save();
			}

		}

		public function set_keys_to_copy( $meta_keys ) {
			array_push( $meta_keys, '_payment_tokens_id' );

			return $meta_keys;
		}

	}

	if ( class_exists( 'WC_Subscriptions' ) ) {
		new UpStroke_Subscriptions_PayPal_Checkout();
	}
}
