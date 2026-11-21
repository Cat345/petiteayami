<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Germanized for WooCommerce compatibility.
 *
 * Germanized appends `force_pay_order=1` to the order-pay URL (which is why the parameter turns up
 * in the WooCommerce REST API's `payment_url` on a Germanized site) and, on that page, loads a
 * script that pre-selects the order's gateway and immediately submits `#order_review` behind a
 * blocking overlay, so the customer is bounced straight to the payment provider.
 *
 * That script will not auto-submit when the gateway's payment box contains an `:input`, which is
 * Germanized's way of asking "does this gateway need something from the customer first?". Our
 * gateways answer that question wrongly: the card number, the IBAN and the Payment Element are all
 * Stripe iframes, not inputs, so the box looks empty and the form is submitted before Stripe.js can
 * produce a payment method. The submit then fails inside Stripe.js, nothing takes the overlay back
 * off, and the page is stuck.
 *
 * So we switch the auto-submit off for the gateways that have a client-side step to complete first.
 * The gateway stays pre-selected and the overlay never appears; the customer fills the Element and
 * pays as usual. Gateways that submit straight through to a server-side redirect (Klarna, Affirm,
 * Afterpay, Alipay, MobilePay, Bancontact) are left alone, so they keep Germanized's one-click
 * bounce to the provider.
 *
 * Germanized's own Klarna Checkout and WooCommerce Subscriptions compatibility classes opt out of
 * the same feature in the same way.
 *
 * @see Issue #1541
 */
if ( ! class_exists( 'FKWCS_Germanized_Compatibility' ) ) {

	class FKWCS_Germanized_Compatibility {

		/**
		 * Gateways whose order-pay submit is completed by Stripe.js in the browser.
		 *
		 * These are the gateways that override `processOrderReview()` in assets/js/stripe-elements.js:
		 * each one has to hand over a payment method, source or confirmed intent built from a mounted
		 * Stripe Element before the form can usefully be posted.
		 *
		 * @var string[]
		 */
		private $client_side_gateways = array(
			'fkwcs_stripe',
			'fkwcs_stripe_sepa',
			'fkwcs_stripe_ach',
			'fkwcs_stripe_ideal',
			'fkwcs_stripe_pix',
			'fkwcs_stripe_multibanco',
			'fkwcs_stripe_eps',
			'fkwcs_stripe_mbway',
			'fkwcs_stripe_cashapp',
			'fkwcs_stripe_twint',
			'fkwcs_stripe_blik',
		);

		public function __construct() {
			add_filter( 'wc_gzd_force_pay_order_params', array( $this, 'maybe_disable_auto_submit' ) );
		}

		/**
		 * Turn Germanized's order-pay auto-submit off for our client-side gateways.
		 *
		 * @param array $params Germanized's `wc_gzd_force_pay_order_params` script arguments.
		 *
		 * @return array
		 */
		public function maybe_disable_auto_submit( $params ) {
			if ( ! is_array( $params ) || empty( $params['gateway'] ) ) {
				return $params;
			}

			$gateway = (string) $params['gateway'];

			/**
			 * Filters whether Germanized may auto-submit the order-pay form for one of our gateways.
			 *
			 * Returning true restores Germanized's own behaviour for that gateway.
			 *
			 * @param bool   $allow   Whether to leave the auto-submit on. Default false for the
			 *                        gateways that complete their submit in the browser.
			 * @param string $gateway The order's payment method id.
			 */
			$allow = apply_filters( 'fkwcs_gzd_allow_force_pay_auto_submit', ! in_array( $gateway, $this->client_side_gateways, true ), $gateway );

			if ( ! $allow ) {
				$params['auto_submit'] = false;
			}

			return $params;
		}
	}

	new FKWCS_Germanized_Compatibility();
}
