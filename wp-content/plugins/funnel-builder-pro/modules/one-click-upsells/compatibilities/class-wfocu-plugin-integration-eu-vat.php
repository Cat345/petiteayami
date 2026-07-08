<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Plugin_Integration_EU_VAT' ) ) {
	/**
	 * EU VAT for WooCommerce Compatibility
	 *
	 * @since 1.0.0
	 */
	#[\AllowDynamicProperties]
	class WFOCU_Plugin_Integration_EU_VAT {

		/**
		 * Constructor
		 */
		public function __construct() {
			if ( ! $this->is_enable() ) {
				return;
			}
			add_action( 'init', array( $this, 'disable_funnel_fragments' ), 1 );
		}

		/**
		 * Disable funnel fragments completely (no upsell context checking)
		 */
		public function disable_funnel_fragments() {
			remove_action( 'woocommerce_update_order_review_fragments', array( \WFOCU_Core()->public, 'maybe_decide_funnel_on_fragments' ), 10 );
		}

		/**
		 * Check if EU VAT plugin is enabled (required by compatibility system)
		 */
		public function is_enable() {
			return class_exists( 'Alg_WC_EU_VAT' ) && function_exists( 'alg_wc_eu_vat' );
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Plugin_Integration_EU_VAT(), 'wfocu_plugin_integration_eu_vat' );
}
