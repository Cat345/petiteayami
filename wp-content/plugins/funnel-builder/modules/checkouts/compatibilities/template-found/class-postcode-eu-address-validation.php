<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_Compatibility_With_Postcode_Eu_Address_Validation' ) ) {
	/**
	 * Compatibility for the standalone Postcode.eu Address Validation plugin.
	 *
	 * Plugin Name: Postcode.eu Address Validation
	 * Plugin URI: https://wordpress.org/plugins/postcode-eu-address-validation/
	 *
	 * The Postcode.eu autocomplete library is enqueued on \WC_Checkout only via
	 * PostcodeNl\AddressAutocomplete\Main::enqueueScripts. On FunnelKit's custom
	 * checkout page that hook needs to be re-registered so the lookup script fires.
	 * This handler is kept separate from the WP Overnight NL Postcode Checker compat
	 * (class-wcnl-postcode.php) so it loads for stores running Postcode.eu standalone,
	 * without pulling in the WP-Overnight-specific NL field logic.
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Postcode_Eu_Address_Validation {
		private $main_object       = null;
		protected static $instance = null;

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'actions' ) );
		}

		public function actions() {
			try {
				$this->main_object = WFACP_Common::remove_actions( 'wp_enqueue_scripts', 'PostcodeNl\AddressAutocomplete\Main', 'enqueueScripts' );
				WFACP_Common::add_actions( 'wp_enqueue_scripts', 'enqueueScripts', $this->main_object );
			} catch ( Exception | Error $e ) {
			}
		}
	}

	WFACP_Compatibility_With_Postcode_Eu_Address_Validation::get_instance();
}
