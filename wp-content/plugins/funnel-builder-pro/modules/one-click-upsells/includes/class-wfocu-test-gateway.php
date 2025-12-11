<?php
/**
 * Author FunnelKit.
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( 'WC_Gateway_WFOCU_Test' ) ) {
	/**
	 * WFOCU Test Gateway.
	 *
	 * Provides a Test gateway to test woofunnel's funnels.
	 *
	 * @class        WC_Gateway_WFOCU_Test
	 * @extends        WC_Payment_Gateway
	 */
	class WC_Gateway_WFOCU_Test extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public $instructions = '';
		public $enable_for_methods = array();
		public $enable_for_virtual = true;

		public function __construct() {
			// Setup general properties
			$this->setup_properties();

			// Load the settings
			$this->init_form_fields();
			$this->init_settings();
			$this->enabled = 'yes';
			// Get settings
			$this->title       = __( 'Test Gateway By FunnelKit', 'woofunnels-upstroke-one-click-upsell' );
			$this->description = __( 'This gateway is registered by FunnelKit for testing Funnels. This is only visible to Admins and not end users,', 'woofunnels-upstroke-one-click-upsell' );
			$this->supports    = array(
				'products',
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions',
				'refunds',
			);
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'process_renewal_payment' ), 10, 2 );

		}

		/**
		 * Setup general properties for the gateway.
		 */
		protected function setup_properties() {
			$this->id                 = 'wfocu_test';
			$this->icon               = '';
			$this->method_title       = __( 'Test Gateway', 'woocommerce' );
			$this->method_description = '';
			$this->has_fields         = false;
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {

			$this->form_fields = array();
		}

		/**
		 * Init settings for gateways.
		 */
		public function init_settings() {

			$this->enabled = 'yes';
		}

		/**
		 * Check If The Gateway Is Available For Use.
		 *
		 * @return bool
		 */
		public function is_available() {

			$is_gateway_on = WFOCU_Core()->data->get_option( 'gateway_test' );


			if ( is_array( $is_gateway_on ) && count( $is_gateway_on ) > 0 && 'yes' === $is_gateway_on[0] ) {


				if ( ( defined( 'JETPACK__VERSION' ) && true === current_user_can( 'manage_options' ) ) || true === current_user_can( 'manage_woocommerce' ) ) {

					return parent::is_available();
				}

				return false;

			} else {
				return false;
			}
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			$order->payment_complete();

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		public function process_renewal_payment( $subscription, $order ) {
			$order->payment_complete();
		}

		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order     = wc_get_order( $order_id );
			$refund_id = 'wfocu_test_rfnd_' . $order_id;

			/* translators: 1) dollar amount 2) transaction id 3) refund message */
			$refund_note = sprintf( __( 'Refunded %1$s Refund ID: %2$s <br/>Reason: %3$s', 'woofunnels-upstroke-one-click-upsell' ), $amount, $refund_id, $reason );

			$order->add_order_note( $refund_note );

			return true;
		}

	}
}