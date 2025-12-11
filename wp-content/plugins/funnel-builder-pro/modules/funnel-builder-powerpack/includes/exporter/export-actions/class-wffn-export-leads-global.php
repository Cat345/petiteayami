<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
include_once __DIR__ . '/class-wffn-export-leads.php';
/**
 * Class WFFN_Export_Contact
 */
if ( ! class_exists( 'WFFN_Export_Leads_Global' ) ) {
	class WFFN_Export_Leads_Global extends WFFN_Export_Leads {
		protected static $slug = 'global_leads';
		private static $ins = null;
		/**
		 * Export action
		 *
		 * @var string
		 */
		protected static $ACTION_HOOK = 'bwf_funnel_leads_global';

		public function get_title() {
			return __( 'Leads', 'funnel-builder-powerpack' );
		}

		public function action_hook() {
			return self::$ACTION_HOOK;
		}

		public function __construct() {
			parent::__construct();
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public static function get_slug() {
			return self::$slug;
		}


	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core()->exporter->register( WFFN_Export_Leads_Global::get_instance() );
	}
}
