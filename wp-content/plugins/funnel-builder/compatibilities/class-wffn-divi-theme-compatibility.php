<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WFFN_Compatibility_With_Divi_Theme
 */
if ( ! class_exists( 'WFFN_Compatibility_With_Divi_Theme' ) ) {
	class WFFN_Compatibility_With_Divi_Theme {

		public function __construct() {
			add_filter( 'et_builder_add_outer_content_wrap', array( $this, 'maybe_filter' ), 999 );
			add_filter( 'wffn_container_attrs', array( $this, 'add_id_for_wffn_container' ) );
			add_action( 'wp_before_admin_bar_render', array( $this, 'maybe_hide_bw_compat_notice' ), 999 );

			// Divi 5 only: prevent Gutenberg from mangling D4 shortcode content
			// when saving FunnelKit post types. When a user opens a FunnelKit page
			// in Gutenberg (e.g. to change the page template) and saves, Divi 5
			// wraps the D4 shortcodes into a single divi/text block inside a
			// generic section/row/column wrapper — destroying the page layout.
			if ( function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled() ) {
				add_filter( 'wp_insert_post_data', array( $this, 'protect_d4_content_on_gutenberg_save' ), 1, 2 );
				add_action( 'wp_enqueue_scripts', array( $this, 'add_d4_animation_fix_on_d5' ) );
				add_action( 'wp_footer', array( $this, 'add_d4_fitvids_fix_on_d5' ), 99 );
			}
		}

		public function is_enable() {
			if ( defined( 'ET_CORE_VERSION' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Prevent Gutenberg from destroying D4 shortcode content on FunnelKit pages.
		 *
		 * When a FunnelKit page with D4 shortcode content is opened in the Gutenberg
		 * block editor (e.g. to change the page template) and saved, Gutenberg wraps
		 * the entire shortcode tree into a single divi/text block inside a generic
		 * divi/placeholder > divi/section > divi/row > divi/column structure. This
		 * destroys the page layout.
		 *
		 * This filter detects that pattern: the new content has D5 block wrappers but
		 * still contains D4 shortcodes as text, while the original DB content was pure
		 * D4 shortcodes. In that case, we preserve the original content.
		 *
		 * @param array $data    Post data about to be saved.
		 * @param array $postarr Raw post data from the request.
		 *
		 * @return array Possibly modified post data.
		 */
		public function protect_d4_content_on_gutenberg_save( $data, $postarr ) {
			$fk_post_types = array( 'wfacp_checkout', 'wfocu_offer', 'wffn_landing', 'wffn_ty', 'wffn_optin', 'wffn_oty' );

			if ( ! in_array( $data['post_type'], $fk_post_types, true ) ) {
				return $data;
			}

			$post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
			if ( $post_id < 1 ) {
				return $data;
			}

			// Skip if this is a Divi VB save — VB uses its own sync endpoint,
			// not the standard wp_insert_post flow from Gutenberg.
			if ( isset( $_REQUEST['et_fb'] ) || isset( $_REQUEST['et_pb_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Read-only check to detect Divi VB context, no data mutation
				return $data;
			}

			// Skip if this is a Divi VB REST sync request.
			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$uri = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( false !== strpos( $uri, '/wp-json/divi/v1/' ) ) {
					return $data;
				}
			}

			// Only protect posts that were built with the Divi builder.
			if ( 'on' !== get_post_meta( $post_id, '_et_pb_use_builder', true ) ) {
				return $data;
			}

			// Skip if already converted to D5 — this post was saved from VB5.
			if ( 'on' === get_post_meta( $post_id, '_et_pb_use_divi_5', true ) ) {
				return $data;
			}

			$new_content = $data['post_content'];

			// The mangled pattern: D5 placeholder wrapper containing D4 shortcodes
			// as raw text instead of properly converted blocks.
			$has_d5_wrapper    = false !== strpos( $new_content, '<!-- wp:divi/placeholder' );
			$has_d4_shortcodes = false !== strpos( $new_content, '[et_pb_' );

			if ( ! $has_d5_wrapper || ! $has_d4_shortcodes ) {
				return $data;
			}

			// Get the original content from the database.
			$original_content = get_post_field( 'post_content', $post_id, 'raw' );

			if ( empty( $original_content ) ) {
				return $data;
			}

			// Only restore if the original was pure D4 shortcodes.
			if ( preg_match( '/^\s*\[et_pb_/', $original_content ) ) {
				$data['post_content'] = $original_content;
			}

			return $data;
		}

		/**
		 * Check if the current page is a FunnelKit post rendering D4 shortcodes.
		 *
		 * @return bool
		 */
		private function is_d4_shortcode_page() {
			global $post;

			if ( is_null( $post ) ) {
				return false;
			}

			$fk_post_types = array( 'wfacp_checkout', 'wfocu_offer', 'wffn_landing', 'wffn_ty', 'wffn_optin', 'wffn_oty' );
			if ( ! in_array( $post->post_type, $fk_post_types, true ) ) {
				return false;
			}

			if ( 'on' !== get_post_meta( $post->ID, '_et_pb_use_builder', true ) ) {
				return false;
			}

			if ( 'on' === get_post_meta( $post->ID, '_et_pb_use_divi_5', true ) ) {
				return false;
			}

			return true;
		}

		/**
		 * On Divi 5 sites rendering D4 shortcodes, the D5 dynamic assets system
		 * does not load the legacy animation CSS because it only detects D5 block
		 * patterns. Inline Divi's legacy_animations_cpt.css so that
		 * `.et_pb_animation_off` elements get `opacity: 1` and waypoint-based
		 * animations (fade, slide, etc.) work correctly.
		 */
		public function add_d4_animation_fix_on_d5() {
			if ( ! $this->is_d4_shortcode_page() ) {
				return;
			}

			$css_file = get_template_directory() . '/includes/builder/feature/dynamic-assets/assets/css/legacy_animations_cpt.css';

			if ( ! file_exists( $css_file ) ) {
				return;
			}

			$css = file_get_contents( $css_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read.

			if ( ! empty( $css ) ) {
				wp_register_style( 'wffn-divi-legacy-animations', false, array(), ET_CORE_VERSION );
				wp_enqueue_style( 'wffn-divi-legacy-animations' );
				wp_add_inline_style( 'wffn-divi-legacy-animations', $css );
			}
		}

		/**
		 * On Divi 5, the fitvids-functions.js script loads AFTER
		 * frontend-scripts.js fires `et_pb_init_modules`, so the fitVids
		 * listener misses the event and responsive video wrapping never runs
		 * for D4 shortcode-rendered videos. Re-trigger fitVids on D4 modules
		 * after all scripts are loaded.
		 */
		public function add_d4_fitvids_fix_on_d5() {
			if ( ! $this->is_d4_shortcode_page() ) {
				return;
			}
			?>
			<script>
				jQuery(function($){if($.fn.fitVids){$('.et_d4_element.et_pb_video, .et_d4_element.et_pb_video_slider').fitVids();}});
			</script>
			<?php
		}

		public function maybe_filter( $add_outer_wrap ) {

			global $post;

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
				return true;
			}

			return $add_outer_wrap;
		}

		/**
		 * Hide Divi's "Backwards Compatibility Mode" admin bar notice on FunnelKit pages.
		 * D4 content is expected until users convert to D5 — the notice is misleading.
		 */
		public function maybe_hide_bw_compat_notice() {
			global $wp_admin_bar, $post;

			if ( ! is_object( $wp_admin_bar ) || ! is_object( $post ) ) {
				return;
			}

			$fk_post_types = array( 'wfocu_offer', 'wffn_ty', 'wffn_optin', 'wffn_landing', 'wffn_oty', 'wfacp_checkout' );
			if ( in_array( $post->post_type, $fk_post_types, true ) ) {
				$wp_admin_bar->remove_node( 'et-builder-shortcode-framework' );
			}
		}

		/**
		 * @param $attrs
		 *
		 * @return mixed
		 */
		public function add_id_for_wffn_container( $attrs ) {
			if ( ! $this->is_enable() ) {
				return $attrs;
			}
			$attrs['id'] = 'page-container';

			return $attrs;
		}
	}

	WFFN_Plugin_Compatibilities::register( new WFFN_Compatibility_With_Divi_Theme(), 'divi_theme' );
}