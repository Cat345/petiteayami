<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Plugin Name: CookieYes | GDPR Cookie Consent (cookie-law-info)
 */

if ( ! class_exists( 'WFACP_CookieYes_Cookie_Consent', false ) ) {
	#[AllowDynamicProperties]
	class WFACP_CookieYes_Cookie_Consent {
		public function __construct() {
			add_action( 'wfacp_checkout_page_found', array( $this, 'remove_actions' ) );
		}

		public function remove_actions() {
			if ( class_exists( 'CookieYes\Lite\Frontend\Frontend' ) ) {
				/**
				 * Filters the CookieYes frontend hooks that FunnelKit strips on the Aero checkout page.
				 *
				 * By default only the visually-conflicting CookieYes banner/style hooks are removed
				 * while `insert_script` is preserved, so Google Consent Mode ( `_ckyGcm` + `gcm.js` )
				 * and the core CookieYes runtime keep loading and can restore a shopper's prior consent
				 * on the checkout page. Integrators can return an empty array to keep the full CookieYes
				 * frontend, or add `'insert_script' => 'wp_head'` to also strip Google Consent Mode.
				 *
				 * @param array $hooks Map of CookieYes Frontend method => WordPress hook to remove.
				 */
				$hooks_to_remove = apply_filters( 'wfacp_cookieyes_remove_frontend_hooks', array(
					'enqueue_scripts' => 'wp_enqueue_scripts',
					'insert_styles'   => 'wp_head',
					'banner_html'     => 'wp_footer',
				) );

				if ( ! is_array( $hooks_to_remove ) ) {
					return;
				}

				foreach ( $hooks_to_remove as $method => $hook ) {
					WFACP_Common::remove_actions( $hook, 'CookieYes\Lite\Frontend\Frontend', $method );
				}
			}
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_CookieYes_Cookie_Consent(), 'cookie_law_info' );
}
