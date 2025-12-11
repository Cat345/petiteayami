<?php

defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_WC_Thankyou_Pages' ) ) {
	/**
	 * Funnel pro optin page module
	 * Class WFFN_Pro_WC_Thankyou_Pages
	 */
	class WFFN_Pro_WC_Thankyou_Pages {

		private static $ins = null;

		/**
		 * WFFN_Pro_Optin_Pages constructor.
		 */
		public function __construct() {
			add_action( 'after_setup_theme', [ $this, 'load_rules' ], 12 );

		}

		/**
		 * @return WFFN_Pro_WC_Thankyou_Pages|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function load_rules() {
			require __DIR__ . '/includes/class-wfty-rules.php';
		}

		public function get_path() {
			return __DIR__;
		}

	}

	if ( class_exists( 'WFFN_Pro_WC_Thankyou_Pages' ) ) {
		WFTP_PRO_Core::register( 'pro_wc_thankyou', 'WFFN_Pro_WC_Thankyou_Pages' );
	}
}