<?php
/**
 * Plugin Name: FunnelKit One Click Upsells
 * Plugin URI: https://funnelkit.com
 * Description: FunnelKit One Click Upsells is a complete system to run post purchase one click upsells in WooCommerce.
 * Version: 3.13.3
 * Author: Funnelkit
 * Author URI: https://funnelkit.com
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woofunnels-upstroke-one-click-upsell
 * Domain Path: /languages/
 *
 * Requires at least: 4.9.0
 * Tested up to: 6.8.3
 * WC requires at least: 3.3.1
 * WC tested up to: 10.3.0
 * WooFunnels: true
 *
 * FunnelKit One Click Upsells is free software.
 * You can redistribute it and/or modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * FunnelKit One Click Upsells is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FunnelKit One Click Upsells. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Core' ) ) {
	#[AllowDynamicProperties]
	class WFOCU_Core {

		/**
		 * @var WFOCU_Core
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
		 * @var WFOCU_Admin
		 */
		public $admin;

		/**
		 * @var WFOCU_Public
		 */
		public $public;
		/**
		 * @var WFOCU_Gateways
		 */
		public $gateways;
		/**
		 * @var WFOCU_Orders
		 */
		public $orders;
		/**
		 * @var WFOCU_Session_Handler
		 */
		public $session;
		/**
		 * @var WFOCU_Data
		 */
		public $data;
		/**
		 * @var WFOCU_Offers
		 */
		public $offers;
		/**
		 * @var WFOCU_Funnels
		 */
		public $funnels;
		/**
		 * @var WFOCU_Template_loader
		 */
		public $template_loader;
		/**
		 * @var WFOCU_Customizer
		 */
		public $customizer;
		/**
		 * @var WFOCU_Shipping
		 */
		public $shipping;
		/**
		 * @var WFOCU_Offer_Process
		 */
		public $process_offer;
		/**
		 * @var WFOCU_Mails
		 */
		public $mails;
		/**
		 * @var WFOCU_DB_Track
		 */
		public $track;
		/**
		 * @var WFOCU_Rules
		 */
		public $rules;
		/**
		 * @var WFOCU_Shortcodes
		 */
		public $shortcodes;
		/**
		 * @var WFOCU_Assets_Loader
		 */
		public $assets;
		/**
		 * @var WFOCU_Logger
		 */
		public $log;
		/**
		 * @var WFOCU_WooFunnels_Support
		 */
		public $support;
		/**
		 * @var WFOCU_DB_Session
		 */
		public $session_db;

		/**
		 * @var WFOCU_Templates_Retriever
		 */
		public $template_retriever;

		/**
		 * @var WFOCU_Template_Importer
		 */
		public $importer;

		/**
		 * @var WFOCU_Importer
		 */
		public $import;

		/**
		 * @var WFOCU_WC_API_Handler
		 */
		public $wc_api;

		/**
		 * @var WFOCU_Exporter
		 */
		public $export;

		/**
		 * @var WFOCU_Role_Capability
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
				if ( true === apply_filters( 'wfocu_should_load_core', true ) ) {
					$this->load_woofunnels_core_classes();
				}
				/**
				 * Loads hooks
				 */
				$this->load_hooks();
			}
		}

		/**
		 * Defining constants
		 */
		public function define_plugin_properties() {
			define( 'WFOCU_VERSION', '3.13.3' );
			define( 'WFOCU_BWF_VERSION', '1.10.12.69' );
			define( 'WFOCU_MIN_WC_VERSION', '3.0.0' );
			define( 'WFOCU_MIN_WP_VERSION', '4.9' );
			define( 'WFOCU_SLUG', 'wfocu' );
			define( 'WFOCU_FULL_NAME', 'FunnelKit One Click Upsells' );
			define( 'WFOCU_PLUGIN_FILE', __FILE__ );
			define( 'WFOCU_PLUGIN_DIR', __DIR__ );
			add_action( 'plugins_loaded', array( $this, 'load_wp_dependent_properties' ), 1 );
		}

		public function load_wp_dependent_properties() {
			define( 'WFOCU_TEMPLATE_DIR', plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'templates' );
			define( 'WFOCU_PLUGIN_URL', untrailingslashit( plugin_dir_url( WFOCU_PLUGIN_FILE ) ) );
			define( 'WFOCU_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			define( 'WFOCU_DB_VERSION', '3.9.0' );

			( defined( 'WFOCU_IS_DEV' ) && true === WFOCU_IS_DEV ) ? define( 'WFOCU_VERSION_DEV', time() ) : define( 'WFOCU_VERSION_DEV', WFOCU_VERSION );
		}

		public function load_dependencies_support() {
			/** Setting up WooCommerce Dependency Classes */
			require_once( __DIR__ . '/woo-includes/woo-functions.php' );
		}

		public function do_dependency_check() {
			if ( ! wfocu_is_woocommerce_active() ) {
				add_action( 'admin_notices', array( $this, 'wc_not_installed_notice' ) );
				$this->is_dependency_exists = false;
				add_action( 'activated_plugin', array( $this, 'maybe_flush_permalink' ) );
			}
		}

		public function load_commons() {
			require __DIR__ . '/includes/class-wfocu-common.php';
			require __DIR__ . '/includes/class-wfocu-woofunnels-support.php';

			require __DIR__ . '/compatibilities/class-wfocu-plugin-compatibilities.php';

			WFOCU_Common::init();
			require __DIR__ . '/admin/includes/class-bwf-admin-settings.php';
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
			add_action( 'plugins_loaded', array( 'WooFunnel_Loader', 'include_core' ), - 1 );
			

			add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
			require_once __DIR__ . '/compatibilities/page-builders/bricks-builder/includes/class-bricks-integration.php';
		}

		public function load_classes() {
			global $wp_version;
			if ( ! version_compare( $wp_version, WFOCU_MIN_WP_VERSION, '>=' ) ) {
				add_action( 'admin_notices', array( $this, 'php_version_dependency_fail' ) );

				return false;
			}
			if ( wfocu_is_woocommerce_active() && class_exists( 'WooCommerce' ) ) {

				global $woocommerce;
				if ( ! version_compare( $woocommerce->version, WFOCU_MIN_WC_VERSION, '>=' ) ) {
					add_action( 'admin_notices', array( $this, 'wc_version_check_notice' ) );

					return false;
				}
				if ( class_exists( 'Upstroke_Mollie_Compatibility' ) ) {
					remove_action( 'wfocu_loaded', array( Upstroke_Mollie_Compatibility::instance(), 'init_hooks' ) );
				}
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				if ( is_plugin_active( 'mollie-payments-for-woocommerce/mollie-payments-for-woocommerce.php' ) ) {
					require __DIR__ . '/includes/class-wfocu-mollie-helper.php';
					require __DIR__ . '/includes/class-wfocu-mollie-helper-compat.php';
					if ( class_exists( 'Upstroke_Mollie_Compatibility' ) ) {
						add_action( 'admin_notices', array( WFOCU_Mollie_Helper::instance(), 'wfocu_mollie_plugin_deactivate_notice' ) );
					}
				}
				$this->load_commons();
				/**
				 * Loads all the public
				 */
				$this->load_public();

				/**
				 * Loads all the admin
				 */
				$this->load_admin();

				/**
				 * Loads override template functions file
				 */
				require __DIR__ . '/includes/wfocu-wc-functions-override.php';
				/**
				 * Loads Database related classes
				 */
				require __DIR__ . '/db/base.php';
				require __DIR__ . '/db/session.php';
				require __DIR__ . '/db/track.php';

				/**
				 * Loads Generic Classes
				 */
				require __DIR__ . '/includes/class-wfocu-session-handler.php';
				require __DIR__ . '/includes/class-wfocu-sv-api-base.php';

				/**
				 * Loads Merge Tags Core
				 */
				require __DIR__ . '/merge-tags/wfocu-shortcode-merge-tags.php';
				require __DIR__ . '/merge-tags/wfocu-dynamic-merge-tags.php';
				require __DIR__ . '/merge-tags/wfocu-static-merge-tags.php';
				require __DIR__ . '/merge-tags/wfocu-syntax-merge-tags.php';

				/**
				 * Loads core classes
				 */
				require __DIR__ . '/includes/class-wfocu-wc-api-handler.php';
				require __DIR__ . '/includes/class-wfocu-payment-gateway-exception.php';
				require __DIR__ . '/includes/class-wfocu-schedules.php';
				require __DIR__ . '/includes/class-wfocu-mails.php';
				require __DIR__ . '/includes/class-wfocu-shortcodes.php';
				require __DIR__ . '/includes/class-wfocu-ajax-controller.php';
				require __DIR__ . '/includes/class-wfocu-rules.php';
				require __DIR__ . '/includes/class-wfocu-data.php';
				require __DIR__ . '/includes/class-wfocu-funnels.php';
				require __DIR__ . '/includes/class-wfocu-gateway.php';
				require __DIR__ . '/includes/class-wfocu-gateways.php';
				require __DIR__ . '/includes/class-wfocu-offers.php';
				require __DIR__ . '/includes/class-wfocu-shipping.php';
				require __DIR__ . '/includes/class-wfocu-offer-process.php';
				require __DIR__ . '/includes/class-wfocu-template-common.php';
				require __DIR__ . '/includes/class-wfocu-customizer-common.php';
				require __DIR__ . '/includes/class-wfocu-assets-loader.php';
				require __DIR__ . '/includes/class-wfocu-template-group.php';
				require __DIR__ . '/includes/class-wfocu-template-loader.php';
				require __DIR__ . '/includes/class-wfocu-orders.php';
				require __DIR__ . '/includes/class-wfocu-ecomm-tracking.php';
				require __DIR__ . '/includes/class-wfocu-logger.php';
				require __DIR__ . '/includes/class-wfocu-customizer.php';
				require __DIR__ . '/compatibilities/page-builders/remote/class-wfocu-remote-template-importer.php';
				require __DIR__ . '/includes/class-wfocu-template-importer.php';
				require __DIR__ . '/includes/class-wfocu-templates-retriever.php';
				require __DIR__ . '/includes/class-wfocu-role-capability.php';

				
			}

			return null;
		}

		public function load_public() {
			require __DIR__ . '/includes/class-wfocu-public.php';
		}

		public function load_admin() {
			require __DIR__ . '/admin/includes/class-wfocu-background-updater.php';
			require __DIR__ . '/admin/includes/wfocu-updater-functions.php';
			require __DIR__ . '/admin/includes/class-bwf-admin-breadcrumbs.php';
			require __DIR__ . '/admin/class-wfocu-admin.php';
			require __DIR__ . '/admin/class-wfocu-wizard.php';
			require __DIR__ . '/admin/class-wfocu-admin-notices.php';
			require __DIR__ . '/admin/class-wfocu-admin-refund.php';
			require __DIR__ . '/admin/class-wfocu-importer.php';
			require __DIR__ . '/admin/class-wfocu-exporter.php';
		}

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		public function load_woofunnels_core_classes() {
			/** Setting Up WooFunnels Core */
			require_once( __DIR__ . '/start.php' );
		}

		public function localization() {
			load_plugin_textdomain( 'woofunnels-upstroke-one-click-upsell', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Added redirection on plugin activation
		 *
		 * @param $plugin
		 */
		public function redirect_on_activation( $plugin ) {
			if ( wfocu_is_woocommerce_active() && class_exists( 'WooCommerce' ) ) {
				if ( $plugin === plugin_basename( __FILE__ ) ) {
					wp_redirect( add_query_arg( array(
						'page'      => 'upstroke',
						'activated' => 'yes',
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
				do_action( 'wfocu_loaded' );
			}
		}

		public static function get_registered_class() {
			return self::$_registered_entity['active'];
		}

		public static function register( $short_name, $class, $overrides = null ) {
			//Ignore classes that have been marked as inactive
			if ( in_array( $class, self::$_registered_entity['inactive'], true ) ) {
				return;
			}
			//Mark classes as active. Override existing active classes if they are supposed to be overridden
			$index = array_search( $overrides, self::$_registered_entity['active'], true );
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
					printf( __( '<strong> Attention: </strong>UpStroke requires WooCommerce version %1$s or greater. Kindly update the WooCommerce plugin.', 'woofunnels-upstroke-one-click-upsell' ), esc_attr( WFOCU_MIN_WC_VERSION ) ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
					echo __( '<strong> Attention: </strong>WooCommerce is not installed or activated. UpStroke is a WooCommerce Extension and would only work if WooCommerce is activated. Please install the WooCommerce Plugin first.', 'woofunnels-upstroke-one-click-upsell' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
                </p>
            </div>
			<?php
		}

		public function php_version_dependency_fail() {
			?>
            <div class="error">
                <p>
					<?php
					echo __( '<strong> Attention: </strong>Your WordPress version is not compatible with UpStroke. UpStroke requires atleast WordPress 4.9 to run.', 'woofunnels-upstroke-one-click-upsell' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
                </p>
            </div>
			<?php
		}

		public function maybe_flush_permalink( $plugin ) {
			if ( 'woocommerce/woocommerce.php' !== $plugin ) {
				return;
			}
			update_option( 'bwf_needs_rewrite', 'yes', true );
		}

		/**
		 * This method declared ours compat with the HPOS mechanism
		 *
		 * @return void
		 * @since 3.6.3
		 */
		public function declare_hpos_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WFOCU_PLUGIN_FILE, true );
			}
		}
	}
}
if ( ! function_exists( 'WFOCU_Core' ) ) {

	/**
	 * Global Common function to load all the classes
	 * @return WFOCU_Core
	 */
	function WFOCU_Core() {
		return WFOCU_Core::get_instance();
	}
}

$GLOBALS['WFOCU_Core'] = WFOCU_Core();
