<?php

namespace WfocuFunnelKit;

use WFOCU_Common;
use WFFN_Thank_You_WC_Pages;
if ( ! class_exists( '\WfocuFunnelKit\Bricks_Integration' ) ) {
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
		 * @var array
		 */
		private static $load_elements = [];

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
		 * @param int $id The ID of the local variable.
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
			if ( ! defined( 'WFOCU_BRICKS_INTEGRATION_DIR' ) ) {
				define( 'WFOCU_BRICKS_INTEGRATION_DIR', __DIR__ . '/' );
			}
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
			include_once WFOCU_BRICKS_INTEGRATION_DIR . 'class-wfocu-template-group-bricks.php';

			add_action( 'wp', array( $this, 'wp_register_elements' ), 8 );
			add_action( 'wp_ajax_bricks_save_post', array( $this, 'wp_register_elements' ), - 1 );
			add_action( 'rest_api_init', array( $this, 'rest_register_elements' ), 9 );
			add_action( 'wffn_import_template_background', array( $this, 'rest_register_elements' ), 9 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			add_filter( 'bricks/element/render', array( $this, 'maybe_setup_template' ), 10 );
			add_filter( 'bricks/frontend/render_data', array( $this, 'maybe_render_shortcodes' ), 10, 2 );
			add_filter( 'bricks/builder/i18n', array( $this, 'i18n_strings' ) );

			/**
			 * modify funnel builder register post type args         */
			add_filter( 'wfocu_funnel_post_type_args', array( $this, 'wffn_modify_register_post_type_args' ) );
			add_filter( 'wfocu_offer_post_type_args', array( $this, 'wffn_modify_register_post_type_args' ) );
			add_action( 'wp', array( $this, 'allow_theme_css_on_upsell' ), 11 );
			add_action( 'theme_templates', array( $this, 'maybe_remove_templates' ), 10, 4 );

			add_action( 'wp_loaded', array( $this, 'load_bricks_importer' ) );
			add_action( 'wffn_import_completed', array( $this, 'setup_default_template' ), 10, 3 );
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
				'wfocu_offer',
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

			if ( class_exists( 'WFOCU_Common' ) && WFOCU_Common::get_offer_post_type_slug() === get_post_type( $post_id ) ) {
				$this->register_elements( 'one-click-upsells' );
			}
		}

		/**
		 * Registers the elements for the Funnel Builder Bricks Integration plugin.
		 *
		 * This method registers the elements for the specified Funnel Builder Bricks Integration modules,
		 * one-click-upsells.
		 */
		public function rest_register_elements() {
			$this->register_elements( 'one-click-upsells' );
		}

		/**
		 * Checks if the current request is made by the Bricks builder and sets up the template accordingly.
		 *
		 * @param bool $render The current render status.
		 *
		 * @return bool The updated render status.
		 */
		public function maybe_setup_template( $render ) {
			if ( bricks_is_builder_call() ) {
				$post_id = isset( \Bricks\Database::$page_data['original_post_id'] ) ? \Bricks\Database::$page_data['original_post_id'] : \Bricks\Database::$page_data['preview_or_post_id'];

				if ( class_exists( 'WFOCU_Common' ) && WFOCU_Common::get_offer_post_type_slug() === get_post_type( $post_id ) ) {
					WFOCU_Core()->template_loader->is_single = true;
					WFOCU_Core()->template_loader->setup_complete_offer_setup_manual( $post_id );
				}
			}

			return $render;
		}

		/**
		 * Checks if there are any shortcodes in the content and renders them if present.
		 *
		 * @param string $content The content to check for shortcodes.
		 * @param \WP_Post $post The post object.
		 *
		 * @return string The modified content with the rendered shortcodes.
		 */
		public function maybe_render_shortcodes( $content, $post ) {
			$shortcodes = array();

			if ( ! empty( $post ) && $post instanceof \WP_Post && WFOCU_Common::get_offer_post_type_slug() === $post->post_type ) {
				$shortcodes = array(
					'wfocu_order_data',
					'wfocu_current_time',
					'wfocu_current_date',
					'wfocu_today',
					'wfocu_current_day',
					'wfocu_countdown_timer',
					'wfocu_order_meta',
					'wfocu_product_offer_price',
					'wfocu_product_sale_price',
					'wfocu_product_regular_price',
					'wfocu_product_price_full',
					'wfocu_product_regular_price_raw',
					'wfocu_product_offer_price_raw',
					'wfocu_product_sale_price_raw',
					'wfocu_product_save_value',
					'wfocu_product_save_percentage',
					'wfocu_product_savings',
					'wfocu_product_single_unit_price',
					'wfocu_product_original_sale_price',
					'wfocu_product_original_sale_price_raw',
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

			include_once WFOCU_BRICKS_INTEGRATION_DIR . 'class-element.php';

			foreach ( glob( WFOCU_BRICKS_INTEGRATION_DIR . 'elements/' . $type . '/class-*.php' ) as $filename ) {
				if ( ! in_array( $filename, self::$load_elements, true ) ) {
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
			$i18n['funnelkit'] = esc_html__( 'FunnelKit' );

			return $i18n;
		}

		/**
		 * Enqueues the necessary scripts and styles for the Bricks Integration class.
		 */
		public function enqueue_scripts() {
			if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
				wp_enqueue_script( 'funnelkit-bricks-integration-pro-scripts', plugin_dir_url( WFOCU_BRICKS_INTEGRATION_DIR ) . '/assets/js/scripts.js', WFOCU_VERSION, true );

				// TODO: Review and create separate elements css file for Bricks.
				wp_enqueue_style( 'wfocu-icons', WFOCU_PLUGIN_URL . '/admin/assets/css/wfocu-font.css', null, WFOCU_VERSION );
			}

			wp_add_inline_style( 'bricks-frontend', '.bricks-button{letter-spacing:normal}' );
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

		public function allow_theme_css_on_upsell() {
			if ( function_exists( 'WFOCU_Core' ) && 'bricks' === get_template() ) {
				remove_action( 'wp_enqueue_scripts', array( WFOCU_Core()->assets, 'wfocu_remove_conflicted_themes_styles' ), 9999 );
				add_filter( 'wfocu_allow_externals_on_customizer', '__return_true' );
			}
		}

		public function maybe_remove_templates( $post_templates, $theme, $post, $post_type ) {
			if ( 'wfocu_offer' === $post_type ) {
				remove_filter( "theme_{$post_type}_templates", array( \WFOCU_Core()->template_loader, 'add_upstroke_page_templates' ), 99 );
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
				include_once WFOCU_BRICKS_INTEGRATION_DIR . 'class-wffn-bricks-importer.php';
			}
		}

		public function setup_default_template( $module_id, $step, $builder ) {
			if ( $builder === 'bricks' ) {
				update_post_meta( $module_id, '_wp_page_template', 'default' );

			}

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