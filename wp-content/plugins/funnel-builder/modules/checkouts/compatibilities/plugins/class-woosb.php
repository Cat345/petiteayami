<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * WPC Product Bundles for WooCommerce (Premium) by WPClever v.6.0.6
 */
if ( ! class_exists( 'WFACP_Woosb' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Woosb {
		public function __construct() {
			add_action( 'wfacp_template_load', array( $this, 'action' ), 8 );
		}

		public function action() {

			if ( ! class_exists( 'WPCleverWoosb' ) ) {
				return;
			}

			WFACP_Common::remove_actions( 'woocommerce_add_cart_item_data', 'WPCleverWoosb', 'woosb_add_cart_item_data' );

			add_filter( 'wfacp_display_quantity_increment', array( $this, 'allow_quantity_input' ), 9999, 5 );
			add_filter( 'wfacp_mini_cart_enable_delete_item', array( $this, 'allow_delete_icon' ), 9999, 3 );
			add_filter( 'wfacp_enable_delete_item', array( $this, 'allow_delete_icon' ), 9999, 3 );
		}



		public function allow_quantity_input( $status, $cart_item, $item_quantity = null, $aero_item_key = null, $cart_item_key = null ) {
			if ( ! is_array( $cart_item ) || empty( $cart_item ) ) {
				return $status;
			}

			if ( isset( $cart_item['woosb_parent_key'] ) ) {
				return false;
			}

			return true;
		}

		public function allow_delete_icon( $status, $cart_item, $cart_item_key = null ) {
			if ( ! is_array( $cart_item ) || empty( $cart_item ) ) {
				return $status;
			}

			if ( isset( $cart_item['woosb_parent_key'] ) ) {
					$status = false;

			} else {
				$status = true;
			}

			return $status;
		}
	}


	WFACP_Plugin_Compatibilities::register( new WFACP_Woosb(), 'woo-product-bundle' );

}
