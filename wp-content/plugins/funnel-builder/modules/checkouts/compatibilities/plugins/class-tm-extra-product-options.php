<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Extra Product Options & Add-Ons for WooCommerce plugin
 *
 * Plugin Name: Extra Product Options & Add-Ons for WooCommerce
 * Version: 7.4.3
 * Author URI: https://themecomplete.com/
 * Author: ThemeComplete
 *
 * This class handles compatibility with Extra Product Options & Add-Ons for WooCommerce plugin
 * by preventing TM EPO from overwriting strike-through prices in the checkout cart display.
 */

if ( ! class_exists( 'WFACP_Compatibility_TM_Extra_Product_Options' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_TM_Extra_Product_Options {

		/**
		 * Constructor - initializes the compatibility hooks
		 */
		public function __construct() {
			add_action( 'wfacp_template_load', array( $this, 'action' ) );
		}

		/**
		 * Main compatibility action
		 *
		 * TM EPO plugin hooks into 'woocommerce_cart_item_subtotal' filter at priority 99999
		 * and completely replaces the subtotal HTML, which breaks our strike-through price display.
		 * This method removes that filter to allow our strike-through pricing to work correctly.
		 *
		 * Note: TM EPO options are still displayed via wc_get_formatted_cart_item_data() in the
		 * product name section, so removing this filter doesn't break the options display.
		 */
		public function action() {
			// Check if TM EPO Cart class is available
			if ( ! function_exists( 'THEMECOMPLETE_EPO_CART' ) ) {
				return;
			}

			// Get the TM EPO Cart instance
			$tm_epo_cart_instance = THEMECOMPLETE_EPO_CART();

			// Verify the instance exists and has the filter method
			if ( ! $tm_epo_cart_instance || ! method_exists( $tm_epo_cart_instance, 'woocommerce_cart_item_subtotal' ) ) {
				return;
			}

			// Remove TM EPO's cart item subtotal filter
			// This prevents TM EPO from overwriting our strike-through price HTML
			// TM EPO hooks at priority 99999, so we need to remove it at that priority
			remove_filter( 'woocommerce_cart_item_subtotal', array( $tm_epo_cart_instance, 'woocommerce_cart_item_subtotal' ), 99999 );
		}
	}

	// Register the compatibility class
	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_TM_Extra_Product_Options(), 'woocommerce-tm-extra-product-options' );
}
