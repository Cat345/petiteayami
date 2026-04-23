<?php
/**
 * Cornerstone Builder Compatibility
 * Fixes shortcode rendering on frontend when pages are built with Cornerstone
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Cornerstone' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Cornerstone {
		private $shortcode_content = '';
		private $meta_key          = '_cornerstone_data';
		private $processed_posts   = array();

		public function __construct() {
			// Only run on frontend, not in admin or REST API
			// Skip in admin to avoid unnecessary processing and potential conflicts
			if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				return;
			}

			// Early check: Only proceed if Cornerstone is active
			if ( ! defined( 'CS_VERSION' ) ) {
				return;
			}

			// Detect shortcodes in Cornerstone data (for FunnelKit's detection system)
			add_filter( 'wfacp_shortcode_exist', array( $this, 'is_shortcode_exists' ), 10, 2 );
			add_filter( 'wfacp_detect_shortcode', array( $this, 'send_shortcode_content' ) );

			// Process shortcodes in Cornerstone's rendered HTML output
			// Priority 20 ensures it runs after Cornerstone's own processing
			add_filter( 'cs_document_html_content', array( $this, 'process_shortcodes_in_html' ), 20, 1 );

			// Fallback: Process shortcodes in the_content filter
			// Priority 999 ensures it runs after most other filters
			add_filter( 'the_content', array( $this, 'process_shortcodes_in_content' ), 999, 1 );
		}

		/**
		 * Check if shortcodes exist in Cornerstone data
		 *
		 * @param bool    $status Current status
		 * @param WP_Post $post Post object
		 * @return bool
		 */
		public function is_shortcode_exists( $status, $post ) {
			if ( true === $status ) {
				return $status;
			}

			$content = $this->get_shortcode_content( $post );
			if ( false !== $content ) {
				$this->shortcode_content = $content;
				$status                  = true;
			}

			return $status;
		}

		/**
		 * Extract shortcode from Cornerstone meta data
		 * Uses regex for more robust extraction that handles attributes correctly
		 *
		 * @param WP_Post $post Post object
		 * @return string|false Shortcode string or false if not found
		 */
		public function get_shortcode_content( $post ) {
			if ( is_null( $post ) || ! $post instanceof WP_Post ) {
				return false;
			}

			// Only get Cornerstone meta, not all meta (performance optimization)
			$cornerstone_data = get_post_meta( $post->ID, $this->meta_key, true );
			if ( empty( $cornerstone_data ) ) {
				return false;
			}

			// Convert to JSON string for searching
			$json_data = json_encode( $cornerstone_data );
			if ( false === $json_data ) {
				return false;
			}

			// Use regex patterns to find shortcodes with any attributes
			// Pattern matches: [wfacp_forms], [wfacp_forms id="123"], [wfacp_mini_cart], etc.
			$patterns = array(
				'/\[wfacp_forms[^\]]*\]/i',
				'/\[wfacp_mini_cart[^\]]*\]/i',
			);

			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $json_data, $matches ) ) {
					$shortcode = $matches[0];
					// Clean up escaped characters from JSON encoding
					$shortcode = str_replace( '."\".', '', stripslashes( $shortcode ) );
					$shortcode = preg_replace( '/\\\\/', '', $shortcode );

					if ( ! empty( $shortcode ) ) {
						return $shortcode;
					}
				}
			}

			return false;
		}

		/**
		 * Return shortcode content for detection
		 *
		 * @param string $post_content Original post content
		 * @return string
		 */
		public function send_shortcode_content( $post_content ) {
			return ! empty( $this->shortcode_content ) ? $this->shortcode_content : $post_content;
		}

		/**
		 * Process shortcodes in Cornerstone's rendered HTML output
		 * This hook runs when Cornerstone renders the final HTML
		 *
		 * @param string $content Rendered HTML content from Cornerstone
		 * @return string Content with shortcodes processed
		 */
		public function process_shortcodes_in_html( $content ) {
			// Input validation - early return for invalid content
			if ( ! is_string( $content ) || empty( $content ) ) {
				return $content;
			}

			// Early return if content doesn't contain our shortcodes
			if ( false === strpos( $content, '[wfacp_forms' ) && false === strpos( $content, '[wfacp_mini_cart' ) ) {
				return $content;
			}

			global $post;
			$post_id = is_null( $post ) ? 0 : $post->ID;

			// Prevent infinite loops and double processing per post
			if ( isset( $this->processed_posts[ $post_id ] ) ) {
				return $content;
			}

			// Mark this post as processed before processing to prevent recursion
			$this->processed_posts[ $post_id ] = true;

			// Process shortcodes in the HTML
			$content = do_shortcode( $content );

			return $content;
		}

		/**
		 * Fallback: Process shortcodes in the_content filter
		 * This ensures shortcodes are processed even if cs_document_html_content doesn't fire
		 *
		 * @param string $content Post content
		 * @return string Content with shortcodes processed
		 */
		public function process_shortcodes_in_content( $content ) {
			// Input validation - early return for invalid content
			if ( ! is_string( $content ) || empty( $content ) ) {
				return $content;
			}

			// Early return if content doesn't contain our shortcodes
			if ( false === strpos( $content, '[wfacp_forms' ) && false === strpos( $content, '[wfacp_mini_cart' ) ) {
				return $content;
			}

			global $post;

			// Skip if no post object
			if ( is_null( $post ) ) {
				return $content;
			}

			$post_id = $post->ID;

			// Skip if already processed via cs_document_html_content
			if ( isset( $this->processed_posts[ $post_id ] ) ) {
				return $content;
			}

			// Check if this is a Cornerstone page (only query if needed)
			// Use single meta query instead of get_post_meta to avoid loading all meta
			if ( empty( get_post_meta( $post_id, $this->meta_key, true ) ) ) {
				return $content;
			}

			// Mark as processed before processing to prevent recursion
			$this->processed_posts[ $post_id ] = true;

			// Process shortcodes
			$content = do_shortcode( $content );

			return $content;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Cornerstone(), 'cornerstone' );
}
