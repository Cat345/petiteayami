<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * https://fibofilters.com/
 * Disables FiboFilters' front-end pipeline on FunnelKit checkout pages so its
 * pre_get_posts / template_redirect hooks and shared WC-loop-state side effects
 * do not blank out the global checkout override.
 *
 * class WFACP_Compatibility_FiboFilters
 */
if ( ! class_exists( 'WFACP_Compatibility_FiboFilters' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_FiboFilters {
		public function __construct() {
			add_filter( 'fibofilters/nofibofilters', array( $this, 'disable_on_checkout' ) );
			add_filter( 'wfacp_css_js_removal_paths', array( $this, 'remove_js' ) );
		}

		/**
		 * Tell FiboFilters to bail out of its front pipeline on FunnelKit checkout pages.
		 * Returning '1' makes FiboFilters skip prepare_request_data() and asset loading.
		 *
		 * Its theme integration reads this filter on plugins_loaded, before WFACP_Template_Loader
		 * has detected the page, so the request path answers for that window.
		 *
		 * @param string $value Incoming filter value.
		 *
		 * @return string
		 */
		public function disable_on_checkout( $value ) {
			if ( '1' === $value || '2' === $value ) {
				return $value;
			}

			if ( true === WFACP_Template_Loader::$is_checkout ) {
				return '1';
			}

			$uri   = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared against known slugs, never output.
			$path  = is_string( $uri ) ? (string) wp_parse_url( $uri, PHP_URL_PATH ) : '';
			$bases = array( WFACP_Common::get_url_rewrite_slug() );

			foreach ( array( 'woocommerce_checkout_page_id', 'woocommerce_cart_page_id' ) as $option ) {
				$bases[] = get_post_field( 'post_name', (int) get_option( $option ) );
			}

			// Compare whole segments so a language prefix such as /de/checkouts/ still matches.
			$segments = array_filter( explode( '/', strtolower( $path ) ) );
			$bases    = array_filter( array_map( 'strtolower', array_map( 'strval', $bases ) ) );

			return array_intersect( $bases, $segments ) ? '1' : $value;
		}

		public function remove_js( $path ) {
			$path[] = 'fibofilters';

			return $path;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_FiboFilters(), 'fibofilters' );
}
