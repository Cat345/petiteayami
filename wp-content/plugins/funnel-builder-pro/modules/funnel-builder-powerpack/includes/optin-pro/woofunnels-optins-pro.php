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
