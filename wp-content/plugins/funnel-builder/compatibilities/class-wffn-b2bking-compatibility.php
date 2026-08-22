<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WFFN_B2BKing_Compatibility
 *
 * Stops B2BKing and its addons from flushing rewrite rules on every init,
 * which otherwise wipes the rewrite_rules option and 404s pages until the
 * user resaves Permalinks.
 */
if ( ! class_exists( 'WFFN_B2BKing_Compatibility' ) ) {

	class WFFN_B2BKing_Compatibility {

		public function __construct() {
			add_filter( 'b2bking_flush_permalinks', '__return_false' );
			add_filter( 'option_b2bking_force_permalinks_flushing_setting', '__return_zero' );
			add_filter( 'wpml_st_disable_rewrite_rules', '__return_true' );

			add_action( 'plugins_loaded', array( $this, 'replace_credit_extend_endpoint' ), 99 );
		}

		/**
		 * b2bking-company-credit-addon hooks an extend_endpoint() callback that
		 * unconditionally calls flush_rewrite_rules() on every init, with no filter
		 * to disable it. Swap it for a flush-free variant.
		 */
		public function replace_credit_extend_endpoint() {
			global $wp_filter;
			if ( ! isset( $wp_filter['b2bking_extend_endpoints'] ) ) {
				return;
			}
			foreach ( $wp_filter['b2bking_extend_endpoints']->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $key => $cb ) {
					if ( ! is_array( $cb['function'] ) || ! is_object( $cb['function'][0] ) ) {
						continue;
					}
					if ( 'B2bkingcredit_Public' !== get_class( $cb['function'][0] ) ) {
						continue;
					}
					if ( 'extend_endpoint' !== $cb['function'][1] ) {
						continue;
					}
					unset( $wp_filter['b2bking_extend_endpoints']->callbacks[ $priority ][ $key ] );
					add_action( 'b2bking_extend_endpoints', array( $this, 'safe_credit_endpoint' ), $priority );
				}
			}
		}

		/**
		 * Flush-free replacement for B2bkingcredit_Public::extend_endpoint():
		 * registers the company-credit endpoint without calling flush_rewrite_rules().
		 */
		public function safe_credit_endpoint() {
			if ( 1 === intval( get_option( 'b2bking_enable_company_credit_setting', 1 ) ) ) {
				add_rewrite_endpoint(
					get_option( 'b2bking_credit_endpoint_setting', 'company-credit' ),
					EP_ROOT | EP_PAGES | EP_PERMALINK
				);
			}
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_B2BKing_Compatibility(), 'b2bking' );
}
