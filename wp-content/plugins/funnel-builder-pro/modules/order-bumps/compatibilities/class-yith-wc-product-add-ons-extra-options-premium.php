<?php
/**
 * YITH WooCommerce Product Add-ons & Extra Options Premium by YITH v.4.10.1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Yith_Product_Addons_Extra_Options_Premium' ) ) {
	class WFOB_Compatibility_With_Yith_Product_Addons_Extra_Options_Premium {

		public function __construct() {
			add_filter( 'wfob_before_add_to_cart', [ $this, 'action' ], 10 );
		}

		public function action() {
			add_filter( 'woocommerce_add_cart_item_data', [ $this, 'execute_meta' ], 8, 4 );
		}

		public function execute_meta( $cart_item_data, $product_id, $posted_data = null, $sold_individually = false ) {
			$post_data = [];
			parse_str( $_POST['post_data'], $post_data );

			if ( isset( $post_data['wfob_input_hidden_data'] ) ) {

				$bump_action_data = json_decode( $post_data['wfob_input_hidden_data'], true );


				foreach ( $bump_action_data as $key => $bump_action_data_value ) {
					if ( strpos( $key, 'yith_wapo_' ) !== false ) {
						$_POST[ $key ] = $bump_action_data_value;
					} elseif ( strpos( $key, 'yith_wapo[' ) !== false ) {
						$temp                                                  = str_replace( 'yith_wapo[][', '', $key );
						$_POST['yith_wapo'][][ str_replace( ']', '', $temp ) ] = $bump_action_data_value;
					}
				}
			}

			return $cart_item_data;

		}

		public function is_enable() {
			return function_exists( 'yith_wapo_init' );
		}

	}

	new WFOB_Compatibility_With_Yith_Product_Addons_Extra_Options_Premium();


}

