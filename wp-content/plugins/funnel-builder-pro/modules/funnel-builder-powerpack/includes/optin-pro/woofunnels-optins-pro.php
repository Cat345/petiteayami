<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

if ( ! class_exists( 'WFOPP_PRO_Core' ) ) {

	/**
	 * Class WFOPP_PRO_Core
	 */
	#[AllowDynamicProperties]
	class WFOPP_PRO_Core {

		/**
		 * @var null
		 */
		public static $_instance = null;

		/**
		 * @var WFFN_Optin_Pages
		 */
		public $pro_optin_pages;

		/**
		 * @var array
		 */
		private static $_registered_entity = array(
			'active'   => array(),
			'inactive' => array(),
		);

		/**
		 * WFOPP_PRO_Core constructor.
		 */
		public function __construct() {
			/**
			 * Load important variables and constants
			 */
			$this->define_pro_properties();

			/**
			 * Loads hooks
			 */
			$this->load_hooks();

		}

		/**
		 * Whether the phone field should auto-detect the visitor's country.
		 *
		 * @return bool
		 */
		private function phone_geoip_enabled() {
			/**
			 * Lets a store skip the third-party lookup and keep the base country.
			 *
			 * @param bool $enabled Whether to auto-detect the country.
			 */
			return (bool) apply_filters( 'wffn_optin_phone_geoip_enabled', true );
		}

		/**
		 * Switches the phone field to auto-detect, in step with the lookup below
		 * so the field never asks for detection without an answer available.
		 *
		 * @param array $localized Data the free plugin localizes for the optin page.
		 *
		 * @return array
		 */
		public function enable_phone_auto_country( $localized ) {
			if ( ! is_array( $localized ) || ! $this->phone_geoip_enabled() ) {
				return $localized;
			}

			$localized['op_flag_country'] = 'auto';

			return $localized;
		}

		/**
		 * Defines the lookup the free plugin's optin script looks for, inline on
		 * its existing handle so a public optin page costs no extra request.
		 *
		 * @return void
		 */
		public function attach_phone_geoip_lookup() {
			if ( ! $this->phone_geoip_enabled() || ! wp_script_is( 'wffn-optin-public', 'enqueued' ) ) {
				return;
			}

			/**
			 * Endpoint used to resolve the visitor's country.
			 *
			 * @param string $endpoint Geolocation endpoint.
			 */
			$endpoint = apply_filters( 'wffn_optin_phone_geoip_endpoint', 'https://ipinfo.io' );

			wp_add_inline_script(
				'wffn-optin-public',
				sprintf(
					'window.wffnOptinGeoLookup=function(cb){try{jQuery.get(%s,function(){},"jsonp").always(function(r){cb(r&&r.country?r.country:"us");});}catch(e){cb("us");}};',
					wp_json_encode( $endpoint )
				),
				'before'
			);
		}

		/**
		 * Defining constants
		 */
		public function define_pro_properties() {
			define( 'WFOPP_PRO_PLUGIN_FILE', __FILE__ );
			define( 'WFOPP_PRO_PLUGIN_DIR', __DIR__ );
			define( 'WFOPP_PRO_PLUGIN_URL', untrailingslashit( plugin_dir_url( WFOPP_PRO_PLUGIN_FILE ) ) );
		}

		/**
		 * Load classes on plugins_loaded hook
		 */
		public function load_hooks() {
			add_action( 'plugins_loaded', array( $this, 'load_modules' ), 5 );
			add_action( 'plugins_loaded', array( $this, 'register_classes' ), 6 );

			/**
			 * Country auto-detection for the phone field. The phone field is ours,
			 * so the lookup behind its flag lives here rather than in the free
			 * plugin, which may not call a third party from a public page.
			 */
			add_action( 'wp_enqueue_scripts', array( $this, 'attach_phone_geoip_lookup' ), 22 );
			add_filter( 'wffn_optin_page_localize_data', array( $this, 'enable_phone_auto_country' ) );
		}

		public function load_modules() {
			require __DIR__ . '/modules/optin-pages/class-wffn-pro-optin-pages.php';
		}

		/**
		 * @return WFOPP_PRO_Core|null
		 */
		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		/**
		 * Register classes
		 */
		public function register_classes() {
			$load_classes = self::get_registered_class();
			if ( is_array( $load_classes ) && count( $load_classes ) > 0 ) {
				foreach ( $load_classes as $access_key => $class ) {
					$this->$access_key = $class::get_instance();
				}
				do_action( 'wfopp_pro_loaded' );
			}
		}

		/**
		 * @return mixed
		 */
		public static function get_registered_class() {
			return self::$_registered_entity['active'];
		}

		public static function register( $short_name, $class, $overrides = null ) {
			self::$_registered_entity['active'][ $short_name ] = $class;

		}
	}
}
if ( ! function_exists( 'WFOPP_PRO_Core' ) ) {
	/**
	 * @return WFOPP_PRO_Core|null
	 */
	function WFOPP_PRO_Core() {  //@codingStandardsIgnoreLine
		return WFOPP_PRO_Core::get_instance();
	}
}

$GLOBALS['WFOPP_PRO_Core'] = WFOPP_PRO_Core();
