<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Gateway_Integration_Braintree_CC' ) ) {
	/**
	 * WFOCU_Gateway_Integration_Braintree_CC class.
	 *
	 * @extends WFOCU_Gateway
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Braintree_CC extends WFOCU_Gateway {


		protected static $ins = null;
		public $token = false;
		public $cc_call_response = false;
		public $maybe_collect_response = false;
		public $unset_opaque_value = false;
		protected $key = 'braintree_credit_card';

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

			/**
			 * Modify order object and populate payment related info as per different scenarios
			 */
			add_filter( 'wc_payment_gateway_' . $this->get_key() . '_get_order', array( $this, 'get_order' ), 999 );

			add_filter( 'wc_payment_gateway_' . $this->get_key() . '_credit_card_transaction_approved_order_note', array( $this, 'maybe_collect_response' ), 10, 3 );

			add_action( 'wfocu_offer_new_order_created_' . $this->get_key(), array( $this, 'save_transaction_id' ), 10, 2 );


			add_action( 'wfocu_footer_before_print_scripts', array( $this, 'maybe_render_in_offer_transaction_scripts' ), 999 );

			/**
			 * We need nonce against the token we have saved, so lets localized it
			 */
			add_filter( 'wfocu_localized_data', array( $this, 'get_nonce_from_token' ), 99, 1 );

			/**
			 * Add payment nonce to the upselll package to be used in other operations
			 */
			add_filter( 'wfocu_upsell_package', array( $this, 'maybe_add_3ds_nonce_in_package' ), 99, 1 );

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
		public function maybe_collect_response( $message, $order, $response ) {

			$this->cc_call_response = $response;

			return $message;
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
						if ( $this->cc_call_response === false ) {
							return;
						}

						$response_class = class_exists( '\WC_Braintree\API\Responses\WC_Braintree_API_Credit_Card_Transaction_Response' ) ? '\WC_Braintree\API\Responses\WC_Braintree_API_Credit_Card_Transaction_Response' : 'WC_Braintree_API_Credit_Card_Transaction_Response';

						if ( ! $this->cc_call_response instanceof $response_class ) {
							return;
						}

						WFOCU_Core()->log->log( 'Braintree CC CC call response collected' . print_r( $this->cc_call_response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						/**
						 * This method here doesn't fire a request to create a new token, it just tells SV framework to save the token for the future use by passing the response as second param.
						 */

						try {
							$this->get_wc_gateway()->get_payment_tokens_handler()->create_token( $order, $this->cc_call_response );
							WFOCU_Core()->log->log( 'Braintree CC CC call successfully tokenized' );
						} catch ( Exception $e ) {
							WFOCU_Core()->log->log( 'Braintree tokenization not succceded ' . print_r( $e->getMessage(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

						}
					}
				}
			}
		}

		public function process_charge( $order ) {

			$is_successful = false;


			$this->handle_client_error();
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
			WFOCU_Core()->log->log( 'Braintree Payment Call response ' . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			if ( true === $this->transaction_approved( $response ) ) {

				WFOCU_Core()->data->set( '_transaction_id', $this->get_transaction_id( $response ) );

				$is_successful = true;
			} else {
				$is_successful = false;
				throw new WFOCU_Payment_Gateway_Exception( sprintf( __( 'Braintree Upsell CC Transaction Failed (%s)', 'woocommerce-upstroke-one-click-upsell' ), $this->get_failure_message( $response ) ), $this->get_failure_code( $response ) );

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
				'merchantAccountId' => ! isset( $order->payment->merchant_account_id ) ? null : $order->payment->merchant_account_id,
				'shipping'          => $this->get_shipping_address( $order ),
				'options'           => array(
					'submitForSettlement'              => 1,
					'storeInVaultOnSuccess'            => 1,
					'addBillingAddressToPaymentMethod' => 1,
				),
				'channel'           => 'buildwoofunnels_bt',
				'deviceData'        => isset( $order->payment->device_data ) ? null : $order->payment->device_data,
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
			$this->set_payment_method( $order, $get_package );
			WFOCU_Core()->log->log( 'Order #' . WFOCU_Core()->data->get_current_order()->get_id() . ': ' . 'Data for the request to Braintree CC' . print_r( $this->request_data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		}

		public function get_token( $order ) {
			$this->token = WFOCU_Common::get_order_meta( $order, '_wc_' . $this->get_key() . '_payment_token' );
			if ( ! empty( $this->token ) ) {
				return $this->token;
			}

			return '';

		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) {
			$this->token = WFOCU_Common::get_order_meta( $order, '_wc_' . $this->get_key() . '_payment_token' );;
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

			if ( isset( $order->payment->billing_address_id ) ) {

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

		protected function set_payment_method( $order, $package ) {

			if ( is_array( $package ) && isset( $package['_wc_braintree_token'] ) ) {
				$this->request_data['paymentMethodNonce'] = $package['_wc_braintree_token'];
			} elseif ( isset( $order->payment->token ) && ! isset( $order->payment->use_3ds_nonce ) ) {

				// use saved payment method (token)
				$this->request_data['paymentMethodToken'] = $order->payment->token;

			} else {

				$this->request_data['paymentMethodNonce'] = $order->payment->nonce;

				// set cardholder name when adding a credit card, note this isn't possible
				// when using a 3DS nonce
				if ( 'credit_card' === $order->payment->type && empty( $order->payment->use_3ds_nonce ) ) {
					$this->request_data['creditCard'] = array( 'cardholderName' => $order->get_formatted_billing_full_name() );
				}
			}


		}

		protected function get_charge_params() {
			return array( $this->request_data );
		}

		public function transaction_approved( $response ) {
			return $response->success;
		}

		/**
		 * Get the success status info for the given parameter, either code or message
		 *
		 * @param string $type status info type, either `code` or `message`
		 *
		 * @return string
		 * @since 3.0.0
		 */
		public function get_failure_message( $response ) {

			return isset( $response->message ) ? $response->message : '';
		}

		/**
		 * Get the success status info for the given parameter, either code or message
		 *
		 * @param string $type status info type, either `code` or `message`
		 *
		 * @return string
		 * @since 3.0.0
		 */
		public function get_failure_code( $response ) {

			return isset( $response->trasaction->status ) ? $response->trasaction->status : '00';
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
			$api           = $this->get_wc_gateway()->get_api();
			$refund_reason = isset( $refund_data['refund_reason'] ) ? $refund_data['refund_reason'] : '';

			// add refund info
			$order->refund           = new stdClass();
			$order->refund->amount   = number_format( $amnt, 2, '.', '' );
			$order->refund->trans_id = $txn_id;
			$order->refund->reason   = $refund_reason;

			$response = $api->refund( $order );

			$trasaction_id = $response->get_transaction_id();

			WFOCU_Core()->log->log( "WFOCU Braintree CC Offer transaction ID: $trasaction_id refund response: " . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			if ( ! $trasaction_id ) {
				$response                    = $api->void( $order );
				$trasaction_id               = $response->get_transaction_id();
				$order->refund->wfocu_voided = true;

				WFOCU_Core()->log->log( "WFOCU Braintree CC Offer transaction ID: $trasaction_id void response: " . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
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

		/**
		 * For the JavaScript API to process clean we need to first fetch the new nonce from the API by passing the token we have
		 * Now we going to fire API calls and if succeded we localie the nonce to use further on JavaScript
		 *
		 * @param array $localized_data
		 *
		 * @return array modified localized array
		 */
		public function get_nonce_from_token( $localized_data ) {

			if ( ! is_array( $localized_data ) || true === $localized_data['is_preview'] ) {
				return $localized_data;
			}

			$get_order = WFOCU_Core()->data->get_current_order();
			if ( ! $get_order || 0 === $get_order->get_id() ) {
				return $localized_data;
			}


			$token = $this->get_token( $get_order );

			if ( empty( $token ) ) {
				return $localized_data;
			}

			/**
			 * try to get nonce by passing token
			 */
			$nonce = $this->_get_nonce_from_token( $token );

			if ( null === $nonce ) {
				return $localized_data;
			}

			if ( ! isset( $localized_data['payments'] ) ) {
				$localized_data['payments'] = [];
			}

			/**
			 * localize nonce
			 */
			$localized_data['payments']['_wc_braintree_cc_nonce'] = $nonce;

			return $localized_data;

		}

		private function _get_nonce_from_token( $token ) {
			$nonce = null;

			try {

				$result = $this->get_wc_gateway()->get_api()->get_nonce_from_payment_token( $token );

				$nonce = $result->get_nonce();

			} catch ( Exception $e ) {

				if ( class_exists( '\WC_Braintree\Plugin' ) ) {
					\WC_Braintree\Plugin::log( $e->getMessage(), 'error' );
				} else {
					$this->get_wc_gateway()->add_debug_message( $e->getMessage(), 'error' );
				}
			}

			return $nonce;
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
		 * Render client side script for better payment flows
		 */
		public function maybe_render_in_offer_transaction_scripts() {

			$is_preview = WFOCU_Core()->public->if_is_preview();
			if ( true === $is_preview ) {
				return;
			}
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return;
			}
			if ( $this->get_key() !== $order->get_payment_method() ) {
				return;
			}
			$this->request_data = [];
			$this->set_billing( $order );
			$last_four = WFOCU_WC_Compatibility::get_order_data( $order, '_wc_braintree_credit_card_account_four' );
			$last_four = ! empty( $last_four ) ? $last_four : '';
			?>
            <style>.wfocuswal-container{z-index:999999 !important}</style>
            <script src="https://js.braintreegateway.com/v1/braintree-data.js"></script> <?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <script src="https://js.braintreegateway.com/web/<?php echo esc_html( class_exists( '\WC_Braintree\WC_Braintree' ) ? \WC_Braintree\WC_Braintree::BRAINTREE_JS_SDK_VERSION : WC_Braintree::BRAINTREE_JS_SDK_VERSION ); ?>/js/three-d-secure.min.js?ver=2.2.6"></script> <?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <script src="https://js.braintreegateway.com/web/<?php echo esc_html( class_exists( '\WC_Braintree\WC_Braintree' ) ? \WC_Braintree\WC_Braintree::BRAINTREE_JS_SDK_VERSION : WC_Braintree::BRAINTREE_JS_SDK_VERSION ); ?>/js/client.min.js?ver=2.2.6"></script> <?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <script>
                (
                    function ($) {
                        "use strict";


                        var wfocuBrainTreePayments = {
                            'Bucket': null,
                            'id': '<?php echo esc_js( $this->get_key() ); ?>',
                            'client_token_nonce': '<?php echo esc_js( wp_create_nonce( 'wc_' . $this->get_key() . '_get_client_token' ) );?>',
                            'threeDSecure': null,
                            'PreventDefault': true,
                            'IsApiReady': false,
                            'init': function () {
                                var r = this;
                                return this.get_client_token().done(function (e) {
                                    return e.success ? braintree.client.create({
                                        authorization: e.data
                                    }).then(function (e) {
                                        return r.client = e, r.setup_integration();
                                    }).catch(function (e) {
                                        return r.HandleApiError(e)
                                    }) : r.HandleApiError(e.data)
                                }).fail(function (e, t, n) {
                                    return r.HandleApiError({"message": "Could not retrieve the client token via AJAX: " + n})
                                });
                            },
                            'get_client_token': function () {
                                var e;
                                return this.id, e = {
                                    action: "wc_" + this.id + "_get_client_token",
                                    nonce: this.client_token_nonce
                                }, jQuery.post(window.wfocu_vars.ajax_url, e)
                            },
                            'setup_integration': function () {
                                var t = this;
                                /**
                                 * Check if 3dsecure is enabled or not at gateway level
                                 * if not then stop creating threedsecure object and enable buttons
                                 * */
                                if (false === t.client.getConfiguration().gatewayConfiguration.threeDSecureEnabled) {
                                    return t.IsApiReady = true, t.Bucket.EnableButtonState()
                                }

                                return braintree.threeDSecure.create({
                                    version: 2,
                                    client: this.client
                                }).then(function (e) {
                                    return t.IsApiReady = true, t.Bucket.EnableButtonState(), t.threeDSecure = e
                                }).catch(function (e) {
                                    return t.HandleApiError(e)
                                });
                            },
                            'processPaymentInit': function () {
                                var r = this;

                                /**
                                 * Check if we have successfull 3dsecure object , if not then process the bucket
                                 *
                                 * */
                                if (_.isNull(r.threeDSecure)) {
                                    this.PreventDefault = false;
                                    this.Bucket.sendBucket();
                                    return;
                                }
                                var c = {
                                    nonce: wfocu_vars.payments._wc_braintree_cc_nonce,
                                    amount: r.Bucket.formatPrice(r.Bucket.getTotal(), 2, "", "."),
                                    email: '<?php echo esc_js( WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' ) ); ?>',
                                    billingAddress: <?php echo wp_kses_post( wp_json_encode( $this->request_data['billing'] ) ); ?>,
                                    additionalInformation: [],
                                    onLookupComplete: function (e, t) {
                                        return t()
                                    }
                                };
                                var lastFour = '<?php echo esc_js( $last_four ); ?>';
                                if ('' !== lastFour) {
                                    c.bin = lastFour
                                }
                                this.threeDSecure.verifyCard(c).then(function (e) {
                                    return r.Process3dsSuccess(e.nonce), true
                                }).catch(function (e) {
                                    return r.HandleApiError(e)
                                });
                            },
                            'Process3dsSuccess': function (nonce) {
                                this.PreventDefault = false;
                                wfocuCommons.addFilter('wfocu_front_charge_data', function (e) {
                                    e._wc_3ds_nonce = nonce;
                                    return e;
                                });
                                this.Bucket.sendBucket();
                            },
                            'HandleApiError': function (e) {
                                this.unblockNativeTransaction();
                                wfocuCommons.addFilter('wfocu_front_charge_data', function (eD) {
                                    eD._client_error = e;
                                    return eD;
                                });
                            },
                            'blockNativeTransaction': function () {

                                this.Bucket.inOfferTransaction = true;
                                this.processPaymentInit();
                                this.PreventDefault = false;
                            },
                            'unblockNativeTransaction': function () {
                                this.PreventDefault = false;
                                this.Bucket.inOfferTransaction = false;
                                this.Bucket.EnableButtonState();
                            }
                        };
                        /**
                         * Save the bucket instance at several
                         */
                        $(document).on('wfocuBucketCreated', function (e, Bucket) {

                            wfocuBrainTreePayments.Bucket = Bucket;
                            wfocuBrainTreePayments.init();
                        });
                        $(document).on('wfocu_external', function (e, Bucket) {

                            /**
                             * Check if we need to mark inoffer transaction to prevent default behavior of page
                             */
                            if (0 !== Bucket.getTotal() && true === wfocuBrainTreePayments.PreventDefault) {
                                wfocuBrainTreePayments.blockNativeTransaction();
                            } else {
                                wfocuBrainTreePayments.unblockNativeTransaction();
                            }

                        });

                        $(document).on('wfocuBucketConfirmationRendered', function (e, Bucket) {
                            wfocuBrainTreePayments.Bucket = Bucket;
                        });
                        $(document).on('wfocuBucketLinksConverted', function (e, Bucket) {
                            wfocuBrainTreePayments.Bucket = Bucket;

                            if (false === wfocuBrainTreePayments.IsApiReady) {
                                wfocuBrainTreePayments.Bucket.DisableButtonState();
                            }
                        });
                    })
                (jQuery);
            </script>
			<?php
		}


		/**
		 * Adds nonce from the post and save it in the package
		 *
		 * @param array $package
		 *
		 * @return array
		 */
		public function maybe_add_3ds_nonce_in_package( $package ) {
			$token = filter_input( INPUT_POST, '_wc_3ds_nonce', FILTER_UNSAFE_RAW );
			if ( ! is_null( $token ) ) {
				$package['_wc_braintree_token'] = $token;
			}

			return $package;
		}

		public function handle_client_error() {
			WFOCU_Core()->log->log( 'Client Error Received:: ' . $this->get_client_error() );
		}
	}

	WFOCU_Gateway_Integration_Braintree_CC::get_instance();
}
