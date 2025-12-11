<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Compatibility_With_Paypal_Express' ) ) {
	class WFOB_Compatibility_With_Paypal_Express {

		public function __construct() {
			add_filter( 'wfob_skip_order_bump', [ $this, 'check_ppec_checkout_enable' ], 10, 2 );
			add_filter( 'wfob_do_not_execute_bump_fragments', [ $this, 'do_not_print_bumps_html' ] );
		}

		public function has_active_session() {
			$status = false;
			if ( function_exists( 'wc_gateway_ppec' ) ) {
				$gateway = wc_gateway_ppec();
				if ( true == property_exists( $gateway, 'checkout' ) && wc_gateway_ppec()->checkout->has_active_session() ) {
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
			if ( $this->has_active_session() ) {

				$status = true;

			}

			return $status;
		}

		public function do_not_print_bumps_html( $status ) {

			if ( $this->has_active_session() ) {
				$status = true;
			}

			return $status;
		}
	}

	new WFOB_Compatibility_With_Paypal_Express();
}