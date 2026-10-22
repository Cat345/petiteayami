<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFFN_Compatibility_FK_Stripe
 * plugin: FunnelKit Stripe Woo Payment Gateway (funnelkit-stripe-woo-payment-gateway)
 *
 * Stripe builds before 1.16.0 resolve the order on the order-pay endpoint straight from
 * the `order-pay` query var while localizing `fkwcs_data`, without checking the `key`
 * parameter or who owns the order. WooCommerce validates the key only inside the
 * shortcode render, which runs after `wp_enqueue_scripts`, so billing PII and order data
 * were readable by anyone enumerating sequential order IDs (Stripe PR #1498 / #1497).
 *
 * This class scrubs that data from the localized payload for any request that does not
 * pass the same rule Stripe 1.16.0 applies itself. It only removes data, never adds any,
 * and steps aside as soon as the gateway can verify the order on its own.
 */
if ( ! class_exists( 'WFFN_Compatibility_FK_Stripe' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Compatibility_FK_Stripe {

		/**
		 * First Stripe release that verifies the order-pay order before localizing it.
		 */
		const FIXED_VERSION = '1.16.0';

		/**
		 * Express-wallet `fkwcs_data` keys built from the order-pay order. Stripe's JS reads
		 * every one of these behind a truthiness check, and the fixed gateway omits them for an
		 * unverified request, so removing them is the exact shape the JS expects.
		 *
		 * `current_user_billing_for_order` is handled separately: getBillingAddress() returns it
		 * unguarded, so it must stay an empty array, which is what Stripe 1.16.0 emits.
		 *
		 * @var string[]
		 */
		private $order_pay_keys = array(
			'gpay_cart_data',
			'fkwcs_google_pay_data',
			'fkwcs_apple_pay_order_pay_data',
			'fkwcs_amazon_pay_order_pay_data',
		);

		/**
		 * Memoized verification result for the current request.
		 *
		 * @var bool|null
		 */
		private $is_verified = null;

		public function __construct() {
			if ( ! $this->is_enable() ) {
				return;
			}

			add_filter( 'fkwcs_localized_data', array( $this, 'scrub_localized_data' ), PHP_INT_MAX );
			add_filter( 'fkwcs_current_user_billing_details_order', array( $this, 'scrub_billing_details' ), PHP_INT_MAX, 2 );
		}

		/**
		 * Load only while the installed Stripe build cannot verify the order itself.
		 *
		 * @return bool
		 */
		public function is_enable() {
			if ( ! defined( 'FKWCS_VERSION' ) || ! function_exists( 'WC' ) ) {
				return false;
			}

			if ( version_compare( FKWCS_VERSION, self::FIXED_VERSION, '>=' ) ) {
				return false;
			}

			// A backported hotfix would carry the helper without the version bump.
			if ( method_exists( '\FKWCS\Gateway\Stripe\Abstract_Payment_Gateway', 'get_verified_order_pay_order' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Drop every order-derived key from `fkwcs_data` on an unverified order-pay request.
		 *
		 * Runs after each gateway's own `fkwcs_localized_data` callback (they hook at 999).
		 *
		 * @param array $data Localized data.
		 *
		 * @return array
		 */
		public function scrub_localized_data( $data ) {
			if ( ! is_array( $data ) || ! $this->is_order_pay_request() || $this->is_verified_order_pay_request() ) {
				return $data;
			}

			if ( array_key_exists( 'current_user_billing_for_order', $data ) ) {
				$data['current_user_billing_for_order'] = array();
			}

			foreach ( $this->order_pay_keys as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					unset( $data[ $key ] );
				}
			}

			return $data;
		}

		/**
		 * Belt and braces for the billing PII specifically (filter exists since Stripe 1.14.0.2).
		 *
		 * @param array     $details Billing details.
		 * @param \WC_Order $order   Order being paid.
		 *
		 * @return array
		 */
		public function scrub_billing_details( $details, $order = null ) {
			if ( ! $this->is_order_pay_request() || $this->is_verified_order_pay_request( $order ) ) {
				return $details;
			}

			return array();
		}

		/**
		 * @return bool
		 */
		private function is_order_pay_request() {
			global $wp;

			return function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) && ! empty( $wp->query_vars['order-pay'] );
		}

		/**
		 * Mirror of Stripe 1.16.0 `get_verified_order_pay_order()`: the `key` must match the
		 * order key, and an order owned by a registered customer is only verified for that
		 * same logged-in customer. A valid key alone does not bypass the ownership check.
		 *
		 * @param \WC_Order|null $order Order to verify; resolved from the query var when omitted.
		 *
		 * @return bool
		 */
		private function is_verified_order_pay_request( $order = null ) {
			if ( null === $order && null !== $this->is_verified ) {
				return $this->is_verified;
			}

			$verified = $this->verify( $order );

			if ( null === $order ) {
				$this->is_verified = $verified;
			}

			return $verified;
		}

		/**
		 * @param \WC_Order|null $order
		 *
		 * @return bool
		 */
		private function verify( $order = null ) {
			global $wp;

			$order_id = isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
			if ( $order_id < 1 ) {
				return false;
			}

			if ( empty( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Woo order-pay links are authorized by the order key, not a nonce.
				return false;
			}

			$order_key = bwf_clean( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- input sanitized via a recursive sanitize_text_field() wrapper on this line.
			if ( ! is_string( $order_key ) || '' === $order_key ) {
				return false;
			}

			if ( ! $order instanceof WC_Order ) {
				$order = wc_get_order( $order_id );
			}

			if ( ! $order instanceof WC_Order || $order->get_id() !== $order_id ) {
				return false;
			}

			if ( ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
				return false;
			}

			$order_user_id = absint( $order->get_user_id() );
			if ( $order_user_id > 0 && ( ! is_user_logged_in() || get_current_user_id() !== $order_user_id ) ) {
				return false;
			}

			return true;
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Compatibility_FK_Stripe(), 'fk_stripe' );
}
