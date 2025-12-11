<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_AeroCheckout' ) ) {
	class WFOB_Compatibility_With_AeroCheckout {
		public function __construct() {
			/* checkout page */
			add_action( 'wfacp_after_template_found', [ $this, 'alter_bump_position' ] );

		}

		public function alter_bump_position() {
			add_filter( 'wfob_bump_positions', [ $this, 'wfob_bump_positions' ] );
		}

		public function wfob_bump_positions( $position ) {

			$position['woocommerce_checkout_order_review_above_order_summary']['hook'] = 'wfacp_before_order_summary_field';
			$position['woocommerce_checkout_order_review_below_order_summary']['hook'] = 'wfacp_after_order_summary_field';
			if ( version_compare( WFACP_VERSION, '3.8.0', '>' ) ) {
				$position['woocommerce_checkout_order_review_below_payment_gateway']['hook'] = 'wfacp_after_gateway_list';
			}

			return $position;
		}


	}

	new WFOB_Compatibility_With_AeroCheckout();
}