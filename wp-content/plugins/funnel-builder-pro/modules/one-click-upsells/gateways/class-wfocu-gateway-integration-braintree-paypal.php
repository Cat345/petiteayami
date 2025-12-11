<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Gateway_Integration_Braintree_PayPal' ) ) {
	/**
	 * WFOCU_Gateway_Integration_Braintree_PayPal class.
	 *
	 * @extends WFOCU_Gateway
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Braintree_PayPal extends WFOCU_Gateway {


		protected static $ins = null;
		public $token = false;
		public $cc_call_response = false;
		public $maybe_collect_response = false;
		public $unset_opaque_value = false;
		protected $key = 'braintree_paypal';

		/**
		 * Constructor
		 */
		public function __construct() {

			parent::__construct();

			/**
			 * Telling Authorize gateway to force tokenize and do not ask user as an option during checkout
			 */
			add_filter( 'wc_payment_gateway_' . $this->get_key() . '_tokenization_forced', array( $this, 'maybe_force_tokenization' ) );

			/**
			 * For a non logged in mode when accept js is turned off, we just need to tokenize the card after the main charge gets completed
			 */
			add_action( 'woocommerce_pre_payment_complete', array( $this, 'maybe_create_token' ), 10, 1 );
			add_filter( 'wc_' . $this->get_key() . '_button_markup_params', array( $this, 'maybe_modify_paypal_markup' ), 15, 1 );

			/**
			 * Modify order object and populate payment related info as per different scenarios
			 */
			add_filter( 'wc_payment_gateway_' . $this->get_key() . '_get_order', array( $this, 'get_order' ), 999 );

			add_action( 'wc_payment_gateway_' . $this->get_key() . '_add_transaction_data', array( $this, 'maybe_collect_response_paypal' ), 10, 2 );

			add_action( 'wfocu_offer_new_order_created_' . $this->get_key(), array( $this, 'save_transaction_id' ), 10, 2 );

			$this->refund_supported = true;

		}

		public static function get_instance() {

			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_force_tokenization( $is_tokenize ) {

			return $this->is_enabled() ? true : $is_tokenize;
		}

		/**
		 * On the initial order success hook we need to store the response as the class property
		 *
		 * @param $message
		 * @param $order
		 * @param $response
		 *
		 */
		public function maybe_collect_response_paypal( $order, $response ) {

			if ( false === is_null( $response ) ) {
				$this->cc_call_response = $response;
			}

		}

		public function maybe_create_token( $order ) {

			$order_base = wc_get_order( $order );
			if ( $order_base instanceof WC_Order && $this->key === $order_base->get_payment_method() && $this->is_enabled( $order_base ) && $this->should_tokenize() ) {

				$order = $this->get_wc_gateway()->get_order( $order );
				if ( $this->should_tokenize() && 0 === $order->get_user_id() ) {

					if ( isset( $order->payment->token ) && $order->payment->token ) {

						// save the tokenized card info for completing the pre-order in the future
						$this->get_wc_gateway()->add_transaction_data( $order );

					} else {

						/**
						 * Checking if we have successfully captured the response
						 */
						$response_class = class_exists( '\WC_Braintree\API\Responses\WC_Braintree_API_Credit_Card_Transaction_Response' ) ? '\WC_Braintree\API\Responses\WC_Braintree_API_Credit_Card_Transaction_Response' : 'WC_Braintree_API_Credit_Card_Transaction_Response';

						if ( ! $this->cc_call_response instanceof $response_class ) {
							return;
						}

						WFOCU_Core()->log->log( 'Braintree PayPal call response collected' . print_r( $this->cc_call_response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						/**
						 * This method here doesn't fire a request to create a new token, it just tells SV framework to save the token for the future use by passing the response as second param.
						 */

						try {
							$this->get_wc_gateway()->get_payment_tokens_handler()->create_token( $order, $this->cc_call_response );
							WFOCU_Core()->log->log( 'Braintree PayPal call successfully tokenized' );
						} catch ( Exception $e ) {
							WFOCU_Core()->log->log( 'Braintree Paypal tokenization not succceded ' . print_r( $e->getMessage(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

						}
					}
				}
			}
		}

		public function process_charge( $order ) {

			$is_successful = false;
			try {
				$gateway = $this->get_wc_gateway();
				$order   = $this->get_wc_gateway()->get_order( $order );
				// configure
				if ( $this->is_braintree_auth() ) {

					// configure with access token
					$gateway_args = array(
						'accessToken' => $gateway->get_auth_access_token(),
					);

				} else {

					$gateway_args = array(
						'environment' => $gateway->get_environment(),
						'merchantId'  => $gateway->get_merchant_id(),
						'publicKey'   => $gateway->get_public_key(),
						'privateKey'  => $gateway->get_private_key(),
					);
				}

				$sdk_gateway = new Braintree\Gateway( $gateway_args );

				$resource = 'transaction';

				$this->set_charge_params( $order );
				$callback_params = $this->get_charge_params();

				$callback = 'sale';
				try {

					$response = call_user_func_array( array( $sdk_gateway->$resource(), $callback ), $callback_params );

				} catch ( Exception $e ) {

					$response = $e;
				}

				if ( $this->handle_response( $response ) ) {
					WFOCU_Core()->data->set( '_transaction_id', $this->get_transaction_id( $response ) );

					$is_successful = true;
				}
			} catch ( Exception $e ) {

				$is_successful = false;
			}

			return $this->handle_result( $is_successful );
		}

		/**
		 * Determines if the gateway is configured with Braintree Auth or standard
		 * API keys.
		 *
		 * @return bool
		 * @since 2.0.0
		 *
		 */
		protected function is_braintree_auth() {

			return $this->get_wc_gateway()->is_connected() && ! $this->get_wc_gateway()->is_connected_manually();
		}

		protected function set_charge_params( $order ) {

			$get_package = WFOCU_Core()->data->get( '_upsell_package' );

			$this->request_data = array(
				'amount'            => $get_package['total'],
				'orderId'           => $this->get_order_number( $order ),
				'merchantAccountId' => empty( $order->payment->merchant_account_id ) ? null : $order->payment->merchant_account_id,
				'shipping'          => $this->get_shipping_address( $order ),
				'options'           => array(
					'submitForSettlement'              => 1,
					'storeInVaultOnSuccess'            => 1,
					'addBillingAddressToPaymentMethod' => 1,
				),
				'channel'           => 'woocommerce_bt',
				'deviceData'        => empty( $order->payment->device_data ) ? null : $order->payment->device_data,
				'taxAmount'         => $order->get_total_tax(),
				'taxExempt'         => $order->get_user_id() > 0 && is_callable( array( WC()->customer, 'is_vat_exempt' ) ) ? WC()->customer->is_vat_exempt() : false,
				'customer'          => array(
					'firstName' => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' ),
					'lastName'  => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' ),
					'company'   => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_company' ),
					'phone'     => preg_replace( '/[^\d\-().]/', '', WFOCU_WC_Compatibility::get_order_data( $order, 'billing_phone' ) ),
					'email'     => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' ),
				),

			);

			$this->set_billing( $order );
			$this->set_payment_method( $order );
			WFOCU_Core()->log->log( 'Order #' . WFOCU_Core()->data->get_current_order()->get_id() . ': ' . 'Data for the request to Braintree PayPal' . print_r( $this->request_data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		}

		/**
		 * Adds pre-orders data to the order object.  Filtered onto SV_WC_Payment_Gateway::get_order()
		 *
		 * @param WC_Order $order the order
		 *
		 * @return WC_Order the orders
		 * @since 4.1.0
		 * @see SV_WC_Payment_Gateway::get_order()
		 *
		 */
		public function get_order( $order ) {

			if ( $this->has_token( $order ) && ! is_checkout_pay_page() ) {

				// if this is a pre-order release payment with a tokenized payment method, get the payment token to complete the order

				// retrieve the payment token
				$order->payment->token = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'payment_token' );

				// retrieve the optional customer id
				$order->customer_id = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'customer_id' );

				// set token data on order
				if ( $this->get_wc_gateway()->get_payment_tokens_handler()->user_has_token( $order->get_user_id(), $order->payment->token ) ) {

					// an existing registered user with a saved payment token
					$token = $this->get_wc_gateway()->get_payment_tokens_handler()->get_token( $order->get_user_id(), $order->payment->token );

					// account last four
					$order->payment->account_number = $token->get_last_four();

					if ( $this->get_wc_gateway()->is_credit_card_gateway() ) {

						// card type
						$order->payment->card_type = $token->get_card_type();

						// exp month/year
						$order->payment->exp_month = $token->get_exp_month();
						$order->payment->exp_year  = $token->get_exp_year();

					} elseif ( $this->get_wc_gateway()->is_echeck_gateway() ) {

						// account type (checking/savings)
						$order->payment->account_type = $token->get_account_type();
					}
				} else {

					// a guest user means that token data must be set from the original order

					// account number
					$order->payment->account_number = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'account_four' );

					if ( $this->get_wc_gateway()->is_credit_card_gateway() ) {

						// card type
						$order->payment->card_type = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'card_type' );
						$expiry_date               = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'card_expiry_date' );
						// expiry date
						if ( ! empty( $expiry_date ) ) {
							list( $exp_year, $exp_month ) = explode( '-', $expiry_date );
							$order->payment->exp_month = $exp_month;
							$order->payment->exp_year  = $exp_year;
						}
					} elseif ( $this->get_wc_gateway()->is_echeck_gateway() ) {

						// account type
						$order->payment->account_type = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'account_type' );
					}
				}
			}

			if ( true === $this->unset_opaque_value && isset( $order->payment->opaque_value ) ) {
				unset( $order->payment->opaque_value );
			}

			return $order;
		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) {
			$this->token = WFOCU_Common::get_order_meta( $order, '_wc_' . $this->get_key() . '_payment_token' );

			if ( ! empty( $this->token ) ) {
				return true;
			}

			return false;

		}

		protected function get_shipping_address( $order ) {
			return array(
				'firstName'         => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_first_name' ),
				'lastName'          => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_last_name' ),
				'company'           => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_company' ),
				'streetAddress'     => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_address_1' ),
				'extendedAddress'   => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_address_2' ),
				'locality'          => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_city' ),
				'region'            => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_state' ),
				'postalCode'        => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_postcode' ),
				'countryCodeAlpha2' => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_country' ),
			);
		}

		protected function set_billing( $order ) {

			if ( ! empty( $order->payment->billing_address_id ) ) {

				// use the existing billing address when using a saved payment method
				$this->request_data['billingAddressId'] = $order->payment->billing_address_id;

			} else {

				// otherwise just set the billing address directly
				$this->request_data['billing'] = array(
					'firstName'         => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' ),
					'lastName'          => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' ),
					'company'           => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_company' ),
					'streetAddress'     => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_address_1' ),
					'extendedAddress'   => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_address_2' ),
					'locality'          => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_city' ),
					'region'            => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_state' ),
					'postalCode'        => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_postcode' ),
					'countryCodeAlpha2' => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_country' ),
				);
			}
		}

		protected function set_payment_method( $order ) {

			if ( ! empty( $order->payment->token ) && empty( $order->payment->use_3ds_nonce ) ) {

				// use saved payment method (token)
				$this->request_data['paymentMethodToken'] = $order->payment->token;

			} else {

				/**  use new payment method (nonce) */
				$this->request_data['paymentMethodNonce'] = $order->payment->nonce;

				// set cardholder name when adding a credit card, note this isn't possible
				// when using a 3DS nonce
				if ( 'credit_card' === $order->payment->type && empty( $order->payment->use_3ds_nonce ) ) {
					$this->request_data['creditCard'] = array( 'cardholderName' => $order->get_formatted_billing_full_name() );
				}
			}

			// add recurring flag to transactions that are subscription renewals
			if ( ! empty( $order->payment->recurring ) ) {
				$this->request_data['recurring'] = true;
			}
		}

		protected function get_charge_params() {
			return array( $this->request_data );
		}

		protected function handle_response( $response ) {

			// check if Braintree response contains exception and convert to framework exception
			if ( $response instanceof Exception ) {
				$this->error = $this->get_api_message( $response );

				return false;
			}

			if ( true === $response->success ) {
				return true;
			}

			return false;
		}

		protected function get_api_message( $e ) {

			$exception_class = get_class( $e );
			if ( class_exists( '\Braintree\Exception\Authentication' ) ) {
				$exception_class = '\\' . $exception_class;
			}

			switch ( $exception_class ) {

				case 'Braintree\Exception\Authentication':
				case '\Braintree\Exception\Authentication':
					$message = __( 'Invalid Credentials, please double-check your API credentials (Merchant ID, Public Key, Private Key, and Merchant Account ID) and try again.', 'woocommerce-gateway-paypal-powered-by-braintree' );
					break;

				case 'Braintree\Exception\Authorization':
				case '\Braintree\Exception\Authorization':
					$message = __( 'Authorization Failed, please verify the user for the API credentials provided can perform transactions and that the request data is correct.', 'woocommerce-gateway-paypal-powered-by-braintree' );
					break;

				case 'Braintree\Exception\DownForMaintenance':
				case '\Braintree\Exception\DownForMaintenance':
					$message = __( 'Braintree is currently down for maintenance, please try again later.', 'woocommerce-gateway-paypal-powered-by-braintree' );
					break;

				case 'Braintree\Exception\NotFound':
				case '\Braintree\Exception\NotFound':
					$message = __( 'The record cannot be found, please contact support.', 'woocommerce-gateway-paypal-powered-by-braintree' );
					break;

				case 'Braintree\Exception\ServerError':
				case '\Braintree\Exception\ServerError':
					$message = __( 'Braintree encountered an error when processing your request, please try again later or contact support.', 'woocommerce-gateway-paypal-powered-by-braintree' );
					break;

				case 'Braintree\Exception\SSLCertificate':
				case '\Braintree\Exception\SSLCertificate':
					$message = __( 'Braintree cannot verify your server\'s SSL certificate. Please contact your hosting provider or try again later.', 'woocommerce-gateway-paypal-powered-by-braintree' );
					break;

				default:
					$message = $e->getMessage();
			}

			return $message;

		}

		public function get_transaction_id( $response ) {

			return ! empty( $response->transaction->id ) ? $response->transaction->id : null;
		}

		public function save_transaction_id( $order, $transaction_id ) {
			$order->update_meta_data( '_wc_' . $this->get_key() . '_trans_id', $transaction_id );
			$order->save_meta_data();
		}

		/**
		 * Handling refund offer
		 *
		 * @param $order
		 *
		 * @return bool
		 */
		public function process_refund_offer( $order ) {
			$refund_data = $_POST;  // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$txn_id        = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
			$amnt          = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
			$refund_reason = isset( $refund_data['refund_reason'] ) ? $refund_data['refund_reason'] : '';
			$api           = $this->get_wc_gateway()->get_api();

			// add refund info
			$order->refund         = new stdClass();
			$order->refund->amount = number_format( $amnt, 2, '.', '' );

			$order->refund->trans_id = $txn_id;

			$order->refund->reason = $refund_reason;

			$response = $api->refund( $order );

			$trasaction_id = $response->get_transaction_id();

			WFOCU_Core()->log->log( "WFOCU Braintree paypal Offer transaction ID: $trasaction_id refund response: " . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			if ( ! $trasaction_id ) {
				$response                    = $api->void( $order );
				$trasaction_id               = $response->get_transaction_id();
				$order->refund->wfocu_voided = true;

				WFOCU_Core()->log->log( "WFOCU Braintree paypal Offer transaction ID: $trasaction_id void response: " . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return $trasaction_id ? $trasaction_id : false;

		}

		/**
		 *
		 * @param $order
		 * @param $amnt
		 * @param $refund_id
		 * @param $offer_id
		 * @param $refund_reason
		 */
		public function wfocu_add_order_note( $order, $amnt, $refund_id, $offer_id, $refund_reason ) {
			if ( isset( $order->refund->wfocu_voided ) && true === $order->refund->wfocu_voided ) {
				/* translators: 1) dollar amount 2) transaction id 3) refund message */
				$refund_note = sprintf( __( 'Voided %1$s - Void Txn ID: %2$s <br/>Offer: %3$s(#%4$s) %5$s', 'woofunnels-upstroke-one-click-upsell' ), $amnt, $refund_id, get_the_title( $offer_id ), $offer_id, $refund_reason );
				$order->add_order_note( $refund_note );
			} else {
				parent::wfocu_add_order_note( $order, $amnt, $refund_id, $offer_id, $refund_reason );
			}
		}

		/**
		 *  Creating transaction URL
		 *
		 * @param $transaction_id
		 * @param $order_id
		 *
		 * @return string
		 */
		public function get_transaction_link( $transaction_id, $order_id ) {

			$order = wc_get_order( $order_id );

			$merchant_id = $this->get_wc_gateway()->get_merchant_id();
			$environment = $this->get_wc_gateway()->get_order_meta( $order, 'environment' );

			if ( $merchant_id && $transaction_id ) {

				$view_transaction_url = sprintf( 'https://%s.braintreegateway.com/merchants/%s/transactions/%s', $this->get_wc_gateway()->is_test_environment( $environment ) ? 'sandbox' : 'www', $merchant_id, $transaction_id );
			}

			if ( ! empty( $view_transaction_url ) && ! empty( $transaction_id ) ) {
				$return_url = sprintf( '<a href="%s">%s</a>', $view_transaction_url, $transaction_id );
			}

			return $return_url;
		}

		public function maybe_modify_paypal_markup( $config ) {

			if ( $this->is_enabled() ) {
				$config['single_use'] = false;
			}

			return $config;
		}


	}


	WFOCU_Gateway_Integration_Braintree_PayPal::get_instance();
}