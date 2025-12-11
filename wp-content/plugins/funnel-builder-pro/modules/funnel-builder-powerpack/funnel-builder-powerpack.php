<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * @package
 * @version 1.0.0
 */
/**
 * Plugin Name: FunnelKit Funnel Builder Powerpack
 * Plugin URI: https://funnelkit.com
 * Description: Its a development addon to any pro functionalities inside funnel builder, it works as submodule for funnel-builder-pro
 * Version: 0.9.2
 * Author: funnelkit
 * Author URI: https://funnelkit.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: funnel-builder-powerpack
 *
 * Requires at least: 4.9.0
 * Tested up to: 5.2
 * WC requires at least: 3.3.1
 * WC tested up to: 3.8
 * Requires PHP: 5.6
 * WooFunnels: true
 */

defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Core' ) ) {

	/**
	 * Class WFFN_Pro_Core
	 */
	#[AllowDynamicProperties]
	class WFFN_Pro_Core {

		/**
		 * @var null
		 */
		public static $_instance = null;

		/**
		 * @var WFFN_Pro_Admin
		 */
		public $admin;

		/**
		 * @var WFFN_Pro_Steps
		 */
		public $steps;

		/**
		 * @var WFFN_Pro_Substeps
		 */
		public $substeps;

		/**
		 * @var WFFN_Pro_WooFunnels_Support
		 */
		public $support;

		/** @var WFFN_Exporter */
		public $exporter = null;

		/**
		 * @var array
		 */
		private static $_registered_entity = array(
			'active'   => array(),
			'inactive' => array(),
		);
		/**
		 * @var bool Dependency check property
		 */
		public $is_dependency_exists = true;

		/**
		 * WFFN_PRO_Core constructor.
		 */
		public function __construct() {
			/**
			 * Load important variables and constants
			 */
			$this->define_plugin_properties();

			/**
			 * Load dependency classes like woo-functions.php
			 */
			$this->load_dependencies_support();

		}

		/**
		 * Defining constants
		 */
		public function define_plugin_properties() {


			add_action( 'plugins_loaded', [ $this, 'do_dependency_check' ], - 999 );
		}

		public function load_dependencies_support() {
			/** Setting up flex funnels lite Dependency Classes */
			require_once( __DIR__ . '/wffn-includes/wffn-pro-functions.php' );
		}

		public function do_dependency_check() {
			$upload_folder_path = str_replace( ABSPATH, '', $this->get_content_dir() );
			define( 'WFFN_PRO_VERSION', defined( 'WFFN_PRO_BUILD_VERSION' ) ? WFFN_PRO_BUILD_VERSION : '0.9.2' );
			define( 'WFFN_MIN_VERSION', '0.9.beta' );
			define( 'WFFN_PRO_SLUG', 'wffn_pro' );
			define( 'WFFN_PRO_FULL_NAME', 'FunnelKit Funnel Builder Pro' );
			define( 'WFFN_PRO_PLUGIN_FILE', __FILE__ );
			define( 'WFFN_PRO_PLUGIN_DIR', __DIR__ );
			define( 'WFFN_PRO_PLUGIN_URL', untrailingslashit( plugin_dir_url( WFFN_PRO_PLUGIN_FILE ) ) );
			define( 'WFFN_PRO_EXPORT_DIR', $this->get_content_dir() . '/funnelkit-uploads/funnel-export' );
			define( 'WFFN_PRO_EXPORT_URL', site_url( $upload_folder_path . '/funnelkit-uploads/funnel-export/' ) );
			define( 'WFFN_PRO_PLUGIN_BASENAME', defined( 'WFFN_PRO_FILE' ) ? plugin_basename( WFFN_PRO_FILE ) : plugin_basename( __FILE__ ) );

			if ( ! wffn_is_lite_active() ) {
				add_action( 'admin_notices', array( $this, 'wffn_lite_not_installed_notice' ) );
				$this->is_dependency_exists = false;
			}

			if ( $this->is_dependency_exists && version_compare( WFFN_VERSION, WFFN_MIN_VERSION, '<' ) ) {
				add_action( 'admin_notices', array( $this, 'wffn_lite_min_version_notice' ) );
				$this->is_dependency_exists = false;
			}
			/**
			 * Initiates and loads WooFunnels start file
			 */
			if ( true === $this->is_dependency_exists ) {
				/**
				 * Loads hooks
				 */
				$this->load_hooks();
			}

		}

		public function get_content_dir() {
			return wp_upload_dir()['basedir'];
		}

		/**
		 * Load classes on plugins_loaded hook
		 */
		public function load_hooks() {
			/**
			 * Initialize Localization
			 */
			require __DIR__ . '/includes/class-wffn-pro-modules.php';
			add_action( 'init', array( $this, 'localization' ) );
			add_action( 'plugins_loaded', array( $this, 'load_classes' ), 2 );
			add_action( 'plugins_loaded', array( $this, 'register_classes' ), 3 );

			add_action( 'activated_plugin', array( $this, 'redirect_on_activation' ) );
			add_filter( 'wffn_conversion_tracking_persistant', '__return_true' );
		}

		public function localization() {
			load_plugin_textdomain( 'funnel-builder-pro', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Load classes
		 */
		public function load_classes() {

			/**
			 * Loads all the admin
			 */
			$this->load_autoloader();

			$this->load_admin();

			$this->load_includes();

			$this->load_commons();

			$this->load_steps();
		}

		/**
		 * Loads the admin
		 */
		public function load_admin() {
			include_once __DIR__ . '/admin/class-wffn-pro-admin.php';
			/**Load Stripe Notice Class */
			if ( is_admin() ) {
				require __DIR__ . '/admin/class-wffn-stripe-admin-controller.php';
			}
		}

		/**
		 * Load includes folder
		 */
		public function load_includes() {
			require __DIR__ . '/includes/class-wffn-pro-public.php';
			require __DIR__ . '/includes/class-wffn-pro-woofunnels-support.php';
			require __DIR__ . '/includes/class-wffn-conversion-data.php';
			require __DIR__ . '/includes/class-wffn-rest-api-endpoint.php';
			require __DIR__ . '/includes/class-wffn-reset-api-endpoint.php';
			require __DIR__ . '/includes/optin-pro/woofunnels-optins-pro.php';
			require __DIR__ . '/includes/wc_thankyou-pro/woofunnels-wc_thankyou-pro.php';
			/**Load Import Export Class */
			require __DIR__ . '/includes/class-wffn-rest-import-export.php';
			require __DIR__ . '/includes/exporter/class-wffn-abstract-exporter.php';
			require __DIR__ . '/includes/exporter/class-wffn-exporter.php';
		}

		/**
		 * Include steps and sub steps
		 */
		public function load_steps() {
			require __DIR__ . '/includes/class-wffn-pro-step-base.php';
			require __DIR__ . '/includes/class-wffn-pro-step.php';
			require __DIR__ . '/includes/class-wffn-pro-steps.php';
			require __DIR__ . '/includes/class-wffn-pro-substep.php';
			require __DIR__ . '/includes/class-wffn-pro-substeps.php';
		}

		public function load_autoloader() {
			spl_autoload_register( array( $this, 'autoload' ) );
		}

		public function autoload( $class_name ) {
			if ( false !== strpos( $class_name, 'WFFN_' ) ) {
				if ( file_exists( __DIR__ . '/includes/' . 'class-' . $this->slugify_classname( $class_name ) . '.php' ) ) {
					require_once __DIR__ . '/includes/' . 'class-' . $this->slugify_classname( $class_name ) . '.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant
				}

			}
		}

		public function slugify_classname( $class_name ) {
			$classname = sanitize_title( $class_name );
			$classname = str_replace( '_', '-', $classname );

			return $classname;
		}

		/**
		 * Includes common functions.
		 */
		public function load_commons() {

		}

		/**
		 * @return WFFN_PRO_Core|null
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

				do_action( 'wffn_pro_loaded' );

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

		public function wffn_lite_not_installed_notice() {
			?>
            <div class="error">
                <p>
					<?php
					echo __( '<strong> Attention: </strong>"FunnelKit Funnel Builder" is not installed or activated. "FunnelKit Funnel Builder Pro" would only work if Funnel Builder is activated. Please install the Funnel Builder Plugin first.', 'funnel-builder-pro' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
                </p>
            </div>
			<?php
		}

		public function wffn_lite_min_version_notice() { ?>
            <div class="error">
                <p>
					<?php
					echo sprintf( __( '<strong> Attention: </strong>"FunnelKit Funnel Builder Pro" is not working because activated "FunnelKit Funnel Builder" version should be greater or equal to %s', 'funnel-builder-pro' ), WFFN_MIN_VERSION ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
                </p>
            </div>
			<?php
		}

		/**
		 * Added redirection on plugin activation
		 *
		 * @param $plugin
		 */
		public function redirect_on_activation( $plugin ) {
			if ( $plugin === plugin_basename( __FILE__ ) ) {

				wp_redirect( add_query_arg( array(
					'page'      => 'bwf_funnels',
					'activated' => 'yes',
				), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
	}
}
if ( ! function_exists( 'WFFN_Pro_Core' ) ) {
	/**
	 * @return WFFN_PRO_Core|null
	 */
	function WFFN_Pro_Core() {  //@codingStandardsIgnoreLine
		return WFFN_Pro_Core::get_instance();
	}
}

$GLOBALS['WFFN_Pro_Core'] = WFFN_Pro_Core();
