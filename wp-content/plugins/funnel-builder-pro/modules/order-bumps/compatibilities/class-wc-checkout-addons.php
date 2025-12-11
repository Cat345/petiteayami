<?php

/**
 * Class WFOB_Compatibility_With_WC_Checkout_addons By Skyverge
 */
if ( ! class_exists( 'WFOB_Compatibility_With_WC_Checkout_addons' ) ) {
	class WFOB_Compatibility_With_WC_Checkout_addons {

		public function __construct() {
			add_action( 'wfob_order_bump_fragments', [ $this, 'remove_add_on_fragments' ] );
		}

		public function remove_add_on_fragments() {
			add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'remove_fragments_from_bump_calls' ], 999 );
		}

		public function remove_fragments_from_bump_calls( $fragments ) {
			if ( isset( $fragments['#wc_checkout_add_ons'] ) ) {
				unset( $fragments['#wc_checkout_add_ons'] );
			}

			return $fragments;
		}
	}

	new WFOB_Compatibility_With_WC_Checkout_addons();
}