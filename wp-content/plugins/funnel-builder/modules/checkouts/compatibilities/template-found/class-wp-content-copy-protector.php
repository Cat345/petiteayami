<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Content Copy Protector (wp-content-copy-protector) compatibility.
 *
 * The copy protector installs global blockers that break the checkout UI:
 *  - `document.onmousedown` returns false for every non-input element, so nothing
 *    outside form fields is clickable — Google address autocomplete suggestions
 *    (.pac-item), select2 dropdowns, custom radio rows, etc.
 *  - a window `touchstart` listener calls preventDefault() — taps die on mobile.
 *  - `html { user-select:none }` is applied globally.
 *
 * There is no content worth copy-protecting on a checkout, so remove the
 * protector's frontend output on FunnelKit checkout pages only. The removals run
 * on `wfacp_after_checkout_page_found`, which fires on `template_include` — before
 * `wp_enqueue_scripts` / `wp_head` / `wp_footer` — and only when the FunnelKit
 * checkout page is the one being rendered; everywhere else the copy protector
 * keeps working untouched.
 */
if ( ! class_exists( 'WFACP_Compatibility_With_WP_Content_Copy_Protector' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_WP_Content_Copy_Protector {

		public function __construct() {
			/* checkout page */
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_copy_protection' ) );
		}

		public function remove_copy_protection() {
			remove_action( 'wp_head', 'wccp_main_settings' );             // selection / mousedown blockers
			remove_action( 'wp_head', 'right_click_premium_settings' );   // context-menu blocker
			remove_action( 'wp_head', 'wccp_css_settings' );              // html { user-select:none }
			remove_action( 'wp_footer', 'alert_message' );                // "content is protected" popup markup
			remove_action( 'wp_enqueue_scripts', 'wccp_free_enqueue_front_end_scripts' );
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_WP_Content_Copy_Protector(), 'wp_content_copy_protector' );
}
