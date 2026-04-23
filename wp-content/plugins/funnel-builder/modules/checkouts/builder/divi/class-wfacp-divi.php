<?php
if ( ! class_exists( 'WFACP_DIVI' ) ) {
	class WFACP_DIVI {
		private static $ins           = null;
		private static $front_locals  = array();
		private $set_our_page_content = '';

		private function __construct() {
			add_action( 'after_setup_theme', array( $this, 'init' ), 5 );

			add_action( 'wfacp_register_template_types', array( $this, 'register_template_type' ), 12 );
			add_filter( 'wfacp_register_templates', array( $this, 'register_templates' ) );
			add_filter( 'wfacp_template_edit_link', array( $this, 'add_template_edit_link' ), 10, 2 );
			add_action( 'woocommerce_checkout_terms_and_conditions', array( $this, 'remove_the_content_filter' ) );

			// Prevent header/footer for checkout pages in Divi 5
			add_filter( 'et_builder_add_outer_content_wrap', array( $this, 'maybe_filter' ), 999 );
		}

		public function init() {
			if ( ! ( class_exists( 'ET_Builder_Plugin' ) || function_exists( 'et_setup_theme' ) ) ) {
				return;
			}
			$this->load_divi5_modules();
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
			if ( isset( $_REQUEST['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder detection parameter
				$status = true;
			}
			// when ajax running for form html
			if ( isset( $_REQUEST['wc-ajax'] ) && 'wfacp_get_divi_data' == $_REQUEST['wc-ajax'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- AJAX request detection for Divi builder
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
				'title'   => __( 'Divi', 'funnel-builder' ),
				'filters' => WFACP_Common::get_template_filter(),
			);

			$loader->register_template_type( $template );
		}

		public function register_templates( $designs ) {

			$templates       = WooFunnels_Dashboard::get_all_templates();
			$designs['divi'] = ( isset( $templates['wc_checkout'] ) && isset( $templates['wc_checkout']['divi'] ) ) ? $templates['wc_checkout']['divi'] : array();

			if ( is_array( $designs['divi'] ) && count( $designs['divi'] ) > 0 ) {
				$is_divi5      = $this->is_divi5_active();
				$template_path = $is_divi5
					? WFACP_Core()->dir( 'builder/divi-5/class-wfacp-template-divi5.php' )
					: WFACP_BUILDER_DIR . '/divi/template/template.php';

				foreach ( $designs['divi'] as $key => $val ) {
					$val['path']             = $template_path;
					$designs['divi'][ $key ] = $val;
				}
			}

			return $designs;
		}


		public function initialize_divi_widgets( $post_id ) {

			$design = WFACP_Common::get_page_design( $post_id );
			if ( ! in_array( $design['selected_type'], array( 'divi', 'divi5' ), true ) ) {
				return;
			}

			// The maybe_filter() method handles outer content wrap for both Divi 4 and Divi 5
			// It's already added in the constructor

			if ( ! isset( $_REQUEST['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder detection parameter
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
			$is_bfb_new_page = isset( $_GET['is_new_page'] ) && '1' === $_GET['is_new_page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Divi builder parameter detection

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
				// Always load parent class first (required for Divi5 template)
				$divi_template_path = WFACP_Core()->dir( 'builder/divi/class-wfacp-divi-template.php' );
				if ( file_exists( $divi_template_path ) && ! class_exists( 'WFACP_Divi_Template' ) ) {
					include_once $divi_template_path;
				}

				$is_divi5 = $this->is_divi5_active();
				if ( $is_divi5 ) {
					$divi5_template_path = WFACP_Core()->dir( 'builder/divi-5/class-wfacp-template-divi5.php' );
					if ( file_exists( $divi5_template_path ) ) {
						include_once $divi5_template_path;
					} else {
						include_once $divi_template_path;
					}
				} else {
					include_once $divi_template_path;
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
				'button_text' => __( 'Edit', 'funnel-builder' ),
			);

			return $links;
		}


		public function init_extension() {

			if ( wp_doing_ajax() ) {

				if ( isset( $_REQUEST['action'] ) && 'et_fb_get_saved_templates' == $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && WFACP_Common::get_post_type_slug() !== $_REQUEST['et_post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder AJAX action detection
					return;
				}

				if ( isset( $_REQUEST['action'] ) && 'et_fb_update_builder_assets' == $_REQUEST['action'] && isset( $_REQUEST['et_post_type'] ) && WFACP_Common::get_post_type_slug() !== $_REQUEST['et_post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder AJAX action detection
					return;
				}

				$post_id = 0;
				if ( isset( $_REQUEST['action'] ) && 'heartbeat' == $_REQUEST['action'] && isset( $_REQUEST['data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- WordPress heartbeat AJAX action
					if ( isset( $_REQUEST['data']['et'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder heartbeat data
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder heartbeat data, sanitized with absint below
						$post_id = isset( $_REQUEST['data']['et']['post_id'] ) ? absint( wp_unslash( $_REQUEST['data']['et']['post_id'] ) ) : 0;

					}
				}

				if ( isset( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder AJAX parameter
					$post_id = absint( wp_unslash( $_REQUEST['post_id'] ) );// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash , WordPress.Security.NonceVerification.Recommended  , FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				}
				if ( isset( $_REQUEST['et_post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder AJAX parameter
					$post_id = absint( wp_unslash( $_REQUEST['et_post_id'] ) );// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash , WordPress.Security.NonceVerification.Recommended  , FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				}
				if ( $post_id > 0 ) {// phpcs:ignore FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
					$post = get_post( $post_id );
					if ( is_null( $post ) || $post->post_type !== WFACP_Common::get_post_type_slug() ) {
						return;
					}
				}
			}

			if ( isset( $_REQUEST['et_fb'] ) && ! isset( $_REQUEST['et_wfacp_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Divi builder detection parameters
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

			if ( ! is_null( $post ) ) {
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
		}

		public function remove_the_content_filter() {
			if ( defined( 'BRICKS_VERSION' ) ) {
				// If Bricks is active, we don`t need to remove the filter that changes the global post variable.
				return;
			}
			remove_filter( 'the_content', array( $this, 'replace_divi_our_page_content' ), 1 );
		}

		/**
		 * Load Divi 5 modules early (before dependency tree hook fires).
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function load_divi5_modules() {
			if ( defined( 'WFACP_DIVI5_MODULES_LOADED' ) ) {
				return;
			}

			$is_divi5 = $this->is_divi5_active();

			if ( $is_divi5 ) {
				// Global — needed for D4→D5 conversion on ANY page.
				add_filter( 'divi.moduleLibrary.conversion.moduleConversionOutline', array( $this, 'extend_checkout_form_conversion_outline' ), 10, 2 );
				add_filter( 'divi.conversion.shortcodeDefaults', array( $this, 'inject_d4_shortcode_defaults' ), 10, 2 );

				// Hide legacy D4 modules from VB picker on all pages.
				add_action( 'divi_visual_builder_before_get_shortcode_module_definitions', array( $this, 'hide_legacy_d4_modules_from_vb' ), 1 );

				// Defer D5 module registration to init:10 behind post-type guard.
				// At plugins_loaded there's no post context; wfacp_checkout registers at init:5.
				add_action( 'init', array( $this, 'maybe_load_d5_modules' ), 10 );
			}
		}

		/**
		 * Conditionally load D5 modules if the current page is a checkout page.
		 */
		public function maybe_load_d5_modules() {
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
			if ( isset( $_GET['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Divi builder detection parameter
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
		private function is_matching_post_type() {
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
			$post_id = 0;
			foreach ( array( 'et_wfacp_id', 'edit', 'post', 'editor_post_id', 'et_post_id', 'postId', 'post_id' ) as $key ) {
				if ( ! empty( $_REQUEST[ $key ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Post-type detection only, no data mutation
					$post_id = absint( $_REQUEST[ $key ] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
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

			// 4. Fallback: match checkout rewrite slug in URL path.
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
		 * Remove legacy D4 module definition callbacks from the VB module picker.
		 *
		 * When Divi 5 is active these D4 shortcode modules must NOT appear in the
		 * Visual Builder inserter because the D5 block-based modules replace them.
		 * Removing the callback from the hook prevents D4 definitions from being
		 * sent to the VB while keeping the shortcode handlers registered so that
		 * unconverted (D4) pages still render correctly on the frontend.
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
		 * @since 1.0.0
		 * @param array $map Block name to FQCN class map.
		 * @return array Modified map with WFACP blocks added.
		 */
		public function register_block_parser_class_map( $map ) {
			$modules_dir = WFACP_Core()->dir( 'builder/divi-5/modules/' );

			$checkout_form_path = $modules_dir . 'CheckoutForm/CheckoutForm.php';
			if ( file_exists( $checkout_form_path ) ) {
				require_once $checkout_form_path;
				$map['wfacp/checkout-form'] = 'WFACP\\Modules\\CheckoutForm\\CheckoutForm';
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

			echo '<style id="wfacp-d5-border-fix">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS only.
		}

		/**
		 * Check if Divi 5 is active using multiple detection methods.
		 *
		 * @since 1.0.0
		 * @return bool True if Divi 5 is active, false otherwise.
		 */
		private function is_divi5_active() {
			return WFFN_Common::is_divi5_active();
		}

		/**
		 * Enqueue Divi 5 Visual Builder Assets.
		 *
		 * @since 1.0.0
		 */
		public function enqueue_visual_builder_assets() {
			if ( ! function_exists( 'et_builder_d5_enabled' ) || ! et_builder_d5_enabled() ) {
				return;
			}

			if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
				return;
			}

			$plugin_dir_url  = WFACP_Core()->url( '/builder/divi-5/visual-builder/build/wfacp-divi5-visual-builder.js' );
			$plugin_dir_path = WFACP_Core()->dir( 'builder/divi-5/visual-builder/build/wfacp-divi5-visual-builder.js' );

			// Wrap getGroupPresetDefaultAttr BEFORE module-library calls it.
			// Strips divi/font entries from checkout module preset lookup while
			// keeping decoration.font intact for style generation.
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
				} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				} catch ( \Error $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				}
			}

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
				} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Silent fail
					// Silent fail - Visual Builder asset registration error
				} catch ( \Error $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Silent fail
					// Silent fail - Visual Builder asset registration error
				}
			}
		}

		/**
		 * Filter to prevent header/footer for checkout pages.
		 *
		 * @param bool $add_outer_wrap Whether to add outer content wrap.
		 * @return bool True to prevent header/footer, false otherwise.
		 */
		public function maybe_filter( $add_outer_wrap ) {
			global $post;

			if ( is_object( $post ) && $post instanceof WP_Post && $post->post_type === 'wfacp_checkout' ) {
				return true;
			}

			return $add_outer_wrap;
		}

		/**
		 * Extend the conversion outline for wfacp/checkout-form for D4→D5 re-conversion.
		 *
		 * @param array  $conversion_outline Current outline.
		 * @param string $module_name        Block name being converted.
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

			// 1. Dynamic form field width/class attributes
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

			// 2. D4 style sub-attributes
			$style_mappings = $this->get_d4_checkout_style_mappings();
			foreach ( $style_mappings as $d4_attr => $d5_path ) {
				if ( ! isset( $conversion_outline['module'][ $d4_attr ] ) ) {
					$conversion_outline['module'][ $d4_attr ] = $d5_path;
				}
			}

			// 3. Value expansion functions for D4 "_typograhy" (typo) attributes
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
		 * Collect checkout field data for dynamic width attribute mappings.
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
				if ( ! empty( $_REQUEST[ $key ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Post-type detection only
					$current_post_id = absint( $_REQUEST[ $key ] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
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
		 * Get D4 style attribute → D5 path mappings for border sub-fields.
		 *
		 * @return array D4 attribute name → D5 conversion path.
		 */
		private function get_d4_checkout_style_mappings() {
			$m = array();

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
		 * Inject D4 shortcode defaults before conversion.
		 *
		 * @param array  $attrs       D4 shortcode attributes.
		 * @param string $module_name D5 module name.
		 * @return array Attributes with D4 defaults merged in.
		 */
		public function inject_d4_shortcode_defaults( $attrs, $module_name ) {
			if ( 'wfacp/checkout-form' !== $module_name ) {
				return $attrs;
			}

			$border_groups = array(
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
				'wfacp_form_fields_border'               => array( 'border_color' => '#bfbfbf' ),
				'wfacp_button_border'                    => array( 'border_color' => '#dddddd' ),
				'wfacp_collapsible_border'               => array( 'border_color' => '#dddddd' ),
				'order_coupon_coupon_border'             => array( 'border_color' => '#bfbfbf' ),
				'product_switching_border'               => array( 'border_color' => '#dddddd' ),
				'product_switching_border_non_selected'  => array( 'border_color' => '#dddddd' ),
				'product_switching_best_value_border'    => array( 'border_color' => '#dddddd' ),
				'product_switching_what_included_border' => array( 'border_color' => '#dddddd' ),
			);

			foreach ( $border_groups as $prefix => $overrides ) {
				$type_key     = $prefix . '_border_type';
				$default_type = isset( $overrides['border_type'] ) ? $overrides['border_type'] : 'solid';
				$border_color = isset( $overrides['border_color'] ) ? $overrides['border_color'] : '#dddddd';

				$border_type = isset( $attrs[ $type_key ] ) ? $attrs[ $type_key ] : $default_type;

				if ( 'none' === $border_type ) {
					continue;
				}

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
