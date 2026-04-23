<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Divi' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Divi
	 */
	class WFOCU_Compatibility_With_Divi {

		public function __construct() {

			add_filter(
				'et_builder_enabled_builder_post_type_options',
				function ( $options ) {
					if ( ! is_array( $options ) ) {
						$options = array();
					}
					$options[ WFOCU_Common::get_offer_post_type_slug() ] = 'on';

					return $options;
				}
			);
			add_filter( 'wfocu_should_render_script_jquery', array( $this, 'should_prevent_jq_on_editor' ), 10 );
			add_action( 'after_setup_theme', array( $this, 'initialize_deep_integration' ), 2 );
			add_filter( 'wfocu_container_attrs', array( $this, 'add_id_for_wfocu_container' ) );
			add_filter( 'et_builder_add_outer_content_wrap', array( $this, 'maybe_filter' ), 999 );
			add_action( 'wp_enqueue_scripts', array( $this, 'maybe_handle_jquery_defer' ), 1 );
			add_action( 'divi_extensions_init', array( $this, 'init_extension' ) );
		}

		public function is_enable() {
			if ( defined( 'ET_CORE_VERSION' ) ) {
				return true;
			}

			return false;
		}

		public function should_prevent_jq_on_editor( $bool ) {
			// Use Divi's built-in function to check if Visual Builder is enabled
			// This is safer than directly accessing $_GET and avoids nonce verification warnings
			if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
				return false;
			}

			return $bool;
		}

		/**
		 * Check if Divi 5 is active
		 *
		 * @return bool
		 */
		private function is_divi_5() {
			if ( did_action( 'after_setup_theme' ) ) {
				return function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled();
			}
			return false;
		}

		/**
		 * Initialize appropriate Divi integration based on version
		 */
		public function initialize_deep_integration() {
			$is_d5 = $this->is_divi_5();

			if ( $is_d5 ) {
				$this->initialize_divi5_integration();
			}

			// ALWAYS load D4 integration — D4 module classes register shortcode
			// handlers needed for frontend rendering, even when D5 is active.
			$this->initialize_divi4_integration();
		}

		/**
		 * Initialize Divi 4 integration
		 */
		public function initialize_divi4_integration() {
			/**
			 * Include UpStroke template group for Divi 4
			 * Slug: 'divi' - same as Divi 5 for consistency
			 */
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/divi/wfocu-template-group-divi.php';
		}

		/**
		 * Initialize Divi 5 integration
		 */
		public function initialize_divi5_integration() {
			// Register D5 template group unconditionally — needed for template import
			// via REST API (funnelkit-app/funnel-offer/*/design) which doesn't match
			// the post-type guard in maybe_load_d5_modules().
			$divi5_template_group = plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/divi-5/wfocu-template-group-divi5.php';
			if ( file_exists( $divi5_template_group ) ) {
				include_once $divi5_template_group;
			}

			// On preview requests, load modules early (like old code) to avoid timing issues.
			if ( isset( $_GET['et_pb_preview'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$modules_file = plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/divi-5/modules/Modules.php';
				if ( file_exists( $modules_file ) ) {
					require_once $modules_file;
				}
				return;
			}

			// Defer module loading to init:110 — after wfocu_offer post type registers at init:100.
			add_action( 'init', array( $this, 'maybe_load_d5_modules' ), 110 );

			// Hide legacy D4 modules from VB picker while keeping shortcode handlers
			// active for frontend rendering of unconverted pages.
			add_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $this, 'hide_legacy_d4_modules_from_vb' ), 1 );

			// Fix canvas template conflicts with Divi 5 VB.
			add_action( 'wp', array( $this, 'maybe_fix_vb_template_compat' ) );
		}

		/**
		 * Conditionally load D5 modules if the current page is an upsell offer page.
		 */
		public function maybe_load_d5_modules() {
			if ( ! $this->is_matching_post_type() ) {
				return;
			}

			// Register D5 modules on both frontend and VB so the block parser
			// can call render_callback. Pages saved with D5 block content need
			// a registered handler; without it the block outputs nothing.
			$modules_file = plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/divi-5/modules/Modules.php';
			if ( file_exists( $modules_file ) ) {
				require_once $modules_file;
			}

			// Register Visual Builder assets for Divi 5 native modules.
			add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'enqueue_visual_builder_assets' ) );
		}

		/**
		 * Check if the current request is for an upsell offer post type.
		 * Same pattern as other FunnelKit Divi dispatchers: $_REQUEST params →
		 * get_the_ID() → url_to_postid() (needed for Divi VB frontend URLs).
		 *
		 * @return bool
		 */
		private function is_matching_post_type(): bool {
			$post_type = WFOCU_Common::get_offer_post_type_slug();

			// 0. REST API requests for wfocu routes — REST_REQUEST constant is not yet defined
			// at init:110 (set later in parse_request), but REQUEST_URI is available.
			// Routes must be registered on every request that calls them, not just offer pages.
			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$uri = wp_unslash( $_SERVER['REQUEST_URI'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( false !== strpos( $uri, '/wp-json/wfocu/' ) ) {
					return true;
				}
				// Plain permalink REST: /?rest_route=/wfocu/v1/...
				if ( isset( $_GET['rest_route'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ), '/wfocu/' ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return true;
				}
			}

			// 1. $_REQUEST params — admin editor, Elementor-style ?post=123, heartbeat AJAX.
			$post_id = 0;
			foreach ( array( 'edit', 'post', 'editor_post_id' ) as $key ) {
				if ( ! empty( $_REQUEST[ $key ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					$post_id = absint( $_REQUEST[ $key ] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					break;
				}
			}

			// 2. WordPress fallback — frontend with resolved query.
			if ( $post_id < 1 && function_exists( 'get_the_ID' ) ) {
				$post_id = (int) get_the_ID();
			}

			// 3. Divi VB uses frontend URLs (/slug/?et_fb=1) with no post ID param.
			// url_to_postid() resolves the slug to a post ID. Works at init time.
			if ( $post_id < 1 && ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$post_id = url_to_postid( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			if ( $post_id > 0 ) {
				return get_post_type( $post_id ) === $post_type;
			}

			return false;
		}

		/**
		 * Hide legacy D4 modules from VB picker.
		 *
		 * D4 module classes must stay loaded for shortcode rendering of
		 * unconverted pages, but removing their set_shortcode_module_definitions
		 * callbacks prevents them from appearing in the VB inserter.
		 */
		public function hide_legacy_d4_modules_from_vb() {
			$slugs = array(
				'et_wfocu_accept_button',
				'et_wfocu_accept_link',
				'et_wfocu_reject_button',
				'et_wfocu_reject_link',
				'et_wfocu_product_title',
				'et_wfocu_product_short_desc',
				'et_wfocu_product_image',
				'et_wfocu_offer_price',
				'et_wfocu_quantity_selector',
				'et_wfocu_variation_selector',
			);

			foreach ( \ET_Builder_Element::get_modules() as $module ) {
				if ( in_array( $module->slug, $slugs, true ) ) {
					remove_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $module, 'set_shortcode_module_definitions' ) );
				}
			}
		}

		/**
		 * Fix canvas template compatibility with Divi 5 Visual Builder.
		 *
		 * - Top window: remove canvas template override so Divi's VB shell loads.
		 * - App window: bridge woofunnels_container hooks to Divi's hooks that
		 *   output the #et-fb-app wrapper visual-builder.js needs for React.
		 */
		public function maybe_fix_vb_template_compat() {
			if ( ! class_exists( 'ET\Builder\Framework\Utility\Conditions' ) ) {
				return;
			}

			if ( ET\Builder\Framework\Utility\Conditions::is_vb_top_window() ) {
				remove_filter( 'template_include', array( WFOCU_Core()->template_loader, 'maybe_load' ), 98 );
				return;
			}

			if ( ET\Builder\Framework\Utility\Conditions::is_vb_app_window()
				&& function_exists( 'et_fb_print_app_wrapper_before_main_content' )
				&& function_exists( 'et_fb_print_app_wrapper_after_main_content' )
			) {
				add_action( 'woofunnels_container_top', 'et_fb_print_app_wrapper_before_main_content' );
				add_action( 'woofunnels_container_bottom', 'et_fb_print_app_wrapper_after_main_content' );
			}
		}

		/**
		 * Enqueue Divi 5 Visual Builder Assets
		 *
		 * @since 1.0.0
		 */
		public function enqueue_visual_builder_assets() {
			// Only register if Divi 5 is enabled and Visual Builder is active
			if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
				return;
			}

			if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
				return;
			}

			$plugin_dir_url  = plugin_dir_url( WFOCU_PLUGIN_FILE );
			$plugin_dir_path = plugin_dir_path( WFOCU_PLUGIN_FILE );

			$visual_builder_js   = $plugin_dir_url . 'compatibilities/page-builders/divi-5/visual-builder/build/wfocu-divi5-visual-builder.js';
			$visual_builder_path = $plugin_dir_path . 'compatibilities/page-builders/divi-5/visual-builder/build/wfocu-divi5-visual-builder.js';

			// Only register if the built file exists
			if ( file_exists( $visual_builder_path ) ) {
				\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
					array(
						'name'    => 'wfocu-divi5-visual-builder',
						'version' => defined( 'WFOCU_VERSION' ) ? WFOCU_VERSION : '1.0.0',
						'script'  => array(
							'src'                => $visual_builder_js,
							'deps'               => array(
								'divi-module-library',
								'divi-vendor-wp-hooks',
								'react',
								'jquery-core',
								'divi-rest',
								'wp-hooks',
							),
							'enqueue_top_window' => false,
							'enqueue_app_window' => true,
						),
					)
				);
			}

			// Enqueue CSS for Divi 5 Visual Builder (ensure it loads from divi-5 folder)
			$css_file_url  = $plugin_dir_url . 'compatibilities/page-builders/divi-5/css/divi.css';
			$css_file_path = $plugin_dir_path . 'compatibilities/page-builders/divi-5/css/divi.css';

			if ( file_exists( $css_file_path ) ) {
				wp_enqueue_style(
					'wfocu-divi5-visual-builder-css',
					$css_file_url,
					array(),
					defined( 'WFOCU_VERSION' ) ? WFOCU_VERSION : '1.0.0'
				);
			}
		}

		/**
		 * Fires on divi_extensions_init — registers CSS only when Divi is actually loading.
		 */
		public function init_extension() {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_divi_css' ), 10 );
		}

		/**
		 * Enqueue Divi CSS based on version
		 *
		 * This method enqueues the CSS file based on Divi version:
		 * - Divi 4: loads from divi/css/divi.css
		 * - Divi 5: loads from divi-5/css/divi.css
		 * Enqueues on offer post type pages and in Visual Builder
		 *
		 * @since 1.0.0
		 */
		public function enqueue_divi_css() {
			global $post;

			// Check if we should enqueue CSS
			$should_enqueue = false;

			// Enqueue on offer post type pages
			if ( ! is_null( $post ) && $post->post_type === 'wfocu_offer' ) {
				$should_enqueue = true;
			}

			// Also enqueue in Visual Builder (for both Divi 4 and Divi 5)
			if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
				$should_enqueue = true;
			}

			if ( $should_enqueue ) {
				$plugin_dir_url  = plugin_dir_url( WFOCU_PLUGIN_FILE );
				$plugin_dir_path = plugin_dir_path( WFOCU_PLUGIN_FILE );

				// Determine CSS path based on Divi version
				$is_d5 = $this->is_divi_5();
				if ( $is_d5 ) {
					// Divi 5: use divi-5 folder
					$css_file_url  = $plugin_dir_url . 'compatibilities/page-builders/divi-5/css/divi.css';
					$css_file_path = $plugin_dir_path . 'compatibilities/page-builders/divi-5/css/divi.css';
				} else {
					// Divi 4: use divi folder
					$css_file_url  = $plugin_dir_url . 'compatibilities/page-builders/divi/css/divi.css';
					$css_file_path = $plugin_dir_path . 'compatibilities/page-builders/divi/css/divi.css';
				}

				// Only enqueue if file exists
				if ( file_exists( $css_file_path ) ) {
					wp_enqueue_style(
						'wfocu-divi-css',
						$css_file_url,
						array(),
						defined( 'WFOCU_VERSION' ) ? WFOCU_VERSION : '1.0.0'
					);
				}
			}
		}

		/**
		 * @param $attrs
		 *
		 * @return mixed
		 */
		public function add_id_for_wfocu_container( $attrs ) {

			$attrs['id'] = 'page-container';

			return $attrs;
		}

		public function maybe_filter( $add_outer_wrap ) {

			global $post;

			if ( is_object( $post ) && $post instanceof WP_Post && $post->post_type === 'wfocu_offer' ) {
				return true;
			}

			return $add_outer_wrap;
		}

		public function maybe_handle_jquery_defer() {
			global $post;

			if ( is_object( $post ) && $post instanceof WP_Post && $post->post_type === 'wfocu_offer' ) {
				add_filter( 'et_builder_enable_jquery_body', '__return_false' );
			}
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Divi(), 'divi' );
}
