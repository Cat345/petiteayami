<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Gateway_Integration_WFOCU_Test' ) ) {
	/**
	 * WFOCU_Gateway_Integration_WFOCU_Test class.
	 *
	 * @extends WFOCU_Gateway
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_WFOCU_Test extends WFOCU_Gateway {


		protected static $ins = null;
		public $key = 'wfocu_test';
		public $token = false;

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->refund_supported = true;

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

		/**
		 * If this gateway is used in the payment for the primary order that means we can run our funnels and we do not need to check for further enable.
		 * @return true
		 */
		public function is_enabled( $order = false ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			return true;
		}

		public function process_charge( $order ) {

			$is_successful = true;

			$order_number = $this->get_order_number( $order );
			WFOCU_Core()->data->set( '_transaction_id', 'wfocu_test_txn_' . $order_number );

			return $this->handle_result( $is_successful, '' );
		}

		/**
		 * Handling refund offer request
		 *
		 * @param $order
		 *
		 * @return bool|string
		 */
		public function process_refund_offer( $order ) {
			$refund_data = $_POST;  // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order_id    = WFOCU_WC_Compatibility::get_order_id( $order );
			if ( $order_id ) {
				$offer_id  = isset( $refund_data['offer_id'] ) ? $refund_data['offer_id'] : '';
				$refund_id = 'wfocu_test_rfnd_' . $order_id . '_' . $offer_id;

				return $refund_id;
			}

			return false;

		}
	}

	WFOCU_Gateway_Integration_WFOCU_Test::get_instance();
}
