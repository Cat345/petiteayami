<?php

//phpcs:ignore WordPress.WP.TimezoneChange.DeprecatedSniff

defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Optin_Pages_Divi' ) ) {
	/**
	 * Class WFFN_Pro_Optin_Pages_Divi
	 */
	#[AllowDynamicProperties]
	class WFFN_Pro_Optin_Pages_Divi {

		private static $ins                          = null;
		public $plugin_dir                           = '';
		public $module_path                          = '';
		public $plugin_dir_url                       = '';
		public $version                              = '1.0.0';
		private static $deep_integration_initialized = false;

		/**
		 * WFFN_Pro_Optin_Pages_Divi constructor.
		 */
		public function __construct() {
			$this->plugin_dir     = plugin_dir_path( __FILE__ );
			$this->module_path    = $this->plugin_dir . 'modules/';
			$this->plugin_dir_url = plugin_dir_url( __FILE__ );

			// Initialize Divi deep integration early during theme setup.
			add_action( 'after_setup_theme', array( $this, 'initialize_deep_integration' ), 1 );

			// Keep Divi 4 hooks for backward compatibility
			add_filter( 'wffn_op_divi_modules', array( $this, 'register_widget' ) );
			add_action( 'wfop_divi_module_js', array( $this, 'enqueue_module_js' ) );

			// Enqueue divi.css directly on optin page (does not depend on lite extension firing wfop_divi_module_js)
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_optin_divi_styles' ), 20 );
		}

		/**
		 * @return WFFN_Pro_Optin_Pages_Divi|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function register_widget( $args ) {
			// Always register D4 module — needed for shortcode rendering of
			// unconverted D4 pages on D5 frontend. VB picker visibility is
			// handled separately by hide_legacy_d4_modules_from_vb().
			$args['optin_form_popup'] = array(
				'name' => __( 'WF Optin Form Popup', 'funnel-builder-powerpack' ),
				'path' => $this->module_path . 'optin-form-popup.php',
			);

			return $args;
		}

		/**
		 * Enqueue divi.css on optin page (runs on wp_enqueue_scripts, does not depend on lite extension).
		 */
		public function enqueue_optin_divi_styles() {
			if ( ! function_exists( 'WFOPP_Core' ) || ! isset( WFOPP_Core()->optin_pages ) || ! WFOPP_Core()->optin_pages->is_wfop_page() ) {
				return;
			}
			wp_enqueue_style( 'woofunnels-op-divi-popup-wfop-divi', $this->plugin_dir_url . 'css/divi.css', array(), $this->version );
		}

		public function enqueue_module_js() {
			// Only run on optin page (when lite extension fires wfop_divi_module_js)
			if ( ! WFOPP_Core()->optin_pages->is_wfop_page() ) {
				return;
			}

			// divi.css is enqueued via enqueue_optin_divi_styles(); avoid duplicate
			// wp_enqueue_style( "woofunnels-op-divi-popup-wfop-divi", ... ) here.

			// Divi 5 uses native module; skip Divi 4 builder bundle
			if ( $this->is_divi_5() ) {
				return;
			}

			// Divi 4: enqueue builder bundle when Visual Builder is active
			if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
				wp_enqueue_script( 'woofunnels-op-divi-popup-builder-bundle', "{$this->plugin_dir_url}scripts/loader.min.js", array( 'react-dom' ), $this->version, true );
				wp_localize_script(
					'woofunnels-op-divi-popup-builder-bundle',
					'wfop_divi_data',
					array(
						'nonce' => wp_create_nonce( 'wfop_divi_ajax' ),
					)
				);
			}
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
			return did_action( 'after_setup_theme' ) && function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled();
		}

		/**
		 * Initialize Divi 5 integration
		 * All code loaded from divi-5/ folder
		 */
		private function initialize_divi5_integration() {
			// Global — needed for D4→D5 conversion on ANY page.
			add_filter( 'divi.moduleLibrary.conversion.moduleConversionOutline', array( $this, 'extend_popup_conversion_outline' ), 10, 2 );

			// Defer D5 module loading to init:10 behind post-type guard.
			// Frontend rendering of D4 content uses the D4 shortcode handler
			// (register_widget), not the D5 render_callback.
			add_action( 'init', array( $this, 'maybe_load_d5_modules' ), 10 );

			// Hide legacy D4 popup from VB picker.
			add_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $this, 'hide_legacy_d4_modules_from_vb' ), 1 );
		}

		/**
		 * Conditionally load D5 modules if the current page is an optin page.
		 */
		public function maybe_load_d5_modules() {
			if ( ! $this->is_matching_post_type() ) {
				return;
			}

			$divi5_modules_path = __DIR__ . '/../divi-5/modules/Modules.php';
			if ( file_exists( $divi5_modules_path ) ) {
				require_once $divi5_modules_path;
			}

			add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'enqueue_visual_builder_assets' ) );

			$divi5_template_path = __DIR__ . '/../divi-5/wfopp-template-group-divi5.php';
			if ( file_exists( $divi5_template_path ) ) {
				require_once $divi5_template_path;
			}
		}

		/**
		 * Check if the current request is for an optin page post type.
		 *
		 * @return bool
		 */
		private function is_matching_post_type(): bool {
			if ( function_exists( 'WFOPP_Core' ) && isset( WFOPP_Core()->optin_pages ) ) {
				$post_id = WFOPP_Core()->optin_pages->get_edit_id();

				if ( $post_id < 1 && function_exists( 'get_the_ID' ) ) {
					$post_id = (int) get_the_ID();
				}

				if ( $post_id < 1 && ! empty( $_SERVER['REQUEST_URI'] ) ) {
					$post_id = url_to_postid( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}

				if ( $post_id > 0 ) {
					return get_post_type( $post_id ) === WFOPP_Core()->optin_pages->get_post_type_slug();
				}
			}

			return false;
		}

		/**
		 * Hide legacy D4 optin popup module from Divi 5 VB picker.
		 */
		public function hide_legacy_d4_modules_from_vb() {
			$slugs = array( 'et_wfop_optin_form_popup', 'wfop_optin_form_popup' );

			foreach ( \ET_Builder_Element::get_modules() as $module ) {
				if ( in_array( $module->slug, $slugs, true ) ) {
					remove_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $module, 'set_shortcode_module_definitions' ) );
				}
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

			$plugin_dir_url  = plugin_dir_url( __FILE__ );
			$plugin_dir_path = __DIR__;

			$visual_builder_js   = $plugin_dir_url . '../divi-5/visual-builder/build/wfopp-divi5-visual-builder.js';
			$visual_builder_path = $plugin_dir_path . '/../divi-5/visual-builder/build/wfopp-divi5-visual-builder.js';

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
						'name'    => 'wfopp-divi5-visual-builder',
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
						'wffn-pro-optin-frontend-style',
						$optin_css_url,
						array(),
						defined( 'WFFN_VERSION_DEV' ) ? WFFN_VERSION_DEV : '1.0.0'
					);
				}
			}
		}

		/**
		 * Initialize Divi 4 integration
		 * All code loaded from divi/ folder
		 */
		private function initialize_divi4_integration() {
			// Divi 4 extension loads via existing hooks
			// This is handled by register_widget() and enqueue_module_js() methods
		}

		/**
		 * Extend the conversion outline for wfopp/optin-form-popup so that ALL D4
		 * shortcode attributes are "known" during D4→D5 re-conversion.
		 *
		 * Without this, dynamic form-field width attributes and D4 style sub-attributes
		 * (typography typos, border sub-fields, box shadow sub-fields) become
		 * unknownAttributes, which forces Divi to keep the block as divi/shortcode-module.
		 *
		 * @param array  $conversion_outline Current outline.
		 * @param string $module_name        Block name being converted.
		 * @return array Modified outline.
		 */
		public function extend_popup_conversion_outline( $conversion_outline, $module_name ) {
			if ( 'wfopp/optin-form-popup' !== $module_name ) {
				return $conversion_outline;
			}

			if ( ! is_array( $conversion_outline ) ) {
				$conversion_outline = array();
			}

			if ( ! isset( $conversion_outline['module'] ) || ! is_array( $conversion_outline['module'] ) ) {
				$conversion_outline['module'] = array();
			}

			// 1. Dynamic form field width attributes (vary per optin page).
			$field_names = $this->get_all_popup_optin_field_names();
			foreach ( $field_names as $field_name ) {
				if ( ! isset( $conversion_outline['module'][ $field_name ] ) ) {
					$conversion_outline['module'][ $field_name ] = $field_name . '.*';
				}
			}

			// 2. D4 style sub-attributes generated by helper methods.
			$style_mappings = $this->get_popup_d4_style_attribute_mappings();
			foreach ( $style_mappings as $d4_attr => $d5_path ) {
				if ( ! isset( $conversion_outline['module'][ $d4_attr ] ) ) {
					$conversion_outline['module'][ $d4_attr ] = $d5_path;
				}
			}

			// 3. Value expansion functions for D4 "_typograhy" (typo) attributes.
			if ( ! isset( $conversion_outline['valueExpansionFunctionMap'] ) || ! is_array( $conversion_outline['valueExpansionFunctionMap'] ) ) {
				$conversion_outline['valueExpansionFunctionMap'] = array();
			}

			$convert_font_callable = 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::convertFont';

			$typo_attrs = array(
				'wfop_optin_form_popup_progress_bar_typography_typograhy',
				'wfop_optin_form_popup_popup_heading_typograhy',
				'wfop_optin_form_popup_popup_subheading_typography_typograhy',
				'wfop_optin_form_popup_text_after_submit_typography_typograhy',
				'wfop_optin_form_popup_btn_text_typo_typograhy',
				'wfop_optin_form_popup_btn_subheading_text_typo_typograhy',
				'label_typography_typograhy',
				'field_typography_typograhy',
				'button_text_typo_typograhy',
				'button_subheading_text_typo_typograhy',
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
		private function get_all_popup_optin_field_names() {
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
		 * advanced section converters.
		 *
		 * @return array D4 attr name => D5 path.
		 */
		private function get_popup_d4_style_attribute_mappings() {
			$k = 'wfop_optin_form_popup';
			$m = array();

			// --- Typography: D4 "_typograhy" typo variant ---
			$typo_groups = array(
				$k . '_progress_bar_typography'      => 'wfop_optin_form_popup_progress_bar_typography',
				$k . '_popup_heading'                => 'wfop_optin_form_popup_popup_heading',
				$k . '_popup_subheading_typography'  => 'wfop_optin_form_popup_popup_subheading_typography',
				$k . '_text_after_submit_typography' => 'wfop_optin_form_popup_text_after_submit_typography',
				$k . '_btn_text_typo'                => 'wfop_optin_form_popup_btn_text_typo',
				$k . '_btn_subheading_text_typo'     => 'wfop_optin_form_popup_btn_subheading_text_typo',
				'label_typography'                   => 'label_typography',
				'field_typography'                   => 'field_typography',
				'button_text_typo'                   => 'button_text_typo',
				'button_subheading_text_typo'        => 'button_subheading_text_typo',
			);

			foreach ( $typo_groups as $d4 => $d5 ) {
				$m[ $d4 . '_typograhy' ] = $d5 . '.decoration.font.font.*';
			}

			// --- Border sub-fields ---
			$border_groups = array(
				'field_border'                    => 'field_border',
				'bwf_button_border'               => 'bwf_button_border',
				$k . '_btn_text_alignment_border' => 'wfop_optin_form_popup_btn_text_alignment_border',
				$k . '_close_btn_border'          => 'wfop_optin_form_popup_close_btn_border',
			);

			foreach ( $border_groups as $d4 => $d5 ) {
				$m[ $d4 . '_border_type' ]          = $d5 . '.decoration.border.*.styles.all.style';
				$m[ $d4 . '_border_color' ]         = $d5 . '.decoration.border.*.styles.all.color';
				$m[ $d4 . '_border_radius_top' ]    = $d5 . '.decoration.border.*.radius.topLeft';
				$m[ $d4 . '_border_radius_bottom' ] = $d5 . '.decoration.border.*.radius.bottomRight';
				$m[ $d4 . '_border_radius_left' ]   = $d5 . '.decoration.border.*.radius.bottomLeft';
				$m[ $d4 . '_border_radius_right' ]  = $d5 . '.decoration.border.*.radius.topRight';
				$m[ $d4 . '_border_width_top' ]     = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_bottom' ]  = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_left' ]    = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_right' ]   = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_with_right' ]    = $d5 . '.decoration.border.*.styles.all.width'; // D4 typo: "with" instead of "width"
			}

			// --- Box shadow sub-fields ---
			$bs_groups = array(
				'button_text_alignment_box_shadow'    => 'button_text_alignment_box_shadow.decoration.boxShadow',
				$k . '_btn_text_alignment_box_shadow' => 'wfop_optin_form_popup_btn_text_alignment_box_shadow.decoration.boxShadow',
			);

			foreach ( $bs_groups as $d4 => $d5 ) {
				$m[ $d4 . '_shadow_enable' ]     = $d5 . '.*.enable';
				$m[ $d4 . '_shadow_type' ]       = $d5 . '.*.type';
				$m[ $d4 . '_shadow_color' ]      = $d5 . '.*.color';
				$m[ $d4 . '_shadow_horizontal' ] = $d5 . '.*.horizontal';
				$m[ $d4 . '_shadow_vertical' ]   = $d5 . '.*.vertical';
				$m[ $d4 . '_shadow_blur' ]       = $d5 . '.*.blur';
				$m[ $d4 . '_shadow_spread' ]     = $d5 . '.*.spread';
			}

			// --- Letter spacing (D4 attr not in D5 module.json, sink it) ---
			$m[ $k . '_text_after_submit_letter_spacing' ] = 'wfop_optin_form_popup_text_after_submit_typography.decoration.font.font.*.letterSpacing';

			return $m;
		}
	}

	WFFN_Pro_Optin_Pages_Divi::get_instance();

}
