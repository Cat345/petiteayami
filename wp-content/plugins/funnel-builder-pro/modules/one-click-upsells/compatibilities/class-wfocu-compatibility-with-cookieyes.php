<?php
/*
 * Plugin Name: CookieYes | GDPR Cookie Consent (cookie-law-info)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Compatibility_With_CookieYes', false ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Compatibility_With_CookieYes {

		public function __construct() {
			// WFOCU_Public::if_is_offer() runs on template_redirect at 10 and caches its result.
			add_action( 'template_redirect', array( $this, 'remove_actions' ), 20 );
		}

		/**
		 * Required by every class registered with WFOCU_Plugin_Compatibilities: the registry calls
		 * is_enable() on each one while resolving currency prices.
		 *
		 * @return bool
		 */
		public function is_enable() {
			return defined( 'CLI_VERSION' );
		}

		public function remove_actions() {
			if ( ! class_exists( 'CookieYes\Lite\Frontend\Frontend', false ) || ! $this->is_offer_page() ) {
				return;
			}

			/**
			 * Filters the CookieYes frontend hooks that FunnelKit strips on an upsell offer page.
			 *
			 * CookieYes blocks scripts until consent and only restores them when consent is "yes",
			 * so a visitor who rejects leaves the offer's scripts dead and its buttons inert.
			 *
			 * Which hook does the damage depends on the install. On a self hosted banner it is
			 * `enqueue_scripts` ( loads js/script.js ). On a site connected to the CookieYes cloud
			 * that branch is skipped and `insert_script` prints the CDN runtime instead, while
			 * `insert_styles` and `banner_html` return early. All four are removed so both setups
			 * are covered. Note this also drops Google Consent Mode on offer pages, because
			 * `insert_script` emits `_ckyGcm` and the CDN tag from the same method.
			 *
			 * @param array $hooks Map of CookieYes Frontend method => WordPress hook to remove.
			 */
			$hooks_to_remove = apply_filters(
				'wfocu_cookieyes_remove_frontend_hooks',
				array(
					'enqueue_scripts' => 'wp_enqueue_scripts',
					'insert_script'   => 'wp_head',
					'insert_styles'   => 'wp_head',
					'banner_html'     => 'wp_footer',
				)
			);

			if ( ! is_array( $hooks_to_remove ) ) {
				return;
			}

			foreach ( $hooks_to_remove as $method => $hook ) {
				WFOCU_Common::remove_actions( $hook, 'CookieYes\Lite\Frontend\Frontend', $method );
			}
		}

		/**
		 * Offer pages have no "page found" action to hook, unlike wfacp_checkout_page_found.
		 *
		 * @return bool
		 */
		public function is_offer_page() {
			if ( ! function_exists( 'WFOCU_Core' ) || is_null( WFOCU_Core()->public ) ) {
				return false;
			}

			$public = WFOCU_Core()->public;

			return ( is_callable( array( $public, 'if_is_offer' ) ) && true === $public->if_is_offer() )
				|| ( is_callable( array( $public, 'if_is_preview' ) ) && true === $public->if_is_preview() );
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_CookieYes(), 'wfocu_compatibility_with_cookieyes' );
}
