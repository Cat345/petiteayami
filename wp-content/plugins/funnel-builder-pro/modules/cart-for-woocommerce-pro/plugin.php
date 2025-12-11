<?php
/**
 * Plugin Name: Cart For WooCommerce Pro
 * Plugin URI: https://funnelkit.com/
 * Description: A WooCommerce Cart plugin from FunnelKit.
 * Version: 0.9.0
 * Author: FunnelKit
 */

namespace FKCart\Pro;
if ( ! class_exists( '\FKCart\Pro\Plugin' ) ) {
	#[\AllowDynamicProperties]
	class Plugin {
		private static $instance = null;

		private function __construct() {
			add_action( 'funnelkit_cart_loaded', [ $this, 'include_core' ], 15 );
			add_action( 'wffn_pro_loaded', array( $this, 'load_exporters' ), 11 );
		}

		public function include_core() {
			if ( ! class_exists( 'WFFN_Core' ) ) {
				/** If no FB lite plugin found */
				return;
			}

			define('FKCART_PRO_PATH',__DIR__);

			include __DIR__ . '/include/upsells.php';
			include __DIR__ . '/include/rewards.php';
			include_once __DIR__ . '/include/fkcart-db-migrator.php';
			include __DIR__ . '/include/special-add-on.php';
			Upsells::getInstance();
			Rewards::getInstance();
			Special_Add_On::getInstance();
			add_action( 'rest_api_init', [ $this, 'init_rest_api' ], 9 );
		}

		/**
		 * Includes conversion actions files
		 */
		public function load_exporters() {
			// load all the trigger files automatically
			require_once __DIR__ . '/include/fkcart-export-cart-conversion.php';
		}

		public function init_rest_api() {
			include __DIR__ . '/rest/conversions.php';
		}

		/**
		 * @return Plugin
		 */
		public static function getInstance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public static function valid_l() {
			if ( ! method_exists( \WFFN_Core()->admin, 'get_license_config' ) ) {
				return true;
			}
			$License               = \WooFunnels_licenses::get_instance();
			$License->plugins_list = null;
			$License->get_plugins_list();
			$state = self::get_current_app_state();
			if ( in_array( $state, [ 'pro_without_license', 'license_expired' ], true ) ) {
				return false;
			}

			return true;
		}


		public static function get_current_app_state() {
			$license_config = \WFFN_Core()->admin->get_license_config( true );


			if ( isset( $license_config['f']['ed'] ) && $license_config['f']['ed'] ) {
				$ed = $license_config['f']['ed'];

				if ( strtotime( 'now' ) > strtotime( $ed ) ) {
					if ( strtotime( 'now' ) - strtotime( $ed ) < $license_config['gp'][0] * DAY_IN_SECONDS ) {
						return 'license_expired_on_grace_period';
					}

					return 'license_expired';
				}
			}
			if ( defined( 'WFFN_VERSION' ) && version_compare( WFFN_VERSION, '3.9.1', '>' ) ) {
				$license_config = \WFFN_Core()->admin->get_license_config( false, false );

			} else {

				$license_config = \WFFN_Core()->admin->get_license_config();

			}

			if ( isset( $license_config['f']['la'] ) && $license_config['f']['la'] === true ) {
				return 'pro';
			}
			$license_config = \WFFN_Core()->admin->get_license_config();

			if ( isset( $license_config['f']['ad'] ) && $license_config['f']['ad'] ) {
				$ad = $license_config['f']['ad'];

				if ( strtotime( 'now' ) - strtotime( $ad ) < $license_config['gp'][1] * DAY_IN_SECONDS ) {
					return 'pro_without_license_on_grace_period';
				}
			}

			return 'pro_without_license';
		}
	}

	Plugin::getInstance();
}