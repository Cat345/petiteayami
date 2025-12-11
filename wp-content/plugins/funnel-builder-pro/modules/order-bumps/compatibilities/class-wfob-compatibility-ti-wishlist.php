<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_Ti_Wishlist' ) ) {
	class WFOB_Compatibility_With_Ti_Wishlist {
		public function __construct() {
			/* checkout page */
			add_action( 'wfob_qv_images', [ $this, 'unhook_wishlist_button' ] );
		}

		public function unhook_wishlist_button() {
			remove_action( 'woocommerce_after_add_to_cart_button', 'tinvwl_view_addto_html', 0 );
		}
	}

	new WFOB_Compatibility_With_Ti_Wishlist();
}