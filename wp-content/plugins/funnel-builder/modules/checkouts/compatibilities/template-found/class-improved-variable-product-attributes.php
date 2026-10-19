<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Plugin_Compatibilities_improved_variable_product_attributes' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Plugin_Compatibilities_improved_variable_product_attributes {

		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', [ $this, 'attach_action_in_footer' ] );
		}

		public function attach_action_in_footer() {
			global $ivpa_global;
			$ivpa_global['init'] = 1; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $ivpa_global is the Improved Variable Product Attributes plugin's own global (file loads only when its WC_Improved_Variable_Product_Attributes_Init class exists); we set its init flag so its footer scripts run on our checkout. The name is theirs and cannot be prefixed.
		}
	}


	WFACP_Plugin_Compatibilities::register( new WFACP_Plugin_Compatibilities_improved_variable_product_attributes(), 'ivpa' );

}


