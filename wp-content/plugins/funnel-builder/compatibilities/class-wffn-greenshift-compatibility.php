<?php
/**
 * Greenshift Page Builder Compatibility
 *
 * Fixes Greenshift inline CSS not loading on FunnelKit global checkout pages.
 * Issue: Greenshift uses get_queried_object_id() to load CSS, but on /checkout/
 * the queried object is the WooCommerce checkout page, not the FunnelKit template.
 *
 * @package FunnelKit
 * @since 3.7.0
 */

if ( ! class_exists( 'WFFN_Greenshift_Compatibility' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Greenshift_Compatibility {

		public function __construct() {
			if ( $this->is_enable() ) {
				add_action( 'wfacp_checkout_page_found', array( $this, 'load_greenshift_css' ), 5 );
			}
		}

		/**
		 * Check if Greenshift is active
		 *
		 * @return bool
		 */
		public function is_enable() {
			return function_exists( 'gspb_get_final_css' );
		}

		/**
		 * Load Greenshift CSS for FunnelKit checkout pages
		 *
		 * When accessing /checkout/ (WooCommerce URL) with FunnelKit global checkout,
		 * Greenshift loads CSS based on get_queried_object_id() which returns the
		 * WooCommerce checkout page ID, not the FunnelKit checkout template ID.
		 * This method ensures the correct CSS is loaded.
		 *
		 * @param int $wfacp_id FunnelKit checkout page ID
		 *
		 * @return void
		 */
		public function load_greenshift_css( $wfacp_id ) {
			// Get queried object ID (WooCommerce checkout page)
			$queried_id = get_queried_object_id();

			// If they're the same, Greenshift will handle it
			if ( $queried_id === $wfacp_id ) {
				return;
			}

			// Get Greenshift CSS from FunnelKit checkout page
			$gspb_css_content = get_post_meta( $wfacp_id, '_gspb_post_css', true );

			if ( empty( $gspb_css_content ) ) {
				return;
			}

			// Process and enqueue the CSS
			$final_css = gspb_get_final_css( $gspb_css_content );

			if ( ! empty( $final_css ) ) {
				wp_register_style( 'greenshift-funnelkit-post-css', false );//phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_enqueue_style( 'greenshift-funnelkit-post-css' );
				wp_add_inline_style( 'greenshift-funnelkit-post-css', $final_css );
			}
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Greenshift_Compatibility(), 'wffn_greenshift' );
}
