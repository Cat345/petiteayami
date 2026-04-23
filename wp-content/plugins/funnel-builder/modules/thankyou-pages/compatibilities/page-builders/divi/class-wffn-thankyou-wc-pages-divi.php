<?php //phpcs:ignore WordPress.WP.TimezoneChange.DeprecatedSniff

defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFFN_ThankYou_WC_Pages_Divi' ) ) {
	/**
	 * Class WFFN_ThankYou_WC_Pages_Divi
	 */
	#[AllowDynamicProperties]
	class WFFN_ThankYou_WC_Pages_Divi {

		private static $ins                          = null;
		private static $deep_integration_initialized = false;
		protected $template_type                     = array();
		protected $design_template_data              = array();
		protected $templates                         = array();
		private $edit_id                             = 0;
		private $url                                 = '';

		/**
		 * WFFN_ThankYou_WC_Pages_Divi constructor.
		 */
		public function __construct() {
			$this->url = plugin_dir_url( __FILE__ );
			add_action( 'after_setup_theme', array( $this, 'initialize_deep_integration' ), 2 );
			add_action( 'divi_extensions_init', array( $this, 'init_extension' ) );
		}

		/**
		 * Initialize deep integration based on Divi version.
		 */
		public function initialize_deep_integration() {
			if ( self::$deep_integration_initialized ) {
				return;
			}
			self::$deep_integration_initialized = true;
			if ( $this->is_divi_5() ) {
				$this->initialize_divi5_integration();
			}
		}

		/**
		 * Check if Divi 5 is enabled.
		 *
		 * @return bool
		 */
		private function is_divi_5(): bool {
			if ( function_exists( 'et_builder_d5_enabled' ) ) {

				return et_builder_d5_enabled();
			}
			if ( interface_exists( 'ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
				return true;
			}
			if ( defined( 'ET_BUILDER_5_DIR' ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Initialize Divi 5 integration. All code loaded from divi-5/ folder.
		 */
		private function initialize_divi5_integration() {
			// Declare D5 compatibility so Divi's readiness checker recognises this plugin.
			// The checker only attributes hooks registered with array callbacks (not closures).
			add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'register_d5_modules_dependency' ) );

			// Global — needed for D4→D5 conversion on ANY page.
			add_filter( 'divi.moduleLibrary.conversion.moduleConversionOutline', array( $this, 'extend_thankyou_conversion_outline' ), 10, 2 );

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
				remove_filter( 'template_include', array( WFFN_Thank_You_WC_Pages::get_instance(), 'may_be_change_template' ), 99 );
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
		 * Conditionally load D5 modules if the current page is a thank-you page.
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

			add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'enqueue_visual_builder_assets' ) );
			add_action( 'wp_head', array( $this, 'output_vb_inline_data' ), 1 );

			$divi5_template_path = __DIR__ . '/../divi-5/wfty-template-group-divi5.php';
			if ( file_exists( $divi5_template_path ) ) {
				require_once $divi5_template_path;
			}
		}

		/**
		 * Check if the current request is for a thank-you page post type.
		 * Same pattern as Elementor: get_edit_id() → get_the_ID() → post type check.
		 *
		 * @return bool
		 */
		private function is_matching_post_type(): bool {
			$post_id = WFFN_Core()->thank_you_pages->get_edit_id();

			if ( $post_id < 1 && function_exists( 'get_the_ID' ) ) {
				$post_id = get_the_ID();
			}

			if ( $post_id < 1 && ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$post_id = url_to_postid( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			if ( $post_id > 0 ) {
				return get_post_type( $post_id ) === WFFN_Core()->thank_you_pages->get_post_type_slug();
			}

			return false;
		}

		/**
		 * Enqueue Thank You Divi CSS on thank you pages.
		 * Divi 4: divi/css/divi.css (unchanged for previous users).
		 * Divi 5: divi-5/css/wfty-divi5.css (all Divi 5 changes live here).
		 */
		public function enqueue_divi_css() {
			if ( ! function_exists( 'WFFN_Core' ) || ! is_callable( array( WFFN_Core()->thank_you_pages, 'is_wfty_page' ) ) ) {
				return;
			}
			if ( ! WFFN_Core()->thank_you_pages->is_wfty_page() ) {
				return;
			}
			$dir = __DIR__;
			$url = plugin_dir_url( __FILE__ );

			if ( $this->is_divi_5() ) {
				$css_file = $dir . '/../divi-5/css/wfty-divi5.css';
				$css_url  = $url . '../divi-5/css/wfty-divi5.css';
				$handle   = 'wfty-divi5-css';
			} else {
				$css_file = $dir . '/css/divi.css';
				$css_url  = $url . 'css/divi.css';
				$handle   = 'wfty-divi-css';
			}

			if ( ! file_exists( $css_file ) ) {
				return;
			}
			$version = filemtime( $css_file );
			wp_enqueue_style( $handle, $css_url, array(), $version );
		}

		/**
		 * Enqueue Divi 5 Visual Builder assets.
		 */
		public function enqueue_visual_builder_assets() {
			if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
				return;
			}
			if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
				return;
			}
			$plugin_dir_url      = plugin_dir_url( __FILE__ );
			$plugin_dir_path     = __DIR__;
			$divi5_dir           = $plugin_dir_path . '/../divi-5';
			$visual_builder_js   = $plugin_dir_url . '../divi-5/visual-builder/build/wfty-divi5-visual-builder.js';
			$visual_builder_path = $divi5_dir . '/visual-builder/build/wfty-divi5-visual-builder.js';
			$divi5_css_path      = $divi5_dir . '/css/wfty-divi5.css';
			$divi5_css_url       = $plugin_dir_url . '../divi-5/css/wfty-divi5.css';

			if ( file_exists( $visual_builder_path ) && class_exists( '\ET\Builder\VisualBuilder\Assets\PackageBuildManager' ) ) {
				\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
					array(
						'name'    => 'wfty-divi5-visual-builder',
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
					)
				);
				// Divi 5 CSS from divi-5 folder only (no impact on divi/ for previous users).
				if ( file_exists( $divi5_css_path ) ) {
					\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
						array(
							'name'    => 'wfty-divi5-visual-builder-style',
							'version' => (string) filemtime( $divi5_css_path ),
							'style'   => array(
								'src'                => $divi5_css_url,
								'deps'               => array(),
								'enqueue_top_window' => false,
								'enqueue_app_window' => true,
							),
						)
					);
				}
			}
		}


		/**
		 * Output inline data for VB React components.
		 * Fires in wp_head so the global is available before VB scripts run.
		 */
		public function output_vb_inline_data() {
			if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
				return;
			}
			$placeholder = function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'thumbnail' ) : '';
			printf(
				'<script>window.wftyDivi5VB=%s;</script>' . "\n",
				wp_json_encode( array( 'placeholderImgSrc' => $placeholder ) )
			);
		}

		/**
		 * Extend conversion outlines for thankyou modules so ALL D4 shortcode
		 * attributes are "known" during D4→D5 re-conversion.
		 *
		 * @param array  $outline     Current outline.
		 * @param string $module_name Block name being converted.
		 * @return array Modified outline.
		 */
		public function extend_thankyou_conversion_outline( $outline, $module_name ) {
			if ( 'wfty/customer-details' === $module_name ) {
				return $this->extend_customer_details_outline( $outline );
			}
			if ( 'wfty/order-details' === $module_name ) {
				return $this->extend_order_details_outline( $outline );
			}
			return $outline;
		}

		/**
		 * Extend conversion outline for wfty/customer-details.
		 *
		 * @param array $outline Current outline.
		 * @return array Modified outline.
		 */
		private function extend_customer_details_outline( $outline ) {
			if ( ! is_array( $outline ) ) {
				$outline = array();
			}
			if ( ! isset( $outline['module'] ) || ! is_array( $outline['module'] ) ) {
				$outline['module'] = array();
			}

			$m = &$outline['module'];

			// Typography "_typograhy" (typo) → font family path.
			$typo_groups = array(
				'wfty_customer_details_heading_typography' => 'wfty_customer_details_heading_typography',
				'wfty_customer_details_det_heading_typography' => 'wfty_customer_details_det_heading_typography',
				'wfty_customer_details_det_text_typography' => 'wfty_customer_details_det_text_typography',
			);

			foreach ( $typo_groups as $d4 => $d5 ) {
				$m[ $d4 . '_typograhy' ] = $d5 . '.decoration.font.font.*';
			}

			// Value expansion functions for _typograhy attributes.
			if ( ! isset( $outline['valueExpansionFunctionMap'] ) || ! is_array( $outline['valueExpansionFunctionMap'] ) ) {
				$outline['valueExpansionFunctionMap'] = array();
			}

			$convert_font_callable = 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::convertFont';

			foreach ( array_keys( $typo_groups ) as $d4 ) {
				$outline['valueExpansionFunctionMap'][ $d4 . '_typograhy' ] = $convert_font_callable;
			}

			return $outline;
		}

		/**
		 * Extend conversion outline for wfty/order-details.
		 *
		 * @param array $outline Current outline.
		 * @return array Modified outline.
		 */
		private function extend_order_details_outline( $outline ) {
			if ( ! is_array( $outline ) ) {
				$outline = array();
			}
			if ( ! isset( $outline['module'] ) || ! is_array( $outline['module'] ) ) {
				$outline['module'] = array();
			}

			$m = &$outline['module'];

			// Typography "_typograhy" (typo) → font family path.
			$typo_groups = array(
				'wfty_order_details_heading_typography'   => 'wfty_order_details_heading_typography',
				'wfty_order_details_product_typography'   => 'wfty_order_details_product_typography',
				'wfty_order_details_subtotal_typography'  => 'wfty_order_details_subtotal_typography',
				'wfty_order_details_total_typography'     => 'wfty_order_details_total_typography',
				'wfty_order_details_variation_typography' => 'wfty_order_details_variation_typography',
				'wfty_order_details_subscription_typography' => 'wfty_order_details_subscription_typography',
				'wfty_order_details_download_typography'  => 'wfty_order_details_download_typography',
			);

			foreach ( $typo_groups as $d4 => $d5 ) {
				$m[ $d4 . '_typograhy' ] = $d5 . '.decoration.font.font.*';
			}

			// Value expansion functions for _typograhy attributes.
			if ( ! isset( $outline['valueExpansionFunctionMap'] ) || ! is_array( $outline['valueExpansionFunctionMap'] ) ) {
				$outline['valueExpansionFunctionMap'] = array();
			}

			$convert_font_callable = 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::convertFont';

			foreach ( array_keys( $typo_groups ) as $d4 ) {
				$outline['valueExpansionFunctionMap'][ $d4 . '_typograhy' ] = $convert_font_callable;
			}

			return $outline;
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

			WFFN_Core()->thank_you_pages->register_template_type( $template );
			$templates = WooFunnels_Dashboard::get_all_templates();
			$designs   = isset( $templates['wc_thankyou'] ) ? $templates['wc_thankyou'] : array();

			if ( isset( $designs['divi'] ) && is_array( $designs['divi'] ) ) {
				foreach ( $designs['divi'] as $d_key => $templates ) {

					if ( isset( $templates['pro'] ) && 'yes' === $templates['pro'] ) {
						$templates['license_exist'] = WFFN_Core()->admin->get_license_status();
					}
					WFFN_Core()->thank_you_pages->register_template( $d_key, $templates, 'divi' );

				}
			} else {

				$empty_template = array(
					'type'               => 'view',
					'import'             => 'no',
					'show_import_popup'  => 'no',
					'slug'               => 'divi_1',
					'build_from_scratch' => true,

				);
				WFFN_Core()->thank_you_pages->register_template( 'divi_1', $empty_template, 'divi' );
			}

			return array();
		}

		/**
		 * @return WFFN_ThankYou_WC_Pages_Divi|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Initialize Divi 4 extension.
		 *
		 * Must load even when Divi 5 is active: pages with unconverted D4 shortcode
		 * content are rendered by D5's backwards-compatibility layer, which calls the
		 * D4 module's render() → prepare_css() to generate dynamic CSS.  Without the
		 * extension the shortcode fallback outputs HTML but no saved styles.
		 */

		/**
		 * Hide legacy D4 thank-you modules from Divi 5 VB module picker.
		 *
		 * Removes set_shortcode_module_definitions callbacks so D4 modules
		 * don't appear in the VB inserter. Shortcode handlers remain for
		 * frontend rendering of unconverted pages.
		 */
		public function hide_legacy_d4_modules_from_vb() {
			$slugs = array( 'et_wfty_customer_details', 'et_wfty_order_details' );

			foreach ( ET_Builder_Element::get_modules() as $module ) {
				if ( in_array( $module->slug, $slugs, true ) ) {
					remove_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $module, 'set_shortcode_module_definitions' ) );
				}
			}
		}

		public function init_extension() {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_divi_css' ), 20 );

			if ( wp_doing_ajax() ) {
				$post_type = WFFN_Core()->thank_you_pages->get_post_type_slug();
				if ( isset( $_REQUEST['action'] ) && 'et_fb_get_saved_templates' === $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && $post_type !== $_REQUEST['et_post_type'] ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					return;
				}

				if ( isset( $_REQUEST['action'] ) && 'et_fb_update_builder_assets' === $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && $post_type !== $_REQUEST['et_post_type'] ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					return;
				}

				$post_id = 0;
				if ( isset( $_REQUEST['action'] ) && 'heartbeat' === $_REQUEST['action'] && isset( $_REQUEST['data'] ) ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					if ( isset( $_REQUEST['data']['et'] ) ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
						$post_id = $_REQUEST['data']['et']['post_id']; //phpcs:ignore
					}
				}

				if ( isset( $_REQUEST['post_id'] ) ) {  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					$post_id = absint( $_REQUEST['post_id'] );  //phpcs:ignore WordPress.Security.NonceVerification.Recommended, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
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

			include __DIR__ . '/class-wfty-divi-extension.php';
		}
	}

	WFFN_ThankYou_WC_Pages_Divi::get_instance();
}
