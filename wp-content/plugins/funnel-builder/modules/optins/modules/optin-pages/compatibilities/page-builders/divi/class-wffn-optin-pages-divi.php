<?php //phpcs:ignore WordPress.WP.TimezoneChange.DeprecatedSniff

defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFFN_Optin_Pages_Divi' ) ) {
	/**
	 * Class WFFN_Optin_Pages_Divi
	 */
	#[AllowDynamicProperties]
	class WFFN_Optin_Pages_Divi {

		private static $ins                          = null;
		protected $template_type                     = array();
		protected $design_template_data              = array();
		protected $templates                         = array();
		private $edit_id                             = 0;
		private $url                                 = '';
		private static $deep_integration_initialized = false;

		/**
		 * WFFN_Optin_Pages_Divi constructor.
		 */
		public function __construct() {
			$this->url = plugin_dir_url( __FILE__ );

			// Add Divi 5 detection hook - use priority 2 like upsell sadad
			// Also add init hook as fallback in case plugins_loaded fires too early
			add_action( 'after_setup_theme', array( $this, 'initialize_deep_integration' ), 2 );

			// Keep Divi 4 hook for backward compatibility
			add_action( 'divi_extensions_init', array( $this, 'init_extension' ) );
		}

		/**
		 * Initialize deep integration based on Divi version
		 */
		public function initialize_deep_integration() {
			// Prevent double execution
			if ( self::$deep_integration_initialized ) {
				return;
			}

			self::$deep_integration_initialized = true;

			if ( $this->is_divi_5() ) {
				$this->initialize_divi5_integration();
			} else {
				$this->initialize_divi4_integration();
			}
		}

		/**
		 * Check if Divi 5 is enabled
		 *
		 * @return bool
		 */
		private function is_divi_5(): bool {
			return function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled();
		}

		/**
		 * Initialize Divi 5 integration
		 * All code loaded from divi-5/ folder
		 */
		private function initialize_divi5_integration() {

			// Declare D5 compatibility so Divi's readiness checker recognises this plugin.
			// The checker only attributes hooks registered with array callbacks (not closures).
			add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'register_d5_modules_dependency' ) );

			// Global — needed for D4→D5 conversion on ANY page.
			add_filter( 'divi.moduleLibrary.conversion.moduleConversionOutline', array( $this, 'extend_optin_form_conversion_outline' ), 10, 2 );

			// Defer module loading to init:10 where url_to_postid() can resolve the page.
			// At plugins_loaded (current hook) there's no post context on regular page loads.
			add_action( 'init', array( $this, 'maybe_load_d5_modules' ), 10 );

			/* Hide legacy D4 modules from VB picker while keeping shortcode handlers active */
			add_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $this, 'hide_legacy_d4_modules_from_vb' ), 1 );

			// Fix canvas template conflicts with Divi 5 VB:
			// - Top window: remove canvas override so Divi's VB shell template loads.
			// - App window: bridge missing et_before/after_main_content hooks so the
			// #et-fb-app wrapper is output for React mounting.
			add_action( 'wp', array( $this, 'maybe_fix_vb_template_compat' ) );
		}

		/**
		 * Fix canvas template compatibility with Divi 5 Visual Builder.
		 *
		 * The VB has two windows that each need different handling:
		 * - Top window: needs visual-builder-top-window.php for the VB app shell,
		 *   so we remove our canvas template override (priority 99).
		 * - App window (iframe): keeps the canvas template for clean layout, but
		 *   the canvas template doesn't fire et_before/after_main_content hooks.
		 *   Those hooks output the #et-fb-app wrapper that visual-builder.js
		 *   requires for React mounting, so we bridge them via
		 *   woofunnels_container_top/bottom.
		 */
		public function maybe_fix_vb_template_compat() {
			if ( ! class_exists( 'ET\Builder\Framework\Utility\Conditions' ) ) {
				return;
			}

			if ( ET\Builder\Framework\Utility\Conditions::is_vb_top_window() ) {
				remove_filter( 'template_include', array( WFOPP_Core()->optin_pages, 'may_be_change_template' ), 99 );
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
		 * D5 compatibility marker for Divi's readiness checker.
		 * Actual module registration is handled per-post-type via maybe_load_d5_modules().
		 *
		 * @param object $dependency_tree Divi dependency tree.
		 */
		public function register_d5_modules_dependency( $dependency_tree ) {
			// Intentionally empty — module registration deferred to maybe_load_d5_modules().
		}

		/**
		 * Conditionally load D5 modules if the current page is an optin page.
		 */
		public function maybe_load_d5_modules() {
			if ( ! $this->is_matching_post_type() ) {
				return;
			}

			// Register D5 modules on both frontend and VB so the block parser
			// can call render_callback. Pages saved with D5 block content need
			// a registered handler; without it the block outputs nothing.
			$divi5_modules_path = __DIR__ . '/../divi-5/modules/Modules.php';
			if ( file_exists( $divi5_modules_path ) ) {
				require_once $divi5_modules_path;
			}

			// Enqueue divi.css on optin pages.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_divi_optin_styles' ), 99 );

			// Register Visual Builder assets for Divi 5 native modules
			add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'enqueue_visual_builder_assets' ) );

			// Load Divi 5 template group (if exists)
			$divi5_template_path = __DIR__ . '/../divi-5/wfop-template-group-divi5.php';
			if ( file_exists( $divi5_template_path ) ) {
				require_once $divi5_template_path;
			}
		}

		/**
		 * Check if the current request is for an optin page post type.
		 * Same pattern as Elementor: get_edit_id() → get_the_ID() → post type check.
		 *
		 * @return bool
		 */
		private function is_matching_post_type(): bool {
			// 0. REST API requests for wfop routes.
			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$uri = wp_unslash( $_SERVER['REQUEST_URI'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( false !== strpos( $uri, '/wp-json/wfop/' ) ) {
					return true;
				}
				if ( isset( $_GET['rest_route'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ), '/wfop/' ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return true;
				}
			}

			// 1. Core's get_edit_id() — checks $_REQUEST params.
			$post_id = WFOPP_Core()->optin_pages->get_edit_id();

			// 2. WordPress fallback.
			if ( $post_id < 1 && function_exists( 'get_the_ID' ) ) {
				$post_id = get_the_ID();
			}

			// 3. Divi VB frontend URLs (/slug/?et_fb=1).
			if ( $post_id < 1 && ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$post_id = url_to_postid( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			if ( $post_id > 0 ) {
				return get_post_type( $post_id ) === WFOPP_Core()->optin_pages->get_post_type_slug();
			}

			return false;
		}

		/**
		 * Extend the conversion outline for wfop/optin-form so that ALL D4
		 * shortcode attributes are "known" during D4→D5 re-conversion.
		 *
		 * Without this, dynamic form-field width attributes (e.g. wfop_optin_first_name)
		 * and D4 style sub-attributes (typography, border, etc.) become unknownAttributes,
		 * which forces Divi to keep the block as divi/shortcode-module.
		 *
		 * @param array  $conversion_outline Current outline.
		 * @param string $module_name        Block name being converted.
		 * @return array Modified outline.
		 */
		public function extend_optin_form_conversion_outline( $conversion_outline, $module_name ) {
			if ( 'wfop/optin-form' !== $module_name ) {
				return $conversion_outline;
			}

			if ( ! is_array( $conversion_outline ) ) {
				$conversion_outline = array();
			}

			if ( ! isset( $conversion_outline['module'] ) || ! is_array( $conversion_outline['module'] ) ) {
				$conversion_outline['module'] = array();
			}

			// 1. Dynamic form field width attributes (vary per optin page).
			$field_names = $this->get_all_optin_field_names();
			foreach ( $field_names as $field_name ) {
				if ( ! isset( $conversion_outline['module'][ $field_name ] ) ) {
					// D4 stores width class string; D5 wraps in viewport object.
					$conversion_outline['module'][ $field_name ] = $field_name . '.*';
				}
			}

			// 2. D4 style sub-attributes generated by helper methods.
			$style_mappings = $this->get_d4_style_attribute_mappings();
			foreach ( $style_mappings as $d4_attr => $d5_path ) {
				if ( ! isset( $conversion_outline['module'][ $d4_attr ] ) ) {
					$conversion_outline['module'][ $d4_attr ] = $d5_path;
				}
			}

			// 3. Value expansion functions for D4 "_typograhy" (typo) attributes.
			// Without convertFont, the raw D4 font string (e.g. "Lato||||||||") is stored
			// as a plain string at font.font.*, overwriting auto-generated sub-properties
			// like font_size. convertFont parses it into a proper D5 font object.
			// Note: "convertFont" is not in the global lookup; use the full callable path.
			if ( ! isset( $conversion_outline['valueExpansionFunctionMap'] ) || ! is_array( $conversion_outline['valueExpansionFunctionMap'] ) ) {
				$conversion_outline['valueExpansionFunctionMap'] = array();
			}

			$convert_font_callable = 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::convertFont';

			$typo_attrs = array(
				'wfop_optin_form_label_typography_typograhy',
				'wfop_optin_form_field_typography_typograhy',
				'wfop_optin_form_button_text_typo_typograhy',
				'wfop_optin_form_button_subheading_text_typo_typograhy',
			);

			foreach ( $typo_attrs as $typo_attr ) {
				$conversion_outline['valueExpansionFunctionMap'][ $typo_attr ] = $convert_font_callable;
			}

			return $conversion_outline;
		}

		/**
		 * Collect every InputName across all optin pages so their width
		 * attributes are present in the conversion outline.
		 *
		 * @return string[] Unique InputName values.
		 */
		private function get_all_optin_field_names() {
			static $cached = null;
			if ( null !== $cached ) {
				return $cached;
			}

			$cached = array();

			if ( ! function_exists( 'WFOPP_Core' ) || ! WFOPP_Core()->optin_pages ) {
				return $cached;
			}

			$post_type = WFOPP_Core()->optin_pages->get_post_type_slug();

			$optin_ids = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'post_status'    => array( 'publish', 'draft', 'private' ),
				)
			);

			$names = array();
			foreach ( $optin_ids as $optin_id ) {
				$fields = WFOPP_Core()->optin_pages->form_builder->get_form_fields( $optin_id );
				if ( ! is_array( $fields ) ) {
					continue;
				}
				foreach ( $fields as $field ) {
					$input_name = isset( $field['InputName'] ) ? $field['InputName'] : '';
					if ( ! empty( $input_name ) ) {
						$names[ $input_name ] = true;
					}
				}
			}

			$cached = array_keys( $names );
			return $cached;
		}

		/**
		 * Map D4 style sub-attributes that aren't auto-generated by Divi's
		 * advanced section converters (borders, box shadow) or that use
		 * non-standard naming (typography typos). Most typography, background,
		 * and spacing mappings are handled by conversion-outline.json.
		 *
		 * @return array<string,string> D4 attribute name => D5 dot-path.
		 */
		private function get_d4_style_attribute_mappings() {
			$k = 'wfop_optin_form'; // D4 key prefix
			$m = array();

			// NOTE: Typography sub-fields (_font_size, _typograhy, _line_height, _text_color)
			// are auto-generated by Divi's getFontConversionMap() via the advanced.fonts section
			// in conversion-outline.json. Do NOT add manual entries here — module-section entries
			// override advanced-section auto-generated entries (PHP array_merge precedence).

			// NOTE: Background colors, spacing (padding/margin), and standalone color fields
			// are handled in the static conversion-outline.json module section.

			// --- Typography: only the D4 "_typograhy" typo variant ---
			// D4 uses "_typograhy" (missing 'p') but Divi auto-generates "_font" for the font family.
			// We need explicit mappings for this D4 typo so the value doesn't become unknownAttributes.
			$typo_groups = array(
				$k . '_label_typography'            => 'wfop_optin_form_label_typography',
				$k . '_field_typography'            => 'wfop_optin_form_field_typography',
				$k . '_button_text_typo'            => 'wfop_optin_form_button_text_typo',
				$k . '_button_subheading_text_typo' => 'wfop_optin_form_button_subheading_text_typo',
			);

			foreach ( $typo_groups as $d4 => $d5 ) {
				// D4 "_typograhy" (typo) → maps to same path as "_font" (font family string).
				$m[ $d4 . '_typograhy' ] = $d5 . '.decoration.font.font.*';
			}

			// --- Border sub-fields ---
			// Divi's auto-generated border names use "border_width_top_{name}" format
			// but D4 shortcodes use "{name}_border_width_top" format. Must map manually.
			$border_groups = array(
				$k . '_field_border' => 'wfop_optin_form_field_border',
				'bwf_button_border'  => 'bwf_button_border',
			);

			foreach ( $border_groups as $d4 => $d5 ) {
				$m[ $d4 . '_border_type' ]          = $d5 . '.decoration.border.*.styles.all.style';
				$m[ $d4 . '_border_color' ]         = $d5 . '.decoration.border.*.styles.all.color';
				$m[ $d4 . '_border_radius_top' ]    = $d5 . '.decoration.border.*.radius.topLeft';
				$m[ $d4 . '_border_radius_bottom' ] = $d5 . '.decoration.border.*.radius.bottomRight';
				$m[ $d4 . '_border_radius_left' ]   = $d5 . '.decoration.border.*.radius.bottomLeft';
				$m[ $d4 . '_border_radius_right' ]  = $d5 . '.decoration.border.*.radius.topRight';
				// Individual border widths → map to the real styles.all.width path.
				// D4 stores the same value on all 4 sides; all map to the single D5
				// "all" width. Value "0" means no border was configured in D4 (matching
				// the D4 frontend which renders borderWidth: 0px / borderStyle: none).
				$m[ $d4 . '_border_width_top' ]    = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_bottom' ] = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_left' ]   = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_right' ]  = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_with_right' ]   = $d5 . '.decoration.border.*.styles.all.width'; // D4 typo: "with" instead of "width"
			}

			// --- Box shadow sub-fields ---
			// Same issue as borders: auto-generated names don't match D4 naming convention.
			$bs  = 'button_text_alignment_box_shadow';
			$d5b = 'button_text_alignment_box_shadow.decoration.boxShadow';

			$m[ $bs . '_shadow_enable' ]     = $d5b . '.*.enable';
			$m[ $bs . '_shadow_type' ]       = $d5b . '.*.type';
			$m[ $bs . '_shadow_color' ]      = $d5b . '.*.color';
			$m[ $bs . '_shadow_horizontal' ] = $d5b . '.*.horizontal';
			$m[ $bs . '_shadow_vertical' ]   = $d5b . '.*.vertical';
			$m[ $bs . '_shadow_blur' ]       = $d5b . '.*.blur';
			$m[ $bs . '_shadow_spread' ]     = $d5b . '.*.spread';

			return $m;
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

			$plugin_dir_url  = plugin_dir_url( __FILE__ );
			$plugin_dir_path = __DIR__;

			$visual_builder_js   = $plugin_dir_url . '../divi-5/visual-builder/build/wfop-divi5-visual-builder.js';
			$visual_builder_path = $plugin_dir_path . '/../divi-5/visual-builder/build/wfop-divi5-visual-builder.js';

			// Only register if the built file exists
			if ( file_exists( $visual_builder_path ) ) {
				if ( class_exists( '\ET\Builder\VisualBuilder\Assets\PackageBuildManager' ) ) {
					// Get optin frontend CSS path
					$optin_css_url  = '';
					$optin_css_path = '';
					if ( function_exists( 'WFOPP_Core' ) ) {
						$optin_css_url  = WFOPP_Core()->optin_pages->url . 'assets/css/wfopp-optin-frontend.css';
						$optin_css_path = WFOPP_Core()->optin_pages->get_module_path() . 'assets/css/wfopp-optin-frontend.css';
					}

					$package_config = array(
						'name'    => 'wfop-divi5-visual-builder',
						'version' => '1.0.0',
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
					);

					// Add CSS if it exists
					if ( ! empty( $optin_css_path ) && file_exists( $optin_css_path ) ) {
						$package_config['style'] = array(
							'src'                => $optin_css_url,
							'deps'               => array(),
							'enqueue_top_window' => false,
							'enqueue_app_window' => true,
						);
					}

					\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build( $package_config );
				}
			}

			// Enqueue CSS for Divi 5 Visual Builder (same as frontend)
			// This ensures the form styling matches between editor and frontend
			if ( function_exists( 'WFOPP_Core' ) ) {
				$optin_css_url  = WFOPP_Core()->optin_pages->url . 'assets/css/wfopp-optin-frontend.css';
				$optin_css_path = WFOPP_Core()->optin_pages->get_module_path() . 'assets/css/wfopp-optin-frontend.css';

				if ( file_exists( $optin_css_path ) ) {
					wp_enqueue_style(
						'wffn-optin-frontend-style',
						$optin_css_url,
						array(),
						defined( 'WFFN_VERSION_DEV' ) ? WFFN_VERSION_DEV : '1.0.0'
					);
				}
			}
		}

		/**
		 * Enqueue Divi optin styles (divi.css) on optin pages.
		 * Runs for both Divi 4 and Divi 5. Ensures form/button layout and Divi-specific styles apply.
		 */
		public function enqueue_divi_optin_styles() {
			if ( ! function_exists( 'WFOPP_Core' ) ) {
				return;
			}
			$optin_pages = WFOPP_Core()->optin_pages;
			// Enqueue when we're on an optin page (flag set by parse_request_for_optin on 'wp').
			if ( $optin_pages->is_wfop_page() ) {
				$this->do_enqueue_divi_css();
				return;
			}
			// Fallback: enqueue when current post is optin post type (covers edge cases where flag is not set).
			global $post;
			if ( $post instanceof WP_Post && $post->post_type === $optin_pages->get_post_type_slug() ) {
				$this->do_enqueue_divi_css();
			}
		}

		/**
		 * Output the divi.css enqueue (shared by enqueue_divi_optin_styles).
		 */
		private function do_enqueue_divi_css() {
			$divi_css_url = plugin_dir_url( __FILE__ ) . 'css/divi.css';
			wp_enqueue_style(
				'woofunnels-op-divi-wfop-divi',
				$divi_css_url,
				array(),
				defined( 'WFFN_VERSION_DEV' ) ? WFFN_VERSION_DEV : '1.0.0'
			);
		}

		/**
		 * Initialize Divi 4 integration
		 * All code loaded from divi/ folder
		 */
		private function initialize_divi4_integration() {
			// Divi 4 extension loads via divi_extensions_init hook in init_extension()
		}


		public function add_default_templates() {

			$template = array(
				'slug'        => 'divi',
				'title'       => __( 'Divi', 'funnel-builder' ),
				'button_text' => __( 'Edit', 'funnel-builder' ),
				'edit_url'    => add_query_arg(
					array(
						'p'         => $this->edit_id,
						'et_fb'     => 1,
						'PageSpeed' => 'off',
					),
					site_url()
				),
			);

			WFOPP_Core()->optin_pages->register_template_type( $template );
			$templates = WooFunnels_Dashboard::get_all_templates();
			$designs   = isset( $templates['optin'] ) ? $templates['optin'] : array();

			if ( isset( $designs['divi'] ) && is_array( $designs['divi'] ) ) {
				foreach ( $designs['divi'] as $d_key => $templates ) {

					if ( isset( $templates['pro'] ) && 'yes' === $templates['pro'] ) {
						$templates['license_exist'] = WFFN_Core()->admin->get_license_status();
					}

					WFOPP_Core()->optin_pages->register_template( $d_key, $templates, 'divi' );

				}
			} else {

				$empty_template = array(
					'type'               => 'view',
					'import'             => 'no',
					'show_import_popup'  => 'no',
					'slug'               => 'divi_1',
					'build_from_scratch' => true,
					'group'              => array(
						'inline',
						'popup',
					),
				);
				WFOPP_Core()->optin_pages->register_template( 'divi_1', $empty_template, 'divi' );
			}

			return array();
		}

		/**
		 * @return WFFN_Optin_Pages_Divi|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Hide legacy D4 optin modules from Divi 5 VB module picker.
		 *
		 * Removes set_shortcode_module_definitions callbacks so D4 modules
		 * don't appear in the VB inserter. Shortcode handlers remain for
		 * frontend rendering of unconverted pages.
		 */
		public function hide_legacy_d4_modules_from_vb() {
			$slugs = array( 'et_wfop_optin_form', 'wfop_optin_form_popup' );

			foreach ( ET_Builder_Element::get_modules() as $module ) {
				if ( in_array( $module->slug, $slugs, true ) ) {
					remove_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $module, 'set_shortcode_module_definitions' ) );
				}
			}
		}

		/**
		 * Initialize Divi 4 extension.
		 *
		 * Initialize Divi 4 extension.
		 *
		 * Hooked on divi_extensions_init (Divi 4 only — removed in
		 * initialize_divi5_integration() when Divi 5 is active).
		 */
		public function init_extension() {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_divi_optin_styles' ), 99 );

			if ( wp_doing_ajax() ) {
				$post_type = WFOPP_Core()->optin_pages->get_post_type_slug();
				if ( isset( $_REQUEST['action'] ) && 'et_fb_get_saved_templates' === $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && $post_type !== $_REQUEST['et_post_type'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					return;
				}

				if ( isset( $_REQUEST['action'] ) && 'et_fb_update_builder_assets' === $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && $post_type !== $_REQUEST['et_post_type'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					return;
				}

				$post_id = 0;
				if ( isset( $_REQUEST['action'] ) && 'heartbeat' === $_REQUEST['action'] && isset( $_REQUEST['data'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					if ( isset( $_REQUEST['data']['et'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
						$post_id = $_REQUEST['data']['et']['post_id']; //phpcs:ignore
					}
				}

				if ( isset( $_REQUEST['post_id'] ) ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					$post_id = absint( $_REQUEST['post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				}
				if ( isset( $_REQUEST['et_post_id'] ) ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					$post_id = absint( $_REQUEST['et_post_id'] );  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				}
				if ( $post_id > 0 ) {
					$post = get_post( $post_id );
					if ( is_null( $post ) || $post->post_type !== $post_type ) {
						return;
					}
				}
			}

			// Load Divi 4 extension from divi/ folder
			include __DIR__ . '/class-wfop-divi-extension.php';
		}
	}

	WFFN_Optin_Pages_Divi::get_instance();
}
