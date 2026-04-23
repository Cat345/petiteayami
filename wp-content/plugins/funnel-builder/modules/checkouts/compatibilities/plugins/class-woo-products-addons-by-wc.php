<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * plugin Name: Woo Product Add-ons by woocommerce (7.0.1)
 *
 */
if ( ! class_exists( 'WFACP_Compatibility_With_WooProductAddOns_by_WooCommerce' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_WooProductAddOns_by_WooCommerce {
		public function __construct() {

			add_filter( 'wfacp_before_add_to_cart', array( $this, 'action' ), 10 );
		}

		public function action() {
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'execute_meta' ), 8, 4 );
		}


		public function execute_meta( $cart_item_data, $product_id, $posted_data = null, $sold_individually = false ) {
			$post_data = array();

			if ( ! isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce add to cart handles nonce verification
				return $cart_item_data;
			}
			parse_str( wp_unslash( $_POST['post_data'] ), $post_data ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce add to cart handles nonce verification; post_data is URL-encoded string parsed into array, individual values sanitized as used

			if ( isset( $post_data['wfacp_input_hidden_data'] ) ) {
				$checkout_action_data = json_decode( $post_data['wfacp_input_hidden_data'], true );
				if ( isset( $checkout_action_data['data'] ) && is_array( $checkout_action_data['data'] ) && count( $checkout_action_data['data'] ) > 0 ) {
					foreach ( $checkout_action_data['data'] as $key => $checkout_action_data_value ) {
						if ( strpos( $key, 'addon-' ) !== false ) {
							$_POST[ $key ] = $checkout_action_data_value;

						}
					}
				}
			}

			return $cart_item_data;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_WooProductAddOns_by_WooCommerce(), 'woocommerce-product-addons' );
}
