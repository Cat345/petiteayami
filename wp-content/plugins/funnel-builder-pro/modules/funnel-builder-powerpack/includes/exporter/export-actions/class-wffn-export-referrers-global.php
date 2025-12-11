<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class WFFN_Export_Referrer_Global
 */
if ( ! class_exists( 'WFFN_Export_Referrer_Global' ) ) {
	class WFFN_Export_Referrer_Global extends WFFN_Abstract_Exporter {
		protected static $slug = 'global_referrers';
		private static $ins = null;

		/**
		 * Export action
		 *
		 * @var string
		 */

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		protected static $ACTION_HOOK = 'bwf_funnel_global_contact_referrers';

		public static function get_slug() {

			return self::$slug;
		}

		public function action_hook() {
			return self::$ACTION_HOOK;
		}

	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core()->exporter->register( WFFN_Export_Referrer_Global::get_instance() );
	}
}