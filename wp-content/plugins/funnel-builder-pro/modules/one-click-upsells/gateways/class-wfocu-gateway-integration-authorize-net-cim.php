<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Gateway_Integration_Authorize_Net_CIM' ) ) {
	/**
	 * WFOCU_Gateway_Integration_Authorize_Net_CIM class.
	 *
	 * @extends WFOCU_Gateway
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Authorize_Net_CIM extends WFOCU_Gateway {


		protected static $ins = null;
		public $token = false;
		public $customer_id = false;
		public $unset_opaque_value = false;
		public $order = false;
		protected $key = 'authorize_net_cim_credit_card';
		const MB_ENCODING = 'UTF-8';
		public $is_error_token = false;

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
			 * This cb is just placed here to make sure that older version where we have processing without js
			 */
			add_action( 'woocommerce_pre_payment_complete', array( $this, 'maybe_create_token' ), 10, 1 );

			/**
			 * For the case when User is not logged in and accept js is on.
			 * We have to get the full control of the main checkout payment.
			 */
			add_filter( 'wc_payment_gateway_' . $this->get_key() . '_process_payment', array( $this, 'process_payment' ), 10, 2 );

			add_action( 'wfocu_front_create_new_order_on_success', function () {
				remove_action( 'woocommerce_pre_payment_complete', array( $this, 'maybe_create_token' ), 10, 1 );  // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UndefinedVariable
			}, - 1 );

			add_action( 'wc_payment_gateway_' . $this->get_key() . '_add_transaction_data', array( $this, 'maybe_add_shipping_address_id_order_for_guests' ) );

			$this->refund_supported = true;

			//Modifying refund request data in case of offer refund to add offer transaction id


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

		public function is_accept_js_on() {
			if ( version_compare( WC_Authorize_Net_CIM::VERSION, '3.0.0', '>=' ) ) {
				return true;
			}

			return $this->get_wc_gateway()->is_accept_js_enabled();

		}

		public function process_payment( $result, $order_id ) {


			$order = $this->get_wc_gateway()->get_order( $order_id );

			if ( $this->should_tokenize() && $this->is_accept_js_on() && empty( $order->get_user_id() ) && $this->is_enabled( $order ) && true === $this->get_wc_gateway()->is_cim_feature_enabled() ) {

				$result = true;
				try {

					// using an existing tokenized payment method
					if ( isset( $order->payment->token ) && $order->payment->token ) {

						$this->get_wc_gateway()->add_transaction_data( $order );

					} else {
						$customer_id_from_session = WFOCU_Core()->data->get( 'authorize_net_cim_customer_id', '', 'gateway' );
						if ( ! empty( $customer_id_from_session ) ) {
							WFOCU_Core()->log->log( "valid process customer ID on session" . $customer_id_from_session );
							$order = $this->validate_and_process_customer_id( $customer_id_from_session, $order, true );
						} else {
							$get_billing_email                  = $order->get_billing_email();
							$get_orders_by_meta_for_customer_id = new WP_Query( array(
								'post_type'    => 'shop_order',
								'post_status'  => 'any',
								'meta_query'   => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
									'relation' => 'AND',
									array(
										'key'     => '_wc_authorize_net_cim_credit_card_customer_id',
										'compare' => '!=',
										'value'   => ''
									),
									array(
										'key'     => '_billing_email',
										'compare' => '=',
										'value'   => $get_billing_email,
									),
								),
								'fields'       => 'ids',
								'order'        => 'DESC',
								'post__not_in' => [ $order->get_id() ], //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn
							) );
							WFOCU_Core()->log->log( ' Orders found for previous customer IDs ' . print_r( $get_orders_by_meta_for_customer_id->posts, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

							/**
							 * If we have saved customer ID for the given Email
							 */
							if ( is_array( $get_orders_by_meta_for_customer_id->posts ) && count( $get_orders_by_meta_for_customer_id->posts ) > 0 ) {

								/**
								 * Try to get the customer ID
								 */
								$customer_id = WFOCU_Common::get_order_meta( wc_get_order( $get_orders_by_meta_for_customer_id->posts[0] ), '_wc_authorize_net_cim_credit_card_customer_id' );
								WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' Customer ID found from order is ' . $customer_id ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

								$get_customer_profile = $customer_id;

								$order = $this->validate_and_process_customer_id( $get_customer_profile, $order, true );
							} else {
								WFOCU_Core()->log->log( "Order: #" . $order->get_id() . 'attempt to create token for fresh customer' );

								$order = $this->_create_token( $order );


								if ( ! empty( $order->customer_id ) && true === $this->is_error_token ) {

									WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' We have found the customer ID from the exception' );


									$get_customer_profile = $order->customer_id;

									$order = $this->validate_and_process_customer_id( $get_customer_profile, $order );
								}

							}
						}


						/**
						 * We need to create shipping ID for the current user on Authorize.Net CIM API
						 * As ShippingAddressID is important for the cases when business owner has shipping-filters enabled in their merchant account.
						 *
						 */
						try {

							/**
							 * When we are in a case when there is a returning user & not logged in then in this case there are chances that shipping API request might fail.
							 * In this case we need to try and get shipping ID from the order meta and set this up for further.
							 */
							$response = $this->get_wc_gateway()->get_api()->create_shipping_address( $order );

						} catch ( Exception $e ) {
							if ( is_array( $get_orders_by_meta_for_customer_id->posts ) && count( $get_orders_by_meta_for_customer_id->posts ) > 0 ) {

								$response = intval( WFOCU_Common::get_order_meta( wc_get_order( $get_orders_by_meta_for_customer_id->posts[0] ), '_authorize_cim_shipping_address_id' ) );
							}
						}
						if ( is_integer( $response ) ) {
							$shipping_address_id = $response;
						} elseif ( is_callable( [ $response, 'get_shipping_address_id' ] ) ) {
							$shipping_address_id = $response->get_shipping_address_id();
						} else {
							$shipping_address_id = 0;
						}
						$order->payment->shipping_address_id = $shipping_address_id;
						WFOCU_Core()->data->set( 'authorize_net_cim_shipping_id', $order->payment->shipping_address_id, 'gateway' );
						WFOCU_Core()->data->save( 'gateway' );

						$this->get_wc_gateway()->add_transaction_data( $order );
						$response = $this->do_main_transaction( $order );
						if ( true !== $response ) {
							return array(
								'result'  => 'failure',
								'message' => ( $response instanceof Exception ) ? $response->getMessage() : '',
							);
						}
					}

					$result = array(
						'result'   => 'success',
						'redirect' => $this->get_wc_gateway()->get_return_url( $order ),
					);
				} catch ( Exception $e ) {
					$result = array(
						'result'  => 'failure',
						'message' => $e->getMessage(),
					);

				}
			}

			return $result;
		}

		public function get_order( $order ) {

			if ( $order instanceof WC_Order && $this->key === $order->get_payment_method() ) {

				if ( ! is_checkout_pay_page() ) {

					// retrieve the payment token

					// retrieve the optional customer id
					$order->customer_id = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'customer_id' );

					$customer_id_from_session = WFOCU_Core()->data->get( 'authorize_net_cim_customer_id', '', 'gateway' );
					if ( empty( $order->customer_id ) && ! empty( $customer_id_from_session ) ) {
						$order->customer_id = $customer_id_from_session;
					}

					$order->payment->token = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'payment_token' );
					$token_from_gateway    = $this->get_token( $order );
					if ( empty( $order->payment->token ) && ! empty( $token_from_gateway ) ) {
						$order->payment->token = $token_from_gateway;
					}
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

							// expiry date
							$expiry_date = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'card_expiry_date' );
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

				$response = intval( $order->get_meta( '_authorize_cim_shipping_address_id' ) );
				if ( ! empty( $response ) ) {
					$order->payment->shipping_address_id = $response;
				}

				if ( true === $this->unset_opaque_value && isset( $order->payment->opaque_value ) ) {
					unset( $order->payment->opaque_value );
				}
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

			if ( false === $this->get_wc_gateway()->is_cim_feature_enabled() ) {
				return false;
			}
			$get_id = WFOCU_WC_Compatibility::get_order_id( $order );


			$this->token = WFOCU_Common::get_order_meta( wc_get_order( $get_id ), '_wc_' . $this->get_key() . '_payment_token' );;

			if ( ! empty( $this->token ) ) {
				return true;
			}

			/**
			 * Fallback when token is not present in the parent order
			 */
			$get_secondary_order = WFOCU_Core()->data->get( 'authorize_net_cim_order_id', '', 'gateway' );

			if ( empty( $get_secondary_order ) ) {
				return false;
			}
			$this->token = WFOCU_Common::get_order_meta( wc_get_order( $get_secondary_order ), '_wc_' . $this->get_key() . '_payment_token' );;


			if ( ! empty( $this->token ) ) {
				return true;
			}

			return false;

		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */

		public function get_token( $order ) {
			$get_id      = WFOCU_WC_Compatibility::get_order_id( $order );
			$this->token = WFOCU_Common::get_order_meta( wc_get_order( $get_id ), '_wc_' . $this->get_key() . '_payment_token' );;


			if ( ! empty( $this->token ) ) {
				return $this->token;
			}

			/**
			 * Fallback when token is not present in the parent order
			 */
			$get_secondary_order = WFOCU_Core()->data->get( 'authorize_net_cim_order_id', '', 'gateway' );

			if ( empty( $get_secondary_order ) ) {
				return '';
			}

			$this->token = WFOCU_Common::get_order_meta( wc_get_order( $get_secondary_order ), '_wc_' . $this->get_key() . '_payment_token' );;

			if ( ! empty( $this->token ) ) {
				return $this->token;
			}

			return '';

		}

		/**
		 * We cloned the function that we need to fire main transaction in the case when accept.js in is action and user is not logged in.
		 *
		 * @param WC_Order $order
		 */
		private function do_main_transaction( $order ) {
			try {

				// order description
				$order->description = sprintf( __( '%1$s - Release Payment for Order %2$s', 'woocommerce-plugin-framework' ), esc_html( $this->get_site_name() ), $order->get_order_number() );

				// token is required
				if ( ! $order->payment->token ) {
					throw new Exception( __( 'Payment token missing/invalid.', 'woocommerce-plugin-framework' ) );
				}

				if ( isset( $order->payment ) && isset( $order->payment->csc ) ) {
					unset( $order->payment->csc );
				}
				// perform the transaction
				if ( $this->get_wc_gateway()->is_credit_card_gateway() ) {

					if ( $this->get_wc_gateway()->perform_credit_card_charge( $order ) ) {
						$response = $this->get_wc_gateway()->get_api()->credit_card_charge( $order );
					} else {
						$response = $this->get_wc_gateway()->get_api()->credit_card_authorization( $order );
					}
				} elseif ( $this->get_wc_gateway()->is_echeck_gateway() ) {
					$response = $this->get_wc_gateway()->get_api()->check_debit( $order );
				}

				// success! update order record
				if ( $response->transaction_approved() ) {

					$last_four = substr( $order->payment->account_number, - 4 );

					// order note based on gateway type
					if ( $this->get_wc_gateway()->is_credit_card_gateway() ) {

						$message = sprintf( __( '%1$s %2$s Release Payment Approved: %3$s ending in %4$s (expires %5$s)', 'woocommerce-plugin-framework' ), $this->get_wc_gateway()->get_method_title(), $this->get_wc_gateway()->perform_credit_card_authorization( $order ) ? 'Authorization' : 'Charge', ! isset( $order->payment->card_type ) ? $order->payment->card_type : 'card', $last_four, ( isset( $order->payment->exp_month ) && isset( $order->payment->exp_year ) ? $order->payment->exp_month . '/' . substr( $order->payment->exp_year, - 2 ) : 'n/a' ) );

					}

					// adds the transaction id (if any) to the order note
					if ( $response->get_transaction_id() ) {
						$message .= ' ' . sprintf( __( '(Transaction ID %s)', 'woocommerce-plugin-framework' ), $response->get_transaction_id() );
					}

					$order->add_order_note( $message );
				}

				if ( $response->transaction_approved() || $response->transaction_held() ) {

					// add the standard transaction data
					$this->get_wc_gateway()->add_transaction_data( $order, $response );

					// allow the concrete class to add any gateway-specific transaction data to the order
					$this->get_wc_gateway()->add_payment_gateway_transaction_data( $order, $response );

					// if the transaction was held (ie fraud validation failure) mark it as such
					if ( $response->transaction_held() || ( $this->get_wc_gateway()->supports( 'authorization' ) && $this->get_wc_gateway()->perform_credit_card_authorization( $order ) ) ) {

						$this->get_wc_gateway()->mark_order_as_held( $order, $this->get_wc_gateway()->supports( 'authorization' ) && $this->get_wc_gateway()->perform_credit_card_authorization( $order ) ? __( 'Authorization only transaction', 'woocommerce-plugin-framework' ) : $response->get_status_message(), $response );

						wc_reduce_stock_levels( $order->get_id() );
					} else {
						// otherwise complete the order
						$order->payment_complete();
					}

					return true;
				} else {

					// failure
					throw new Exception( sprintf( '%s: %s', $response->get_status_code(), $response->get_status_message() ) );

				}
			} catch ( Exception $e ) {
				if ( isset( $response ) ) {
					$this->get_wc_gateway()->mark_order_as_failed( $order, sprintf( __( 'Release Payment Failed: %s', 'woocommerce-plugin-framework' ), $e->getMessage() ), $response );
				} else {
					$this->get_wc_gateway()->mark_order_as_failed( $order, sprintf( __( 'Release Payment Failed: %s', 'woocommerce-plugin-framework' ), $e->getMessage() ) );

				}


				return $e;
			}
		}


		public function maybe_create_token( $order ) {

			$order_base = wc_get_order( $order );
			if ( $order_base instanceof WC_Order && $this->key === $order_base->get_payment_method() && false === $this->is_accept_js_on() && true === $this->get_wc_gateway()->is_cim_feature_enabled() ) {

				$order = $this->get_wc_gateway()->get_order( $order );
				if ( $this->should_tokenize() && 0 === $order->get_user_id() ) {

					if ( isset( $order->payment->token ) && $order->payment->token ) {

						$this->get_wc_gateway()->add_transaction_data( $order );

					} else {
						/**
						 * Handling some error from Authorize.net CIM API throwing error
						 * This error shows up when same phone number/name/email used to create token
						 */
						$order_for_shipping = $order;
						// otherwise tokenize the payment method
						try {
							$order                   = $this->get_wc_gateway()->get_payment_tokens_handler()->create_token( $order );
							$this->is_order_modified = true;
							$this->modified_order    = $order;
						} catch ( Exception $e ) {

							$re  = '/[0-9]+/';
							$str = $e->getMessage();

							preg_match_all( $re, $str, $matches, PREG_SET_ORDER, 0 );

							if ( $matches && is_array( $matches ) && isset( $matches[0][0] ) && '00039' === $matches[0][0] ) {

								$get_order_by_meta = new WP_Query( array(
									'post_type'   => 'shop_order',
									'post_status' => 'any',
									'meta_query'  => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
										array(
											'key'     => '_wc_authorize_net_cim_credit_card_customer_id',
											'value'   => $matches[1][0],
											'compare' => '=',
										),
									),
									'fields'      => 'ids',
									'order'       => 'ASC',
								) );

								if ( is_array( $get_order_by_meta->posts ) && count( $get_order_by_meta->posts ) > 0 ) {

									WFOCU_Core()->data->set( 'authorize_net_cim_order_id', $get_order_by_meta->posts[0], 'gateway' );
									$order_for_shipping = $this->get_wc_gateway()->get_order( $get_order_by_meta->posts[0] );
									WFOCU_Core()->data->set( 'authorize_net_cim_customer_id', $matches[1][0], 'gateway' );
									WFOCU_Core()->data->save( 'gateway' );
								}
							}
						}

						/**
						 * We need to create shipping ID for the current user on Authorize.Net CIM API
						 * As ShippingAddressID is important for the cases when business owner has shipping-filters enabled in their merchant account.
						 *
						 */
						try {

							/**
							 * When we are in a case when there is a returning user & not logged in then in this case there are chances that shipping API request might fail.
							 * In this case we need to try and get shipping ID from the order meta and set this up for further.
							 */
							$response = $this->get_wc_gateway()->get_api()->create_shipping_address( $order );

						} catch ( Exception $e ) {

							$response = intval( $order_for_shipping->get_meta( '_authorize_cim_shipping_address_id' ) );

						}

						$shipping_address_id                 = is_numeric( $response ) ? $response : $response->get_shipping_address_id();
						$order->payment->shipping_address_id = $shipping_address_id;
						WFOCU_Core()->data->set( 'authorize_net_cim_shipping_id', $order->payment->shipping_address_id, 'gateway' );
						WFOCU_Core()->data->save( 'gateway' );

					}
				}
			}
		}

		public function process_charge( $order ) {

			$is_successful = false;
			try {
				$api         = $this->get_wc_gateway()->get_api();
				$environment = $this->get_wc_gateway()->get_environment();
				$url         = ( 'production' === $environment ) ? $api::PRODUCTION_ENDPOINT : $api::TEST_ENDPOINT;

				$gateway = $this->get_wc_gateway();
				/**
				 * Modify order object and populate payment related info as per different scenarios
				 */
				add_filter( 'wc_payment_gateway_' . $this->get_key() . '_get_order', array( $this, 'get_order' ), 999 );

				$this->order = $gateway->get_order( $order );
				$request     = $this->create_transaction_request( 'capture', $order );
				WFOCU_Core()->log->log( 'AUTHORIZE CIM REQUEST :' . print_r( $request, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$response = wp_safe_remote_request( $url, $this->get_request_attributes( $request ) );
				$body     = wp_remote_retrieve_body( $response );
				$body     = preg_replace( '/\xEF\xBB\xBF/', '', $body );
				$result   = json_decode( $body, true );
				WFOCU_Core()->log->log( 'AUTHORIZE CIM RESPONSE :' . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( is_wp_error( $response ) ) {
					$is_successful = false;
				} else {
					if ( isset( $result['messages'] ) && isset( $result['messages']['resultCode'] ) && 'Ok' === $result['messages']['resultCode'] && ! empty( $result['directResponse'] ) ) {
						$trans_id = $this->get_transaction_id( $result['directResponse'] );
						WFOCU_Core()->data->set( '_transaction_id', $trans_id );
						$is_successful = true;

					} else {
						WFOCU_Core()->log->log( 'AUTHORIZE CIM ERROR :' . print_r( $result, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$order_note = sprintf( __( 'Authorize.net CIM Transaction Failed (%s)', 'woofunnels-upstroke-one-click-upsell' ), isset( $result['messages']['message']['text'] ) ? $result['messages']['message']['text'] : __( 'Unable to parse error, Check logs for more info', 'woofunnels-upstroke-one-click-upsell' ) );
						$order->add_order_note( $order_note );
						$is_successful = false;
					}
				}
			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( 'AUTHORIZE CIM ERROR :' . print_r( $e, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$order_note = sprintf( __( 'Authorize.net CIM Transaction Failed (%s)', 'woofunnels-upstroke-one-click-upsell' ), $e->getMessage() );
				$order->add_order_note( $order_note );
			}

			return $this->handle_result( $is_successful );
		}

		protected function create_transaction_request( $type ) {
			$order            = $this->order;
			$transaction_type = ( 'auth_only' === $type ) ? 'profileTransAuthOnly' : 'profileTransAuthCapture';
			$get_package      = WFOCU_Core()->data->get( '_upsell_package' );

			/**
			 * We need to create shipping ID for the current user on Authorize.Net CIM API
			 * As ShippingAddressID is important for the cases when business owner has shipping-filters enabled in their merchant account.
			 *
			 */
			$maybe_get_shipping_id_from_session = WFOCU_Core()->data->get( 'authorize_net_cim_shipping_id', '', 'gateway' );

			if ( isset( $order->payment ) && isset( $order->payment->shipping_address_id ) && ! empty( $order->payment->shipping_address_id ) ) {
				$shipping_address_id = $order->payment->shipping_address_id;
			} elseif ( ! empty( $maybe_get_shipping_id_from_session ) ) {
				$shipping_address_id = $maybe_get_shipping_id_from_session;
			} else {
				$response = $this->get_wc_gateway()->get_api()->create_shipping_address( $order );

				WFOCU_Core()->log->log( 'Log for shipping address-' . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$shipping_address_id = is_numeric( $response ) ? $response : $response->get_shipping_address_id();

			}

			return apply_filters( 'wfocu_payment_authorize_transaction_args', array(
				'createCustomerProfileTransactionRequest' => array(
					'merchantAuthentication' => array(
						'name'           => wc_clean( $this->get_wc_gateway()->get_api_login_id() ),
						'transactionKey' => wc_clean( $this->get_wc_gateway()->get_api_transaction_key() ),
					),
					'refId'                  => $this->get_order_number( $order ),
					'transaction'            => array(
						$transaction_type => array(
							'amount'                    => $this->number_format( $get_package['total'] ),
							'tax'                       => $this->get_taxes(),
							'shipping'                  => $this->get_shipping(),
							'lineItems'                 => $this->get_line_items(),
							'customerProfileId'         => $this->get_customer_id( $order ),
							'customerPaymentProfileId'  => $this->get_token( $order ),
							'customerShippingAddressId' => $shipping_address_id,
							'order'                     => array(
								'invoiceNumber'       => ltrim( $this->get_order_number( $order ), _x( '#', 'hash before the order number', 'woocommerce-gateway-authorize-net-cim' ) ),
								'description'         => $this->str_truncate( $this->order->description . '::' . $this->get_order_number( $order ), 255 ),
								'purchaseOrderNumber' => $this->str_truncate( preg_replace( '/\W/', '', $this->order->payment->po_number ), 25 ),
							),

						),
					),

					'extraOptions' => $this->get_extra_options(),

				),
			), $this );
		}

		/**
		 * Adds tax information to the request.
		 *
		 * @return array
		 * @since 2.0.0
		 */
		protected function get_taxes() {

			if ( $this->order->get_total_tax() > 0 ) {

				$taxes = array();

				foreach ( $this->order->get_tax_totals() as $tax_code => $tax ) {

					$taxes[] = sprintf( '%s (%s) - %s', $tax->label, $tax_code, $tax->amount );
				}

				return array(
					'amount'      => $this->number_format( $this->order->get_total_tax() ),
					'name'        => __( 'Order Taxes', 'woocommerce-gateway-authorize-net-cim' ),
					'description' => $this->str_truncate( implode( ', ', $taxes ), 255 ),
				);

			} else {

				return array();
			}
		}

		/**
		 * Adds shipping information to the request.
		 *
		 * @return array
		 * @since 2.0.0
		 */
		protected function get_shipping() {

			if ( $this->order->get_total_shipping() > 0 ) {

				return array(
					'amount'      => $this->number_format( $this->order->get_total_shipping() ),
					'name'        => __( 'Order Shipping', 'woocommerce-gateway-authorize-net-cim' ),
					'description' => $this->str_truncate( $this->order->get_shipping_method(), 255 ),
				);

			} else {

				return array();
			}
		}

		/**
		 * Adds order line items to the request.
		 *
		 * @return array
		 * @since 2.0.0
		 */
		protected function get_line_items() {

			$line_items = array();
			$package    = WFOCU_Core()->data->get( '_upsell_package' );

			if ( isset( $package['products'] ) && is_array( $package['products'] ) && count( $package['products'] ) > 0 ) {
				foreach ( $package['products'] as $product_data ) {
					/**
					 * @var WC_Product $product_data ['data']
					 */
					$line_items[] = array(
						'itemId'    => $this->str_truncate( $product_data['data']->get_id(), 31 ),
						'name'      => $this->str_to_sane_utf8( $this->str_truncate( htmlentities( $product_data['data']->get_name(), ENT_QUOTES, 'UTF-8', false ), 31 ) ),
						'quantity'  => $product_data['qty'],
						'unitPrice' => $this->number_format( $product_data['price'] ),
					);
				}
			}

			// maximum of 30 line items per order
			if ( count( $line_items ) > 30 ) {
				$line_items = array_slice( $line_items, 0, 30 );
			}

			return $line_items;
		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */

		public function get_customer_id( $order ) {

			$this->customer_id = WFOCU_Common::get_order_meta( $order, '_wc_' . $this->get_key() . '_customer_id' );

			if ( ! empty( $this->customer_id ) ) {
				return $this->customer_id;
			}

			/**
			 * Fallback when token is not present in the parent order
			 */
			$get_secondary_order = WFOCU_Core()->data->get( 'authorize_net_cim_order_id', '', 'gateway' );

			if ( empty( $get_secondary_order ) ) {
				return '';
			}

			$this->customer_id = WFOCU_Common::get_order_meta( wc_get_order( $get_secondary_order ), '_wc_' . $this->get_key() . '_customer_id' );

			if ( ! empty( $this->customer_id ) ) {
				return $this->customer_id;
			}

			return '';

		}

		/**
		 * Get extra options for the CIM transaction.
		 *
		 * Extra options are fields that auth.net accepts but aren't part of the CIM API
		 *
		 * @return string
		 * @since 2.0.0
		 */
		protected function get_extra_options() {

			$options = array(
				'x_solution_id'      => 'A1000065',
				'x_customer_ip'      => WFOCU_WC_Compatibility::get_order_data( $this->order, 'customer_ip_address' ),
				'x_currency_code'    => WFOCU_WC_Compatibility::get_order_data( $this->order, 'currency' ),
				// TODO: this can be improved by detecting certain failure conditions (AVS/CVV failures) and dynamically setting the duplicate window to 0 as needed @MR
				'x_duplicate_window' => 0,
				'x_delim_char'       => '|',
				'x_encap_char'       => ':',
			);

			return http_build_query( $options, '', '&' );
		}

		public function get_request_attributes( $request ) {
			return array(
				'method'      => 'POST',
				'timeout'     => MINUTE_IN_SECONDS,
				'redirection' => 0,
				'httpversion' => '1.0',
				'sslverify'   => true,
				'blocking'    => true,
				'headers'     => array(
					'content-type' => 'application/json',
					'accept'       => 'application/json',
				),
				'body'        => $this->get_request_body( $request ),
				'cookies'     => array(),
			);
		}

		protected function get_request_body( $request ) {
			return wp_json_encode( $request );
		}

		private function get_transaction_id( $response ) {

			// in liveMode validation can't use the extraOptions request param
			// to set the response delimiter or encapulsation character, so we
			// may need to provide a filter for the delim/encaps chars used here
			// in case someone uses the liveMode filter and cannot set their merchant
			// acount to the values we use @MR

			// adjust response based on our hybrid delimiter :|: (delimiter = | encapsulation = :)
			// remove the leading encap character and add a trailing delimiter/encap character
			// so explode works correctly (direct response string starts and ends with an encapsulation
			// character)
			$direct_response = ltrim( strval( $response ), ':' ) . '|:';

			// parse response
			$response = explode( ':|:', $direct_response );

			if ( empty( $response ) ) {
				return '';
			}

			// offset array by 1 to match Authorize.Net's order, mainly for readability
			array_unshift( $response, null );

			$new_direct_response = array();

			// direct response fields are URL encoded, but we currently do not use any fields
			// (e.g. billing/shipping details) that would be affected by that
			$response_fields = array(
				'response_code'        => 1,
				'response_subcode'     => 2,
				'response_reason_code' => 3,
				'response_reason_text' => 4,
				'authorization_code'   => 5,
				'avs_response'         => 6,
				'transaction_id'       => 7,
				'amount'               => 10,
				'account_type'         => 11, // CC or ECHECK
				'transaction_type'     => 12, // AUTH_ONLY or AUTH_CAPTUREVOID probably
				'csc_response'         => 39,
				'cavv_response'        => 40,
				'account_last_four'    => 51,
				'card_type'            => 52,
			);

			foreach ( $response_fields as $field => $order ) {

				$new_direct_response[ $field ] = ( isset( $response[ $order ] ) ) ? $response[ $order ] : '';
			}

			return isset( $new_direct_response['transaction_id'] ) && '' !== $new_direct_response['transaction_id'] ? $new_direct_response['transaction_id'] : '';
		}

		public function get_shipping_addr( $order ) {
			// address fields

			$shipping_address = trim( WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_address_1' ) . ' ' . WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_address_2' ) );

			$fields = array(
				'firstName' => array(
					'value' => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_first_name' ),
					'limit' => 50,
				),
				'lastName'  => array(
					'value' => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_last_name' ),
					'limit' => 50,
				),
				'company'   => array(
					'value' => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_company' ),
					'limit' => 50,
				),
				'address'   => array(
					'value' => $shipping_address,
					'limit' => 60,
				),
				'city'      => array(
					'value' => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_city' ),
					'limit' => 40,
				),
				'state'     => array(
					'value' => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_state' ),
					'limit' => 40,
				),
				'zip'       => array(
					'value' => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_postcode' ),
					'limit' => 20,
				),
				'country'   => array(
					'value' => WFOCU_WC_Compatibility::get_order_data( $order, 'shipping_country' ),
					'limit' => 60,
				),
			);

			return $fields;

		}

		/**
		 * @param WC_Order $order
		 */
		public function maybe_add_shipping_address_id_order_for_guests( $order ) {
			if ( isset( $order->payment ) && isset( $order->payment->shipping_address_id ) && ! empty( $order->payment->shipping_address_id ) ) {
				$order->update_meta_data( '_authorize_cim_shipping_address_id', $order->payment->shipping_address_id );
				$order->save_meta_data();
				$order->save();
			}
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
			$gateway       = $this->get_wc_gateway();
			$refund_reason = isset( $refund_data['refund_reason'] ) ? $refund_data['refund_reason'] : '';

			// add refund info
			$order->refund           = new stdClass();
			$order->refund->amount   = number_format( $amnt, 2, '.', '' );
			$order->refund->reason   = $refund_reason;
			$order->refund->trans_id = $txn_id;


			if ( version_compare( WC_Authorize_Net_CIM::VERSION, '3.7.2', '>=' ) ) {
				// set token data on the order
				$transaction              = $api->get_transaction_details( $order->refund->trans_id, $order->get_id() );
				$order->refund->last_four = $transaction->get_last_four();

				if ( empty( $order->refund->last_four ) ) {
					$order->refund->last_four = $this->get_wc_gateway()->get_order_meta( $order, 'account_four' );
				}

				$expiry_date = $this->get_wc_gateway()->get_order_meta( $order, 'card_expiry_date' );

				if ( $expiry_date ) {
					$order->refund->expiry_date = gmdate( 'm-Y', strtotime( '20' . $expiry_date ) );
				}

				if ( empty( $order->refund->expiry_date ) ) {
					$order->refund->expiry_date = 'XXXX';
				}
				$response = $api->refund( $order );

				WFOCU_Core()->log->log( "WFOCU Authorize Offer refund transaction ID" . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$transaction_id = $response->get_transaction_id();

				if ( ! $transaction_id ) {
					$response                    = $api->void( $order );
					$transaction_id              = $response->get_transaction_id();
					$order->refund->wfocu_voided = true;
					WFOCU_Core()->log->log( "WFOCU Authorize Offer void transaction id: $transaction_id response: " . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				}
			} else {
				$order->refund->customer_profile_id         = $gateway->get_order_meta( $order, 'customer_id' );
				$order->refund->customer_payment_profile_id = $gateway->get_order_meta( $order, 'payment_token' );

				add_filter( 'wc_authorize_net_cim_api_request_data', array( $this, 'wfocu_modify_refund_request_data' ), 10, 2 );

				$response = $api->refund( $order );

				$transaction_id = $response->get_transaction_id();

				WFOCU_Core()->log->log( "WFOCU Authorize Offer refund transaction ID: $transaction_id response: " . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( ! $transaction_id ) {
					$response                    = $api->void( $order );
					$transaction_id              = $response->get_transaction_id();
					$order->refund->wfocu_voided = true;
					WFOCU_Core()->log->log( "WFOCU Authorize Offer void transaction id: $transaction_id response: " . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				}
			}

			return $transaction_id ? $transaction_id : false;

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
		 * Modifying refund request data Auth Offer post modified request data
		 *
		 * @param $request_data
		 * @param $order
		 * @param $gateway
		 */
		public function wfocu_modify_refund_request_data( $request_data, $order ) {

			if ( isset( $_POST['action'] ) && 'wfocu_admin_refund_offer' === $_POST['action'] ) {  // phpcs:ignore WordPress.Security.NonceVerification.Missing
				WFOCU_Core()->log->log( 'Auth request data: ' . print_r( $request_data, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$refund_data = $_POST;  // phpcs:ignore WordPress.Security.NonceVerification.Missing

				$offer_id = isset( $refund_data['offer_id'] ) ? $refund_data['offer_id'] : '';
				$order_id = WFOCU_WC_Compatibility::get_order_id( $order );

				if ( isset( $request_data['createCustomerProfileTransactionRequest'] ) && isset( $request_data['createCustomerProfileTransactionRequest']['refId'] ) ) {
					$request_data['createCustomerProfileTransactionRequest']['refId'] = $order_id . '_' . $offer_id;
				}

				if ( isset( $request_data['createCustomerProfileTransactionRequest'] ) && isset( $request_data['createCustomerProfileTransactionRequest']['transaction'] ) && isset( $request_data['createCustomerProfileTransactionRequest']['transaction']['profileTransRefund'] ) && isset( $request_data['createCustomerProfileTransactionRequest']['transaction']['profileTransRefund']['order'] ) && isset( $request_data['createCustomerProfileTransactionRequest']['transaction']['profileTransRefund']['order']['invoiceNumber'] ) ) {
					$request_data['createCustomerProfileTransactionRequest']['transaction']['profileTransRefund']['order']['invoiceNumber'] = $order_id . '_' . $offer_id;
				}
				WFOCU_Core()->log->log( 'Auth Offer post modified request data: ' . print_r( $request_data, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return $request_data;
		}

		/**
		 * Gets the current WordPress site name.
		 *
		 * This is helpful for retrieving the actual site name instead of the
		 * network name on multisite installations.
		 *
		 *
		 * @return string
		 */
		public function get_site_name() {

			return ( is_multisite() ) ? get_blog_details()->blogname : get_bloginfo( 'name' );
		}

		/**
		 * Format a number with 2 decimal points, using a period for the decimal
		 * separator and no thousands separator.
		 *
		 * Commonly used for payment gateways which require amounts in this format.
		 *
		 *
		 * @param float $number
		 *
		 * @return string
		 */
		public function number_format( $number ) {

			return number_format( (float) $number, 2, '.', '' );
		}

		/**
		 * Return a string with insane UTF-8 characters removed, like invisible
		 * characters, unused code points, and other weirdness. It should
		 * accept the common types of characters defined in Unicode.
		 *
		 * The following are allowed characters:
		 *
		 * p{L} - any kind of letter from any language
		 * p{Mn} - a character intended to be combined with another character without taking up extra space (e.g. accents, umlauts, etc.)
		 * p{Mc} - a character intended to be combined with another character that takes up extra space (vowel signs in many Eastern languages)
		 * p{Nd} - a digit zero through nine in any script except ideographic scripts
		 * p{Zs} - a whitespace character that is invisible, but does take up space
		 * p{P} - any kind of punctuation character
		 * p{Sm} - any mathematical symbol
		 * p{Sc} - any currency sign
		 *
		 * pattern definitions from http://www.regular-expressions.info/unicode.html
		 *
		 * @param string $string
		 *
		 * @return string
		 * @since 4.0.0
		 *
		 */
		public function str_to_sane_utf8( $string ) {

			$sane_string = preg_replace( '/[^\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Zs}\p{P}\p{Sm}\p{Sc}]/u', '', $string );

			// preg_replace with the /u modifier can return null or false on failure
			return ( is_null( $sane_string ) || false === $sane_string ) ? $string : $sane_string;
		}

		/**
		 * Truncates a given $string after a given $length if string is longer than
		 * $length. The last characters will be replaced with the $omission string
		 * for a total length not exceeding $length
		 *
		 * @param string $string text to truncate
		 * @param int $length total desired length of string, including omission
		 * @param string $omission omission text, defaults to '...'
		 *
		 * @return string
		 * @since 2.2.0
		 *
		 */
		public function str_truncate( $string, $length, $omission = '...' ) {

			if ( $this->multibyte_loaded() ) {

				// bail if string doesn't need to be truncated
				if ( mb_strlen( $string, self::MB_ENCODING ) <= $length ) {
					return $string;
				}

				$length -= mb_strlen( $omission, self::MB_ENCODING );

				return mb_substr( $string, 0, $length, self::MB_ENCODING ) . $omission;

			} else {

				$string = $this->str_to_ascii( $string );

				// bail if string doesn't need to be truncated
				if ( strlen( $string ) <= $length ) {
					return $string;
				}

				$length -= strlen( $omission );

				return substr( $string, 0, $length ) . $omission;
			}
		}

		/**
		 * Helper method to check if the multibyte extension is loaded, which
		 * indicates it's safe to use the mb_*() string methods
		 *
		 * @return bool
		 * @since 2.2.0
		 */
		protected function multibyte_loaded() {

			return extension_loaded( 'mbstring' );
		}

		/**
		 * Returns a string with all non-ASCII characters removed. This is useful
		 * for any string functions that expect only ASCII chars and can't
		 * safely handle UTF-8. Note this only allows ASCII chars in the range
		 * 33-126 (newlines/carriage returns are stripped)
		 *
		 * @param string $string string to make ASCII
		 *
		 * @return string
		 * @since 2.2.0
		 *
		 */
		public function str_to_ascii( $string ) {

			// strip ASCII chars 32 and under
			$string = filter_var( $string, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW );

			// strip ASCII chars 127 and higher
			return filter_var( $string, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH );
		}

		/**
		 * Override this method to handle scenarios of large order number
		 *
		 * @param WC_Order $order
		 *
		 * @return int|mixed|string|void
		 */
		public function get_order_number( $order ) {

			$order_number = parent::get_order_number( $order );
			if ( 20 <= strlen( $order_number ) ) {
				$get_type_index_offer = WFOCU_Core()->data->get( '_current_offer_type_index' );
				$order_number         = $order->get_id() . '_' . $get_type_index_offer;
			}

			return $order_number;

		}

		public function check_if_we_have_tokenized( $current_last_four, $get_saved_payment_methods ) {

			if ( empty( $get_saved_payment_methods ) ) {
				return false;
			}

			foreach ( $get_saved_payment_methods as $method ) {
				if ( true === $this->if_expired_card( $method->get_exp_year(), $method->get_exp_month() ) ) {
					continue;
				}
				if ( $current_last_four === $method->get_last_four() ) {
					return $method->get_id();
				}
			}

			return false;
		}

		/**
		 * Check if card validity is still there or not
		 *
		 * @param $year
		 * @param $month
		 */
		public function if_expired_card( $year, $month ) {
			$expires = DateTime::createFromFormat( 'mY', $month . $year );
			$now     = new DateTime();

			if ( $expires < $now ) {
				return true;
			} else {
				return false;
			}
		}

		public function _create_token( $order ) {
			try {
				$order = $this->get_wc_gateway()->get_payment_tokens_handler()->create_token( $order );


			} catch ( Exception $e ) {
				$this->is_error_token = true;
				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' Unable to create a token during primary order ' . $e->getCode() . "::" . $e->getMessage() );

			}

			return $order;
		}

		public function validate_and_process_customer_id( $customer_profile_id, $order, $create_token = false ) {
			/**
			 * Check if we have token against the extered card, if not the try creating one
			 */
			try {
				$existing_payment_methods = $this->get_wc_gateway()->get_api()->get_tokenized_payment_methods( $customer_profile_id )->get_payment_tokens();
				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' existing tokens found are  ' . print_r( $existing_payment_methods, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$maybe_get_token = $this->check_if_we_have_tokenized( $order->payment->last_four, $existing_payment_methods );

				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' last 4 is ' . print_r( $order->payment->last_four, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' get the token after matching last 4  ' . print_r( $maybe_get_token, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				WFOCU_Core()->data->set( 'authorize_net_cim_customer_id', $customer_profile_id, 'gateway' );
				WFOCU_Core()->data->save( 'gateway' );
			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . 'Error showed up while getting exiting tokens' . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}


			if ( ! empty( $maybe_get_token ) ) {
				/**
				 * save this token in the meta so that later it could be used for the payment
				 */
				$order->update_meta_data( '_wc_authorize_net_cim_credit_card_payment_token', $maybe_get_token );
				$order->save_meta_data();
			} else {
				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . 'attempt to create token for returning customer' );

				$order = $this->get_order( $order );
				if ( $create_token ) {
					$this->_create_token( $order );
				}

			}

			$order = $this->get_order( $order );

			$this->get_wc_gateway()->add_transaction_data( $order );
			WFOCU_Core()->data->save( 'gateway' );

			return $order;
		}


		/**
		 * Modify the upsell skip reason and order note for Stripe gateway.
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
		public function filter_upsell_skip_reason( $order, $skip_key, $reason_messages, $edit_link, $contact_support, $upsell_s_link ) {
			$custom_note = '';

			// Check if the skip reason corresponds to Stripe UPE mode being incompatible
			if ( $skip_key === 6 ) {

				$custom_note = sprintf( '<div style="display:flex;align-items:center;margin-bottom:4px;gap:4px;padding-left:20px !important;background: url(%s) no-repeat left !important;">
                    <strong style="font-size:13px;">%s</strong>
                </div>
                <strong>%s</strong>: %s
                <div style="margin:8px 0px;">%s <a href="%s" target="_blank">%s</a></div>', esc_url( WFOCU_PLUGIN_URL . '/admin/assets/img/icon_error.svg' ), __( 'Upsell Skipped', 'woofunnels-upstroke-one-click-upsell' ), __( 'Authorize.CIM gateway ', 'woofunnels-upstroke-one-click-upsell' ), __( 'The current Stripe UPE payment mode does not support one-click upsells.', 'woofunnels-upstroke-one-click-upsell' ), __( 'Recommendation: For best compatibility, switch to', 'woofunnels-upstroke-one-click-upsell' ), esc_url( $upsell_s_link ), __( 'FunnelKit Stripe Gateway', 'woofunnels-upstroke-one-click-upsell' ) );
			}

			return [
				'skip_id' => $skip_key,
				'note'    => ! empty( $custom_note ) ? $custom_note : ( $reason_messages[ $skip_key ] ?? '' )
			];
		}

	}


	WFOCU_Gateway_Integration_Authorize_Net_CIM::get_instance();
}