<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFACP_Compatibility_With_Happy_Elementor' ) ) {
	/**
	 * Happy Elementor Addons by weDevs (v.3.2.1)
	 * Plugin Path : https://happyaddons.com
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Happy_Elementor {
		public function __construct() {
			add_filter( 'admin_init', [ $this, 'do_not_execute' ], 2 );
		}

		public function do_not_execute() {
			if ( ! isset( $_REQUEST['page'] ) || $_REQUEST['page'] !== 'wfacp' || ! class_exists( 'Happy_Addons\Elementor\Dashboard' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin request routing; WP core builds this URL without a nonce.
				return;
			}

			remove_action( 'admin_enqueue_scripts', [ 'Happy_Addons\Elementor\Dashboard', 'enqueue_scripts' ] );
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Happy_Elementor(), 'happy-elementor' );
}