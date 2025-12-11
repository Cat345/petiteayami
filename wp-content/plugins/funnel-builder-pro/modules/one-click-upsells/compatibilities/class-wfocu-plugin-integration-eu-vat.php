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
	class WFOCU_Plugin_Integration_EU_VAT {

		/**
		 * Constructor
		 */
		public function __construct() {
			if ( ! $this->is_enable() ) {
				return;
			}
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'maybe_disable_eu_vat_during_upsell' ), 1 );
		}

		/**
		 * Disable EU VAT during upsell process
		 */
		public function maybe_disable_eu_vat_during_upsell() {
			if ( $this->is_upsell_context() ) {
				remove_action( 'woocommerce_before_calculate_totals', array( alg_wc_eu_vat()->core, 'maybe_exclude_vat' ), 99 );
			}
		}

		/**
		 * Check if we're in an upsell context
		 */
		private function is_upsell_context() {
			// Check for FunnelKit upsell indicators
			return (
				( isset( $_REQUEST['wfocu-key'] ) && ! empty( $_REQUEST['wfocu-key'] ) ) ||
				( isset( $_REQUEST['offer'] ) && ! empty( $_REQUEST['offer'] ) ) ||
				( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) && strpos( $_POST['action'], 'wfocu_' ) === 0 )
			);
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
