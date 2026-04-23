<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Customer Reviews for WooCommerce plugin
 *
 * Prevents wfacp_is_theme_builder from returning true when editing products in Elementor
 * (avoids wfacp_post modifying $post incorrectly). Does NOT remove Elementor's __return_true,
 * so checkout editing continues to work. We only override to false for product pages.
 *
 * @package FunnelKit
 * @since 1.0.0
 */

// Check if Customer Reviews for WooCommerce plugin is active and Elementor is active
if ( ! class_exists( 'CR_Reviews' ) && ! defined( 'IVOLE_CONTENT_DIR' ) ) {
	return;
}

if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
	return;
}

if ( ! class_exists( 'WFACP_Customer_Reviews_WooCommerce' ) ) {
	/**
	 * Class WFACP_Customer_Reviews_WooCommerce
	 */
	#[AllowDynamicProperties]
	class WFACP_Customer_Reviews_WooCommerce {

		/**
		 * Constructor
		 */
		public function __construct() {
			// Priority 20: run after Elementor's __return_true (10). Only override for product pages.
			add_filter( 'wfacp_is_theme_builder', array( $this, 'prevent_theme_builder_for_products' ), 20 );
		}

		/**
		 * Override to false only when editing a product in Elementor. Leave checkout untouched.
		 *
		 * @param bool $is_theme_builder Current value (true from Elementor when in editor).
		 *
		 * @return bool
		 */
		public function prevent_theme_builder_for_products( $is_theme_builder ) {
			// Only act when in Elementor editor context
			$is_elementor = isset( $_REQUEST['elementor-preview'] )
				|| ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], array( 'elementor', 'elementor_ajax' ), true ) )
				|| ( isset( $_REQUEST['preview_id'] ) && isset( $_REQUEST['preview_nonce'] ) );//phpcs:ignore 

			if ( ! $is_elementor ) {
				return $is_theme_builder;
			}

			$post_id = 0;

			if ( isset( $_REQUEST['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin page detection
				$post_id = absint( $_REQUEST['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin page detection
			} elseif ( isset( $_REQUEST['editor_post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin page detection
				$post_id = absint( $_REQUEST['editor_post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin page detection
			} elseif ( isset( $_REQUEST['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor preview iframe
				$post_id = absint( $_REQUEST['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification not required for admin page detection
			}

			if ( $post_id <= 0 ) {
				global $post;
				if ( isset( $post->ID ) && $post->ID > 0 ) {
					$post_id = absint( $post->ID );
				}
			}

			if ( $post_id <= 0 ) {
				return $is_theme_builder;
			}

			$post_type = get_post_type( $post_id );

			// Only override to false when editing a product. Checkout and other pages keep $is_theme_builder.
			if ( $post_type && 'product' === $post_type ) {
				return false;
			}

			return $is_theme_builder;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Customer_Reviews_WooCommerce(), 'customer-reviews-woocommerce' );
}
