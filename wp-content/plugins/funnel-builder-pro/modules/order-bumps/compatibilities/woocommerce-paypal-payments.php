<?php
/**
 * WooCommerce PayPal Payments Compatibility
 *
 * @package FunnelKit\OrderBumps\Compatibilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Compatibility_With_PayPal_Payments' ) ) {
	/**
	 * WooCommerce PayPal Payments
	 * https://wordpress.org/plugins/woocommerce-paypal-payments/
	 *
	 * Issue: PayPal Payments plugin adds a div element (<div class="ppcp-messages">)
	 * that breaks the Quick View AJAX response when it gets included in the HTML output.
	 *
	 * Solution: Remove the ppcp-messages div from Quick View AJAX output to prevent
	 * JSON structure breakage.
	 *
	 * Class WFOB_Compatibility_With_PayPal_Payments
	 */
	#[\AllowDynamicProperties]
	class WFOB_Compatibility_With_PayPal_Payments {

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Fix for Quick View popup breaking due to ppcp-messages div.
			add_action( 'init', array( $this, 'prevent_paypal_messages' ), 1 );
		}

		/**
		 * Prevent PayPal messages from rendering during Quick View AJAX.
		 *
		 * This must run early on init to add the filter before PayPal sets up its hooks.
		 * Uses PayPal's official filter to disable messages during Quick View AJAX.
		 *
		 * @return void
		 */
		public function prevent_paypal_messages() {
			// Only proceed if this is a Quick View AJAX request.
			if ( ! $this->is_quick_view_ajax() ) {
				return;
			}

			// Use PayPal's official filter to disable Pay Later messaging during Quick View.
			// This is the cleanest and most reliable approach.
			add_filter( 'woocommerce_paypal_payments_should_render_pay_later_messaging', '__return_false', 999 );
		}

		/**
		 * Check if current request is for Order Bump Quick View AJAX.
		 *
		 * @return bool
		 */
		private function is_quick_view_ajax() {
			// Check if this is an AJAX request.
			if ( ! wp_doing_ajax() ) {
				return false;
			}

			// Check for order bump quick view AJAX action.
			if ( isset( $_REQUEST['action'] ) && 'wfob_quick_view_ajax' === $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}

			// Also check for WC AJAX format.
			if ( isset( $_REQUEST['wc-ajax'] ) && 'wfob_quick_view_ajax' === $_REQUEST['wc-ajax'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}

			// Check if wfob_id and product_id are present (order bump identifier).
			if ( isset( $_POST['wfob_id'] ) && isset( $_POST['product_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return true;
			}

			return false;
		}
	}

	new WFOB_Compatibility_With_PayPal_Payments();
}
