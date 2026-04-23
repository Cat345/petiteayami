<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Borica Payments Gateway Compatibility
 * Dequeues borica checkout scripts on FunnelKit checkout pages
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Borica_Payments' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Borica_Payments {

		/**
		 * Constructor - Initialize hooks
		 */
		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'action' ), 1 );
		}

		/**
		 * Add dequeue action when checkout page is found
		 */
		public function action() {
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_scripts' ), 9999 );
		}

		/**
		 * Dequeue borica checkout script
		 */
		public function dequeue_scripts() {
			if ( ! $this->is_enable() ) {
				return;
			}

			wp_dequeue_script( 'borica_checkout_js' );
			wp_deregister_script( 'borica_checkout_js' );
		}

		/**
		 * Check if Borica Payments plugin is active
		 *
		 * @return bool
		 */
		public function is_enable() {
			return function_exists( 'borica_add_meta' );
		}
	}

	// Register compatibility class
	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Borica_Payments(), 'borica_payments' );
}
