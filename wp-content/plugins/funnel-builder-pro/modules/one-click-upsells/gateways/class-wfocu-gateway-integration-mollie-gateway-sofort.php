<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Gateway_Integration_Mollie_Gateway_Sofort' ) ) {
	/**
	 * Class WFOCU_Mollie_Gateway_Sofort
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Mollie_Gateway_Sofort extends WFOCU_Gateway_Integration_Mollie_Gateway_Abstract {
		public $key = 'mollie_wc_gateway_sofort';
		protected static $ins = null;

		/**
		 * WFOCU_Mollie_Gateway_Sofort constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFOCU_Mollie_Gateway_Sofort|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

	}
}