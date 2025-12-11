<?php
/**
 * Author PhpStorm.
 */
if ( ! class_exists( 'UpStroke_Subscriptions_Stripe' ) ) {
	class UpStroke_Subscriptions_Stripe extends WFOCU_Gateway_Integration_Stripe {

		public function __construct() {

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_stripe_source_to_subscription' ), 10, 3 );
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_stripe_keys_to_copy' ), 10, 1 );
		}

		/**
		 * Save Subscription details
		 *
		 * @param WC_Subscription $subscription
		 * @param $key
		 * @param WC_Order $order
		 */
		public function save_stripe_source_to_subscription( $subscription, $key, $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$get_customer_id = $order->get_meta( '_stripe_customer_id', true );
			$get_source_id   = $order->get_meta( '_stripe_source_id', true );

			if ( ! empty( $get_customer_id ) && ! empty( $get_source_id ) ) {
				$subscription->update_meta_data( '_stripe_customer_id', $get_customer_id );
				$wfocu_token_id = WooFunnels_UpStroke_PowerPack::get_order_meta( $order, '_wfocu_stripe_source_id' );
				if ( ! empty( $wfocu_token_id ) ) {
					$get_source_id = $wfocu_token_id;
				}

				$subscription->update_meta_data( '_stripe_source_id', $get_source_id );
				$subscription->save();
			}

		}

		public function set_stripe_keys_to_copy( $meta_keys ) {
			array_push( $meta_keys, '_stripe_customer_id', '_stripe_source_id' );

			return $meta_keys;
		}

	}

	if ( class_exists( 'WC_Subscriptions' ) ) {
		new UpStroke_Subscriptions_Stripe();
	}
}