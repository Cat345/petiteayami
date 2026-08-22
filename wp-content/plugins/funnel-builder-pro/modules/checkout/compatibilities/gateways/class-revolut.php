<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Revolut Gateway Compatibility
 *
 * While the Revolut gateway is processing, WooCommerce blocks the checkout form (blockUI) but the
 * block overlay stays hidden, so there is no loading feedback. Forcing the overlay visible needs
 * `display:block !important` (blockUI applies inline styles to `.blockOverlay`, which specificity
 * alone can't override). That single declaration is emitted here as internal CSS instead of in the
 * plugin stylesheet, where !important is not allowed. The overlay's spinner background itself lives
 * in wfacp-style.css (pre-existing).
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Revolut' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Revolut {

		/**
		 * Constructor - Initialize hooks
		 */
		public function __construct() {
			add_action( 'wfacp_internal_css', array( $this, 'add_internal_css' ) );
		}

		/**
		 * Output the processing overlay + spinner CSS on FunnelKit checkout pages.
		 */
		public function add_internal_css() {
			if ( ! $this->is_enable() ) {
				return;
			}

			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}

			echo '<style id="wfacp-revolut-processing-overlay">';
			echo 'body .wfacp_main_form #wfacp_checkout_form.processing .blockUI.blockOverlay{display:block !important;}';
			echo '</style>';
		}

		/**
		 * Check if the Revolut gateway plugin is active.
		 *
		 * @return bool
		 */
		public function is_enable() {
			return defined( 'WC_GATEWAY_REVOLUT_VERSION' );
		}
	}

	// Register compatibility class
	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Revolut(), 'revolut' );
}
