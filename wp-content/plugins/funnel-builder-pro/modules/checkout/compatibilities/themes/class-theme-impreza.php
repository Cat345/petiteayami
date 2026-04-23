<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Theme_Impreza' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Theme_Impreza {

		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_actions' ) );
		}

		/**
		 * Remove actions that might interfere with checkout fields
		 */
		public function remove_actions() {
			// Prevent us-core from dequeuing Select2 CSS which breaks country/state dropdowns
			if ( function_exists( 'us_wc_dequeue_checkout_styles' ) ) {
				remove_action( 'wp_enqueue_scripts', 'us_wc_dequeue_checkout_styles', 100 );
			}
		}
	}
	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Theme_Impreza(), 'impreza' );
}
