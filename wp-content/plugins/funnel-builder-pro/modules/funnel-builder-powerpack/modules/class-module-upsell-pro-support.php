<?php
if ( ! class_exists( 'WFFN_Pro_Upsells_PRO_Support' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Upsells_PRO_Support {


		private static $ins = null;
		public $environment = null;

		/**
		 * WFFN_Pro_Upsells_PRO_Support constructor.
		 */
		public function __construct() {
		}

		/**
		 * @return WFFN_Pro_Upsells_PRO_Support|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public static function setup_hooks() {
			add_action( 'plugins_loaded', function () {
				if ( class_exists( 'WooFunnels_Support_WFOCU_PowerPack' ) ) {
					$inst = WooFunnels_Support_WFOCU_PowerPack::get_instance();
					remove_filter( 'woofunnels_plugins_license_needed', array( $inst, 'add_license_support' ), 10 );
					remove_action( 'init', array( $inst, 'init_licensing' ), 12 );
					remove_action( 'woofunnels_licenses_submitted', array( $inst, 'process_licensing_form' ) );
					remove_action( 'woofunnels_deactivate_request', array( $inst, 'maybe_process_deactivation' ) );
					remove_action( 'before_woocommerce_init', array( WooFunnels_UpStroke_PowerPack::instance(), 'declare_hpos_compatibility' ) );

				}
			}, 999999 );
		}

		public static function maybe_load() {
			if ( self::is_module_exists() ) {
				return;
			}
			self::setup_hooks();
			require_once plugin_dir_path( WFFN_PRO_FILE ) . 'modules/one-click-upsells-powerpack/woofunnels-upstroke-power-pack.php';
		}

		public static function is_module_exists() {
			$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
			}

			return in_array( 'woofunnels-upstroke-power-pack/woofunnels-upstroke-power-pack.php', $active_plugins, true ) || array_key_exists( 'woofunnels-upstroke-power-pack/woofunnels-upstroke-power-pack.php', $active_plugins );


		}
	}


	WFFN_Pro_Modules::register( 'one-click-upsells-powerpack/woofunnels-upstroke-power-pack.php', 'WFFN_Pro_Upsells_PRO_Support' );
}