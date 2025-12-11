<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Gateway' ) ) {
	/**
	 * Abstract Class for all the Gateway Support Class
	 * Class WFOCU_Gateway
	 */
	abstract class WFOCU_Gateway extends WFOCU_SV_API_Base {


		public $amount = 0;
		public $token = null;
		public $refund_supported = false;
		protected $key = '';
		public $supports = [];

		public function __construct() {

		}


		/**
		 * @return WC_Payment_Gateway
		 */
		public function get_wc_gateway() {
			global $woocommerce;
			$gateways = $woocommerce->payment_gateways->payment_gateways();

			return $gateways[ $this->key ];
		}

		public function get_amount() {
			return $this->amount;
		}

		public function set_amount( $amount ) {
			$this->amount = $amount;
		}

		public function get_key() {
			return $this->key;
		}

		/**
		 * This function checks for the need to do the tokenization.
		 * We have to fetch the funnel to decide whether to tokenize the user or not.
		 * @return int|false funnel ID on success false otherwise
		 *
		 */
		public function should_tokenize() {

			return WFOCU_Core()->data->is_funnel_exists();
		}


		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			return false;

		}


		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function get_token( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			return false;

		}

		/**
		 * Charge the upsell and capture payments
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function process_charge( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			return false;

		}

		public function handle_result( $result, $message = '' ) {
			if ( $result ) {
				WFOCU_Core()->data->set( '_transaction_status', 'successful' );

				WFOCU_Core()->data->set( '_transaction_message', __( 'Your order is updated.', 'woofunnels-upstroke-one-click-upsell' ) );

			} else {
				WFOCU_Core()->data->set( '_transaction_status', 'failed' );

				WFOCU_Core()->data->set( '_transaction_message', ( ! empty( $message ) ) ? $message : __( 'Unable to process at the moment.', 'woofunnels-upstroke-one-click-upsell' ) );

			}

			return $result;
		}

		/**
		 * @param WC_Order $order
		 *
		 * @return bool
		 */
		public function is_enabled( $order = false ) {
			$get_chosen_gateways = WFOCU_Core()->data->get_option( 'gateways' );
			if ( is_array( $get_chosen_gateways ) && in_array( $this->key, $get_chosen_gateways, true ) ) {

				return apply_filters( 'wfocu_front_payment_gateway_integration_enabled', true, $order );
			}

			return false;
		}


		public function get_order_number( $order ) {

			$get_offer_id = WFOCU_Core()->data->get( 'current_offer' );

			if ( ! empty( $get_offer_id ) ) {
				return apply_filters( 'wfocu_payments_get_order_number', WFOCU_WC_Compatibility::get_order_id( $order ) . '_' . $get_offer_id, $this );
			} else {
				return WFOCU_WC_Compatibility::get_order_id( $order );
			}

		}

		/**
		 * Tell the system to run without a token or not
		 * @return bool
		 */
		public function is_run_without_token() {
			return false;
		}

		/**
		 * Allow gateways to declare whether they support offer refund
		 *
		 * @param WC_Order $order
		 *
		 * @return bool
		 */
		public function is_refund_supported( $order = false ) {

			if ( $this->refund_supported ) {

				return apply_filters( 'wfocu_payment_gateway_refund_supported', true, $order );
			}

			return false;
		}

		/**
		 * Processing refund request
		 *
		 * @param $order
		 *
		 * @return bool
		 */
		public function process_refund_offer( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			return false;
		}

		/**
		 * Providing refund button html for amdin order edit page
		 *
		 * @param $funnel_id
		 * @param $offer_id
		 * @param $total_charge
		 * @param $transaction_id
		 * @param $refunded
		 *
		 * @return string
		 */
		public function get_refund_button_html( $funnel_id, $offer_id, $total_charge, $transaction_id, $refunded, $event_id ) {
			$button_class = ( $refunded ) ? 'disabled' : 'wfocu-refund';
			$button_text  = ( $refunded ) ? __( 'Refunded', 'woofunnels-upstroke-one-click-upsell' ) : __( 'Refund', 'woofunnels-upstroke-one-click-upsell' );

			$button_html = sprintf( '<a href="javascript:void(0);" data-event_id="%s" data-funnel_id="%s" data-offer_id="%s" data-amount="%s" data-txn="%s" class="button %s">%s</a>', $event_id, $funnel_id, $offer_id, $total_charge, $transaction_id, $button_class, $button_text );

			return $button_html;
		}

		/**
		 * Adding common order in a standard format for offer refunds
		 *
		 * @param $order
		 * @param $amnt
		 * @param $refund_id
		 * @param $offer_id
		 * @param $refund_reason
		 */
		public function wfocu_add_order_note( $order, $amnt, $refund_id, $offer_id, $refund_reason ) {
			/* translators: 1) dollar amount 2) transaction id 3) refund message */
			$refund_note = sprintf( __( 'Refunded %1$s Refund ID: %2$s <br/>Offer: %3$s(#%4$s) %5$s', 'woofunnels-upstroke-one-click-upsell' ), $amnt, $refund_id, get_the_title( $offer_id ), $offer_id, $refund_reason );

			$order->add_order_note( $refund_note );
		}

		/**
		 *  Creating transaction test/URL
		 *
		 * @param $transaction_id
		 * @param $order_id
		 *
		 * @return string
		 */
		public function get_transaction_link( $transaction_id, $order_id ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,     VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			return $transaction_id;
		}


		public function handle_client_error() {
			$get_error = $this->get_client_error();
			if ( ! empty( $get_error ) ) {
				throw new WFOCU_Payment_Gateway_Exception( $get_error, 105 );
			}
		}

		public function get_client_error() {
			$get_package = WFOCU_Core()->data->get( '_upsell_package' );
			if ( isset( $get_package['_client_error'] ) ) {
				return $get_package['_client_error'];
			}

			return '';
		}


		public function format_failed_note( $order_note ) {
			$heading     = __( 'Offer Failed Reason', 'woofunnels-upstroke-one-click-upsell' );
			$reason_text = __( 'Reason:', 'woofunnels-upstroke-one-click-upsell' );
			/*
			 * Check if the string contains a Reason
			 */
			if ( strpos( $order_note, $reason_text ) !== false ) {
				// Extract the part after "Reason:"
				$parts         = explode( $reason_text, $order_note, 2 );
				$reason_detail = trim( $parts[1] );
			} else {
				/*
				 * If no reason is found, assume the entire string is the reason
				 */
				$reason_detail = trim( $order_note );
			}

			/*
			 * Ensure the reason ends with a period
			 */
			if ( substr( $reason_detail, - 1 ) !== '.' ) {
				$reason_detail .= '.';
			}
			$reason_detail = htmlspecialchars_decode( $reason_detail );

			$svg_icon    = WFOCU_PLUGIN_URL . '/admin/assets/img/icon_failed.svg';
			$reason_base = '<div style="display:flex;align-items:center;margin-bottom:4px;gap:4px;padding-left:20px !important;background: url(' . esc_url( $svg_icon ) . ') no-repeat left !important;">
							<strong style="font-size:13px;">' . $heading . '</strong>
	    					</div><strong>' . $reason_text . '</strong> ' . $reason_detail;

			return $reason_base;
		}

		/**
		 * Handle API Error during the client integration
		 *
		 * @param $order_note string Order note to add
		 * @param $log string
		 * @param $order WC_Order
		 */
		public function handle_api_error( $order_note, $log, $order, $create_failed_order = false ) {
			$reason_base = '';
			if ( ! empty( $order_note ) ) {

				$reason_base = $this->format_failed_note( $order_note );

				/**
				 * Add stripe recovery text
				 * 1. check gateway related error
				 * 2. Check if fk stripe not active
				 * 3. If another stripe run on site
				 */
				if ( class_exists( 'WFFN_Core' ) && ! class_exists( '\FKWCS_Gateway_Stripe' ) && in_array( $order->get_payment_method(), [
						'stripe',
						'stripe_cc',
						'stripe_applepay',
						'stripe_googlepay',
						'stripe_sepa'
					], true ) ) {
					$stripe_link = admin_url( '/admin.php?page=bwf&path=/settings/stripe' );
					$stripe_text = __( '<strong>Tip: </strong>We recommend using FunnelKit Stripe Gateway for better compatibility with upsells. <a target="_blank" href="' . $stripe_link . '">Click here to activate</a>', 'woofunnels-upstroke-one-click-upsell' );
					$reason_base .= sprintf( __( '<div style="margin:8px 0px">%s</div> ', 'woofunnels-upstroke-one-click-upsell' ), $stripe_text );
				}

				$order->add_order_note( $reason_base );
			}
			if ( ! empty( $log ) ) {
				WFOCU_Core()->log->log( 'Order #' . $order->get_id() . " - " . print_r( $log, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}
			if ( true === $create_failed_order ) {
				$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );
				if ( ! empty( $reason_base ) && ! empty( WFOCU_Core()->public->failed_order ) && ! empty( WFOCU_Core()->public->failed_order->get_id() ) ) {
					WFOCU_Core()->public->failed_order->add_order_note( $reason_base );
				}

				wp_send_json( apply_filters( 'wfocu_modify_error_json_response', array(
					'result'   => 'error',
					'response' => $data,
				), $order ) );
			}
		}

		public function supports( $feature ) {
			return in_array( $feature, $this->supports, true );
		}

		/**
		 * Default method to modify the upsell skip reason and order note.
		 * Individual gateway classes can override this if needed.
		 *
		 * @param WC_Order $order
		 * @param int $skip_key
		 * @param array $reason_messages
		 * @param string $edit_link
		 * @param string $contact_support
		 * @param string $upsell_s_link
		 *
		 * @return array
		 */
		public function filter_upsell_skip_reason( $order, $skip_key, $reason_messages, $edit_link, $contact_support, $upsell_s_link ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			// Use default reason message if available
			$custom_note = isset( $reason_messages[ $skip_key ] ) ? $reason_messages[ $skip_key ] : '';

			return [
				'skip_id' => $skip_key,
				'note'    => $custom_note
			];
		}
	}
}