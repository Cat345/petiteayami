<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Developed by WebToffee
 * #[AllowDynamicProperties]
 * class WFACP_Webtoffee_Gift_Cards_Compatiblity
 */
if ( ! class_exists( 'WFACP_Webtoffee_Gift_Cards_Compatiblity' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Webtoffee_Gift_Cards_Compatiblity {

		public function __construct() {
			add_action( 'wfacp_internal_css', array( $this, 'internal_css_js' ) );
		}

		public function internal_css_js() {

			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}

			$bodyClass = 'body';

			if ( 'pre_built' !== $instance->get_template_type() ) {

				$bodyClass = 'body #wfacp-e-form';
			}

			echo '<style>';
			echo $bodyClass . ' .woocommerce-checkout-review-order.wfacp-oder-detail{position:relative;}';
			echo $bodyClass . ' .wt_gc_checkout_store_credit_balance{padding:0 33px;}';
			echo '</style>';
		}
	}

	new WFACP_Webtoffee_Gift_Cards_Compatiblity();
}
