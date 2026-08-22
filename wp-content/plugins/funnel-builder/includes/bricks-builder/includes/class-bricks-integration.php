<?php

namespace FunnelKit;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly

use WFACP_Common;
use WFFN_Thank_You_WC_Pages;

if ( ! class_exists( '\FunnelKit\Bricks_Integration' ) ) {
	#[\AllowDynamicProperties]
	final class Bricks_Integration {
		/**
		 * Indicates whether the integration is registered or not.
		 *
		 * @var bool
		 */
		protected $is_registered = false;

		private static $front_locals = array();

		/**
		 * Singleton instance of the class
		 *
		 * @var Bricks_Integration|null
		 */
		private static $instance = null;

		/**
		 * contain all loaded elements
		 *
		 * @var array
		 */
		private static $load_elements = array();


		private $post_id = 0;

		/**
		 * Private constructor to prevent direct instantiation.
		 */
		private function __construct() {
			$this->define_constants();
			add_action( 'after_setup_theme', array( $this, 'init' ) );
			add_filter( 'option_bricks_global_settings', array( $this, 'setup_supported_post_types' ) );
		}

		/**
		 * Returns an instance of the Bricks_Integration class.
		 *
		 * This method follows the singleton design pattern to ensure that only one instance of the class is created.
		 *
		 * @return Bricks_Integration An instance of the Bricks_Integration class.
		 */
		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Sets the local variable with the given name and ID.
		 *
		 * @param string $name The name of the local variable.
		 * @param int    $id The ID of the local variable.
		 *
		 * @return void
		 */
		public static function set_locals( $name, $id ) {
			self::$front_locals[ $name ] = $id;
		}

		/**
		 * Retrieves the local variables used in the class.
		 *
		 * @return array The local variables used in the class.
		 */
		public static function get_locals() {
			return self::$front_locals;
		}

		/**
		 * Checks the status of the builder.
		 *
		 * This function checks if the Bricks builder is installed and retrieves its version.
		 *
		 * @return array An array containing the builder status information:
		 *               - 'found' (bool): Whether the builder is found or not.
		 *               - 'error' (string): Any error message encountered during the check.
		 *               - 'is_old_version' (string): Whether the builder is an old version or not.
		 *               - 'version' (string): The version of the builder found.
		 */
		public static function check_builder_status() {
			$response = array(
				'found'          => false,
				'error'          => '',
				'is_old_version' => 'no',
				'version'        => '',
			);

			if ( defined( 'BRICKS_VERSION' ) ) {
				$response['found']   = true;
				$response['version'] = BRICKS_VERSION;
			}

			return $response;
		}

		/**
		 * Defines the constants used in the Bricks Integration class.
		 *
		 * This method is responsible for defining the constants used in the Bricks Integration class.
		 * It sets the version number, absolute path, and plugin basename constants.
		 *
		 * @access private
		 * @return void
		 */
		private function define_constants() {
			define( 'FUNNELKIT_BRICKS_INTEGRATION_ABSPATH', plugin_dir_path( WFFN_PLUGIN_FILE ) . 'includes/bricks-builder/' );
		}


		/**
		 * Initializes the Bricks Integration class.
		 *
		 * This method is responsible for initializing the Bricks Integration class by registering the elements.
		 *
		 * @return void
		 */
		public function init() {
			if ( ! defined( 'BRICKS_VERSION' ) ) {
				return;
			}

			if ( ! defined( 'WFFN_VERSION' ) ) {
				return;
			}

			add_action( 'wp', array( $this, 'early_register_elements' ), PHP_INT_MIN );

			add_action( 'wp', array( $this, 'wp_register_elements' ), 8 );

			add_action( 'wp_ajax_bricks_save_post', array( $this, 'wp_register_elements' ), - 1 );
			add_action( 'wp_ajax_bricks_regenerate_css_file', array( $this, 'register_elements_for_css_regeneration' ), - 1 );
			add_action( 'bricks_regenerate_css_files', array( $this, 'register_all_elements' ), - 1 );
			add_action( 'rest_api_init', array( $this, 'rest_register_elements' ), 9 );
			add_action( 'wffn_before_import_checkout_template', array( $this, 'rest_register_elements_on_import' ), 10, 2 );
			add_action( 'wffn_import_template_background', array( $this, 'rest_register_elements' ), 9 );
			add_action( 'wfacp_template_import', array( $this, 'on_checkout_import' ), 9, 2 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			add_filter( 'bricks/element/render', array( $this, 'maybe_setup_template' ), 10 );
			add_filter( 'bricks/frontend/render_data', array( $this, 'maybe_render_shortcodes' ), 10, 2 );
			add_filter( 'bricks/builder/i18n', array( $this, 'i18n_strings' ) );

			add_filter( 'wfacp_register_templates', array( $this, 'register_templates_checkout' ) );
			add_filter( 'wfacp_locate_template', array( $this, 'add_wfacp_template' ) );

			/**
			 * modify funnel builder register post type args
			 */
			add_filter( 'wffn_landing_post_type_args', array( $this, 'wffn_modify_register_post_type_args' ) );
			add_filter( 'wffn_optin_post_type_args', array( $this, 'wffn_modify_register_post_type_args' ) );
			add_filter( 'wffn_oty_post_type_args', array( $this, 'wffn_modify_register_post_type_args' ) );
			add_filter( 'wfacp_post_type_args', array( $this, 'wffn_modify_register_post_type_args' ) );
			add_filter( 'wffn_thank_you_post_type_args', array( $this, 'wffn_modify_register_post_type_args' ) );

			add_filter( 'wffn_allowed_themes', array( $this, 'allow_theme_css' ) );

			add_action( 'wp_head', array( $this, 'add_custom_css_for_optin_popup' ), 11 );
			add_action( 'theme_templates', array( $this, 'maybe_remove_templates' ), 10, 4 );

			add_action( 'wp_loaded', array( $this, 'load_bricks_importer' ) );
			add_action( 'wp_loaded', array( $this, 'load_wfacp_importer' ) );
			add_action( 'wffn_import_completed', array( $this, 'setup_default_template' ), 10, 3 );

			add_action(
				'wfacp_checkout_page_found',
				function ( $post_id ) {
					$this->post_id = $post_id;
					add_filter(
						'bricks/builder/data_post_id',
						function () {
							return $this->post_id;
						}
					);
				}
			);

			add_action( 'wfacp_template_removed', array( $this, 'delete_data' ) );
		}

		/**
		 * Sets up the supported post types for the Bricks integration.
		 *
		 * This function adds the specified post types to the 'postTypes' array in the $bricks_global_settings parameter.
		 * If the 'postTypes' array is empty, it initializes it as an empty array.
		 * It then checks each post type in the $post_types array and adds it to the 'postTypes' array if it is not already present.
		 *
		 * @param array $bricks_global_settings The global settings for the Bricks integration.
		 *
		 * @return array The updated $bricks_global_settings array with the added post types.
		 */
		public function setup_supported_post_types( $bricks_global_settings ) {
			$post_types = array(
				'wffn_landing',
				'wffn_ty',
				'wffn_optin',
				'wffn_oty',
				'wfacp_checkout',
			);

			if ( empty( $bricks_global_settings['postTypes'] ) ) {
				$bricks_global_settings['postTypes'] = array();
			}

			foreach ( $post_types as $post_type ) {
				if ( ! in_array( $post_type, $bricks_global_settings['postTypes'], true ) ) {
					$bricks_global_settings['postTypes'][] = $post_type;
				}
			}

			return $bricks_global_settings;
		}


		/**
		 * Conditionally registers FunnelKit's Bricks elements as early as possible on `wp`.
		 *
		 * Bricks 2.0 builds and statically caches its per-element permission list very early on the
		 * `wp` hook (Builder_Permissions::$sections is populated from the currently registered
		 * Bricks\Elements::$elements). If our elements are not registered by then, their
		 * edit_element_* keys are absent from the cached permission list and administrators get the
		 * intermittent "your builder capability doesn't allow you to access these settings" error in
		 * the builder. The original wp@8 pass is too late and gates on Bricks\Database::$page_data,
		 * which is not populated until wp@10 — so the post-type gate could resolve against post_id 0.
		 *
		 * We therefore run on `wp` at PHP_INT_MIN — the first `wp` callback, before the permission
		 * cache locks — but still GATED on the post type. The queried object is already resolved when
		 * `wp` fires, and the Bricks builder loads at the funnel post's own permalink, so the queried
		 * object IS the checkout / thank-you / optin post in the builder (where the permission error
		 * occurs). Registration is idempotent via self::$load_elements, so the heavier wp@8
		 * wp_register_elements() pass (which also sets up the checkout template and handles the
		 * default-checkout override on the front end) still runs unchanged.
		 *
		 * @return void
		 */
		public function early_register_elements() {
			if ( ! class_exists( 'Bricks\Element' ) ) {
				return;
			}

			$post_type = get_post_type( get_queried_object_id() );
			if ( empty( $post_type ) ) {
				return;
			}

			if ( class_exists( 'WFACP_Common' ) && WFACP_Common::get_post_type_slug() === $post_type ) {
				$this->register_elements( 'checkout' );
			}

			if ( ! is_null( WFFN_Core()->thank_you_pages ) && WFFN_Core()->thank_you_pages->get_post_type_slug() === $post_type ) {
				$this->register_elements( 'thankyou-pages' );
			}

			if ( WFOPP_Core()->optin_pages->get_post_type_slug() === $post_type ) {
				$this->register_elements( 'optin-pages' );
			}
		}

		/**
		 * Registers elements based on the post type.
		 *
		 * This method is responsible for registering elements based on the post type of the current page.
		 * It checks the post type of the page and registers elements accordingly for different post types.
		 *
		 * @return void
		 */
		public function wp_register_elements() {
			if ( ! class_exists( 'Bricks\Element' ) ) {
				return;
			}

			$post_id = isset( \Bricks\Database::$page_data['original_post_id'] ) ? \Bricks\Database::$page_data['original_post_id'] : \Bricks\Database::$page_data['preview_or_post_id'];

			if ( class_exists( 'WFACP_Common' ) && ( ( WFACP_Common::get_post_type_slug() === get_post_type( $post_id ) ) || ( WFACP_Common::get_post_type_slug() === get_post_type( $this->post_id ) ) ) ) {

				if ( 0 === did_action( 'wfacp_template_class_found' ) && ! is_null( WFACP_Core()->template_loader ) ) {
					WFACP_Core()->template_loader::$is_checkout = true;
					WFACP_Common::set_id( $post_id );
					WFACP_Core()->template_loader->maybe_setup_page();
				}

				$this->register_elements( 'checkout' );
			}

			if ( ! is_null( WFFN_Core()->thank_you_pages ) && WFFN_Core()->thank_you_pages->get_post_type_slug() === get_post_type( $post_id ) ) {
				$this->register_elements( 'thankyou-pages' );
			}

			if ( WFOPP_Core()->optin_pages->get_post_type_slug() === get_post_type( $post_id ) ) {
				$this->register_elements( 'optin-pages' );
			}
		}

		/**
		 * Register elements during Bricks CSS file regeneration (AJAX).
		 *
		 * When "Regenerate CSS files" is clicked in Bricks settings, it processes each post via
		 * wp_ajax_bricks_regenerate_css_file. The 'wp' hook doesn't fire during AJAX, so our
		 * elements aren't registered and their control CSS is not generated.
		 * This method checks the post type being regenerated and registers the appropriate elements.
		 *
		 * @return void
		 */
		public function register_elements_for_css_regeneration() {
			$post_id = isset( $_POST['data'] ) ? absint( wp_unslash( $_POST['data'] ) ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Missing , FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck

			if ( empty( $post_id ) ) {
				return;
			}

			$post_type = get_post_type( $post_id );

			if ( class_exists( 'WFACP_Common' ) && WFACP_Common::get_post_type_slug() === $post_type ) {
				if ( 0 === did_action( 'wfacp_template_class_found' ) && ! is_null( WFACP_Core()->template_loader ) ) {
					WFACP_Core()->template_loader::$is_checkout = true;
					WFACP_Common::set_id( $post_id );
					WFACP_Core()->template_loader->maybe_setup_page();
				}
				$this->register_elements( 'checkout' );
			}

			if ( ! is_null( WFFN_Core()->thank_you_pages ) && WFFN_Core()->thank_you_pages->get_post_type_slug() === $post_type ) {
				$this->register_elements( 'thankyou-pages' );
			}

			if ( WFOPP_Core()->optin_pages->get_post_type_slug() === $post_type ) {
				$this->register_elements( 'optin-pages' );
			}
		}

		/**
		 * Register all elements for bulk CSS regeneration (cron/CLI).
		 *
		 * When CSS files are regenerated in bulk (theme update cron, WP-CLI), all element types
		 * need to be registered upfront since multiple post types are processed.
		 *
		 * @return void
		 */
		public function register_all_elements() {
			if ( class_exists( 'WFACP_Common' ) ) {
				$this->register_elements( 'checkout' );
			}
			if ( wffn_is_wc_active() ) {
				$this->register_elements( 'thankyou-pages' );
			}
			$this->register_elements( 'optin-pages' );
		}

		/**
		 * Registers the elements for the Funnel Builder Bricks Integration plugin.
		 *
		 * This method registers the elements for the specified Funnel Builder Bricks Integration modules,
		 * such as thankyou-pages, optin-pages, and checkout.
		 */
		public function rest_register_elements() {
			if ( wffn_is_wc_active() ) {
				$this->register_elements( 'thankyou-pages' );
			}
			$this->register_elements( 'optin-pages' );
			add_filter(
				'rest_request_before_callbacks',
				function ( $response, $handler, $request ) {
					if ( $request->get_param( 'action' ) === 'bricks_render_element' ) {
						$this->register_elements( 'checkout' );
					}

					return $response;
				},
				10,
				3
			);
		}

		/**
		 * Registers the elements for the Funnel Builder Bricks Integration plugin.
		 *
		 * This method registers the elements for the specified Funnel Builder Bricks Integration modules,
		 * such as thankyou-pages, optin-pages, and checkout.
		 */
		public function on_checkout_import( $aero_id, $builder ) {

			if ( $builder === 'bricks' ) {
				WFACP_Core()->template_loader::$is_checkout = true;
				WFACP_Common::set_id( $aero_id );
				WFACP_Core()->template_loader->maybe_setup_page();
				$this->register_elements( 'checkout' );

			}
		}


		/**
		 * Registers the elements for the Funnel Builder Bricks Integration plugin.
		 *
		 * This method registers the elements for the specified Funnel Builder Bricks Integration modules,
		 * such as thankyou-pages, optin-pages, and checkout.
		 */
		public function rest_register_elements_on_import( $id, $builder ) {

			if ( ! class_exists( 'Bricks\Element' ) ) {
				return;
			}

			if ( $builder === 'bricks' && class_exists( 'WFACP_Common' ) && ( ( WFACP_Common::get_post_type_slug() === get_post_type( $id ) ) ) ) {

				if ( 0 === did_action( 'wfacp_template_class_found' ) ) {
					WFACP_Core()->template_loader::$is_checkout = true;
					WFACP_Common::set_id( $id );
					WFACP_Core()->template_loader->maybe_setup_page();
				}
				$this->register_elements( 'checkout' );
			}
		}

		/**
		 * Checks if the current request is made by the Bricks builder and sets up the template accordingly.
		 *
		 * @param bool $render The current render status.
		 *
		 * @return bool The updated render status.
		 */
		public function maybe_setup_template( $render ) {
			if ( bricks_is_builder_call() || bricks_is_builder() ) {
				add_filter( 'wfacp_is_theme_builder', '__return_true' );
			}

			if ( bricks_is_builder_call() ) {
				$post_id = isset( \Bricks\Database::$page_data['original_post_id'] ) ? \Bricks\Database::$page_data['original_post_id'] : \Bricks\Database::$page_data['preview_or_post_id'];

				if ( class_exists( 'WFACP_Common' ) && WFACP_Common::get_post_type_slug() === get_post_type( $post_id ) ) {
					WFACP_Common::set_id( $post_id );
					WFACP_Core()->template_loader->load_template( $post_id );
				}
			}

			return $render;
		}

		/**
		 * Checks if there are any shortcodes in the content and renders them if present.
		 *
		 * @param string   $content The content to check for shortcodes.
		 * @param \WP_Post $post The post object.
		 *
		 * @return string The modified content with the rendered shortcodes.
		 */
		public function maybe_render_shortcodes( $content, $post ) {
			$shortcodes = array();

			if ( ! empty( $post ) && $post instanceof \WP_Post && WFFN_Thank_You_WC_Pages::get_post_type_slug() === $post->post_type ) {
				$shortcodes = array(
					'wfty_customer_email',
					'wfty_customer_first_name',
					'wfty_customer_last_name',
					'wfty_customer_phone_number',
					'wfty_order_number',
					'wfty_order_total',
				);
			}

			foreach ( $shortcodes as $shortcode ) {
				if ( has_shortcode( $content, $shortcode ) ) {
					return do_shortcode( $content );
				}
			}

			return $content;
		}

		/**
		 * Registers elements of a specific type.
		 *
		 * This method iterates through the files in the specified directory and registers each element file.
		 *
		 * @param string $type The type of elements to register.
		 *
		 * @return void
		 */
		public function register_elements( $type ) {
			if ( ! class_exists( 'Bricks\Element' ) ) {
				return;
			}

			include_once FUNNELKIT_BRICKS_INTEGRATION_ABSPATH . 'includes/class-element.php';

			foreach ( glob( FUNNELKIT_BRICKS_INTEGRATION_ABSPATH . 'includes/elements/' . $type . '/class-*.php' ) as $filename ) {

				if ( ! in_array( $filename, self::$load_elements, true ) ) {

					if ( $filename === FUNNELKIT_BRICKS_INTEGRATION_ABSPATH . 'includes/elements/' . $type . '/class-optin-popup.php' && ! defined( 'WFFN_PRO_FILE' ) ) {
						continue;
					}
					if ( $filename === FUNNELKIT_BRICKS_INTEGRATION_ABSPATH . 'includes/elements/' . $type . '/class-order-summary.php' && false === wfacp_pro_dependency() ) {
						continue;
					}
					self::$load_elements[] = $filename;
					\Bricks\Elements::register_element( $filename );
				}
			}
		}

		/**
		 * Adds internationalization strings for FunnelKit plugin.
		 *
		 * @param array $i18n The array of internationalization strings.
		 *
		 * @return array The modified array of internationalization strings.
		 */
		public function i18n_strings( $i18n ) {
			$i18n['funnelkit'] = esc_html__( 'FunnelKit', 'funnel-builder' ); //phpcs:ignore WordPress.WP.I18n.TextDomainMismatch

			return $i18n;
		}

		/**
		 * Enqueues the necessary scripts and styles for the Bricks Integration class.
		 */
		public function enqueue_scripts() {
			if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {

				wp_enqueue_script( 'funnelkit-bricks-integration-scripts', plugin_dir_url( WFFN_PLUGIN_FILE ) . 'includes/bricks-builder/assets/js/scripts.js', WFFN_VERSION, true );//phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter

				if ( defined( 'WFTY_PLUGIN_FILE' ) ) {
					wp_enqueue_style( 'wffn_woo_thankyou_page', plugin_dir_url( WFTY_PLUGIN_FILE ) . 'assets/css/wffn-woo-thankyou-el-widgets.css', array(), WFFN_VERSION, 'all' );
				}

				if ( defined( 'WFACP_PLUGIN_URL' ) ) {
					wp_enqueue_style( 'wfacp-icons', WFACP_PLUGIN_URL . '/admin/assets/css/wfacp-font.css', null, WFACP_VERSION );

				}
			}

			wp_register_style( 'funnelkit-bricks-integration-wfacp', plugin_dir_url( WFFN_PLUGIN_FILE ) . 'includes/bricks-builder/assets/css/wfacp.css', array(), WFFN_VERSION, 'all' );
			wp_add_inline_style( 'bricks-frontend', '.bricks-button{letter-spacing:normal}' );
		}


		public function register_templates_checkout( $designs ) {
			$templates = \WooFunnels_Dashboard::get_all_templates();

			$designs['bricks'] = ( isset( $templates['wc_checkout'] ) && isset( $templates['wc_checkout']['bricks'] ) ) ? $templates['wc_checkout']['bricks'] : array();

			if ( is_array( $designs['bricks'] ) && count( $designs['bricks'] ) > 0 ) {
				foreach ( $designs['bricks'] as $key => $val ) {
					$val['path']               = WFACP_BUILDER_DIR . '/elementor/template/template.php';
					$designs['bricks'][ $key ] = $val;
				}
			}

			return $designs;
		}

		public function load_wfacp_importer() {
			if ( did_action( 'wfacp_loaded' ) ) {
				include_once __DIR__ . '/class-wfacp-bricks-importer.php';

			}
		}

		public function add_wfacp_template( $template_data ) {
			if ( $template_data === false ) {
				return array(
					'path'          => __DIR__ . '/checkout/template.php',
					'slug'          => 'brick_1',
					'name'          => 'bricks',
					'template_type' => 'bricks',
				);
			}

			return $template_data;
		}

		public function wffn_modify_register_post_type_args( $args ) {
			if ( ! is_array( $args ) ) {
				return $args;
			}

			if ( $args['exclude_from_search'] ) {
				$args['exclude_from_search'] = false;
			}

			return $args;
		}


		public function allow_theme_css( $args ) {
			global $post;
			if ( ( ! empty( $post ) && in_array(
				$post->post_type,
				array(
					'wffn_landing',
					'wffn_ty',
					'wffn_optin',
					'wffn_oty',

				),
				true
			) ) ) { // change here particular post type
				array_push( $args, 'bricks' );

				return $args;
			}

			return $args;
		}

		public function add_custom_css_for_optin_popup() {
			global $post;

			if ( true === bricks_is_builder_iframe() && ! is_null( $post ) && WFOPP_Core()->optin_pages->get_post_type_slug() === $post->post_type ) {
				?>
				<style>
					div.brxe-wffn-optin-popup {
						transform: none !important;
					}
				</style>
				<?php
			}
		}


		public function maybe_remove_templates( $post_templates, $theme, $post, $post_type ) {
			switch ( $post_type ) {
				case 'wffn_landing':
					remove_filter( "theme_{$post_type}_templates", array( \WFFN_Core()->landing_pages, 'registered_page_templates' ), 99 );
					break;
				case 'wffn_ty':
					remove_filter( "theme_{$post_type}_templates", array( \WFFN_Core()->thank_you_pages, 'registered_page_templates' ), 99 );
					break;
				case 'wffn_optin':
					remove_filter( "theme_{$post_type}_templates", array( \WFOPP_Core()->optin_pages, 'registered_page_templates' ), 99 );
					break;
				case 'wffn_oty':
					remove_filter( "theme_{$post_type}_templates", array( \WFOPP_Core()->optin_ty_pages, 'registered_page_templates' ), 99 );
					break;
				case 'wfacp_checkout':
					remove_filter( "theme_{$post_type}_templates", array( 'WFACP_Common', 'registered_page_templates' ), 9999 );
					break;
				case 'default':
					break;
			}
		}

		/**
		 * Loads the Bricks importer class.
		 *
		 * This method includes the class-wffn-bricks-importer.php file, which contains the implementation of the Bricks importer functionality.
		 *
		 * @since 1.0.0
		 */
		public function load_bricks_importer() {
			$response = self::check_builder_status();
			if ( true === $response['found'] && empty( $response['error'] ) ) {
				include_once FUNNELKIT_BRICKS_INTEGRATION_ABSPATH . 'includes/class-wffn-bricks-importer.php';
			}
		}

		public function setup_default_template( $module_id, $step, $builder ) {
			if ( $builder === 'bricks' ) {
				update_post_meta( $module_id, '_wp_page_template', 'default' );

			}
		}

		public function delete_data( $aero_id ) {
			delete_post_meta( $aero_id, BRICKS_DB_PAGE_CONTENT );
		}
	}

	/**
	 * Returns an instance of the Bricks Integration class.
	 *
	 * @return Bricks_Integration The instance of the Bricks Integration class.
	 */
	function bricks_integration() {
		return Bricks_Integration::get_instance();
	}

	// Calls the bricks_integration function.
	bricks_integration();
}
