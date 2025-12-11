<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Angel_Eye' ) ) {
	class WFOB_Compatibility_With_Angel_Eye {
		public function __construct() {
			add_filter( 'wfob_skip_order_bump', [ $this, 'check_angel_eye_checkout_enable' ] );
			add_filter( 'wfob_do_not_execute_bump_fragments', [ $this, 'do_not_print_bumps_html' ] );
		}

		/**
		 * @param $status  bool
		 * @param $instance WFACP_public
		 */
		public function check_angel_eye_checkout_enable( $status ) {
			if ( is_null( WC()->session ) ) {
				return $status;
			}
			if ( ! is_admin() ) {
				$paypal_express_checkout = WC()->session->get( 'paypal_express_checkout' );
				if ( isset( $paypal_express_checkout ) ) {
					$status = true;
				}
			}

			return $status;
		}

		/**
		 * @param $status  bool
		 * @param $instance WFACP_public
		 */
		public function check_ppec_checkout_enable( $status ) {
			if ( is_null( WC()->session ) ) {
				return $status;
			}
			$paypal_express_checkout = WC()->session->get( 'paypal_express_checkout' );
			if ( isset( $paypal_express_checkout ) ) {
				$status = true;
			}

			return $status;
		}

		public function do_not_print_bumps_html( $status ) {

			if ( is_null( WC()->session ) ) {
				return $status;
			}
			$paypal_express_checkout = WC()->session->get( 'paypal_express_checkout' );
			if ( isset( $paypal_express_checkout ) ) {
				$status = true;
			}

			return $status;
		}
	}

	new WFOB_Compatibility_With_Angel_Eye();
}