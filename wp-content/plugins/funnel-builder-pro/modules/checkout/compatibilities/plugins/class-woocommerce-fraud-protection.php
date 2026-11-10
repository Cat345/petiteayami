<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * WooCommerce Fraud Protection by Automattic
 * https://github.com/woocommerce/woocommerce-fraud-protection
 *
 * Fraud Protection adds its checkout script (which sends the Blackbox session ID with the order)
 * from woocommerce_checkout_before_order_review, a hook of WooCommerce's form-checkout.php that
 * the FunnelKit checkout templates do not fire. Without the script every order reaches the verifier
 * with no session ID (session_id_error "unknown") and is blocked. Load it from our own order review hook.
 *
 * The same applies to the pay-for-order page: Fraud Protection loads its pay-for-order script from
 * before_woocommerce_pay_form, which our order-pay template does not fire, so every payment there was blocked. Load it
 * from woocommerce_pay_order_before_submit, which runs only once the order, key and payment checks pass and the form prints.
 */
if ( ! class_exists( 'WFACP_Compatibility_WooCommerce_Fraud_Protection' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_WooCommerce_Fraud_Protection {
		public function __construct() {
			add_action( 'wfacp_checkout_before_order_review', [ $this, 'enqueue_checkout_script' ] );
			add_action( 'woocommerce_pay_order_before_submit', [ $this, 'enqueue_pay_for_order_script' ] );
		}

		public function enqueue_checkout_script() {
			$protector = '\Automattic\WooCommerce\Internal\FraudProtectionPlugin\Protectors\ShortcodeCheckoutProtector';
			if ( ! class_exists( $protector ) ) {
				return;
			}

			$instance = wc_get_container()->get( $protector );
			if ( method_exists( $instance, 'enqueue_shortcode_checkout_script' ) ) {
				$instance->enqueue_shortcode_checkout_script();
			}
		}

		public function enqueue_pay_for_order_script() {
			if ( ! WFACP_Core()->pay->is_order_pay() ) {
				return;
			}

			$protector = '\Automattic\WooCommerce\Internal\FraudProtectionPlugin\Protectors\PayForOrderProtector';
			if ( ! class_exists( $protector ) ) {
				return;
			}

			$instance = wc_get_container()->get( $protector );
			if ( method_exists( $instance, 'enqueue_pay_for_order_script' ) ) {
				$instance->enqueue_pay_for_order_script();
			}
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_WooCommerce_Fraud_Protection(), 'woocommerce-fraud-protection' );
}
