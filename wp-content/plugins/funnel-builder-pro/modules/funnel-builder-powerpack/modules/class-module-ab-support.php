<?php
if ( ! class_exists( 'WFFN_Pro_AB_Support' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_AB_Support {


		private static $ins = null;
		public $environment = null;

		/**
		 * WFFN_Pro_Bump_Support constructor.
		 */
		public function __construct() {
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

		/**
		 * Returns true when the A/B test module's admin UI should be active.
		 * Fresh DB read — no globals, no cache.
		 */
		public static function is_admin_enabled() {
			if ( self::is_module_exists() ) {
				return true;
			}
			$modules = get_option( '_bwf_individual_modules', [] );

			return (bool) apply_filters( 'wffn_show_menu_ab_tests', isset( $modules['ab_tests'] ) && 'yes' === $modules['ab_tests'] );
		}

		public static function setup_hooks() {

			add_action( 'admin_menu', function () {
				if ( self::is_admin_enabled() ) {
					return;
				}
				remove_submenu_page( 'woofunnels', 'bwf_ab_tests' );
				if ( class_exists( 'BWFABT_Admin' ) ) {
					$inst = BWFABT_Admin::get_instance();
					remove_action( 'admin_menu', array( $inst, 'register_admin_menu' ), 100 );
				}
			}, 999 );

			add_action( 'plugins_loaded', function () {
				if ( class_exists( 'BWFABT_Admin' ) ) {

					$inst_support = BWFABT_WooFunnels_Support::get_instance();
					remove_filter( 'woofunnels_plugins_license_needed', array( $inst_support, 'add_license_support' ), 10 );
					remove_action( 'init', array( $inst_support, 'init_licensing' ), 12 );
					remove_action( 'woofunnels_licenses_submitted', array( $inst_support, 'process_licensing_form' ) );
					remove_action( 'woofunnels_deactivate_request', array( $inst_support, 'maybe_process_deactivation' ) );
				}
			}, 999999 );
		}

		public static function maybe_load() {
			if ( self::is_module_exists() ) {
				return;
			}
			self::setup_hooks();

			require_once plugin_dir_path( WFFN_PRO_FILE ) . 'modules/woofunnels-ab-tests/woofunnels-ab-tests.php';
		}

		public static function is_module_exists() {

			$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}

			return in_array( 'woofunnels-ab-tests/woofunnels-ab-tests.php', $active_plugins, true ) || array_key_exists( 'woofunnels-ab-tests/woofunnels-ab-tests.php', $active_plugins );


		}
	}


	WFFN_Pro_Modules::register( 'woofunnels-ab-tests/woofunnels-ab-tests.php', 'WFFN_Pro_AB_Support' );
}