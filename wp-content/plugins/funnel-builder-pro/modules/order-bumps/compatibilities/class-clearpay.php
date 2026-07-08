<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibilities_ClearPay' ) ) {
	#[\AllowDynamicProperties]
	class WFOB_Compatibilities_ClearPay {
		public function __construct() {
			add_filter( 'wfob_need_payment_gateway_refresh', array( $this, 'need_refresh' ), 10, 2 );
		}

		public function need_refresh( $status, $available_after_gateways ) {
			if ( class_exists( 'Clearpay_Plugin' ) && isset( $available_after_gateways['clearpay'] ) ) {
				$status = true;
			}

			return $status;
		}
	}

	new WFOB_Compatibilities_ClearPay();
}
