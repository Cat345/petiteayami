<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Plugin Name: Etch Page Builder
 * Compatibility class for WFACP embed form shortcode
 */

if ( ! class_exists( 'WFACP_Compatibility_With_Etch' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Etch {

		public function __construct() {
			// Detect shortcode in post content
			add_filter( 'wfacp_shortcode_exist', array( $this, 'detect_shortcode' ), 10, 2 );
			add_filter( 'wfacp_detect_shortcode', array( $this, 'extract_shortcode_from_content' ), 10, 2 );

			// Don't block shortcode printing on frontend
			add_filter( 'wfacp_do_not_allow_shortcode_printing', array( $this, 'do_not_block_shortcode_printing' ) );

			// Process wfacp_forms shortcode when Etch processes shortcodes
			add_filter( 'etch_process_shortcodes', array( $this, 'process_wfacp_forms_shortcode' ), 10, 2 );
		}

		/**
		 * Detect if wfacp_forms shortcode exists in post content
		 * Simple string search works because shortcodes appear as text in block markup too
		 *
		 * @param bool    $status Current shortcode detection status
		 * @param WP_Post $post Current post object
		 * @return bool True if shortcode found, false otherwise
		 */
		public function detect_shortcode( $status, $post ) {
			if ( true === $status || ! defined( 'ETCH_PLUGIN_DIR' ) ) {
				return $status;
			}

			if ( is_null( $post ) || ! $post instanceof WP_Post ) {
				return $status;
			}

			$content = $post->post_content;

			if ( ! empty( $content ) && ( false !== strpos( $content, '[wfacp_forms' ) || false !== strpos( $content, '[WFACP_FORMS' ) ) ) {

				return true;
			}

			$excerpt = $post->post_excerpt;
			if ( ! empty( $excerpt ) && ( false !== strpos( $excerpt, '[wfacp_forms' ) || false !== strpos( $excerpt, '[WFACP_FORMS' ) ) ) {

				return true;
			}

			return $status;
		}

		/**
		 * Extract shortcode from content
		 * Simple extraction - just find the first shortcode occurrence
		 *
		 * @param string  $post_content Original post content
		 * @param WP_Post $post Current post object
		 * @return string Content with shortcode extracted or original content
		 */
		public function extract_shortcode_from_content( $post_content, $post ) {
			if ( ! defined( 'ETCH_PLUGIN_DIR' ) || is_null( $post ) || ! $post instanceof WP_Post ) {
				return $post_content;
			}

			// Simple extraction - find first shortcode
			$start_position = strpos( $post_content, '[wfacp_forms' );
			if ( false === $start_position ) {
				$start_position = strpos( $post_content, '[WFACP_FORMS' );
			}

			if ( false !== $start_position ) {
				$shortcode_string = substr( $post_content, $start_position );
				$closing_position = strpos( $shortcode_string, ']', 1 );
				if ( false !== $closing_position ) {
					$shortcode_string = substr( $post_content, $start_position, $closing_position + 1 );
					if ( strlen( $shortcode_string ) > 0 ) {
						return $shortcode_string;
					}
				}
			}

			return $post_content;
		}

		/**
		 * Don't block shortcode printing when in Etch blocks
		 *
		 * @param bool $status Current blocking status
		 * @return bool
		 */
		public function do_not_block_shortcode_printing( $status ) {
			if ( ! defined( 'ETCH_PLUGIN_DIR' ) ) {
				return $status;
			}

			// Don't block on frontend (where blocks are rendered)
			if ( ! is_admin() ) {
				return false; // false means don't block
			}

			// Allow in admin AJAX for block previews
			if ( is_admin() && wp_doing_ajax() ) {
				return false;
			}

			return $status;
		}

		/**
		 * Process wfacp_forms shortcode when Etch processes shortcodes
		 *
		 * Etch's ShortcodeProcessor calls this filter before do_shortcode().
		 * We let WordPress handle the shortcode naturally via do_shortcode(),
		 * which properly handles the two-phase execution of wfacp_forms.
		 *
		 * @param string $html HTML content with shortcodes
		 * @param string $block_name Block name being processed
		 * @return string Processed HTML
		 */
		public function process_wfacp_forms_shortcode( $html, $block_name ) {
			if ( ! defined( 'ETCH_PLUGIN_DIR' ) ) {
				return $html;
			}

			// Only process if contains wfacp_forms shortcode
			if ( false === stripos( $html, '[wfacp_forms' ) ) {
				return $html;
			}

			return do_shortcode( $html );
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Etch(), 'etch-builder' );
}
