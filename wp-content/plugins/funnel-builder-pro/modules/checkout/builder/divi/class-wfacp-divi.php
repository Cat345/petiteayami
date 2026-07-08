<?php
if ( ! class_exists( 'WFACP_DIVI' ) ) {
	#[\AllowDynamicProperties]
	class WFACP_DIVI {
		private static $ins           = null;
		private static $front_locals  = array();
		private $set_our_page_content = '';

		// No static property needed - using global constant instead

		private function __construct() {
			add_action( 'after_setup_theme', array( $this, 'init' ), 5 );

			add_action( 'wfacp_register_template_types', array( $this, 'register_template_type' ), 12 );
			add_filter( 'wfacp_register_templates', array( $this, 'register_templates' ) );
			add_filter( 'wfacp_template_edit_link', array( $this, 'add_template_edit_link' ), 10, 2 );
			add_action( 'woocommerce_checkout_terms_and_conditions', array( $this, 'remove_the_content_filter' ) );

			// CRITICAL: Add filter to prevent header/footer for Divi 5 (same pattern as upsell module)
			// This filter is called by Divi to determine if outer content wrap should be added
			// For checkout pages, we want to return true to prevent header/footer
			add_filter( 'et_builder_add_outer_content_wrap', array( $this, 'maybe_filter' ), 999 );
		}

		/**
		 * Load Divi 5 modules early (before dependency tree hook fires).
		 *
		 * Following the same pattern as upsell module:
		 * - Loads on plugins_loaded hook with priority 2
		 * - Must be loaded before divi_module_library_modules_dependency_tree hook fires
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function load_divi5_modules(): void {
			if ( defined( 'WFACP_DIVI5_MODULES_LOADED' ) ) {
				return;
			}

			$is_divi5 = $this->is_divi5_active();

			if ( $is_divi5 ) {
				// Declare D5 compatibility so Divi's readiness checker recognises this plugin.
				// The checker only attributes hooks registered with array callbacks (not closures).
				add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'register_d5_modules_dependency' ) );

				// Global — needed for D4→D5 conversion on ANY page.
				add_filter( 'divi.moduleLibrary.conversion.moduleConversionOutline', array( $this, 'extend_checkout_form_conversion_outline' ), 10, 2 );
				add_filter( 'divi.moduleLibrary.conversion.moduleConversionOutline', array( $this, 'extend_mini_cart_conversion_outline' ), 10, 2 );
				add_filter( 'divi.conversion.shortcodeDefaults', array( $this, 'inject_d4_shortcode_defaults' ), 10, 2 );

				// Hide legacy D4 modules from VB picker on all pages.
				add_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $this, 'hide_legacy_d4_modules_from_vb' ), 1 );

				// Defer D5 module registration to init:10 behind post-type guard.
				// At plugins_loaded there's no post context; url_to_postid() works at init:10
				// (wfacp_checkout post type registers at init:5).
				add_action( 'init', array( $this, 'maybe_load_d5_modules' ), 10 );

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
		 * Conditionally load D5 modules if the current page is a checkout page.
		 */
		public function maybe_load_d5_modules(): void {
			if ( defined( 'WFACP_DIVI5_MODULES_LOADED' ) ) {
				return;
			}

			if ( ! $this->is_matching_post_type() ) {
				return;
			}

			$divi5_modules_path = WFACP_Core()->dir( 'builder/divi-5/modules/Modules.php' );
			if ( file_exists( $divi5_modules_path ) ) {
				require_once $divi5_modules_path;

				// Force module registration if dependency tree hook already fired.
				// Modules.php registers on divi_module_library_modules_dependency_tree (init:0)
				// but we load at init:10, so that hook is missed. Directly instantiate and
				// call load() on each module to trigger ModuleRegistration::register_module().
				if ( did_action( 'init' ) && interface_exists( 'ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) ) {
					$modules_dir = WFACP_Core()->dir( 'builder/divi-5/modules/' );
					$module_map  = array(
						'CheckoutForm' => 'WFACP\\Modules\\CheckoutForm\\CheckoutForm',
						'MiniCart'     => 'WFACP\\Modules\\MiniCart\\MiniCart',
					);
					foreach ( $module_map as $dir => $class ) {
						$file = $modules_dir . $dir . '/' . $dir . '.php';
						if ( file_exists( $file ) ) {
							require_once $file;
						}
						if ( class_exists( $class ) ) {
							try {
								$instance = new $class();
								if ( method_exists( $instance, 'load' ) ) {
									$instance->load();
								}
							} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
							} catch ( \Error $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
							}
						}
					}
				}

				// VB assets hook only fires inside Visual Builder.
				add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'enqueue_visual_builder_assets' ), 10, 0 );
			}

			// Block parser class map: VB only.
			if ( isset( $_GET['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				add_filter( 'divi_block_parser_block_to_class_map', array( $this, 'register_block_parser_class_map' ) );
			}

			// Fix D4→D5 converted border defaults on frontend.
			add_action( 'wp_head', array( $this, 'output_border_conversion_fix_css' ), 999 );

			// Template classes — needed in both VB and frontend contexts.
			$template_common_path = WFACP_Core()->dir( 'includes/class-wfacp-template.php' );
			if ( file_exists( $template_common_path ) && ! class_exists( 'WFACP_Template_Common' ) ) {
				require_once $template_common_path;
			}

			$divi_template_path = WFACP_Core()->dir( 'builder/divi/class-wfacp-divi-template.php' );
			if ( file_exists( $divi_template_path ) && ! class_exists( 'WFACP_Divi_Template' ) ) {
				require_once $divi_template_path;
			}

			$divi5_template_path = WFACP_Core()->dir( 'builder/divi-5/class-wfacp-template-divi5.php' );
			if ( file_exists( $divi5_template_path ) ) {
				require_once $divi5_template_path;
			}

			$divi5_importer_path = WFACP_Core()->dir( 'builder/divi-5/class-wfacp-divi5-importer.php' );
			if ( file_exists( $divi5_importer_path ) ) {
				require_once $divi5_importer_path;
			}

			// Fix canvas template conflicts with Divi 5 VB.
			add_action( 'wp', array( $this, 'maybe_fix_vb_template_compat' ) );
		}

		/**
		 * Check if the current request is for a checkout page.
		 *
		 * @return bool
		 */
		private function is_matching_post_type(): bool {
			$post_type = WFACP_Common::get_post_type_slug();

			// 0. REST API requests for wfacp routes.
			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$uri = wp_unslash( $_SERVER['REQUEST_URI'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( false !== strpos( $uri, '/wp-json/wfacp/' ) ) {
					return true;
				}
				if ( isset( $_GET['rest_route'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ), '/wfacp/' ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return true;
				}
			}

			// 1. $_REQUEST params — admin editor, AJAX, Divi VB REST calls.
			// Divi VB passes et_post_id and currentPage[id]/postId in REST requests.
			$post_id = 0;
			foreach ( array( 'et_wfacp_id', 'edit', 'post', 'editor_post_id', 'et_post_id', 'postId', 'post_id' ) as $key ) {
				if ( ! empty( $_REQUEST[ $key ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$post_id = absint( $_REQUEST[ $key ] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					break;
				}
			}

			// 2. WordPress fallback.
			if ( $post_id < 1 && function_exists( 'get_the_ID' ) ) {
				$post_id = (int) get_the_ID();
			}

			// 3. Divi VB frontend URLs (/slug/?et_fb=1).
			if ( $post_id < 1 && ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$post_id = url_to_postid( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			if ( $post_id > 0 ) {
				if ( get_post_type( $post_id ) === $post_type ) {
					return true;
				}
				// Store checkout override: /checkout/ is a 'page' but FunnelKit overrides it.
				if ( method_exists( 'WFACP_Common', 'get_checkout_page_id' ) ) {
					$override_id = WFACP_Common::get_checkout_page_id();
					if ( $override_id > 0 && absint( $post_id ) === absint( wc_get_page_id( 'checkout' ) ) ) {
						return true;
					}
				}
			}

			// 4. Fallback: match checkout rewrite slug in the URL path.
			// url_to_postid() can fail at init:10 if rewrite rules haven't
			// been flushed for the checkout post type's custom slug.
			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) { //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$uri          = wp_unslash( $_SERVER['REQUEST_URI'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$rewrite_slug = WFACP_Common::get_url_rewrite_slug();
				if ( ! empty( $rewrite_slug ) && false !== strpos( $uri, '/' . $rewrite_slug . '/' ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Fix canvas template compatibility with Divi 5 Visual Builder.
		 *
		 * - Top window: remove canvas template override so Divi's VB shell loads.
		 * - App window: bridge wfacp_template_container hooks to Divi's hooks that
		 *   output the #et-fb-app wrapper visual-builder.js needs for React.
		 */
		public function maybe_fix_vb_template_compat() {
			if ( ! class_exists( 'ET\Builder\Framework\Utility\Conditions' ) ) {
				return;
			}

			if ( ET\Builder\Framework\Utility\Conditions::is_vb_top_window() ) {
				$instance = WFACP_Template_loader::get_instance();
				remove_filter( 'template_include', array( $instance, 'assign_template' ), 95 );
				remove_filter( 'template_include', array( $instance, 'assign_template' ), 99 );
				return;
			}

			if ( ET\Builder\Framework\Utility\Conditions::is_vb_app_window()
			&& function_exists( 'et_fb_print_app_wrapper_before_main_content' )
			&& function_exists( 'et_fb_print_app_wrapper_after_main_content' )
			) {
				add_action( 'wfacp_template_container_top', 'et_fb_print_app_wrapper_before_main_content' );
				add_action( 'wfacp_template_container_bottom', 'et_fb_print_app_wrapper_after_main_content' );
			}
		}

		/**
		 * Hide legacy D4 checkout modules from Divi 5 VB module picker.
		 *
		 * Removes set_shortcode_module_definitions callbacks for our D4 modules
		 * so they don't appear in the VB inserter. Shortcode handlers remain
		 * registered for frontend rendering of unconverted pages.
		 *
		 * @since 1.0.0
		 */
		public function hide_legacy_d4_modules_from_vb() {
			$slugs = array( 'wfacp_checkout_form', 'wfacp_checkout_form_summary' );

			foreach ( ET_Builder_Element::get_modules() as $module ) {
				if ( in_array( $module->slug, $slugs, true ) ) {
					remove_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $module, 'set_shortcode_module_definitions' ) );
				}
			}
		}

		/**
		 * Register WFACP block-to-class mappings for Divi 5 frontend BlockParser.
		 *
		 * The BlockParser uses this map to find and instantiate module classes
		 * when rendering blocks on the frontend. Without this, our wfacp/* blocks
		 * are not recognized and produce no HTML output.
		 *
		 * The BlockParser calls: new $class_name() then $instance->load()
		 * which registers the render_callback via ModuleRegistration::register_module().
		 *
		 * @since 1.0.0
		 * @param array $map Block name to FQCN class map.
		 * @return array Modified map with WFACP blocks added.
		 */
		public function register_block_parser_class_map( $map ) {
			// Ensure module class files are loaded so the BlockParser can instantiate them.
			$modules_dir = WFACP_Core()->dir( 'builder/divi-5/modules/' );

			$checkout_form_path = $modules_dir . 'CheckoutForm/CheckoutForm.php';
			if ( file_exists( $checkout_form_path ) ) {
				require_once $checkout_form_path;
				$map['wfacp/checkout-form'] = 'WFACP\\Modules\\CheckoutForm\\CheckoutForm';
			}

			$mini_cart_path = $modules_dir . 'MiniCart/MiniCart.php';
			if ( file_exists( $mini_cart_path ) ) {
				require_once $mini_cart_path;
				$map['wfacp/mini-cart'] = 'WFACP\\Modules\\MiniCart\\MiniCart';
			}

			return $map;
		}

		/**
		 * Output corrective CSS for D4→D5 converted border defaults.
		 *
		 * Backward compat mode renders D5 blocks through D4 engine which reads
		 * the stored attrs directly. Wrong border color (#333 instead of #ddd)
		 * and width (3px all instead of 1px/3px bottom) need CSS overrides.
		 */
		public function output_border_conversion_fix_css() {
			$post_id = get_the_ID();
			if ( ! $post_id ) {
				return;
			}

			$content = get_post_field( 'post_content', $post_id );
			if ( empty( $content ) || strpos( $content, 'wfacp/checkout-form' ) === false ) {
				return;
			}

			$css = '';

			// Section border fix.
			$css .= '.et-db #et-boc .et-l [class*="wfacp_checkout_form"] #wfacp-e-form .wfacp-section {';
			$css .= 'border-color: #dddddd !important;';
			$css .= 'border-top-width: 1px !important;';
			$css .= 'border-left-width: 1px !important;';
			$css .= 'border-right-width: 1px !important;';
			$css .= '}';

			// Mini cart border fix.
			$css .= '.et-db #et-boc .et-l [class*="wfacp_mini_cart"] .wfacp_mini_cart_start_h {';
			$css .= 'border-color: #dddddd !important;';
			$css .= 'border-top-width: 1px !important;';
			$css .= 'border-left-width: 1px !important;';
			$css .= 'border-right-width: 1px !important;';
			$css .= '}';

			echo '<style id="wfacp-d5-border-fix">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS only.
		}

		/**
		 * Check if Divi 5 is active.
		 *
		 * @since 1.0.0
		 * @return bool True if Divi 5 is active, false otherwise.
		 */
		private function is_divi5_active(): bool {
			if ( did_action( 'after_setup_theme' ) ) {
				return function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled();
			}
			return false;
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

			// CRITICAL: Fix URL path - ensure correct path separator
			// The url() method needs a leading slash to properly join paths
			$plugin_dir_url  = WFACP_Core()->url( '/builder/divi-5/visual-builder/build/wfacp-divi5-visual-builder.js' );
			$plugin_dir_path = WFACP_Core()->dir( 'builder/divi-5/visual-builder/build/wfacp-divi5-visual-builder.js' );

			// Wrap getGroupPresetDefaultAttr BEFORE module-library calls it.
			// Depends on divi-module-utils (provides divi.moduleUtils) which loads
			// before divi-module-library. Strips divi/font entries from checkout
			// modules' preset lookup while keeping decoration.font for styles.
			$preset_fix_url  = WFACP_Core()->url( '/builder/divi-5/visual-builder/build/wfacp-preset-fix.js' );
			$preset_fix_path = WFACP_Core()->dir( 'builder/divi-5/visual-builder/build/wfacp-preset-fix.js' );

			if ( file_exists( $preset_fix_path ) ) {
				try {
					\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
						array(
							'name'    => 'wfacp-preset-fix',
							'version' => defined( 'WFACP_VERSION' ) ? WFACP_VERSION : '1.0.0',
							'script'  => array(
								'src'                => $preset_fix_url,
								'deps'               => array( 'divi-module-utils' ),
								'enqueue_top_window' => false,
								'enqueue_app_window' => true,
							),
						)
					);
				} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				} catch ( \Error $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				}
			}

			// Only register if the built file exists
			if ( file_exists( $plugin_dir_path ) ) {
				try {
					\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
						array(
							'name'    => 'wfacp-divi5-visual-builder',
							'version' => defined( 'WFACP_VERSION' ) ? WFACP_VERSION : '1.0.0',
							'script'  => array(
								'src'                => $plugin_dir_url,
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
				} catch ( \Exception $e ) {
					// Silent fail - Visual Builder asset registration error
				} catch ( \Error $e ) {
					// Silent fail - Visual Builder asset registration error
				}
			}
		}

		public function init() {
			if ( ! ( class_exists( 'ET_Builder_Plugin' ) || function_exists( 'et_setup_theme' ) ) ) {
				return;
			}
			$this->load_divi5_modules();

			// Note: Divi 5 modules are loaded in load_divi5_modules() on plugins_loaded hook
			// This ensures et_builder_d5_enabled() function is available

			// Continue with common initialization
			add_filter( 'wfacp_is_theme_builder', array( $this, 'is_divi_page' ) );
			add_action( 'wfacp_template_removed', array( $this, 'delete_divi_data' ) );
			add_action( 'wfacp_duplicate_pages', array( $this, 'duplicate_template' ), 10, 3 );
			add_action( 'wfacp_get_divi_form_data', array( $this, 'builder_actions' ), 10, 2 );
			add_action( 'et_save_post', array( $this, 'migrate_label' ) );
			$this->register();
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public static function set_locals( $name, $id ) {
			self::$front_locals[ $name ] = $id;
		}

		public static function get_locals() {
			return self::$front_locals;
		}

		public function is_divi_page( $status ) {
			// At load
			if ( isset( $_REQUEST['et_fb'] ) ) {
				$status = true;
			}
			// when ajax running for form html
			if ( isset( $_REQUEST['wc-ajax'] ) && 'wfacp_get_divi_data' == $_REQUEST['wc-ajax'] ) {
				$status = true;
			}

			if ( function_exists( 'et_fb_is_builder_ajax' ) && et_fb_is_builder_ajax() ) {
				$status = true;
			}

			return $status;
		}

		private function register() {

			add_action( 'wfacp_checkout_page_found', array( $this, 'initialize_divi_widgets' ) );
			add_action( 'wfacp_template_load', array( $this, 'load_divi_abs_class' ), 10, 2 );

			add_action( 'divi_extensions_init', array( $this, 'init_extension' ) );
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 1003 );
		}

		/**
		 * @param $loader WFACP_Template_loader
		 */
		public function register_template_type( $loader ) {
			$template = array(
				'slug'    => 'divi',
				'title'   => __( 'Divi', 'woofunnels-aero-checkout' ),
				'filters' => WFACP_Common::get_template_filter(),
			);
			$loader->register_template_type( $template );
		}

		public function register_templates( $designs ) {
			$templates = WooFunnels_Dashboard::get_all_templates();
			$is_divi5  = $this->is_divi5_active();

			$designs['divi'] = ( isset( $templates['wc_checkout']['divi'] ) ) ? $templates['wc_checkout']['divi'] : array();
			if ( is_array( $designs['divi'] ) && count( $designs['divi'] ) > 0 ) {
				$template_path = $is_divi5
				? WFACP_Core()->dir( 'builder/divi-5/class-wfacp-template-divi5.php' )
				: WFACP_BUILDER_DIR . '/divi/template/template.php';

				foreach ( $designs['divi'] as $key => $val ) {
					$val['path']             = $template_path;
					$designs['divi'][ $key ] = $val;
				}
			}

			// Register divi5-exclusive templates (e.g. divi-shoppe).
			if ( $is_divi5 && ! empty( $templates['wc_checkout']['divi5'] ) ) {
				$d5_path          = WFACP_Core()->dir( 'builder/divi-5/class-wfacp-template-divi5.php' );
				$designs['divi5'] = array();
				foreach ( $templates['wc_checkout']['divi5'] as $key => $val ) {
					$val['path']              = $d5_path;
					$designs['divi5'][ $key ] = $val;
				}
			}

			return $designs;
		}


		public function initialize_divi_widgets( $post_id ) {
			$design = WFACP_Common::get_page_design( $post_id );
			if ( ! in_array( $design['selected_type'], array( 'divi', 'divi5' ), true ) ) {
				return;
			}

			// CRITICAL: The maybe_filter() method handles outer content wrap for both Divi 4 and Divi 5
			// It checks the post type and returns true to prevent header/footer
			// No need to add the filter here again - it's already added in constructor

			if ( ! isset( $_REQUEST['et_fb'] ) ) {
				global $post;
				$post                       = get_post( $post_id );
				$this->set_our_page_content = $post->post_content;
				remove_filter( 'the_content', 'et_builder_add_builder_content_wrapper' );
				add_filter( 'wfacp_assign_default_theme_template', '__return_false' );
				add_filter( 'the_content', array( $this, 'replace_divi_our_page_content' ), 1 );
				// D5: wrap after do_blocks so the block parser can't drop the freeform wrapper.
				if ( $this->is_divi5_active() ) {
					add_filter( 'the_content', array( $this, 'ensure_divi5_builder_wrappers' ), 15 );
				}
			}
		}

		/**
		 * Filter to prevent header/footer for checkout pages (same pattern as upsell module).
		 *
		 * @param bool $add_outer_wrap Whether to add outer content wrap.
		 * @return bool True to prevent header/footer, false otherwise.
		 */
		public function maybe_filter( $add_outer_wrap ) {
			global $post;

			// Check if this is a checkout page (wfacp_checkout post type)
			if ( is_object( $post ) && $post instanceof WP_Post && $post->post_type === 'wfacp_checkout' ) {
				return true; // Return true to prevent header/footer
			}

			return $add_outer_wrap;
		}

		public function replace_divi_our_page_content( $content ) {
			if ( '' !== $this->set_our_page_content ) {
				// D5 wraps later at priority 15; D4 wraps inline here.
				$content = $this->is_divi5_active()
					? $this->set_our_page_content
					: $this->et_builder_add_builder_content_wrapper( $this->set_our_page_content );
			}
			do_action( 'wfacp_divi_page_content_replaced', $this, $content );

			return $content;
		}

		// D5 safety net: re-add #et-boc/.et-l--post wrappers after do_blocks() if the parser dropped them.
		public function ensure_divi5_builder_wrappers( $content ) {
			if ( false !== strpos( $content, '<div id="et-boc"' ) ) {
				return $content;
			}
			return $this->et_builder_add_builder_content_wrapper( $content );
		}

		public function et_builder_add_builder_content_wrapper( $content ) {
			$is_bfb_new_page = isset( $_GET['is_new_page'] ) && '1' === $_GET['is_new_page'];

			if ( ! is_singular() && ! $is_bfb_new_page && ! et_theme_builder_is_layout_post_type( get_post_type( get_the_ID() ) ) ) {
				return $content;
			}
			if ( function_exists( 'et_builder_get_layout_opening_wrapper' ) ) {
				$content = et_builder_get_layout_opening_wrapper() . $content . et_builder_get_layout_closing_wrapper();
			}

			/**
			 * Filter whether to add the outer builder content wrapper or not.
			 *
			 * @param bool $wrap
			 *
			 * @since 4.0
			 */
			if ( function_exists( 'et_builder_get_builder_content_opening_wrapper' ) ) {
				$content = et_builder_get_builder_content_opening_wrapper() . $content . et_builder_get_builder_content_closing_wrapper();
			}

			return $content;
		}

		public function load_divi_abs_class( $wfacp_id, $template = array() ) {
			if ( empty( $template ) ) {
				return;
			}
			if ( in_array( $template['selected_type'], array( 'divi', 'divi5' ), true ) ) {
				// CRITICAL: Always load parent class first (required for Divi5 template)
				$divi_template_path = WFACP_Core()->dir( 'builder/divi/class-wfacp-divi-template.php' );
				if ( file_exists( $divi_template_path ) && ! class_exists( 'WFACP_Divi_Template' ) ) {
					include_once $divi_template_path;
				}

				// CRITICAL: Load Divi 5 template class if Divi 5 is active, otherwise Divi 4
				$is_divi5 = $this->is_divi5_active();
				if ( $is_divi5 ) {
					$divi5_template_path = WFACP_Core()->dir( 'builder/divi-5/class-wfacp-template-divi5.php' );
					if ( file_exists( $divi5_template_path ) ) {
						include_once $divi5_template_path;
					} else {
						// Fallback to Divi 4 if Divi 5 template doesn't exist
						include_once WFACP_Core()->dir( 'builder/divi/class-wfacp-divi-template.php' );
					}
				} else {
					include_once WFACP_Core()->dir( 'builder/divi/class-wfacp-divi-template.php' );
				}
			}
		}

		public function add_template_edit_link( $links, $admin ) {
			$url           = add_query_arg(
				array(
					'et_fb'       => '1',
					'et_wfacp_id' => $admin->wfacp_id,
				),
				get_the_permalink( $admin->wfacp_id )
			);
			$links['divi'] = array(
				'url'         => $url,
				'button_text' => __( 'Edit', 'elementor' ),
			);

			return $links;
		}


		public function init_extension() {
			// NOTE: Do NOT skip D4 extension loading when D5 is active.
			// D5 stores content in block format but our shortcodes
			// ([wfacp_checkout_form], [wfacp_checkout_form_summary]) are
			// embedded inside divi/text blocks. When D5 outputs that text,
			// do_shortcode() needs D4's module class registered to render
			// them. Loading the D4 extension here ensures the shortcode
			// handlers are available on the frontend.

			// Only proceed with Divi 4 extension loading if Divi 5 is NOT active
			if ( wp_doing_ajax() ) {

				if ( isset( $_REQUEST['action'] ) && 'et_fb_get_saved_templates' == $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && WFACP_Common::get_post_type_slug() !== $_REQUEST['et_post_type'] ) {
					return;
				}

				if ( isset( $_REQUEST['action'] ) && 'et_fb_update_builder_assets' == $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && WFACP_Common::get_post_type_slug() !== $_REQUEST['et_post_type'] ) {
					return;
				}

				$post_id = 0;
				if ( isset( $_REQUEST['action'] ) && 'heartbeat' == $_REQUEST['action'] && isset( $_REQUEST['data'] ) ) {
					if ( isset( $_REQUEST['data']['et'] ) ) {
						$post_id = $_REQUEST['data']['et']['post_id'];

					}
				}

				if ( isset( $_REQUEST['post_id'] ) ) {
					$post_id = absint( $_REQUEST['post_id'] );
				}
				if ( isset( $_REQUEST['et_post_id'] ) ) {
					$post_id = absint( $_REQUEST['et_post_id'] );
				}

				if ( $post_id > 0 ) {
					$post = get_post( $post_id );
					if ( is_null( $post ) || $post->post_type !== WFACP_Common::get_post_type_slug() ) {
						return;
					}
				}
			}

			if ( isset( $_REQUEST['et_fb'] ) && ! isset( $_REQUEST['et_wfacp_id'] ) ) {
				return;
			}

			include __DIR__ . '/class-wfacp-divi-extension.php';
		}

		public function add_admin_bar_link() {
			/**
			 * @var $wp_admin_bar WP_Admin_Bar;
			 */ global $wp_admin_bar;

			if ( ! is_null( $wp_admin_bar ) ) {
				$node = $wp_admin_bar->get_node( 'et-use-visual-builder' );
				if ( ! is_null( $node ) ) {
					$node = (array) $node;
					global $post;
					if ( ! is_null( $post ) && $post->post_type == WFACP_Common::get_post_type_slug() ) {
						$wfacp_id     = $post->ID;
						$href         = $node['href'];
						$node['href'] = add_query_arg( array( 'et_wfacp_id' => $wfacp_id ), $href );
						$wp_admin_bar->add_node( $node );
					}
				}
			}
		}

		/**
		 * Delete Elementor saved data from postmeta of aerocheckout ID
		 */
		public function delete_divi_data( $post_id ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => '',
				)
			);
			delete_post_meta( $post_id, 'et_enqueued_post_fonts' );
		}

		public function duplicate_template( $new_post_id, $post_id, $data ) {
			if ( in_array( $data['_wfacp_selected_design']['selected_type'], array( 'divi', 'divi5' ), true ) ) {
				$data = array(
					'_et_pb_use_builder'     => get_post_meta( $post_id, '_et_pb_use_builder', true ),
					'et_enqueued_post_fonts' => get_post_meta( $post_id, 'et_enqueued_post_fonts', true ),
				);
				foreach ( $data as $meta_key => $meta_value ) {
					update_post_meta( $new_post_id, $meta_key, $meta_value );
				}
			}
		}

		public function builder_actions( $post, $json ) {
			add_filter(
				'wfacp_forms_field',
				function ( $field, $key ) use ( $json ) {

					return $this->modern_label( $field, $key, $json );
				},
				20,
				2
			);
		}

		public function modern_label( $field, $key, $data ) {
			if ( empty( $field ) ) {
				return $field;
			}

			if ( 'wfacp-modern-label' != $data['wfacp_label_position'] || ! isset( $field['placeholder'] ) ) {
				return $field;
			}

			return WFACP_Common::live_change_modern_label( $field );
		}

		public function migrate_label( $post_id ) {
			$post = get_post( $post_id );

			if ( is_null( $post ) ) {
				return;
			}
			if ( false !== strpos( $post->post_content, 'wfacp-modern-label' ) ) {
				$field_label = 'wfacp-modern-label';
				WFACP_Common_Helper::modern_label_migrate( $post_id );
			} elseif ( false !== strpos( $post->post_content, 'wfacp-top' ) ) {
				$field_label = 'wfacp-top';
			} else {
				$field_label = 'wfacp-inside';
			}
			update_post_meta( $post_id, '_wfacp_field_label_position', $field_label );
		}

		public function remove_the_content_filter() {
			if ( defined( 'BRICKS_VERSION' ) ) {
				// If Bricks is active, we don`t need to remove the filter that changes the global post variable.
				return;
			}
			remove_filter( 'the_content', array( $this, 'replace_divi_our_page_content' ), 1 );
		}

		/**
		 * Extend the conversion outline for wfacp/checkout-form so that ALL D4
		 * shortcode attributes are "known" during D4→D5 re-conversion.
		 *
		 * Without this, D4 style sub-attributes (typography, border, etc.) become unknownAttributes,
		 * which forces Divi to keep the block as divi/shortcode-module.
		 *
		 * @param array  $conversion_outline Current outline.
		 * @param string $module_name        Block name being converted.
		 *
		 * @return array Modified outline.
		 */
		public function extend_checkout_form_conversion_outline( $conversion_outline, $module_name ) {
			if ( 'wfacp/checkout-form' !== $module_name ) {
				return $conversion_outline;
			}

			if ( ! is_array( $conversion_outline ) ) {
				$conversion_outline = array();
			}

			if ( ! isset( $conversion_outline['module'] ) || ! is_array( $conversion_outline['module'] ) ) {
				$conversion_outline['module'] = array();
			}

			// 1. Dynamic form field width/class attributes (vary per checkout page).
			// D4 generates attributes in two forms:
			// - bare:     {field_id}  (e.g. "shipping_address_1")
			// - prefixed: wfacp_{template_slug}_{field_id}_field  (e.g. "wfacp_divi-minimalist-step-3_shipping_address_1_field")
			// Both must be mapped so that Divi's converter doesn't treat them as unknownAttributes.
			$field_data = $this->get_all_checkout_field_data();
			foreach ( $field_data['field_names'] as $field_name ) {
				if ( ! isset( $conversion_outline['module'][ $field_name ] ) ) {
					$conversion_outline['module'][ $field_name ] = $field_name . '.*';
				}
			}
			foreach ( $field_data['prefixed_names'] as $prefixed_name ) {
				if ( ! isset( $conversion_outline['module'][ $prefixed_name ] ) ) {
					$conversion_outline['module'][ $prefixed_name ] = $prefixed_name . '.*';
				}
			}

			// 2. D4 style sub-attributes generated by helper methods.
			$style_mappings = $this->get_d4_checkout_style_mappings();
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
				'tab_heading_typography_typograhy',
				'tab_subheading_typography_typograhy',
				'breadcrumb_heading_typography_typograhy',
				'progress_bar_heading_typography_typograhy',
				'order_summary_cart_item_typo_typograhy',
				'order_summary_strike_through_typo_typograhy',
				'order_summary_low_stock_message_typo_typograhy',
				'order_summary_enable_saving_price_message_typo_typograhy',
				'order_summary_product_meta_typo_typograhy',
				'order_summary_cart_total_label_typo_typograhy',
				'order_summary_cart_subtotal_heading_typo_typograhy',
				'wfacp_form_payment_method_typo_typograhy',
				'wfacp_privacy_policy_font_typograhy',
				'wfacp_terms_conditions_font_typograhy',
				'order_coupon_coupon_typography_typograhy',
				'order_coupon_label_typo_typograhy',
				'order_coupon_input_typo_typograhy',
				'order_coupon_button_typo_typograhy',
				'order_coupon_btn_typo_typograhy',
				'selected_item_typography_typograhy',
				'selected_you_save_typo_typograhy',
				'product_switching_best_value_typography_typograhy',
				'product_switching_what_included_heading_typograhy',
				'product_switching_what_included_product_title_typograhy',
				'product_switching_what_included_product_description_typograhy',
				'product_switching_optional_item_typography_typograhy',
				'non_selected_you_save_typo_typograhy',
				'section_heading_typo_typograhy',
				'section_sub_heading_typo_typograhy',
				'wfacp_form_fields_label_typo_typograhy',
				'wfacp_form_fields_input_typo_typograhy',
				'wfacp_font_family_typography_typograhy',
				'wfacp_form_payment_button_typo_typograhy',
				'checkout_button_sub_text_font_size_typograhy',
			);

			foreach ( $typo_attrs as $typo_attr ) {
				$conversion_outline['valueExpansionFunctionMap'][ $typo_attr ] = $convert_font_callable;
			}

			// 4. Border width/radius conversion functions.
			// D4 stores border width and radius values as plain numbers (e.g. "40", "1")
			// without a CSS unit. convertBorderWidth appends "px" so D5 receives valid
			// CSS values like "40px". Without this, border-radius and border-width render
			// as unitless numbers (invalid CSS) in the Visual Builder.
			$border_groups_for_conversion = array(
				'wfacp_form_fields_border',
				'wfacp_button_border',
				'wfacp_collapsible_border',
				'section_border',
				'form_border',
				'form_heading_border',
				'order_coupon_coupon_border',
				'product_switching_best_value_border',
				'product_switching_item_border',
				'product_switching_what_included_border',
				'product_switching_border_non_selected',
				'form_section_border',
			);

			$border_suffixes = array(
				'_border_radius_top',
				'_border_radius_bottom',
				'_border_radius_left',
				'_border_radius_right',
			);

			foreach ( $border_groups_for_conversion as $group ) {
				foreach ( $border_suffixes as $suffix ) {
					$conversion_outline['valueExpansionFunctionMap'][ $group . $suffix ] = 'convertBorderWidth';
				}
			}

			// border_radius_steps only has radius sub-attributes (from add_border_radius_new).
			$radius_suffixes = array( '_border_radius_top', '_border_radius_bottom', '_border_radius_left', '_border_radius_right' );
			foreach ( $radius_suffixes as $suffix ) {
				$conversion_outline['valueExpansionFunctionMap'][ 'border_radius_steps' . $suffix ] = 'convertBorderWidth';
			}

			return $conversion_outline;
		}

		/**
		 * Extend the conversion outline for wfacp/mini-cart.
		 *
		 * @param array  $conversion_outline Current outline.
		 * @param string $module_name        Block name being converted.
		 *
		 * @return array Modified outline.
		 */
		public function extend_mini_cart_conversion_outline( $conversion_outline, $module_name ) {
			if ( 'wfacp/mini-cart' !== $module_name ) {
				return $conversion_outline;
			}

			if ( ! is_array( $conversion_outline ) ) {
				$conversion_outline = array();
			}

			if ( ! isset( $conversion_outline['module'] ) || ! is_array( $conversion_outline['module'] ) ) {
				$conversion_outline['module'] = array();
			}

			// 1. Map D4 style sub-attributes so the converter doesn't treat them
			// as unknownAttributes (which forces the block to stay as divi/shortcode-module).
			$style_mappings = $this->get_d4_mini_cart_style_mappings();
			foreach ( $style_mappings as $d4_attr => $d5_path ) {
				if ( ! isset( $conversion_outline['module'][ $d4_attr ] ) ) {
					$conversion_outline['module'][ $d4_attr ] = $d5_path;
				}
			}

			// 2. Value expansion functions for D4 "_typograhy" (typo) attributes.
			if ( ! isset( $conversion_outline['valueExpansionFunctionMap'] ) || ! is_array( $conversion_outline['valueExpansionFunctionMap'] ) ) {
				$conversion_outline['valueExpansionFunctionMap'] = array();
			}

			$convert_font_callable = 'ET\Builder\Packages\Conversion\AdvancedOptionConversion::convertFont';

			$typo_attrs = array(
				'mini_cart_coupon_heading_typo_typograhy',
				'wfacp_form_mini_cart_coupon_label_typo_typograhy',
				'wfacp_form_mini_cart_coupon_input_typo_typograhy',
				'wfacp_form_mini_cart_coupon_button_typo_typograhy',
				'mini_cart_total_label_typo_typograhy',
				'mini_cart_strike_through_typo_typograhy',
				'mini_cart_low_stock_message_typo_typograhy',
				'mini_cart_enable_saving_price_message_typo_typograhy',
			);

			foreach ( $typo_attrs as $typo_attr ) {
				$conversion_outline['valueExpansionFunctionMap'][ $typo_attr ] = $convert_font_callable;
			}

			// 3. Border width conversion functions for coupon border sub-attributes.
			$border_width_attrs = array(
				'wfacp_form_mini_cart_coupon_border_border_width_top',
				'wfacp_form_mini_cart_coupon_border_border_width_bottom',
				'wfacp_form_mini_cart_coupon_border_border_width_left',
				'wfacp_form_mini_cart_coupon_border_border_width_right',
				'wfacp_form_mini_cart_coupon_border_border_radius_top',
				'wfacp_form_mini_cart_coupon_border_border_radius_bottom',
				'wfacp_form_mini_cart_coupon_border_border_radius_left',
				'wfacp_form_mini_cart_coupon_border_border_radius_right',
				'mini_cart_item_border_radius_top',
				'mini_cart_item_border_radius_bottom',
				'mini_cart_item_border_radius_left',
				'mini_cart_item_border_radius_right',
			);

			foreach ( $border_width_attrs as $bw_attr ) {
				$conversion_outline['valueExpansionFunctionMap'][ $bw_attr ] = 'convertBorderWidth';
			}

			return $conversion_outline;
		}

		/**
		 * Collect checkout field data for dynamic width attribute mappings.
		 *
		 * Returns both bare field names and template-prefixed field names so
		 * the conversion outline covers all possible D4 attribute formats.
		 *
		 * @return array{field_names: string[], prefixed_names: string[]}
		 */
		private function get_all_checkout_field_data() {
			static $cached = null;
			if ( null !== $cached ) {
				return $cached;
			}

			$field_names    = array();
			$prefixed_names = array();

			// Only load fields for the current post being edited.
			// D4→D5 conversion is per-page — no need to query every checkout on the site.
			// The old approach called get_page_layout() on ALL posts which internally runs
			// get_post_meta($id) without a key, unserializing ALL postmeta per post.
			// On sites with many checkouts this exhausted 256 MB in maybe_unserialize().
			$current_post_id = 0;

			// Check request parameters (VB page load, AJAX, REST body).
			foreach ( array( 'et_wfacp_id', 'et_post_id', 'post_id', 'postId' ) as $key ) {
				if ( ! empty( $_REQUEST[ $key ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$current_post_id = absint( $_REQUEST[ $key ] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					break;
				}
			}

			// Fallback: global $post (available during SettingsData callbacks in VB app window).
			if ( $current_post_id < 1 ) {
				global $post;
				if ( ! empty( $post->ID ) && get_post_type( $post->ID ) === WFACP_Common::get_post_type_slug() ) {
					$current_post_id = $post->ID;
				}
			}

			if ( $current_post_id > 0 ) {
				$design        = get_post_meta( $current_post_id, '_wfacp_selected_design', true );
				$template_slug = '';
				if ( is_array( $design ) && ! empty( $design['selected'] ) ) {
					$template_slug = $design['selected'];
				}

				$address_sub_keys = array(
					'first_name',
					'last_name',
					'company',
					'address_1',
					'address_2',
					'city',
					'postcode',
					'country',
					'state',
					'phone',
				);

				// Get fields from the current post's layout.
				$layout = get_post_meta( $current_post_id, '_wfacp_page_layout', true );
				if ( is_array( $layout ) && ! empty( $layout['fieldsets'] ) ) {
					foreach ( $layout['fieldsets'] as $step_fields ) {
						if ( ! is_array( $step_fields ) ) {
							continue;
						}
						foreach ( $step_fields as $section ) {
							if ( ! is_array( $section ) || empty( $section['fields'] ) ) {
								continue;
							}
							foreach ( $section['fields'] as $field ) {
								if ( empty( $field['id'] ) ) {
									continue;
								}

								$field_names[] = $field['id'];

								if ( ! empty( $template_slug ) ) {
									$prefixed_names[] = 'wfacp_' . $template_slug . '_' . $field['id'] . '_field';
								}

								// Expand composite address fields into individual WC field IDs.
								if ( ! empty( $field['fields_options'] ) && is_array( $field['fields_options'] ) ) {
									$is_address_composite = (
									$field['id'] === 'address'
									|| strpos( $field['id'], 'shipping' ) !== false
									|| strpos( $field['id'], 'billing' ) !== false
									);

									if ( $is_address_composite ) {
										foreach ( array( 'shipping_', 'billing_' ) as $wc_prefix ) {
											foreach ( $address_sub_keys as $sub_key ) {
												if ( isset( $field['fields_options'][ $sub_key ] ) ) {
													$wc_field_id   = $wc_prefix . $sub_key;
													$field_names[] = $wc_field_id;

													if ( ! empty( $template_slug ) ) {
														$prefixed_names[] = 'wfacp_' . $template_slug . '_' . $wc_field_id . '_field';
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}

			$cached = array(
				'field_names'    => array_unique( $field_names ),
				'prefixed_names' => array_unique( $prefixed_names ),
			);

			return $cached;
		}

		/**
		 * Get D4 style attribute → D5 path mappings for manual border sub-fields.
		 *
		 * D4 border attribute names don't match auto-generated names, so we map them manually.
		 *
		 * @return array<string, string> D4 attribute name → D5 conversion path.
		 */
		private function get_d4_checkout_style_mappings() {
			$m = array();

			// Border groups: D4 key → D5 attribute name in conversion outline
			$border_groups = array(
				'wfacp_form_fields_border'               => 'wfacp_form_fields_border',
				'wfacp_button_border'                    => 'wfacp_button_border',
				'wfacp_collapsible_border'               => 'wfacp_collapsible_border',
				'section_border'                         => 'section_border',
				'form_border'                            => 'form_border',
				'form_heading_border'                    => 'form_heading_border',
				'order_coupon_coupon_border'             => 'order_coupon_coupon_border',
				'mini_product_image_border_color'        => 'mini_product_image_border_color',
				'active_step_count_border_color'         => 'active_step_count_border_color',
				'inactive_step_count_border_color'       => 'inactive_step_count_border_color',
				'active_tab_border_bottom_color'         => 'active_tab_border_bottom_color',
				'inactive_tab_border_bottom_color'       => 'inactive_tab_border_bottom_color',
				'order_summary_divider_line_color'       => 'order_summary_divider_line_color',
				'order_summary_cart_item_image_border_radius' => 'order_summary_cart_item_image_border_radius',
				'wfacp_form_fields_focus_color'          => 'wfacp_form_fields_focus_color',
				'wfacp_form_fields_validation_color'     => 'wfacp_form_fields_validation_color',
				'product_switching_best_value_border_color' => 'product_switching_best_value_border_color',
				'product_switching_best_value_border'    => 'product_switching_best_value_border',
				'product_switching_item_border'          => 'product_switching_item_border',
				'product_switching_border'               => 'product_switching_item_border',
				'product_switching_what_included_border' => 'product_switching_what_included_border',
				'product_switching_border_non_selected'  => 'product_switching_border_non_selected',
				'form_section_border'                    => 'section_border',
				'border_radius_steps'                    => 'border_radius_steps',
				'progress_bar_circle_color'              => 'progress_bar_circle_color',
			);

			foreach ( $border_groups as $d4 => $d5 ) {
				$m[ $d4 . '_border_type' ]          = $d5 . '.decoration.border.*.styles.all.style';
				$m[ $d4 . '_border_color' ]         = $d5 . '.decoration.border.*.styles.all.color';
				$m[ $d4 . '_border_width_top' ]     = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_bottom' ]  = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_left' ]    = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_right' ]   = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_with_right' ]    = $d5 . '.decoration.border.*.styles.all.width'; // D4 typo: "with" not "width"
				$m[ $d4 . '_border_radius_top' ]    = $d5 . '.decoration.border.*.radius.topLeft';
				$m[ $d4 . '_border_radius_bottom' ] = $d5 . '.decoration.border.*.radius.bottomRight';
				$m[ $d4 . '_border_radius_left' ]   = $d5 . '.decoration.border.*.radius.bottomLeft';
				$m[ $d4 . '_border_radius_right' ]  = $d5 . '.decoration.border.*.radius.topRight';
			}

			// Box shadow sub-fields
			$box_shadow_groups = array(
				'section_box_shadow'      => 'section_box_shadow.decoration.boxShadow',
				'form_section_box_shadow' => 'form_section_box_shadow.decoration.boxShadow',
			);

			foreach ( $box_shadow_groups as $bs => $d5b ) {
				$m[ $bs . '_shadow_enable' ]     = $d5b . '.*.enable';
				$m[ $bs . '_shadow_type' ]       = $d5b . '.*.type';
				$m[ $bs . '_shadow_color' ]      = $d5b . '.*.color';
				$m[ $bs . '_shadow_horizontal' ] = $d5b . '.*.horizontal';
				$m[ $bs . '_shadow_vertical' ]   = $d5b . '.*.vertical';
				$m[ $bs . '_shadow_blur' ]       = $d5b . '.*.blur';
				$m[ $bs . '_shadow_spread' ]     = $d5b . '.*.spread';
			}

			return $m;
		}

		/**
		 * Map D4 mini-cart attributes to D5 conversion paths.
		 *
		 * Covers typography sub-attributes, border sub-attributes, color fields,
		 * and content toggles not already present in conversion-outline.json.
		 *
		 * @return array<string, string> D4 attribute name → D5 conversion path.
		 */
		private function get_d4_mini_cart_style_mappings() {
			$m = array();

			// --- Typography groups -----------------------------------------------
			// Each D4 typography group generates: _font_size, _typograhy, _line_height, _letter_spacing.
			// Some groups already have partial mappings in conversion-outline.json;
			// the !isset() guard in the caller prevents overwrites.
			$typo_groups = array(
				'mini_cart_section_typo'                  => 'miniCartHeading',
				'mini_cart_product_typo'                  => 'miniCartProductTypo',
				'mini_cart_coupon_heading_typo'           => 'miniCartCouponLinkTypo',
				'wfacp_form_mini_cart_coupon_label_typo'  => 'miniCartCouponInputLabelTypo',
				'wfacp_form_mini_cart_coupon_input_typo'  => 'miniCartCouponInputFieldTypo',
				'wfacp_form_mini_cart_coupon_button_typo' => 'miniCartCouponButtonTypo',
				'mini_cart_product_meta_typo'             => 'miniCartProductMetaTypo',
				'mini_cart_total_label_typo'              => 'mini_cart_total_label_typo',
				'mini_cart_total_typo'                    => 'mini_cart_total_typo',
				'wfacp_mini_cart_font_family'             => 'wfacp_mini_cart_font_family',
				'mini_cart_strike_through_typo'           => 'miniCartStrikeThroughTypo',
				'mini_cart_low_stock_message_typo'        => 'miniCartLowStockTypo',
				'mini_cart_enable_saving_price_message_typo' => 'miniCartSavingTextTypo',
			);

			foreach ( $typo_groups as $d4 => $d5 ) {
				$m[ $d4 . '_font_size' ]      = $d5 . '.decoration.font.font.*.size';
				$m[ $d4 . '_typograhy' ]      = $d5 . '.decoration.font.font.*';
				$m[ $d4 . '_line_height' ]    = $d5 . '.decoration.font.font.*.lineHeight';
				$m[ $d4 . '_letter_spacing' ] = $d5 . '.decoration.font.font.*.letterSpacing';
			}

			// --- Border groups ---------------------------------------------------
			$border_groups = array(
				'wfacp_form_mini_cart_coupon_border' => 'miniCartCouponInputFieldBorder',
				'mini_cart_border'                   => 'wfacp_mini_cart_border',
			);

			foreach ( $border_groups as $d4 => $d5 ) {
				$m[ $d4 . '_border_type' ]          = $d5 . '.decoration.border.*.styles.all.style';
				$m[ $d4 . '_border_color' ]         = $d5 . '.decoration.border.*.styles.all.color';
				$m[ $d4 . '_border_width_top' ]     = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_bottom' ]  = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_left' ]    = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_width_right' ]   = $d5 . '.decoration.border.*.styles.all.width';
				$m[ $d4 . '_border_radius_top' ]    = $d5 . '.decoration.border.*.radius.topLeft';
				$m[ $d4 . '_border_radius_bottom' ] = $d5 . '.decoration.border.*.radius.bottomRight';
				$m[ $d4 . '_border_radius_left' ]   = $d5 . '.decoration.border.*.radius.bottomLeft';
				$m[ $d4 . '_border_radius_right' ]  = $d5 . '.decoration.border.*.radius.topRight';
			}

			// Product image border radius.
			$m['mini_cart_item_border_radius_top']    = 'miniCartProductImageBorder.decoration.border.*.radius.topLeft';
			$m['mini_cart_item_border_radius_bottom'] = 'miniCartProductImageBorder.decoration.border.*.radius.bottomRight';
			$m['mini_cart_item_border_radius_left']   = 'miniCartProductImageBorder.decoration.border.*.radius.bottomLeft';
			$m['mini_cart_item_border_radius_right']  = 'miniCartProductImageBorder.decoration.border.*.radius.topRight';

			// Product image border color.
			$m['mini_cart_product_image_border_color'] = 'miniCartProductImageBorder.decoration.border.*.styles.all.color';

			// Divider border color.
			$m['mini_cart_divider_color'] = 'mini_cart_divider_color.decoration.border.*.styles.all.color';

			// --- Color fields ----------------------------------------------------
			$m['mini_cart_product_color']                 = 'miniCartProductTypo.decoration.font.font.*.color';
			$m['mini_cart_coupon_label_text_color']       = 'miniCartCouponLinkTypo.decoration.font.font.*.color';
			$m['wfacp_form_fields_label_color']           = 'miniCartCouponInputLabelTypo.decoration.font.font.*.color';
			$m['wfacp_form_mini_cart_coupon_input_color'] = 'miniCartCouponInputFieldTypo.decoration.font.font.*.color';
			$m['mini_cart_product_meta_color']            = 'miniCartProductMetaTypo.decoration.font.font.*.color';
			$m['mini_cart_total_color']                   = 'mini_cart_total_typo.decoration.font.font.*.color';
			$m['wfacp_form_mini_cart_coupon_focus_color'] = 'wfacpFormMiniCartCouponFocusColor.decoration.background.color.*.hex';

			// --- Content toggles & text fields -----------------------------------
			$simple_attrs = array(
				'enable_product_image'                  => 'enableProductImage.*',
				'enable_quantity_box'                   => 'enableQuantityBox.*',
				'enable_delete_item'                    => 'enableDeleteItem.*',
				'mini_cart_coupon_button_text'          => 'miniCartCouponButtonText.innerContent.*',
				'mini_cart_enable_strike_through_price' => 'miniCartEnableStrikeThroughPrice.*',
				'mini_cart_enable_low_stock_trigger'    => 'miniCartEnableLowStockTrigger.*',
				'mini_cart_low_stock_message'           => 'miniCartLowStockMessage.innerContent.*',
				'mini_cart_enable_saving_price_message' => 'miniCartEnableSavingPriceMessage.*',
				'mini_cart_saving_price_message'        => 'miniCartSavingPriceMessage.innerContent.*',
				'mini_cart_section_typo_alignment'      => 'miniCartHeading.decoration.font.font.*.textAlign',
			);

			foreach ( $simple_attrs as $d4 => $d5 ) {
				$m[ $d4 ] = $d5;
			}

			return $m;
		}

		/**
		 * Inject D4 shortcode defaults before conversion.
		 *
		 * D4 modules define field defaults in PHP (via add_border()), but these
		 * defaults are not stored in the shortcode markup. This method injects
		 * the missing D4 defaults so the standard conversion mappings can
		 * transform them into the correct D5 attribute paths.
		 *
		 * @param array  $attrs       D4 shortcode attributes.
		 * @param string $module_name D5 module name.
		 *
		 * @return array Attributes with D4 defaults merged in.
		 */
		public function inject_d4_shortcode_defaults( $attrs, $module_name ) {
			if ( 'wfacp/checkout-form' !== $module_name && 'wfacp/mini-cart' !== $module_name ) {
				return $attrs;
			}

			// D4 border groups: prefix => [ actual defaults from add_border() calls ].
			// Base add_border() defaults: border_type=solid, border_color=#dddddd, widths=1.
			// Groups that override border_type to 'none' are listed with that override.
			// Groups with non-standard border_color are listed with their actual color.
			$border_groups = 'wfacp/checkout-form' === $module_name
			? array(
				// Defaults to 'none' — only inject if user explicitly set a visible type.
				'form_border'                            => array(
					'border_type'  => 'none',
					'border_color' => '#dddddd',
				),
				'form_section_border'                    => array(
					'border_type'  => 'none',
					'border_color' => '#dddddd',
				),
				'form_heading_border'                    => array(
					'border_type'  => 'none',
					'border_color' => '#dddddd',
				),
				// Defaults to 'solid' — inject defaults even when type is not in shortcode.
				'wfacp_form_fields_border'               => array( 'border_color' => '#bfbfbf' ),
				'wfacp_button_border'                    => array( 'border_color' => '#dddddd' ),
				'wfacp_collapsible_border'               => array( 'border_color' => '#dddddd' ),
				'order_coupon_coupon_border'             => array( 'border_color' => '#bfbfbf' ),
				'product_switching_border'               => array( 'border_color' => '#dddddd' ),
				'product_switching_border_non_selected'  => array( 'border_color' => '#dddddd' ),
				'product_switching_best_value_border'    => array( 'border_color' => '#dddddd' ),
				'product_switching_what_included_border' => array( 'border_color' => '#dddddd' ),
			)
			: array(
				'wfacp_form_mini_cart_coupon_border' => array( 'border_color' => '#bfbfbf' ),
				'mini_cart_border'                   => array( 'border_color' => '#dddddd' ),
			);

			foreach ( $border_groups as $prefix => $overrides ) {
				$type_key     = $prefix . '_border_type';
				$default_type = $overrides['border_type'] ?? 'solid';
				$border_color = $overrides['border_color'] ?? '#dddddd';

				// Resolve effective border type: shortcode value or D4 default.
				$border_type = $attrs[ $type_key ] ?? $default_type;

				if ( 'none' === $border_type ) {
					continue;
				}

				// Inject missing D4 defaults for visible borders.
				$defaults = array(
					$prefix . '_border_color'        => $border_color,
					$prefix . '_border_width_top'    => '1px',
					$prefix . '_border_width_bottom' => '1px',
					$prefix . '_border_width_left'   => '1px',
					$prefix . '_border_width_right'  => '1px',
				);

				foreach ( $defaults as $key => $value ) {
					if ( ! isset( $attrs[ $key ] ) ) {
						$attrs[ $key ] = $value;
					}
				}
			}

			return $attrs;
		}
	}

	WFACP_DIVI::get_instance();
}
