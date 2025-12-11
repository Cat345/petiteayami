<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Gateway_Integration_Cheque' ) ) {
	/**
	 * WFOCU_Gateway_Integration_Cheque class.
	 *
	 * @extends WFOCU_Gateway
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Cheque extends WFOCU_Gateway {


		protected static $ins = null;
		public $key = 'cheque';
		public $token = false;

		/**
		 * Constructor
		 */
		public function __construct() {

			parent::__construct();
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			return true;

		}

		public function process_charge( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$is_successful = true;

			return $this->handle_result( $is_successful, '' );
		}


	}


	WFOCU_Gateway_Integration_Cheque::get_instance();
}
