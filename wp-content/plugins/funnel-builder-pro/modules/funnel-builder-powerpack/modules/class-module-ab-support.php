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

		public static function setup_hooks() {

			add_action( 'admin_head', function () {
				global $submenu, $woofunnels_menu_slug;
				$modules   = get_option( '_bwf_individual_modules', [] );
				$ab_exists = apply_filters( 'wffn_show_menu_ab_tests', ( isset( $modules['ab_tests'] ) && 'yes' === $modules['ab_tests'] ) );

				foreach ( $submenu as $key => $men ) {
					if ( $woofunnels_menu_slug !== $key ) {
						continue;
					}

					foreach ( $men as $k => $d ) {
						if ( 'admin.php?page=bwf_ab_tests' === $d[2] && ! $ab_exists ) {

							unset( $submenu[ $key ][ $k ] );
						}
					}
				}
			} );

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