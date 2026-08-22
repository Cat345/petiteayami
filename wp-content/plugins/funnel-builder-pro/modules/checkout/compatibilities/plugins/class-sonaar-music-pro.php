<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Sonaar_Music_Pro' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Sonaar_Music_Pro {

		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_actions' ) );
			// Also run during the ajax order-summary refresh, otherwise the duplicate thumbnail
			// returns whenever the summary fragments are re-rendered (qty/shipping changes).
			add_action( 'wfacp_before_process_checkout_template_loader', array( $this, 'remove_actions' ) );
		}

		/**
		 * Sonaar Pro Addon prepends its own 50x50 image to every cart item name via
		 * SRMP3_WooCommerce::srmp3_add_image_checkout on woocommerce_cart_item_name (priority 9999),
		 * duplicating the thumbnail FunnelKit already renders via .wfacp-pro-thumb. Remove it on
		 * FunnelKit checkout pages so only FunnelKit's thumbnail remains.
		 */
		public function remove_actions() {
			if ( ! class_exists( 'SRMP3_WooCommerce' ) || ! class_exists( 'WFACP_Common' ) ) {
				return;
			}

			WFACP_Common::remove_actions( 'woocommerce_cart_item_name', 'SRMP3_WooCommerce', 'srmp3_add_image_checkout' );
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Sonaar_Music_Pro(), 'Sonaar_Music_Pro' );
}
