<?php

//phpcs:ignore WordPress.WP.TimezoneChange.DeprecatedSniff

defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Optin_Pages_Divi' ) ) {
	/**
	 * Class WFFN_Pro_Optin_Pages_Divi
	 */
	#[AllowDynamicProperties]
	class WFFN_Pro_Optin_Pages_Divi {

		private static $ins = null;
		public $plugin_dir = '';
		public $module_path = '';
		public $plugin_dir_url = '';
		public $version = '1.0.0';

		/**
		 * WFFN_Pro_Optin_Pages_Divi constructor.
		 */
		public function __construct() {
			$this->plugin_dir     = plugin_dir_path( __FILE__ );
			$this->module_path    = $this->plugin_dir . 'modules/';
			$this->plugin_dir_url = plugin_dir_url( __FILE__ );

			add_filter( 'wffn_op_divi_modules', [ $this, 'register_widget' ] );
			add_action( 'wfop_divi_module_js', [ $this, 'enqueue_module_js' ] );
		}

		/**
		 * @return WFFN_Pro_Optin_Pages_Divi|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function register_widget( $args ) {
			$args['optin_form_popup'] = array(
				'name' => __( 'WF Optin Form Popup', 'funnel-builder-powerpack' ),
				'path' => $this->module_path . 'optin-form-popup.php',
			);

			return $args;
		}

		public function enqueue_module_js() {
			// Frontend Bundle
			if ( ! WFOPP_Core()->optin_pages->is_wfop_page() ) {
				return;
			}
			wp_enqueue_style( "woofunnels-op-divi-popup-wfop-divi", "{$this->plugin_dir_url}css/divi.css", [], $this->version );
			if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
				wp_enqueue_script( "woofunnels-op-divi-popup-builder-bundle", "{$this->plugin_dir_url}scripts/loader.min.js", [ 'react-dom' ], $this->version, true );
			}
		}

	}

	WFFN_Pro_Optin_Pages_Divi::get_instance();

}