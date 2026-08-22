<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fallback declarations for the license classes relocated to the premium plugin.
 *
 * @deprecated The real implementation ships with Funnel Builder Pro (PowerPack).
 *
 * The free plugin carries no license logic (WP.org review: the free zip may not
 * ship update-checker code). The premium plugin loads the real classes itself —
 * by direct require or through its own autoloader. Premium builds released
 * before the relocation, however, call WooFunnels_licenses without a
 * class_exists guard, and several of those call sites run on the storefront
 * (Order Bumps bump filtering during checkout totals, One-Click Upsells funnel
 * resolution, Cart Pro's license gate) — dropping the name outright would fatal
 * a customer's checkout rather than merely degrade licensing.
 *
 * This file is reached only through the fallback autoloader that Funnel Builder
 * registers late on plugins_loaded (see WFFN_Core::load_license_fallback_autoloader),
 * behind every premium loader — so by the time it fires, nothing on the site
 * provides the real classes and these inert stand-ins take their place. Each
 * behaves exactly like its real counterpart holding no stored license data: the
 * woofunnels_plugins_license_needed filter still populates the plugin list
 * (premium support classes register through it), but no entry ever carries
 * activation data, so every consumer reads the unlicensed state.
 *
 * Delete once the minimum supported premium release always loads its own
 * license classes.
 */
if ( ! class_exists( 'WooFunnels_Licenses' ) ) {
	#[AllowDynamicProperties]
	class WooFunnels_Licenses {

		protected static $instance;
		public $plugins_list;

		/**
		 * @return WooFunnels_Licenses
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Same population as the real class, minus the merge of stored
		 * activation data (there is none without the real controller).
		 */
		public function get_plugins_list() {
			$this->plugins_list = apply_filters( 'woofunnels_plugins_license_needed', array() );
		}

		/**
		 * @param string $type all|woofunnels|autonami
		 *
		 * @return array
		 */
		public function get_data( $type = 'all' ) {
			if ( is_null( $this->plugins_list ) ) {
				$this->get_plugins_list();
			}
			$plugins = is_array( $this->plugins_list ) ? $this->plugins_list : array();

			if ( 'autonami' === $type || 'woofunnels' === $type ) {
				$autonami = array_filter(
					$plugins,
					function ( $license ) {
						return isset( $license['plugin'] ) && false !== strpos( $license['plugin'], 'Automations' );
					}
				);

				return ( 'autonami' === $type ) ? $autonami : array_diff_key( $plugins, $autonami );
			}

			return $plugins;
		}

		public function is_expired( $license_data ) {
			if ( isset( $license_data['_data'] ) && isset( $license_data['_data']['expired'] ) && ! empty( $license_data['_data']['expired'] ) ) {
				return true;
			}

			return false;
		}

		public function is_disabled( $license_data ) {
			if ( empty( $license_data['_data']['activated'] ) ) {
				return true;
			}

			return false;
		}

		/** No license data ever qualifies, so there is nothing to show. */
		public function maybe_show_invalid_license_error() {
		}

		public function show_invalid_license_notice( $plugins, $type ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- signature preserved from the real class.
		}

		public function license_url( $type ) {
			if ( 'woofunnels' === $type && defined( 'WFFN_VERSION' ) ) {
				return admin_url( '/admin.php?page=bwf&path=/settings/woofunnels_general_settings' );
			}
			if ( 'autonami' === $type && defined( 'BWFAN_VERSION' ) && version_compare( BWFAN_VERSION, '2.0', '>=' ) ) {
				return admin_url( '/admin.php?page=autonami&path=%2Fsettings' );
			}

			return admin_url( 'admin.php?page=woofunnels&tab=licenses' );
		}

		public function get_secret_license_key( $key ) {
			$last_six              = substr( $key, - 6 );
			$initial_string        = str_replace( $last_six, '', $key );
			$initial_string_length = strlen( $initial_string );

			return str_repeat( 'x', $initial_string_length ) . $last_six;
		}
	}
}

if ( ! class_exists( 'WooFunnels_License_check' ) ) {
	/** Inert fallback: no plugins are registered for update checking. */
	class WooFunnels_License_check {
		public static function get_plugins() {
			return array();
		}
	}
}

if ( ! class_exists( 'WooFunnels_License_Controller' ) ) {
	/** Inert fallback: no stored activation data exists without the real controller. */
	class WooFunnels_License_Controller {
		public static function get_plugins() {
			return array();
		}
	}
}
