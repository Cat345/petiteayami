<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Gateway_Integration_Mollie_Gateway_Bancontact' ) ) {
	/**
	 * Class WFOCU_Mollie_Gateway_Bancontact
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Mollie_Gateway_Bancontact extends WFOCU_Gateway_Integration_Mollie_Gateway_Abstract {
		public $key = 'mollie_wc_gateway_bancontact';
		protected static $ins = null;


		/**
		 * WFOCU_Mollie_Gateway_Bancontact constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return null|WFOCU_Mollie_Gateway_Bancontact
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}
	}
}