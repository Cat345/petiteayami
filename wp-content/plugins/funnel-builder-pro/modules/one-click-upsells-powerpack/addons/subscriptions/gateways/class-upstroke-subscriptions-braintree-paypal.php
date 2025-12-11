<?php
/**
 * Author PhpStorm.
 */
if ( ! class_exists( 'UpStroke_Subscriptions_Braintree_PayPal' ) ) {
	class UpStroke_Subscriptions_Braintree_PayPal extends WFOCU_Gateway_Integration_Braintree_PayPal {

		public function __construct() {

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_braintree_paypal_token_to_subscription' ), 10, 3 );
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_braintree_paypal_keys_to_copy' ), 10, 1 );
		}

		/**
		 * Save Subscription Details
		 *
		 * @param WC_Subscription $subscription
		 * @param $key
		 * @param WC_Order $order
		 */
		public function save_braintree_paypal_token_to_subscription( $subscription, $key, $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$get_braintree_token = $order->get_meta( '_wc_braintree_paypal_payment_token', true );

			if ( ! empty( $get_braintree_token ) ) {
				$subscription->update_meta_data( '_wc_braintree_paypal_payment_token', $get_braintree_token );
				$subscription->save();
			}

			if ( $order instanceof WC_Order && $this->get_key() === $order->get_payment_method() ) {
				add_filter( 'wc_payment_gateway_' . $this->get_key() . '_get_order', array( $this, 'get_order' ), 999 );

				/**
				 * Sometimes when upstroke creates subscription, it also creates user & because payment processes before user creation the token is not getting inserted into usermeta
				 * This means that order ID is the only place where token is available making subscription renewals to fail.
				 */
				$order = $this->get_wc_gateway()->add_transaction_data( $this->get_wc_gateway()->get_order( $order ) );

			}
		}

		public function set_braintree_paypal_keys_to_copy( $meta_keys ) {
			array_push( $meta_keys, '_wc_braintree_paypal_payment_token' );
			array_push( $meta_keys, '_wc_braintree_paypal_customer_id' );
			array_push( $meta_keys, '_wc_braintree_paypal_customer_id' );

			return $meta_keys;
		}

	}

	if ( class_exists( 'WC_Subscriptions' ) ) {
		new UpStroke_Subscriptions_Braintree_PayPal();
	}
}