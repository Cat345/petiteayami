<?php
if ( ! class_exists( 'WFACP_Divi_Extension' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Divi_Extension extends DiviExtension {

		/**
		 * The gettext domain for the extension's translations.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $gettext_domain = 'woofunnels-aero-checkout';
		public static $field_color_type = 'color';

		/**
		 * The extension's WP Plugin name.
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */
		public $name = 'woofunnels-aero-checkout';

		/**
		 * The extension's version
		 *
		 * @since 1.0.0
		 *
		 * @var string
		 */

		private $module_path = '';

		public $modules_instance = [];
		private $builder_setup_done = false;

		/**
		 * WFACP_Divi_Extension constructor.
		 *
		 * @param string $name
		 * @param array $args
		 */
		public function __construct( $name = 'woofunnels-aero-checkout', $args = array() ) {
			$this->version        = WFACP_VERSION;
			$this->plugin_dir     = WFACP_Core()->dir( 'builder/divi/' );
			$this->module_path    = WFACP_Core()->dir( 'builder/divi/modules/' );
			$this->plugin_dir_url = WFACP_Core()->url( '/builder/divi/' );

			parent::__construct( $name, $args );
		}

		protected function _enqueue_bundles() {
			// Divi 5 uses its own module system — D4 extension bundles not needed
			if ( function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled() ) {
				return;
			}

			if ( WFACP_Common::is_theme_builder() ) {
				$this->enqueue_module_js();
			} else {
				parent::_enqueue_bundles();
			}
		}

		public function wp_hook_enqueue_scripts() {
			if ( WFACP_Common::is_theme_builder() ) {
				parent::wp_hook_enqueue_scripts();
			}
		}

		private function enqueue_module_js() {
			$frontend_url = "{$this->plugin_dir_url}js/frontend.js";

			wp_enqueue_script( "{$this->name}-frontend-bundle", $frontend_url, $this->filter_registered_deps( $this->_bundle_dependencies['frontend'] ), $this->version, true );

			if ( et_core_is_fb_enabled() ) {

				// Builder Bundle
				$bundle_url = "{$this->plugin_dir_url}js/loader.min.js";
				wp_enqueue_script( "{$this->name}-builder-bundle", $bundle_url, $this->filter_registered_deps( isset( $this->_bundle_dependencies['builder'] ) ? $this->_bundle_dependencies['builder'] : array() ), $this->version, true );
			}
		}

		/**
		 * Keep only dependency handles that WordPress has actually registered.
		 *
		 * Divi core's DiviExtension base class seeds `divi-custom-script` (the theme's
		 * front-end custom.js) into the inherited `$this->_bundle_dependencies['frontend']`
		 * array. That handle is only registered on real front-end views — in the checkout
		 * editor context (theme builder / visual builder) Divi never registers it, so
		 * WP 6.9.1+'s `WP_Scripts::add` validation raises an "enqueued with dependencies
		 * that are not registered" notice. Filtering the deps at the enqueue point drops
		 * the unregistered handle without mutating the inherited property, so the real
		 * front-end path (parent::_enqueue_bundles) keeps `divi-custom-script` intact.
		 *
		 * @since 3.22.2
		 *
		 * @param array $deps Dependency handles to filter (non-array coerced to empty).
		 *
		 * @return array Re-indexed list of handles that are currently registered.
		 */
		private function filter_registered_deps( $deps ) {
			if ( ! is_array( $deps ) ) {
				return array();
			}

			return array_values( array_filter( $deps, static function ( $handle ) {
				return wp_script_is( $handle, 'registered' );
			} ) );
		}


		private function get_modules() {
			$modules = [
				'checkout_form' => [
					'name' => __( 'Checkout Form', 'woofunnels-aero-checkout' ),
					'path' => $this->module_path . 'class-divi-form.php',
				],
				'mini_cart'     => [
					'name' => __( 'Mini Cart', 'woofunnels-aero-checkout' ),
					'path' => $this->module_path . 'class-divi-mini-cart.php',
				]
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

		private function setup_builder_module() {
			if ( true == $this->builder_setup_done ) {
				return;
			}
			$response = WFACP_Common::check_builder_status( 'divi' );
			if ( isset( $response['version'] ) && version_compare( $response['version'], '4.10.0', '>' ) ) {
				self::$field_color_type = 'color-alpha';
			}

			$modules = $this->get_modules();
			if ( ! empty( $modules ) ) {
				include_once WFACP_Core()->dir( 'builder/divi/class-abstract-wfacp-fields.php' );
				include_once WFACP_Core()->dir( 'builder/divi/class-wfacp-html-block-divi.php' );
				foreach ( $modules as $key => $module ) {
					if ( ! file_exists( $module['path'] ) ) {
						continue;
					}
					$this->modules_instance[ $key ] = include_once $module['path'];
				}
				remove_action( 'et_builder_modules_loaded', array( $this, 'hook_et_builder_modules_loaded' ) );
				remove_action( 'et_builder_ready', array( $this, 'hook_et_builder_ready' ), 9 );
				$this->builder_setup_done = true;
			}
		}
	}

	new WFACP_Divi_Extension;
}