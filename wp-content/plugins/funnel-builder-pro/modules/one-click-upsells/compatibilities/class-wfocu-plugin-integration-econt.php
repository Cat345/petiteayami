<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Plugin_Integration_Econt' ) ) {
	/**
	 * Econt Express Shipping Compatibility
	 * Skips funnel re-decision when Econt triggers update_checkout for shipping cost (econt.js:1310).
	 * That call re-runs maybe_decide_funnel_on_fragments → setup_funnel(), causing preview=true.
	 */
	class WFOCU_Plugin_Integration_Econt {

		public function __construct() {
			if ( ! $this->is_enable() ) {
				return;
			}
			add_action( 'wp_footer', array( $this, 'add_js_marker' ), 5 );
			add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'skip_funnel_on_econt_update' ), 5 );
		}

		public function add_js_marker() {
			if ( ! is_checkout() ) {
				return;
			}
			?>
			<script>jQuery(function($){$(document.body).on('update_checkout',function(e,a){if(a==='calculate_loading')$('form.checkout').append('<input type=hidden name=wfocu_econt_skip value=1>')});$(document.body).on('updated_checkout',function(){$('[name=wfocu_econt_skip]').remove()})});</script>
			<?php
		}

		public function skip_funnel_on_econt_update( $fragments ) {
			if ( ! isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				return $fragments;
			}
			$post_data = bwf_clean( wp_unslash( $_POST['post_data'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
			if ( strpos( $post_data, 'wfocu_econt_skip=1' ) !== false && WFOCU_Core()->public ) {
				remove_filter( 'woocommerce_update_order_review_fragments', array( WFOCU_Core()->public, 'maybe_decide_funnel_on_fragments' ), 10 );
			}
			return $fragments;
		}

		public function is_enable() {
			return defined( 'ECONT_PLUGIN_DIR' ) || class_exists( 'Econt_Express' );
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Plugin_Integration_Econt(), 'wfocu_plugin_integration_econt' );
}
