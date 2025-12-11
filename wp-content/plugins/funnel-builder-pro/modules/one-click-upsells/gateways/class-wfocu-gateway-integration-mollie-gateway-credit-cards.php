<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Gateway_Integration_Mollie_Gateway_Credit_Cards' ) ) {
	/**
	 * Class WFOCU_Mollie_Gateway_Credit_Cards
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Mollie_Gateway_Credit_Cards extends WFOCU_Gateway_Integration_Mollie_Gateway_Abstract {
		public $key = 'mollie_wc_gateway_creditcard';
		protected static $ins = null;

		/**
		 * WFOCU_Mollie_Gateway_Credit_Cards constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return null|WFOCU_Mollie_Gateway_Credit_Cards
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}
	}
}