<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

if ( ! class_exists( 'WFTP_PRO_Core' ) ) {

	/**
	 * Class WFTP_PRO_Core
	 */
	#[AllowDynamicProperties]
	class WFTP_PRO_Core {

		/**
		 * @var null
		 */
		public static $_instance = null;
		public static $_registered_entity = null;

		
		/**
		 * WFTY_PRO_Core constructor.
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
			define( 'WFTY_PRO_PLUGIN_FILE', __FILE__ );
			define( 'WFTY_PRO_PLUGIN_DIR', __DIR__ );
			define( 'WFTY_PRO_PLUGIN_URL', untrailingslashit( plugin_dir_url( WFTY_PRO_PLUGIN_FILE ) ) );
		}

		/**
		 * Load classes on plugins_loaded hook
		 */
		public function load_hooks() {
			add_action( 'plugins_loaded', array( $this, 'load_modules' ), 5 );
			add_action( 'plugins_loaded', array( $this, 'register_classes' ), 6 );
		}

		public function load_modules() {

			require __DIR__ . '/modules/wc_thankyou/class-wffn-pro-wc-thankyou-pages.php';
			require __DIR__ . '/class-wffn-rest-thankyou-api-endpoint.php';
		}

		/**
		 * @return WFTY_PRO_Core|null
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
				do_action( 'wfty_pro_loaded' );
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
if ( ! function_exists( 'WFTY_PRO_Core' ) ) {
	/**
	 * @return WFTY_PRO_Core|null
	 */
	function WFTP_PRO_Core() {  //@codingStandardsIgnoreLine
		return WFTP_PRO_Core::get_instance();
	}
}

$GLOBALS['WFTP_PRO_Core'] = WFTP_PRO_Core();
