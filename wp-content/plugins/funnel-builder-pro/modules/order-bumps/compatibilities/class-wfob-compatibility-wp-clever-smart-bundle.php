<?php
if ( ! class_exists( 'WFOB_Compatibility_With_Wp_Clever' ) ) {
	/**
	 * Smart bundle by Wp Clever
	 * Class WFOB_Wp_Clever_Compatibility
	 */
	class  WFOB_Compatibility_With_Wp_Clever {
		public function __construct() {
			add_filter( 'wfob_exclude_cart_item_in_rule', [ $this, 'exclude_bundle_product' ], 10, 2 );
		}

		public function exclude_bundle_product( $status, $item ) {
			if ( isset( $item['woosb_parent_key'] ) ) {
				$status = true;
			}

			return $status;
		}
	}

	new WFOB_Compatibility_With_Wp_Clever();
}