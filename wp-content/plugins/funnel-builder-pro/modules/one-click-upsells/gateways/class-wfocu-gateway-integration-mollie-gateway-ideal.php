<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Gateway_Integration_Mollie_Gateway_Ideal' ) ) {
	/**
	 * Class WFOCU_Mollie_Gateway_Ideal
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Mollie_Gateway_Ideal extends WFOCU_Gateway_Integration_Mollie_Gateway_Abstract {
		public $key = 'mollie_wc_gateway_ideal';
		protected static $ins = null;

		/**
		 * WFOCU_Mollie_Gateway_Ideal constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFOCU_Mollie_Gateway_Ideal|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

	}
}