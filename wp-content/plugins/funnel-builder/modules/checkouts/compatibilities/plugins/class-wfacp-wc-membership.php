<?php
if ( ! class_exists( 'WFACP_Compatibility_WC_MemberShip' ) ) {
	/**
	 * #[AllowDynamicProperties]
	 *
	 * class WFACP_WC_MemberShip   WooCommerce Memberships By SkyVerge
	 * Page Redirect in ajax when coupon apply
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_WC_MemberShip {
		public function __construct() {
			add_filter( 'pre_option_wc_memberships_redirect_page_id', array( $this, 'send_null_page_id' ) );
			add_filter( 'wp_redirect_status', array( $this, 'remove_redirect_action' ) );

			add_action( 'wp_loaded', array( $this, 'remove_action' ) );
		}

		public function remove_redirect_action( $status ) {
			if ( isset( $_REQUEST['wc-ajax'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ), 'wfacp' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce AJAX handles nonce verification
				$status = false;
			}

			return $status;
		}

		public function send_null_page_id( $status ) {
			if ( isset( $_REQUEST['wc-ajax'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ), 'wfacp' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce AJAX handles nonce verification
				$status = null;
			}

			return $status;
		}

		public function remove_action() {
			if ( class_exists( 'WC_Memberships_Frontend', false ) && isset( $_POST['wfacp_login_hidden'] ) && wc_string_to_bool( sanitize_text_field( wp_unslash( $_POST['wfacp_login_hidden'] ) ) ) === true ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce login form handles nonce verification
				WFACP_Common::remove_actions( 'woocommerce_login_redirect', 'WC_Memberships_Frontend', 'redirect_to_page_upon_woocommerce_login' );
			}
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_WC_MemberShip(), 'wc_membership' );
}
