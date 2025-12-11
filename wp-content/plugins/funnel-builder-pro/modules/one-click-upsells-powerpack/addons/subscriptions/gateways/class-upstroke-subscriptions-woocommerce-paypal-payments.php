<?php
/**
 * Author PhpStorm.
 */

use WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
if ( ! class_exists( 'UpStroke_Subscriptions_WooCommerce_PayPal_Payments' ) ) {
	class UpStroke_Subscriptions_WooCommerce_PayPal_Payments extends WFOCU_Gateway_Integration_PayPal_Payments {

		public function __construct() {

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_to_subscription' ), 10, 3 );
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_keys_to_copy' ), 10, 1 );
			add_filter( 'wfocu_ppcp_gateway_process_client_order_api_args', array( $this, 'maybe_vault_payment' ), 10, 4 );
			add_filter( 'woocommerce_paypal_payments_subscriptions_get_token_for_customer', array( $this, 'maybe_save_renewal_payment_token' ), 1000, 3 );
			add_filter( 'woocommerce_payment_successful_result', array( $this, 'maybe_payment_by_action_schedule' ), 10, 2 );

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
			$subscription->update_meta_data( '_ppcp_paypal_order_id', $order->get_meta( '_ppcp_paypal_order_id' ) );
			$subscription->update_meta_data( 'payment_token_id', $order->get_meta( 'payment_token_id' ) );
			$subscription->save_meta_data();

		}

		public function set_keys_to_copy( $meta_keys ) {
			array_push( $meta_keys, '_ppcp_paypal_order_id', 'payment_token_id', 'wfocu_ppcp_renewal_payment_token' );

			return $meta_keys;
		}

		/**
		 * @param $args
		 * @param $parent_order
		 * @param $posted_data
		 * @param $offer_package
		 *
		 * @return array
		 */
		public function maybe_vault_payment( $args, $parent_order, $posted_data, $offer_package ) {
			$is_subscription = false;
			foreach ( $offer_package['products'] as $product ) {
				$get_product = $product['data'];
				if ( $get_product instanceof WC_Product && ( in_array( $get_product->get_type(), array(
							'subscription',
							'variable-subscription',
							'subscription_variation'
						), true ) || apply_filters( 'wfocu_force_subscription_product', false, $product ) ) ) {
					$is_subscription = true;
					break;
				}
			}


			if ( true === $is_subscription && ( 0 === get_current_user_id() || empty( get_user_meta( get_current_user_id(), 'ppcp-vault-token', true ) ) ) ) {
				/*
				 * get a unique user ID for create vault payment method
				 */
				$user_id = get_current_user_id();
				$new_obj = new CustomerRepository( 'WC-' );
				$user_id = $new_obj->customer_id_for_user( $user_id );

				/*
				 * Modify API supports for support vault payment methods
				 */
				if ( isset( $args['body'] ) && isset( $args['headers'] ) ) {
					$args['body']['payment_source']       = array(
						'paypal' => array(
							'attributes' => array(
								'customer' => array(
									'id' => $user_id,
								),
								'vault'    => array(
									'confirm_payment_token' => 'ON_ORDER_COMPLETION',
									'usage_type'            => 'MERCHANT',
									'customer_type'         => 'CONSUMER'
								)
							)
						)
					);
					$args['headers']['PayPal-Request-Id'] = uniqid( 'BWF_PPCP-', true );
				}
			}

			return $args;
		}

		/**
		 * @param $token
		 * @param $customer
		 * @param $order
		 *
		 * Set token for renewal payment
		 *
		 * @return mixed|PaymentToken
		 */
		public function maybe_save_renewal_payment_token( $token, $customer, $order ) {

			$vault_token = get_user_meta( $customer->get_id(), 'ppcp-vault-token', true );


			if ( is_array( $vault_token ) && count( $vault_token ) > 0 ) {
				return $token;
			}

			$subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $order->get_meta( '_subscription_renewal' ) ) : null;

			if ( $subscription ) {
				$token_id = WooFunnels_UpStroke_PowerPack::get_order_meta( wc_get_order( $subscription->get_parent_id() ), 'wfocu_ppcp_renewal_payment_token' );

				/**
				 * Additional check on primary order for token id
				 */
				if ( empty( $token_id ) ) {
					$primary_id = wc_get_order( $subscription->get_parent_id() )->get_meta( '_wfocu_primary_order', true );

					if ( ! empty( $primary_id ) ) {
						$token_id = WooFunnels_UpStroke_PowerPack::get_order_meta( wc_get_order( $primary_id ), 'wfocu_ppcp_renewal_payment_token' );
					}
				}

				if ( ! empty( $token_id ) ) {
					$token = new PaymentToken( $token_id, new \stdClass(), 'PAYMENT_METHOD_TOKEN' );
				}
			}

			return $token;
		}

		/**
		 * Setup upsell funnel when paypal create action payment scheduled for subscription product
		 *
		 * @param $result
		 * @param $order_id
		 *
		 * @return mixed
		 */
		public function maybe_payment_by_action_schedule( $result, $order_id ) {

			if ( ! $this->is_enabled() ) {
				return $result;
			}

			if ( ! isset( $result['result'] ) || 'success' !== $result['result'] ) {
				return $result;
			}

			$order = wc_get_order( $order_id );

			if ( ! $order instanceof WC_Order ) {
				return $result;
			}

			if ( $this->get_key() !== $order->get_payment_method() ) {
				return $result;
			}

			if ( ! $this->has_subscription( $order_id ) ) {
				return $result;
			}

			if ( ! $this->should_tokenize() ) {
				return $result;
			}

			/**
			 * return if funnel already setup
			 */
			if ( did_action( 'wfocu_funnel_init_event' ) ) {
				return $result;
			}

			/** Check and re-setup funnel Paypal created action schedule for subscription product  */
			WFOCU_Core()->log->log( 'Order #' . $order_id . ': Funnel re-setup for paypal subscription product.' );
			WFOCU_Core()->public->maybe_setup_upsell( $order_id );
			$result['redirect'] = $this->get_wc_gateway()->get_return_url( $order );

			return $result;
		}

		/**
		 * Checks if order contains subscription.
		 *
		 * @param int $order_id The order Id.
		 *
		 * @return boolean Whether order is a subscription or not.
		 */
		public function has_subscription( $order_id ) {
			return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
		}

	}

	if ( class_exists( 'WC_Subscriptions' ) ) {
		new UpStroke_Subscriptions_WooCommerce_PayPal_Payments();
	}
}