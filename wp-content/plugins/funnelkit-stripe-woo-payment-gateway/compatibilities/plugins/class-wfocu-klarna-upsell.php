<?php



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Plugin_Integration_Fkwcs_Klarna' ) && class_exists( 'WFOCU_Gateway' ) ) {
	class WFOCU_Plugin_Integration_Fkwcs_Klarna extends FKWCS_LocalGateway_Upsell {
		protected static $instance           = null;
		public $key                          = 'fkwcs_stripe_klarna';
		protected $payment_method_type       = 'klarna';
		protected $stripe_verify_js_callback = 'confirmKlarnaPayment';

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * WooCommerce lists Puerto Rico as its own country code (PR), but Stripe and Klarna
		 * treat it as part of the United States: Klarna rejects a purchase_country of PR, so
		 * the billing country must be sent as US with PR as the state.
		 *
		 * @param array     $payment_method
		 * @param \WC_Order $order
		 *
		 * @return array
		 */
		protected function get_confirm_payment_method_data( $payment_method, $order ) {
			if ( isset( $payment_method['billing_details']['address']['country'] ) && 'PR' === $payment_method['billing_details']['address']['country'] ) {
				$payment_method['billing_details']['address']['country'] = 'US';
				$payment_method['billing_details']['address']['state']   = 'PR';
			}

			return $payment_method;
		}
	}

	WFOCU_Plugin_Integration_Fkwcs_Klarna::get_instance();
}
