<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
include_once __DIR__ . '/class-wffn-export-contact.php';
/**
 * Class WFFN_Export_Contact
 */
if ( ! class_exists( 'WFFN_Export_Contact_Global' ) ) {
	class WFFN_Export_Contact_Global extends WFFN_Export_Contact {
		protected static $slug = 'global_contacts';
		private static $ins = null;

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_global_contact_export';

		public static function get_slug() {

			return self::$slug;
		}

		public function action_hook() {
			return self::$ACTION_HOOK;
		}


	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core()->exporter->register( WFFN_Export_Contact_Global::get_instance() );
	}
}