<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Spectra' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Spectra {

		public function __construct() {
			// Issue #8260: Hook early to provide correct page ID
			add_action( 'wp', array( $this, 'setup_spectra_compatibility' ), 50 );
		}

		/**
		 * Setup Spectra compatibility
		 *
		 * @since 3.22.1
		 */
		public function setup_spectra_compatibility() {
			if ( ! WFACP_Common::is_disabled() && WFACP_Core()->public->is_checkout_override() ) {
				add_filter( 'pre_option_woocommerce_checkout_page_id', array( $this, 'return_funnelkit_checkout_id' ) );
			}
		}

		/**
		 * Return FunnelKit page ID
		 *
		 * @return int
		 * @since 3.22.1
		 */
		public function return_funnelkit_checkout_id() {
			if ( ! is_checkout() ) {
				return false;
			}

			return WFACP_Common::get_id();
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Spectra(), 'spectra' );
}
