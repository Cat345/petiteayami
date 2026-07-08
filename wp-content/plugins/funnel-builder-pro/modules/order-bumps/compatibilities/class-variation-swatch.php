<?php
/**
 * Variation Swatches By CartFlows
 */
if ( ! class_exists( 'WFOB_Compatibility_With_CFW_Swatches' ) ) {
	#[\AllowDynamicProperties]
	class WFOB_Compatibility_With_CFW_Swatches {
		public function __construct() {
			add_filter( 'cfvsw_is_required_page', array( $this, 'enable_checkout_as_required_page' ) );
			add_action( 'wfob_qv_images', array( $this, 'action' ) );
		}

		public function action() {
			add_filter( 'woocommerce_wfob_dropdown_variation_attribute_options_html', array( $this, 'change_filter' ), 10, 2 );
		}

		public function change_filter( $html, $args ) {
			return apply_filters( 'woocommerce_dropdown_variation_attribute_options_html', $html, $args );
		}

		public function enable_checkout_as_required_page( $status ) {
			return is_checkout() ? true : $status;
		}
	}

	new WFOB_Compatibility_With_CFW_Swatches();
}
