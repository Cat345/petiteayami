<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All in One SEO compatibility.
 *
 * Prevents AIOSEO Pro Crawl Cleanup from stripping FunnelKit thank you page
 * query args before order/upsell validation can use them.
 *
 * @since 3.13.2
 */
if ( ! class_exists( 'WFFN_AIOSEO_Compatibility' ) ) {

	class WFFN_AIOSEO_Compatibility {

		public function __construct() {
			add_action( 'template_redirect', array( $this, 'maybe_disable_query_arg_blocking' ), 0 );
			add_filter( 'aioseo_can_remove_query_args', array( $this, 'maybe_prevent_query_arg_removal' ) );
		}

		/**
		 * @return bool
		 */
		public function is_enable() {
			return function_exists( 'aioseo' );
		}

		/**
		 * Disable AIOSEO's query arg blocking on FunnelKit thank you pages.
		 *
		 * AIOSEO creates the QueryArgs instance without keeping a public reference,
		 * so remove its callback from the WP hook registry before priority 1 runs.
		 *
		 * @return void
		 */
		public function maybe_disable_query_arg_blocking() {
			if ( true !== $this->is_funnelkit_thank_you_page() ) {
				return;
			}

			if ( ! class_exists( 'AIOSEO\Plugin\Common\Main\QueryArgs' ) ) {
				return;
			}

			global $wp_filter;

			if ( empty( $wp_filter['template_redirect'] ) || ! $wp_filter['template_redirect'] instanceof WP_Hook ) {
				return;
			}

			if ( empty( $wp_filter['template_redirect']->callbacks[1] ) || ! is_array( $wp_filter['template_redirect']->callbacks[1] ) ) {
				return;
			}

			foreach ( $wp_filter['template_redirect']->callbacks[1] as $callback ) {
				if ( empty( $callback['function'] ) || ! is_array( $callback['function'] ) || empty( $callback['function'][0] ) || empty( $callback['function'][1] ) ) {
					continue;
				}

				if ( ! is_object( $callback['function'][0] ) || 'maybeRemoveQueryArgs' !== $callback['function'][1] ) {
					continue;
				}

				if ( $callback['function'][0] instanceof AIOSEO\Plugin\Common\Main\QueryArgs ) {
					remove_action( 'template_redirect', array( $callback['function'][0], 'maybeRemoveQueryArgs' ), 1 );
					break;
				}
			}
		}

		/**
		 * Clean integration path if AIOSEO adds this filter in future versions.
		 *
		 * @param bool $can_remove_query_args Whether AIOSEO can remove query args.
		 *
		 * @return bool
		 */
		public function maybe_prevent_query_arg_removal( $can_remove_query_args ) {
			if ( true === $this->is_funnelkit_thank_you_page() ) {
				return false;
			}

			return $can_remove_query_args;
		}

		/**
		 * @return bool
		 */
		private function is_funnelkit_thank_you_page() {
			return function_exists( 'is_singular' ) && is_singular( 'wffn_ty' );
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_AIOSEO_Compatibility(), 'aioseo' );
}
