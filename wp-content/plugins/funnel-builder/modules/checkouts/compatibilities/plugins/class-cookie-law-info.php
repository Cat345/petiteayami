<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Plugin Name: CookieYes | GDPR Cookie Consent (cookie-law-info)
 */

if ( ! class_exists( 'WFACP_CookieYes_Cookie_Consent' ) ) {
	#[AllowDynamicProperties]
	class WFACP_CookieYes_Cookie_Consent {
		public function __construct() {
			add_action( 'wfacp_checkout_page_found', array( $this, 'remove_actions' ) );
		}

		public function remove_actions() {
			if ( class_exists( 'CookieYes\Lite\Frontend\Frontend' ) ) {
				WFACP_Common::remove_actions( 'wp_enqueue_scripts', 'CookieYes\Lite\Frontend\Frontend', 'enqueue_scripts' );
				WFACP_Common::remove_actions( 'wp_head', 'CookieYes\Lite\Frontend\Frontend', 'insert_script' );
				WFACP_Common::remove_actions( 'wp_head', 'CookieYes\Lite\Frontend\Frontend', 'insert_styles' );
				WFACP_Common::remove_actions( 'wp_footer', 'CookieYes\Lite\Frontend\Frontend', 'banner_html' );
			}
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_CookieYes_Cookie_Consent(), 'cookie_law_info' );
}
