<?php //phpcs:ignore
/**
 * Plugin Name: A/B Experiments for FunnelKit
 * Plugin URI: https://funnelkit.com
 * Description: A/B Test Funnel Steps to find winning version and gain more revenue
 * Version: 1.7.0
 * Author: Funnelkit
 * Author URI: https://funnelkit.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woofunnels-ab-tests
 *
 * Requires at least: 5.0
 * Tested up to: 6.4.2
 * Requires PHP: 7.0
 * WooFunnels: true
 */

defined( 'ABSPATH' ) || exit; //Exit if accessed directly

if ( ! class_exists( 'BWFABT_Core' ) ) {


	/**
	 * Class BWFABT_Core
	 */
	#[AllowDynamicProperties]
	class BWFABT_Core {

		/**
		 * @var null
		 */
		public static $_instance = null;

		/**
		 * @var BWFABT_Admin
		 */
		public $admin;

		/** @var BWFABT_Data_Store */
		public $data_store = null;

		/** @var BWFABT_Reports */
		public $reports = null;

		/** @var BWFABT_Controllers */
		public $controllers = null;

		/** @var BWFABT_Role_Capability */
		public $role = null;

		/**
		 * BWFABT_Core constructor.
		 */
		public function __construct() {
			/**
			 * Load important variables and constants
			 */
			$this->define_plugin_properties();

			require __DIR__ . '/includes/bwfabt-functions.php';

			/**
			 * Loads hooks
			 */
			$this->load_hooks();
		}

		/**
		 * Defining constants
		 */
		public function define_plugin_properties() {
			define( 'BWFABT_VERSION', '1.7.0' );
			define( 'BWFABT_MIN_WC_VERSION', '3.5' );
			define( 'BWFABT_MIN_WP_VERSION', '5.0' );
			define( 'BWFABT_SLUG', 'bwfabt' );
			define( 'BWFABT_FULL_NAME', 'A/B Experiments for FunnelKit' );
			define( 'BWFABT_PLUGIN_FILE', __FILE__ );
			define( 'BWFABT_PLUGIN_DIR', __DIR__ );
			define( 'BWFABT_PLUGIN_URL', untrailingslashit( plugin_dir_url( BWFABT_PLUGIN_FILE ) ) );
			define( 'BWFABT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			define( 'BWFABT_DB_VERSION', '1.4' );

			( defined( 'BWFABT_IS_DEV' ) && true === BWFABT_IS_DEV ) ? define( 'BWFABT_VERSION_DEV', time() ) : define( 'BWFABT_VERSION_DEV', BWFABT_VERSION );
		}

		/**
		 * Load classes on plugins_loaded hook
		 */
		public function load_hooks() {
			add_action( 'plugins_loaded', array( $this, 'load_classes' ), 1 );
		}

		/**
		 * Loading classes
		 */
		public function load_classes() {
			/**
			 * Loads the admin
			 */
			require __DIR__ . '/includes/class-bwfabt-admin.php';
			$this->admin = BWFABT_Admin::get_instance();
			require __DIR__ . '/includes/class-bwfabt-role-capability.php';
			require __DIR__ . '/includes/class-bwf-admin-breadcrumbs.php';

			require __DIR__ . '/includes/class-bwfabt-ajax-controller.php';

			require __DIR__ . '/includes/class-bwfabt-data-store.php';

			require __DIR__ . '/includes/class-bwfabt-experiment.php';
			require __DIR__ . '/includes/class-bwfabt-experiment-table.php';

			require __DIR__ . '/includes/class-bwfabt-variant.php';

			require __DIR__ . '/includes/class-bwfabt-controller.php';
			require __DIR__ . '/includes/class-bwfabt-controllers.php';

			require __DIR__ . '/includes/class-bwfabt-report.php';
			require __DIR__ . '/includes/class-bwfabt-reports.php';
			require __DIR__ . '/includes/class-bwfabt-woofunnels-support.php';


			if ( function_exists( 'wfocu_is_woocommerce_active' ) && wfocu_is_woocommerce_active() ) {
				require __DIR__ . '/test-modules/upstroke/class-bwfabt-controller-upstroke.php';
				require __DIR__ . '/test-modules/upstroke/class-bwfabt-report-upstroke.php';
				require __DIR__ . '/test-modules/offer/class-bwfabt-controller-offer.php';
				require __DIR__ . '/test-modules/offer/class-bwfabt-report-offer.php';
			}

			if ( function_exists( 'wfob_is_woocommerce_active' ) && wfob_is_woocommerce_active() ) {
				require __DIR__ . '/test-modules/order-bumps/class-bwfabt-controller-order-bump.php';
				require __DIR__ . '/test-modules/order-bumps/class-bwfabt-report-order-bump.php';
			}

			if ( function_exists( 'WFFN_Core' ) && class_exists( 'WFFN_Core' ) ) {
				require __DIR__ . '/test-modules/landing/class-bwfabt-controller-landing.php';
				require __DIR__ . '/test-modules/landing/class-bwfabt-report-landing.php';

				if ( function_exists( 'wffn_is_wc_active' ) && wffn_is_wc_active() ) {
					require __DIR__ . '/test-modules/thankyou/class-bwfabt-controller-thankyou.php';
					require __DIR__ . '/test-modules/thankyou/class-bwfabt-report-thankyou.php';
				}

				if ( function_exists( 'WFOPP_Core' ) && class_exists( 'WFOPP_Core' ) ) {
					require __DIR__ . '/test-modules/optin/class-bwfabt-controller-optin.php';
					require __DIR__ . '/test-modules/optin/class-bwfabt-report-optin.php';
					require __DIR__ . '/test-modules/optin-ty/class-bwfabt-controller-optin-ty.php';
					require __DIR__ . '/test-modules/optin-ty/class-bwfabt-report-optin-ty.php';
				}
				require __DIR__ . '/compatibilities/class-bwfabt-wffn-compatibility.php';
			}

			if ( function_exists( 'wfacp_is_woocommerce_active' ) && wfacp_is_woocommerce_active() ) {
				require __DIR__ . '/test-modules/aero-checkout/class-bwfabt-controller-aero-checkout.php';
				require __DIR__ . '/test-modules/aero-checkout/class-bwfabt-report-aero-checkout.php';
			}

			//Rest API classes
			require __DIR__ . '/includes/class-bwfabt-rest-controller.php';
			require __DIR__ . '/includes/class-bwfabt-rest-variant.php';
			require __DIR__ . '/includes/class-bwfabt-rest-experiment.php';
			require __DIR__ . '/includes/class-bwfabt-rest-analytics.php';
		}

		/**
		 * @return BWFABT_Core|null
		 */
		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		/**
		 * @return BWFABT_Data_Store
		 */
		public function get_dataStore() {
			if ( empty( $this->data_store ) ) {
				$class            = apply_filters( 'bwfabt_data_store_class', 'BWFABT_Data_Store' );
				$this->data_store = new $class();
			}

			return $this->data_store;
		}
	}
}

if ( ! function_exists( 'BWFABT_Core' ) ) {
	/**
	 * @return BWFABT_Core|null
	 */
	function BWFABT_Core() {  //@codingStandardsIgnoreLine
		return BWFABT_Core::get_instance();
	}
}

$GLOBALS['BWFABT_Core'] = BWFABT_Core();
