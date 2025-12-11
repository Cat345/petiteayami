<?php
if ( ! class_exists( 'WFOCU_Divi_Extension' ) ) {
	class WFOCU_Divi_Extension extends DiviExtension {

		/**
		 * The gettext domain for the extension's translations.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $gettext_domain = 'wfacp-woofunnels-aero-divi';
		public static $field_color_type = 'color';

		/**
		 * The extension's WP Plugin name.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $name = 'woofunnels-upstroke-divi';

		/**
		 * The extension's version
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $version = '1.0.0';

		private $module_path = '';

		public $modules_instance = [];
		private $builder_setup_done = false;

		/**
		 * WFACP_Divi_Extension constructor.
		 *
		 * @param string $name
		 * @param array $args
		 */
		public function __construct( $name = 'woofunnels-upstroke-divi', $args = array() ) {
			$this->plugin_dir     = plugin_dir_path( __FILE__ );
			$this->module_path    = $this->plugin_dir . 'modules/';
			$this->plugin_dir_url = plugin_dir_url( __FILE__ );
			parent::__construct( $name, $args );
			add_filter( 'et_theme_builder_template_layouts', [ $this, 'disable_header_footer' ], 99 );

		}

		protected function _enqueue_bundles() {
			$this->enqueue_module_js();

		}

		public function wp_hook_enqueue_scripts() {
			parent::wp_hook_enqueue_scripts();
			wp_dequeue_style( 'woofunnels-upstroke-divi-styles' );
		}

		private function enqueue_module_js() {
			// Frontend Bundle
			global $post;
			if ( ! is_null( $post ) && $post->post_type === 'wfocu_offer' ) {
				wp_enqueue_style( "{$this->name}-wfocu-divi", "{$this->plugin_dir_url}css/divi.css", [], $this->version );
			}
			if ( et_core_is_fb_enabled() ) {
				wp_enqueue_script( "{$this->name}-builder-bundle", "{$this->plugin_dir_url}scripts/loader.min.js", [ 'react-dom' ], $this->version, true );
			}
		}


		private function get_modules() {
			$modules = [
				'accept_button'      => [
					'name' => __( 'WF Accept Button', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'accept-button.php',
				],
				'reject_button'      => [
					'name' => __( 'WF Reject Button', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'reject-button.php',
				],
				'accept_link'        => [
					'name' => __( 'WF Accept Link', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'accept-link.php',
				],
				'reject_link'        => [
					'name' => __( 'WF Reject Link', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'reject-link.php',
				],
				'product_title'      => [
					'name' => __( 'WF Product Title', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-title.php',
				],
				'product_images'     => [
					'name' => __( 'WF Product Images', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-images.php',
				],
				'product_short_desc' => [
					'name' => __( 'WF Product Short Description', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'product-short-desc.php',
				],
				'variation_selector' => [
					'name' => __( 'WF Variation Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'variation-selector.php',
				],
				'qty_selector'       => [
					'name' => __( 'WF Quantity Selector', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'qty-selector.php',
				],
				'offer_price'        => [
					'name' => __( 'WF Offer Price', 'woofunnels-upstroke-one-click-upsell' ),
					'path' => $this->module_path . 'offer-price.php',
				],

			];

			return apply_filters( 'wfacp_divi_modules', $modules, $this );
		}

		// This function run upto divi builder 4.9.*
		public function hook_et_builder_modules_loaded() {
			$this->setup_builder_module();
		}

		/**
		 * THis function run From Divi 4.10.0
		 */
		public function hook_et_builder_ready() {
			$this->setup_builder_module();
		}

		public function setup_builder_module() {
			if ( true == $this->builder_setup_done ) {
				return;
			}
			$modules  = $this->get_modules();
			$response = WFOCU_Common::check_builder_status( 'divi' );
			if ( isset( $response['version'] ) && version_compare( $response['version'], '4.10.0', '>' ) ) {
				self::$field_color_type = 'color-alpha';
			}

			if ( ! empty( $modules ) ) {
				include_once __DIR__ . '/class-abstract-wfocu-fields.php';
				include_once __DIR__ . '/class-wfocu-html-block-divi.php';
				foreach ( $modules as $key => $module ) {
					if ( ! file_exists( $module['path'] ) ) {
						continue;
					}
					$this->modules_instance[ $key ] = include_once $module['path'];
					$this->modules_instance[ $key ]->set_name( $module['name'] );
					remove_action( 'et_builder_modules_loaded', array( $this, 'hook_et_builder_modules_loaded' ) );
					remove_action( 'et_builder_ready', array( $this, 'hook_et_builder_ready' ), 9 );
					$this->builder_setup_done = true;
				}
			}
		}

		public function disable_header_footer( $layouts ) {
			if ( ! isset( $_GET['et_fb'] ) || ! defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' ) ) {
				return $layouts;
			}

			global $post;
			if ( is_null( $post ) || $post->post_type !== WFOCU_Common::get_offer_post_type_slug() ) {
				return $layouts;
			}

			$my_template = get_post_meta( $post->ID, '_wp_page_template', true );
			if ( ( 'wfocu-boxed.php' === $my_template || 'wfocu-canvas.php' === $my_template ) && isset( $layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ] ) ) {
				$layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['id']       = 0;
				$layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['enabled']  = false;
				$layouts[ ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE ]['override'] = false;
				$layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['id']       = 0;
				$layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['enabled']  = false;
				$layouts[ ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE ]['override'] = false;
			}

			return $layouts;
		}


	}

	new WFOCU_Divi_Extension;
}