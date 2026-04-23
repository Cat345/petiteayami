<?php
/**
 * PublicSquare Payments by PublicSquare V 1.1.8
 * Plugin URI: https://www.publicsquare.com/
 * Issue: Payment fields (card number, CVV, expiry) not displaying on FunnelKit checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_PublicSquare_Payments' ) ) {
	#[AllowDynamicProperties]
	class WFACP_PublicSquare_Payments {

		public function __construct() {
			add_action( 'wfacp_after_template_found', array( $this, 'prevent_blocks_hook_removal' ), 9 );
			add_action( 'wfacp_internal_css', array( $this, 'add_css' ) );
		}

		public function prevent_blocks_hook_removal() {
			if ( ! class_exists( 'WC_Payment_Gateway_CC_PublicSquare' ) ) {
				return;
			}

			if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
				return;
			}

			$gateways = WC()->payment_gateways()->payment_gateways();
			if ( ! isset( $gateways['publicsquare'] ) ) {
				return;
			}

			$gateway = $gateways['publicsquare'];

			if ( ! $gateway || ! is_a( $gateway, 'WC_Payment_Gateway_CC_PublicSquare' ) ) {
				return;
			}

			if ( ! method_exists( $gateway, 'is_available' ) || ! $gateway->is_available() ) {
				return;
			}

			add_filter( 'wfacp_verify_payment_methods_dependencies', '__return_false', 10, 1 );
		}

		public function add_css() {
			echo '<style>';
			echo 'body #wfacp-e-form fieldset#wc-publicsquare-cc-form { width: 100%; }';
			echo '</style>';
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_PublicSquare_Payments(), 'publicsquare-payments' );
}
