<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Class WFFN_Compatibility_With_Avada_Theme
 */
if ( ! class_exists( 'WFFN_Compatibility_With_Avada_Theme' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Compatibility_With_Avada_Theme {

		public function __construct() {
			add_filter( 'elementor/frontend/builder_content_data', array( $this, 'remove_avada_parse_elementor_content' ), 9, 2 );
			add_action( 'template_redirect', array( $this, 'disable_off_canvas_on_checkout' ), 99 );
		}

		/**
		 * Avada Off Canvas (e.g. a site-wide mini-cart sliding bar) loads itself on every page via
		 * `wp_footer`, including the FunnelKit checkout, where it renders a SECOND copy of the
		 * checkout form (`<form id="wfacp_checkout_form">`). On `update_order_review` the duplicate
		 * empty `wfob_input_hidden_data` overrides the real one, so order bumps (and other fields)
		 * silently fail to apply. Stop Avada from rendering Off Canvas on any checkout page.
		 *
		 * `is_checkout()` covers native WC checkout AND FunnelKit funnel/override checkouts, since
		 * FunnelKit forces `woocommerce_is_checkout` true at `wp` priority 5 (before this runs).
		 */
		public function disable_off_canvas_on_checkout() {
			if ( function_exists( 'is_checkout' ) && is_checkout() && function_exists( 'AWB_Off_Canvas_Front_End' ) ) {
				remove_action( 'wp_footer', array( AWB_Off_Canvas_Front_End(), 'insert' ), 0 );
			}
		}

		public function is_enable() {
			if ( class_exists( 'FusionBuilder' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @param $data
		 * @param $post_id
		 *
		 * @return mixed|void
		 */
		public function remove_avada_parse_elementor_content( $data, $post_id ) {

			if ( $post_id <= 0 ) {
				return $data;
			}
			$post = get_post( $post_id );

			if ( ! is_null( $post ) && in_array(
				$post->post_type,
				array(
					'wfacp_checkout',
					'wfocu_offer',
					'wffn_landing',
					'wffn_ty',
					'wffn_optin',
					'wffn_oty',

				),
				true
			) ) {

				WFFN_Common::remove_actions( 'elementor/frontend/builder_content_data', 'FusionBuilder', 'parse_elementor_content' );
			}

			return $data;
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Compatibility_With_Avada_Theme(), 'avada' );
}
