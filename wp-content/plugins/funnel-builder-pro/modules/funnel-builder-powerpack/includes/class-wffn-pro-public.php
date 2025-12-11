<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

/**
 * Funnel Public facing functionality
 * Class WFFN_Pro_Public
 */
if ( ! class_exists( 'WFFN_Pro_Public' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Pro_Public {

		private static $ins = null;
		public $environment = null;

		/**
		 * WFFN_Pro_Public constructor.
		 */
		public function __construct() {
		}

		/**
		 * @return WFFN_Pro_Public|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}
	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'public', 'WFFN_Pro_Public' );
	}
}