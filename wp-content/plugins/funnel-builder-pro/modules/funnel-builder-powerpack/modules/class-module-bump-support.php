<?php
if ( ! class_exists( 'WFFN_Pro_Bump_Support' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Bump_Support {


		private static $ins = null;
		public $environment = null;

		/**
		 * WFFN_Pro_Bump_Support constructor.
		 */
		public function __construct() {
		}

		public static function is_admin_enabled() {
			if ( self::is_module_exists() ) {
				return true;
			}
			$modules = get_option( '_bwf_individual_modules', [] );
			return (bool) apply_filters( 'wffn_show_menu_bump', isset( $modules['bump'] ) && 'yes' === $modules['bump'] );
		}

		/**
		 * @return WFFN_Pro_Bump_Support|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public static function setup_hooks() {
			add_action( 'plugins_loaded', function () {
				if ( function_exists( 'WFOB_Core' ) ) {
					remove_action( 'admin_notices', array( WFOB_Core(), 'wc_not_installed_notice' ) );
				}

			}, 999 );
			add_filter( 'wfob_should_load_core', function () {
				return false;
			} );

			add_action( 'admin_menu', function () {
				if ( self::is_admin_enabled() ) {
					return;
				}
				remove_submenu_page( 'woofunnels', 'wfob' );
			}, 999999 );

			add_action( 'plugins_loaded', function () {
				if ( class_exists( 'WFOB_WooFunnels_Support' ) ) {
					$inst = WFOB_WooFunnels_Support::get_instance();
					remove_action( 'admin_menu', array( $inst, 'add_menus' ), 81 );
					remove_filter( 'woofunnels_plugins_license_needed', array( $inst, 'add_license_support' ), 10 );
					remove_action( 'init', array( $inst, 'init_licensing' ), 12 );
					remove_action( 'woofunnels_licenses_submitted', array( $inst, 'process_licensing_form' ) );
					remove_action( 'woofunnels_deactivate_request', array( $inst, 'maybe_process_deactivation' ) );
					remove_action( 'before_woocommerce_init', array( WFOB_Core(), 'declare_hpos_compatibility' ) );
				}
			}, 999999 );
		}

		public static function maybe_load() {
			if ( self::is_module_exists() ) {
				return;
			}
			self::setup_hooks();
			require_once plugin_dir_path( WFFN_PRO_FILE ) . 'modules/order-bumps/woofunnels-order-bump.php';
		}

		public static function is_module_exists() {

			$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}

			return in_array( 'woofunnels-order-bump/woofunnels-order-bump.php', $active_plugins, true ) || array_key_exists( 'woofunnels-order-bump/woofunnels-order-bump.php', $active_plugins );


		}
	}


	WFFN_Pro_Modules::register( 'order-bumps/woofunnels-order-bump.php', 'WFFN_Pro_Bump_Support' );
}
