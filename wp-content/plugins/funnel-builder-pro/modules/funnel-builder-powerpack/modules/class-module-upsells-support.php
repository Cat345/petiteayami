<?php
if (! class_exists( 'WFFN_Pro_Upsells_Support' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Upsells_Support {


		private static $ins = null;
		public $environment = null;

		/**
		 * WFFN_Pro_Upsells_Support constructor.
		 */
		public function __construct() {
		}

		/**
		 * @return WFFN_Pro_Upsells_Support|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public static function setup_hooks() {
			add_action( 'plugins_loaded', function () {
				if ( function_exists( 'WFOCU_Core' ) ) {
					remove_action( 'admin_notices', array( WFOCU_Core(), 'wc_not_installed_notice' ) );
				}


			}, 999 );
			add_filter( 'wfocu_should_load_core', function () {
				return false;
			} );
			add_filter( 'wfocu_override_wizard', function () {
				return true;
			} );
			add_action( 'admin_head', function () {

				global $submenu, $woofunnels_menu_slug;
				foreach ( $submenu as $key => $men ) {
					if ( $woofunnels_menu_slug !== $key ) {
						continue;
					}

					$modules       = get_option( '_bwf_individual_modules', [] );
					$upsell_exists = apply_filters( 'wffn_show_menu_upsell', ( isset( $modules['upsells'] ) && 'yes' === $modules['upsells'] ) );

					foreach ( $men as $k => $d ) {
						if ( 'admin.php?page=upstroke' === $d[2] && ! $upsell_exists ) {
							unset( $submenu[ $key ][ $k ] );
						}
					}
				}

			} );

			add_action( 'plugins_loaded', function () {
				if ( function_exists( 'WFOCU_Core' ) ) {

					$inst = WFOCU_Core()->support;
					remove_action( 'admin_menu', array( $inst, 'add_menus' ), 81 );
					remove_filter( 'woofunnels_plugins_license_needed', array( $inst, 'add_license_support' ), 10 );
					remove_action( 'init', array( $inst, 'init_licensing' ), 12 );
					remove_action( 'admin_init', array( $inst, 'maybe_handle_license_activation_wizard' ), 1 );
					remove_action( 'woofunnels_licenses_submitted', array( $inst, 'process_licensing_form' ) );
					remove_action( 'woofunnels_deactivate_request', array( $inst, 'maybe_process_deactivation' ) );
					remove_action( 'before_woocommerce_init', array( WFOCU_Core(), 'declare_hpos_compatibility' ) );
				}
			}, 999999 );
		}

		public static function maybe_load() {
			if ( self::is_module_exists() ) {
				return;
			}
			self::setup_hooks();
			require_once plugin_dir_path( WFFN_PRO_FILE ) . 'modules/one-click-upsells/woofunnels-upstroke-one-click-upsell.php';
		}

		public static function is_module_exists() {

			$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}

			return in_array( 'woofunnels-upstroke-one-click-upsell/woofunnels-upstroke-one-click-upsell.php', $active_plugins, true ) || array_key_exists( 'woofunnels-upstroke-one-click-upsell/woofunnels-upstroke-one-click-upsell.php', $active_plugins );


		}
	}


	WFFN_Pro_Modules::register( 'one-click-upsells/woofunnels-upstroke-one-click-upsell.php', 'WFFN_Pro_Upsells_Support' );
}