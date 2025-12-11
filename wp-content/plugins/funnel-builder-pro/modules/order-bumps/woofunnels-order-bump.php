<?php

/**
 * Plugin Name: FunnelKit Order Bumps
 * Plugin URI: https://funnelkit.com
 * Description: Use Order Bumps to make last minute pre-purchase offers. Let user upgrade their order with a single click. Control visibility by setting rules. Super Easy to customize Bump designs.
 * Version: 3.12.1
 * Author: Funnelkit
 * Author URI: https://funnelkit.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woofunnels-order-bump
 *
 * Requires at least: 4.9
 * Tested up to: 6.8.1
 * WC requires at least: 3.0
 * WC tested up to: 9.9.2
 * WooFunnels: true
 *
 * FunnelKit Order Bumps is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FunnelKit Order Bumps. If not, see <http://www.gnu.org/licenses/>.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Core' ) ) {
	#[AllowDynamicProperties]
	class WFOB_Core {

		/**
		 * @var WFOB_Core
		 */
		public static $_instance = null;
		private static $_registered_entity = array(
			'active'   => array(),
			'inactive' => array(),
		);
		/**
		 * @var bool Dependency check property
		 */
		private $is_dependency_exists = true;

		/**
		 * @var WFOB_Admin
		 */
		public $admin;

		/**
		 * @var WFOB_Public
		 */
		public $public;

		/**
		 * @var WFOB_Rules
		 */
		public $rules;


		/**
		 * @var WFOB_WooFunnels_Support
		 */
		public $support;

		/**
		 * @var WFOB_Role_Capability
		 */
		public $role;

		public function __construct() {

			/**
			 * Load important variables and constants
			 */
			$this->define_plugin_properties();

			/**
			 * Load dependency classes like woo-functions.php
			 */
			$this->load_dependencies_support();

			/**
			 * Run dependency check to check if dependency available
			 */
			$this->do_dependency_check();
			/**
			 * Initiates and loads WooFunnels start file
			 */
			if ( true === $this->is_dependency_exists ) {


				if ( true === apply_filters( 'wfob_should_load_core', true ) ) {
					$this->load_woofunnels_core_classes();
				}
				/**
				 * Loads common file
				 */
				$this->load_commons();

			}
		}

		/**
		 * Defining constants
		 */
		public function define_plugin_properties() {
			define( 'WFOB_VERSION', '3.12.1' );
			define( 'WFOB_MIN_WC_VERSION', '3.0' );
			define( 'WFOB_BWF_VERSION', '1.10.12.61' );
			define( 'WFOB_MIN_WP_VERSION', '4.9' );
			define( 'WFOB_SLUG', 'wfob' );
			define( 'WFOB_TEXTDOMAIN', 'woofunnels-order-bump' );
			define( 'WFOB_FULL_NAME', 'FunnelKit Order Bumps' );
			define( 'WFOB_PLUGIN_FILE', __FILE__ );
			define( 'WFOB_PLUGIN_DIR', __DIR__ );
			define( 'WFOB_SKIN_DIR', __DIR__ . '/skins' );
			define( 'WFOB_PLUGIN_URL', untrailingslashit( plugin_dir_url( WFOB_PLUGIN_FILE ) ) );
			define( 'WFOB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			define( 'WFOB_ADMIN_PATH', WFOB_PLUGIN_URL . '/admin' );
			define( 'WFOB_DB_VERSION', '1.0' );
			( defined( 'WFOB_IS_DEV' ) && true === WFOB_IS_DEV ) ? define( 'WFOB_VERSION_DEV', time() ) : define( 'WFOB_VERSION_DEV', WFOB_VERSION );
		}

		public function load_dependencies_support() {
			/** Setting up WooCommerce Dependency Classes */
			require_once( __DIR__ . '/woo-includes/woo-functions.php' );
		}

		public function do_dependency_check() {
			if ( ! wfob_is_woocommerce_active() ) {
				add_action( 'admin_notices', array( $this, 'wc_not_installed_notice' ) );
				$this->is_dependency_exists = false;
			}
		}

		public function load_commons() {
			require WFOB_PLUGIN_DIR . '/includes/class-wfob-common.php';
			require WFOB_PLUGIN_DIR . '/includes/class-add-new-position.php';
			require WFOB_PLUGIN_DIR . '/includes/class-wfob-woofunnel-support.php';
			require WFOB_PLUGIN_DIR . '/includes/class-wfob-ajax-controller.php';
			require WFOB_PLUGIN_DIR . '/compatibilities/class-wfob-plugin-compatibilities.php';

			WFOB_Common::init();

			/**
			 * Loads common hooks
			 */
			$this->load_hooks();
		}

		public function load_hooks() {
			/**
			 * Initialize Localization
			 */
			add_action( 'init', array( $this, 'localization' ) );
			add_action( 'plugins_loaded', array( $this, 'load_classes' ), 1 );
			add_action( 'plugins_loaded', array( $this, 'register_classes' ), 1 );
			/** Redirecting Plugin to the settings page after activation */
			add_action( 'activated_plugin', array( $this, 'redirect_on_activation' ) );
			add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );

		}

		public function load_classes() {

			if ( wfob_is_woocommerce_active() && class_exists( 'WooCommerce' ) ) {

				global $wp_version;
				if ( ! version_compare( $wp_version, WFOB_MIN_WP_VERSION, '>=' ) ) {
					add_action( 'admin_notices', array( $this, 'wp_version_check_notice' ) );

					return false;
				}

				global $woocommerce;
				if ( ! version_compare( $woocommerce->version, WFOB_MIN_WC_VERSION, '>=' ) ) {
					add_action( 'admin_notices', array( $this, 'wc_version_check_notice' ) );

					return false;
				}

				/**
				 * Loads all the public
				 */
				$this->load_public();

				/**
				 * Loads all the admin
				 */
				$this->load_admin();

				/**
				 * Loads core classes
				 */
				require WFOB_PLUGIN_DIR . '/includes/class-dynamic-merge-tags.php';
				require WFOB_PLUGIN_DIR . '/includes/class-wfob-rules.php';
				require WFOB_PLUGIN_DIR . '/includes/class-wfob-role-capability.php';
				require WFOB_PLUGIN_DIR . '/includes/class-wfob-reporting.php';


			}

			return null;
		}

		public function load_public() {

			require WFOB_PLUGIN_DIR . '/includes/class-wfob-public.php';
		}

		public function load_admin() {
			require WFOB_PLUGIN_DIR . '/admin/class-wfob-admin.php';
			require WFOB_PLUGIN_DIR . '/admin/class-wfob-exporter.php';
			require WFOB_PLUGIN_DIR . '/admin/class-wfob-importer.php';

			require WFOB_PLUGIN_DIR . '/admin/includes/class-bwf-admin-settings.php';
			require WFOB_PLUGIN_DIR . '/admin/includes/class-bwf-admin-breadcrumbs.php';
		}

		public static function get_instance() {
			if ( null == self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		public function load_woofunnels_core_classes() {
			/** Setting Up WooFunnels Core */
			require_once( 'start.php' );
		}

		public function localization() {
			load_plugin_textdomain( 'woofunnels-order-bump', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Added redirection on plugin activation
		 *
		 * @param $plugin
		 */
		public function redirect_on_activation( $plugin ) {
			if ( wfob_is_woocommerce_active() && class_exists( 'WooCommerce' ) ) {
				if ( $plugin === plugin_basename( __FILE__ ) ) {
					wp_redirect( add_query_arg( array(
						'page' => 'wfob',
					), admin_url( 'admin.php' ) ) );
					exit;
				}
			}
		}

		public function register_classes() {
			$load_classes = self::get_registered_class();

			if ( is_array( $load_classes ) && count( $load_classes ) > 0 ) {
				foreach ( $load_classes as $access_key => $class ) {
					$this->$access_key = $class::get_instance();
				}
				do_action( 'wfob_loaded' );
			}
		}

		public static function get_registered_class() {
			return self::$_registered_entity['active'];
		}

		public static function register( $short_name, $class, $overrides = null ) {
			//Ignore classes that have been marked as inactive
			if ( in_array( $class, self::$_registered_entity['inactive'] ) ) {
				return;
			}
			//Mark classes as active. Override existing active classes if they are supposed to be overridden
			$index = array_search( $overrides, self::$_registered_entity['active'] );
			if ( false !== $index ) {
				self::$_registered_entity['active'][ $index ] = $class;
			} else {
				self::$_registered_entity['active'][ $short_name ] = $class;
			}

			//Mark overridden classes as inactive.
			if ( ! empty( $overrides ) ) {
				self::$_registered_entity['inactive'][] = $overrides;
			}
		}


		public function wc_version_check_notice() {
			?>
            <div class="error">
                <p>
					<?php
					/* translators: %1$s: Min required woocommerce version */
					printf( __( '<strong> Attention: </strong>OrderBump requires WooCommerce version %1$s or greater. Kindly update the WooCommerce plugin.', 'woofunnels-order-bump' ), WFOB_MIN_WC_VERSION );
					?>
                </p>
            </div>
			<?php
		}

		public function wp_version_check_notice() {
			?>
            <div class="error">
                <p>
					<?php
					/* translators: %1$s: Min required woocommerce version */
					printf( __( '<strong> Attention: </strong>OrderBump requires WordPress version %1$s or greater. Kindly update the WordPress.', 'woofunnels-order-bump' ), WFOB_MIN_WP_VERSION );
					?>
                </p>
            </div>
			<?php
		}

		public function wc_not_installed_notice() {
			?>
            <div class="error">
                <p>
					<?php
					echo __( '<strong> Attention: </strong>WooCommerce is not installed or activated. OrderBump is a WooCommerce Extension and would only work if WooCommerce is activated. Please install the WooCommerce Plugin first.', 'woofunnels-order-bump' );
					?>
                </p>
            </div>
			<?php
		}

		/**
		 * This method declared ours compat with the HPOS mechanism
		 *
		 * @return void
		 * @since 1.17.0
		 */
		public function declare_hpos_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {

				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WFOB_PLUGIN_FILE, true );
			}
		}
	}
}

if ( ! function_exists( 'WFOB_Core' ) ) {

	/**
	 * Global Common function to load all the classes
	 * @return WFOB_Core
	 */
	function WFOB_Core() {  //@codingStandardsIgnoreLine
		return WFOB_Core::get_instance();
	}
}

$GLOBALS['WFOB_Core'] = WFOB_Core();
