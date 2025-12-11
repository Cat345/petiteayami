<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Plugin_Compatibilities_improved_variable_product_attributes' ) ) {
	class WFOB_Plugin_Compatibilities_improved_variable_product_attributes {
		public function __construct() {
			add_action( 'woocommerce_before_checkout_form', [ $this, 'attach_action_in_footer' ] );
		}

		public function attach_action_in_footer() {
			global $ivpa_global;
			unset( $ivpa_global );
			if ( class_exists( 'WC_Improved_Variable_Product_Attributes_Init' ) ) {
				global $ivpa_global;
				$ivpa_global['init'] = 1;
			}
		}
	}


	new WFOB_Plugin_Compatibilities_improved_variable_product_attributes();


}