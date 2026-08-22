<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Compatibility_With_WPC_Variations_Radio_Buttons' ) ) {

	class WFOB_Compatibility_With_WPC_Variations_Radio_Buttons {

		public function __construct() {
			add_action( 'init', array( $this, 'maybe_remove_woovr_hooks' ), 1 );
		}

		public function maybe_remove_woovr_hooks() {
			if ( ! $this->is_quick_view_ajax() ) {
				return;
			}

			$instance = WPClever_Woovr::instance();
			remove_action( 'woocommerce_before_variations_form', array( $instance, 'before_variations_form' ) );
		}

		private function is_quick_view_ajax() {
			if ( ! wp_doing_ajax() ) {
				return false;
			}

			if ( isset( $_REQUEST['action'] ) && 'wfob_quick_view_ajax' === $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}

			if ( isset( $_REQUEST['wc-ajax'] ) && 'wfob_quick_view_ajax' === $_REQUEST['wc-ajax'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}

			return false;
		}
	}

	new WFOB_Compatibility_With_WPC_Variations_Radio_Buttons();
}
