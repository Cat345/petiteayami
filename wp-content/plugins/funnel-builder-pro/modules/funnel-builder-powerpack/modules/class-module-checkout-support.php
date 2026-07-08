<?php
if (! class_exists( 'WFFN_Pro_Checkout_Support' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Checkout_Support {


		private static $ins = null;
		public $environment = null;


		/**
		 * @return WFFN_Pro_Checkout_Support|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public static function is_admin_enabled() {
			if ( self::is_module_exists() ) {
				return true;
			}
			$modules = get_option( '_bwf_individual_modules', [] );
			return (bool) apply_filters( 'wffn_show_menu_checkout', isset( $modules['checkout'] ) && 'yes' === $modules['checkout'] );
		}

		public static function setup_hooks() {
			add_action( 'plugins_loaded', function () {
				if ( function_exists( 'WFACP_Core' ) ) {
					remove_action( 'admin_notices', array( WFACP_Core(), 'wc_not_installed_notice' ) );
				}


			}, 999 );
			add_filter( 'wfacp_should_load_core', function () {
				return false;
			} );

			add_filter( 'wfacp_override_wizard', function () {
				return true;
			} );

			add_action( 'admin_menu', function () {
				if ( self::is_admin_enabled() ) {
					return;
				}
				global $submenu;
				foreach ( $submenu as $parent_slug => $men ) {
					foreach ( $men as $d ) {
						if ( 'wfacp' === $d[2] ) {
							remove_submenu_page( $parent_slug, $d[2] );
							break 2;
						}
					}
				}
			}, 999999 );

			add_action( 'plugins_loaded', function () {
				if ( class_exists( 'WFACP_Autonami' ) ) {
					$inst = WFACP_Autonami::get_instance();
					remove_action( 'admin_menu', array( $inst, 'register_admin_menu' ), 90 );

					$inst = WFACP_Core()->support;
					remove_action( 'admin_menu', array( $inst, 'add_menus' ), 81 );
					remove_filter( 'woofunnels_plugins_license_needed', array( $inst, 'add_license_support' ), 10 );
					remove_action( 'init', array( $inst, 'init_licensing' ), 12 );
					remove_action( 'admin_init', array( $inst, 'maybe_handle_license_activation_wizard' ), 1 );
					remove_action( 'woofunnels_licenses_submitted', array( $inst, 'process_licensing_form' ) );
					remove_action( 'woofunnels_deactivate_request', array( $inst, 'maybe_process_deactivation' ) );
					remove_action( 'before_woocommerce_init', array( WFACP_Core(), 'declare_hpos_compatibility' ) );
				}

			}, 999999 );
		}

		public static function maybe_load() {
			if ( self::is_module_exists() ) {
				return;
			}
			self::setup_hooks();
			require_once plugin_dir_path( WFFN_PRO_FILE ) . 'modules/checkout/woofunnels-aero-checkout.php';
		}

		public static function is_module_exists() {
			$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}

			return in_array( 'woofunnels-aero-checkout/woofunnels-aero-checkout.php', $active_plugins, true ) || array_key_exists( 'woofunnels-aero-checkout/woofunnels-aero-checkout.php', $active_plugins );


		}
	}


	WFFN_Pro_Modules::register( 'checkout/woofunnels-aero-checkout.php', 'WFFN_Pro_Checkout_Support' );
}

