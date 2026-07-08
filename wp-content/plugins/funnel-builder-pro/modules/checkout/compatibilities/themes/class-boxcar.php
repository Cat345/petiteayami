<?php
/**
 * Boxcar by ApusTheme
 * https://themeforest.net/user/apustheme
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Boxcar' ) ) {

	#[\AllowDynamicProperties]
	class WFACP_Compatibility_With_Boxcar {

		public function __construct() {
			add_action( 'after_setup_theme', array( $this, 'register_elementor_widget' ), 20 );
		}

		public function register_elementor_widget() {
			// Check if both Elementor and FunnelKit are active
			if ( ! class_exists( 'Elementor\Plugin' ) || ! class_exists( 'WFACP_Core' ) ) {
				return;
			}

			// Skip in admin area
			if ( is_admin() ) {
				return;
			}

			// Run widget fix only on frontend (preserve editor functionality)
			if ( ! wfacp_elementor_edit_mode() ) {
				// Remove Boxcar's Elementor widgets from init hook
				$instance = WFACP_Common::remove_actions( 'init', 'Boxcar_Elementor_Extensions', 'elementor_widgets' );

				// Re-register widgets on wp hook instead to prevent conflict with FunnelKit
				if ( $instance instanceof Boxcar_Elementor_Extensions ) {
					add_action( 'wp', array( $instance, 'elementor_widgets' ), 100 );
				}
			}
		}
	}

	new WFACP_Compatibility_With_Boxcar();
}
