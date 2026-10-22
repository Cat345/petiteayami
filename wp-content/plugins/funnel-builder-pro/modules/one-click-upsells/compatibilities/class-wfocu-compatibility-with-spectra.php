<?php
/**
 * Spectra (Ultimate Addons for Gutenberg) compatibility.
 *
 * Spectra's Container block re-runs its inner HTML through wp_kses() on render, which strips the
 * disallowed `javascript:` protocol from FunnelKit reject/accept anchors and rewrites
 * `href="javascript:void(0);"` into the relative URL `href="void(0);"`. The browser then performs a
 * real navigation to that bogus URL, unloading the page and aborting the in-flight skip/accept AJAX.
 *
 * This filter restores `href="javascript:void(0);"` for anchors that carry the `wfocu_skip_offer`
 * or `wfocu_upsell` class once the href has been reduced to `void(0);`. It is strictly scoped to
 * those FunnelKit controls so it can never re-introduce a `javascript:` href on arbitrary content.
 *
 * @package woofunnels-upstroke-one-click-upsell
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Compatibility_With_Spectra' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Compatibility_With_Spectra {

		public function __construct() {
			// Priority 99 so this runs after Spectra's Container render_block callback has sanitized the inner HTML.
			add_filter( 'render_block', array( $this, 'restore_action_link_href' ), 99, 2 );
		}

		public function is_enable() {
			return defined( 'SPECTRA_BLOCKS_VER' );
		}

		/**
		 * Restore the `javascript:void(0);` href on FunnelKit reject/accept anchors stripped by a sanitizer.
		 *
		 * @param string $block_content Rendered block HTML.
		 * @param array  $block         Parsed block.
		 *
		 * @return string
		 */
		public function restore_action_link_href( $block_content, $block ) {
			if ( ! is_string( $block_content ) || '' === $block_content ) {
				return $block_content;
			}

			// Fast bail: nothing to do unless a FunnelKit action class and the corrupted href are both present.
			if ( false === strpos( $block_content, 'void(0);' ) || ( false === strpos( $block_content, 'wfocu_skip_offer' ) && false === strpos( $block_content, 'wfocu_upsell' ) ) ) {
				return $block_content;
			}

			// Match each opening anchor tag; only rewrite those that carry a FunnelKit action class and the corrupted href.
			$restored = preg_replace_callback(
				'#<a\b[^>]*>#i',
				function ( $matches ) {
					$tag = $matches[0];

					if ( ( false === strpos( $tag, 'wfocu_skip_offer' ) && false === strpos( $tag, 'wfocu_upsell' ) ) || false === strpos( $tag, 'void(0);' ) ) {
						return $tag;
					}

					$rewritten = preg_replace( '#\bhref="void\(0\);"#i', 'href="javascript:void(0);"', $tag );

					return is_string( $rewritten ) ? $rewritten : $tag;
				},
				$block_content
			);

			// preg_replace_callback() returns null on failure (e.g. backtrack limit); fall back to the original content.
			return is_string( $restored ) ? $restored : $block_content;
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Spectra(), 'spectra' );
}
