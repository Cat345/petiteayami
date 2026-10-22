<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * plugin Name: WPLoyalty by wployalty v.1.3.4
 * Plugin URI: https://wployalty.net
 *
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Wployalty' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Wployalty {
		public function __construct() {
				/* checkout page */
			add_action( 'after_setup_theme', array( $this, 'remove_actions' ) );
		}
		public function remove_actions() {
			if ( ! class_exists( '\Wlr\App\Controllers\Site\DisplayMessage' ) ) {
				return;
			}

			if ( WFACP_Common::is_theme_builder() ) {
				add_filter( 'wlr_is_checkout_earn_message_enabled', '__return_false' );
			}

			/*
			 * The Gutenberg block-editor preview fetches the checkout over admin-ajax
			 * (get_gutenberg_checkout_from_data / get_gutenberg_mini_cart_data). WPLoyalty's earn
			 * message callback calls wp_send_json_success() whenever wp_doing_ajax() is true, which
			 * terminates that request and returns the loyalty string in place of the checkout HTML,
			 * blanking the edit screen. is_theme_builder() is still false at this point (the ajax
			 * callback flips wfacp_is_theme_builder later, after WPLoyalty has already hooked in),
			 * so gate on the request's action instead and switch WPLoyalty's messages off for it.
			 */
			if ( $this->is_gutenberg_preview_ajax() ) {
				add_filter( 'wlr_before_display_messages', '__return_false' );
			}
		}

		private function is_gutenberg_preview_ajax() {
			if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Detecting the ajax action name only; no request data is processed and nothing capability-gated happens here.
				return false;
			}

			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Detecting the ajax action name only; no request data is processed and nothing capability-gated happens here.

			return in_array( $action, array( 'get_gutenberg_checkout_from_data', 'get_gutenberg_mini_cart_data' ), true );
		}
	}
	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Wployalty(), 'wployalty' );
}
