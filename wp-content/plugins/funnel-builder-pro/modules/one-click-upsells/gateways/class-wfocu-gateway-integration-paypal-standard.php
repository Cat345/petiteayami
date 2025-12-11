<?php
if ( ! class_exists( 'WFOCU_Gateway_Integration_PayPal_Standard' ) ) {
	/**
	 *  We have managed to integrate paypal standard with WFOCU.
	 * The trick is to fire Paypal Express che  ckout calls and modify the paypal arguments in such a way that further payment processing will be managed by Paypal Express checkout and not standard.
	 * We have also integrated SV_API_Base class to fire remote requests
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_PayPal_Standard extends WFOCU_Gateway {
		protected $key = 'paypal';
		protected static $ins = null;
		/** the production endpoint */
		const PRODUCTION_ENDPOINT = 'https://api-3t.paypal.com/nvp';

		/** the sandbox endpoint */
		const SANDBOX_ENDPOINT = 'https://api-3t.sandbox.paypal.com/nvp';


		/** @var array the request parameters */
		public $parameters = array();
		public $response_params = array();
		public $button_max_count = 0;
		/**
		 * List of locales supported by PayPal.
		 *
		 * @var array
		 */
		protected $_supported_locales = array(
			'da_DK',
			'de_DE',
			'en_AU',
			'en_GB',
			'en_US',
			'es_ES',
			'fr_CA',
			'fr_FR',
			'he_IL',
			'id_ID',
			'it_IT',
			'ja_JP',
			'nl_NL',
			'no_NO',
			'pl_PL',
			'pt_BR',
			'pt_PT',
			'ru_RU',
			'sv_SE',
			'th_TH',
			'tr_TR',
			'zh_CN',
			'zh_HK',
			'zh_TW',
		);

		public function __construct() {
			parent::__construct();


			add_filter( 'woocommerce_paypal_args', array( $this, 'modify_paypal_args' ), 10, 2 );

			add_action( 'wp_head', array( $this, 'maybe_check_ref_transaction' ) );

			add_action( 'wfocu_footer_before_print_scripts', array( $this, 'maybe_render_in_offer_transaction_scripts' ), 999 );
			add_filter( 'wfocu_allow_ajax_actions_for_charge_setup', array( $this, 'allow_paypal_express_check_action' ) );

			add_filter( 'wfocu_front_buy_button_attributes', array( $this, 'maybe_add_id_attribute_to_support_inline_paypal' ), 10, 2 );
			add_filter( 'wfocu_front_confirmation_button_attributes', array( $this, 'maybe_add_id_attribute_to_support_inline_paypal' ), 10 );

			/**
			 * handle pdt on offer pages
			 */


			//changing transaction id in offer refund function to set it of offer transaciton in stead of parent order
			add_filter( 'woocommerce_paypal_refund_request', array( $this, 'wfocu_woocommerce_paypal_refund_request_data' ), 10, 2 );

			$this->refund_supported = true;
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_check_ref_transaction() {

			if ( isset( $_GET['wfocu_paypal_check'] ) && current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$response = $this->set_express_checkout( array(
					'currency' => 'usd',

					'return_url' => $this->get_callback_url( 'wfocu_paypal_create_billing_agreement' ),
					'cancel_url' => $this->get_callback_url( 'wfocu_paypal_create_billing_agreement' ),
					'notify_url' => $this->get_callback_url( 'wfocu_paypal_create_billing_agreement' ),
				) );
				?>
                <div class="wfocu-notice bwf-notice notice notice-warning">
				<pre>
					<?php
					print_r( $response ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					?>
					</pre>
                </div>
				<?php
			}

			if ( isset( $_GET['wfocu_paypal_check_call'] ) && current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$response = $this->set_express_checkout( array(
					'currency'     => 'usd',
					'order'        => wc_get_order( wc_clean( $_GET['wfocu_paypal_check_call'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'billing_type' => '',
					'return_url'   => $this->get_callback_url( 'wfocu_paypal_create_billing_agreement' ),
					'cancel_url'   => $this->get_callback_url( 'wfocu_paypal_create_billing_agreement' ),
					'notify_url'   => $this->get_callback_url( 'wfocu_paypal_create_billing_agreement' ),

				) );
				?>
                <div class="wfocu-notice bwf-notice notice notice-warning">
				<pre>
				<?php
				print_r( $response );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				?>
					</pre>
                </div>
				<?php
			}

		}

		/**
		 * Modify paypal arguments & pass express checkout arguments
		 *
		 * @param array $args
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		public function modify_paypal_args( $args, $order ) {

			if ( false === $this->should_tokenize() ) {
				WFOCU_Core()->log->log( 'Switching back to paypal Standard: Reason: should_tokenize() false.' );

				return $args;
			}

			if ( false === $this->has_api_credentials_set() ) {
				WFOCU_Core()->log->log( 'Switching back to paypal Standard: Reason: Credentials are not set.' );

				return $args;
			}

			/**
			 * Check if gateway is enabled and we have reference transactions turned off.
			 */
			if ( true === $this->is_enabled() && false === $this->is_reference_trans_enabled() ) {

				WFOCU_Core()->funnels->setup_funnel_options( WFOCU_Core()->data->get_funnel_id() );
				$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
				$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;
				WFOCU_Core()->public->update_primary_order_meta( $order );
				if ( true === $is_batching_on ) {

					$order->update_meta_data( '_wfocu_paypal_hold_ipn', 'funnel' );
					$order->save_meta_data();
				}

				/**
				 * Set return URL for the PayPal, as the data setup completes, we can safely assume that this return URL would be a valid offer URL.
				 */
				$args['return'] = add_query_arg( array( 'wfocu-si'  => WFOCU_Core()->data->get_transient_key(),
				                                        'wfocu_ord' => $order->get_id()
				), WC()->api_request_url( 'wfocu_paypal_return_payment' ) );
				WFOCU_Core()->log->log( 'PayPal Args from WFOCU: ' . print_R( $args, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $args;
			} else {

				if ( true !== $this->is_enabled( $order ) ) {
					WFOCU_Core()->log->log( 'Switching back to paypal Standard: Reason: Paypal Standard is not enabled in settings. Or Subscription in the primary order' );

					return $args;
				}

				// First we need to request an express checkout token for setting up a billing agreement, to do that, we need to pull details of the transaction from the PayPal Standard args and massage them into the Express Checkout params
				try {
					$response = $this->set_express_checkout( array(
						'currency'    => $args['currency_code'],
						'return_url'  => $this->get_callback_url( 'wfocu_paypal_create_billing_agreement' ),
						'cancel_url'  => $args['cancel_return'],
						'notify_url'  => $args['notify_url'],
						'custom'      => $args['custom'],
						'order'       => $order,
						'no_shipping' => $order->needs_shipping_address() ? 0 : 1,
					) );

					if ( ! isset( $response['TOKEN'] ) || '' === $response['TOKEN'] ) {

						WFOCU_Core()->log->log( 'Switching back to paypal Standard: Reason: Unable to set Express checkout' );
						WFOCU_Core()->log->log( 'Result For setExpressCheckout: ' . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

						return $args;
					}
				} catch ( Exception $e ) {
					WFOCU_Core()->log->log( 'Switching back to paypal Standard: Reason: Unable to set Express checkout' );
					WFOCU_Core()->log->log( 'Exception For setExpressCheckout' . print_r( $e, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					return $args;
				}


				WFOCU_Core()->data->set( 'transient_key', '_wfocu_funnel_data_' . WFOCU_WC_Compatibility::get_order_id( $order ) );
				WFOCU_Core()->data->save();
				$paypal_args = array(
					'cmd'   => '_express-checkout',
					'token' => $response['TOKEN'],
				);

				return $paypal_args;
			}

		}


		/**
		 * Get the wc-api URL to redirect to
		 *
		 * @param string $action checkout action, either `set_express_checkout or `get_express_checkout_details`
		 *
		 * @return string URL
		 * @since 2.0
		 */
		public function get_callback_url( $action ) {
			return add_query_arg( 'action', $action, WC()->api_request_url( 'wfocu_paypal' ) );
		}

		public function has_api_credentials_set() {
			$credentials_are_set = false;
			$environment         = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'production';

			$api_creds_prefix = '';
			if ( 'sandbox' === $environment ) {
				$api_creds_prefix = 'sandbox_';
			}

			if ( '' !== $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_username' ) && '' !== $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_password' ) && '' !== $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_signature' ) ) {
				$credentials_are_set = true;
			}

			return $credentials_are_set;
		}

		/**
		 * Sets the prams for setExpressCheckout call and executes it
		 *
		 * @param array $args
		 * @param bool $is_upsell
		 *
		 * @return object
		 * @throws Exception
		 */
		public function set_express_checkout( $args, $is_upsell = false ) {

			$environment = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'production';

			$api_creds_prefix = '';
			if ( 'sandbox' === $environment ) {
				$api_creds_prefix = 'sandbox_';
			}

			$this->set_api_credentials( $this->get_key(), $environment, $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_username' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_password' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_signature' ) );
			$this->set_express_checkout_args( $args, $is_upsell );
			$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );

			$request         = new stdClass();
			$request->path   = '';
			$request->method = 'POST';
			$request->body   = $this->to_string();
			WFOCU_Core()->data->set( 'paypal_request_data', $this->get_parameters(), 'paypal' );

			return $this->perform_request( $request );
		}

		/**
		 * Get Details about the passed express checkout token
		 *
		 * @param $token
		 *
		 * @return object
		 * @throws Exception
		 */
		public function get_express_checkout_details( $token ) {
			$environment      = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'production';
			$api_creds_prefix = '';
			if ( 'sandbox' === $environment ) {
				$api_creds_prefix = 'sandbox_';
			}
			$this->set_api_credentials( $this->get_key(), $environment, $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_username' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_password' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_signature' ) );
			$this->get_express_checkout_args( $token );
			$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );
			$request         = new stdClass();
			$request->path   = '';
			$request->method = 'POST';
			$request->body   = $this->to_string();

			return $this->perform_request( $request );
		}


		/**
		 * Sets up arguments and performs DoExpressCheckout call
		 *
		 * @param $token
		 * @param $order
		 * @param $args
		 *
		 * @return object
		 * @throws Exception
		 */
		public function do_express_checkout( $token, $order, $args ) {
			$environment      = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'production';
			$api_creds_prefix = '';
			if ( 'sandbox' === $environment ) {
				$api_creds_prefix = 'sandbox_';
			}
			$this->set_api_credentials( $this->get_key(), $environment, $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_username' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_password' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_signature' ) );
			$this->set_do_express_checkout_args( $token, $order, $args );
			$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );
			$request         = new stdClass();
			$request->path   = '';
			$request->method = 'POST';
			$request->body   = $this->to_string();

			return $this->perform_request( $request );
		}


		/**
		 * Sets up arguments and performs DoReferenceTransaction call
		 *
		 * @param $billing_agreement_id
		 * @param $order
		 * @param $args
		 *
		 * @return object
		 * @throws Exception
		 */
		public function do_reference_transaction( $billing_agreement_id, $order, $args ) {
			$environment      = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'production';
			$api_creds_prefix = '';
			if ( 'sandbox' === $environment ) {
				$api_creds_prefix = 'sandbox_';
			}
			$this->set_api_credentials( $this->get_key(), $environment, $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_username' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_password' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_signature' ) );
			$this->do_reference_transaction_args( $billing_agreement_id, $order, $args );
			$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );
			$request         = new stdClass();
			$request->path   = '';
			$request->method = 'POST';
			$request->body   = $this->to_string();

			return $this->perform_request( $request );
		}

		/**
		 * Sets up GetExpressCheckoutDetails API call arguments
		 *
		 * @param string $token
		 *
		 * @see WFOCU_Gateway_Integration_PayPal_Standard::get_express_checkout_details()
		 *
		 */
		public function get_express_checkout_args( $token ) {

			$this->set_method( 'GetExpressCheckoutDetails' );
			$this->add_parameter( 'TOKEN', $token );
		}

		/**
		 * Sets up DoExpressCheckoutPayment API Call arguments
		 *
		 * @param string $token Unique token of the payment initiated
		 * @param WC_Order $order
		 * @param array $args
		 */
		public function set_do_express_checkout_args( $token, $order, $args ) {
			$this->set_method( 'DoExpressCheckoutPayment' );

			// set base params
			$this->add_parameters( array(
				'TOKEN'            => $token,
				'PAYERID'          => $args['payer_id'],
				'BUTTONSOURCE'     => 'WooThemes_Cart',
				'RETURNFMFDETAILS' => 1,
			) );

			$this->add_payment_details_parameters( $order, $args['payment_action'] );
		}


		/**
		 * Sets up API credentials to the class that we need later during the API call
		 *
		 * @param $gateway_id
		 * @param $api_environment
		 * @param $api_username
		 * @param $api_password
		 * @param $api_signature
		 */
		public function set_api_credentials( $gateway_id, $api_environment, $api_username, $api_password, $api_signature ) {
			// tie API to gateway
			$this->gateway_id = $gateway_id;

			// request URI does not vary per-request
			$this->request_uri = ( 'production' === $api_environment ) ? self::PRODUCTION_ENDPOINT : self::SANDBOX_ENDPOINT;

			// PayPal requires HTTP 1.1
			$this->request_http_version = '1.1';

			$this->api_username  = $api_username;
			$this->api_password  = $api_password;
			$this->api_signature = $api_signature;
		}

		/**
		 * @hooked over `wc_api_wfocu_paypal`
		 * Its a redirect from paypal and contains success
		 *
		 */
		public function maybe_create_billing() {
			if ( ! isset( $_GET['action'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			switch ( $_GET['action'] ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				// called when the customer is returned from PayPal after authorizing their payment, used for retrieving the customer's checkout details
				case 'wfocu_paypal_create_billing_agreement':
					// bail if no token
					if ( ! isset( $_GET['token'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						return;
					}

					// get token to retrieve checkout details with
					$token = wc_clean( $_GET['token'] );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended

					try {

						$express_checkout_details_response = $this->get_express_checkout_details( $token );

						// Make sure the billing agreement was accepted
						if ( 1 === $express_checkout_details_response['BILLINGAGREEMENTACCEPTEDSTATUS'] || '1' === $express_checkout_details_response['BILLINGAGREEMENTACCEPTEDSTATUS'] ) {

							$order = $this->get_order_from_response( $express_checkout_details_response );

							if ( is_null( $order ) ) {
								throw new WFOCU_Payment_Gateway_Exception( __( 'Unable to find order for PayPal billing agreement.', 'woofunnels-upstroke-one-click-upsell' ), 101, $this->get_key() );
							}

							// we need to process an initial payment
							if ( WFOCU_Common::get_amount_for_comparisons( $order->get_total() ) > 0 ) {
								$billing_agreement_response = $this->do_express_checkout( $token, $order, array(
									'payment_action' => 'Sale',
									'payer_id'       => $this->get_value_from_response( $express_checkout_details_response, 'PAYERID' ),
								) );
							} else {
								throw new WFOCU_Payment_Gateway_Exception( __( 'Order total is not greater than zero.', 'woofunnels-upstroke-one-click-upsell' ), 101, $this->get_key() );

							}

							$get_session = $this->get_session_from_response( $express_checkout_details_response );

							if ( ! empty( $get_session ) ) {

								WFOCU_Core()->data->transient_key = $get_session;
								WFOCU_Core()->data->load_funnel_from_session();
							}
							if ( $this->has_api_error( $billing_agreement_response ) ) {

								WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $order ) . ' Billing agreement Failure found. Report Below' );
								WFOCU_Core()->log->log( print_r( $billing_agreement_response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								throw new WFOCU_Payment_Gateway_Exception( $this->get_api_error( $billing_agreement_response ), 101, $this->get_key() );


							}


							$order->set_payment_method( 'paypal' );

							WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $order ) . ': DoexpressCheckoutREsponse' . print_r( $billing_agreement_response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

							$order->update_meta_data( '_paypal_subscription_id', $this->get_value_from_response( $billing_agreement_response, 'BILLINGAGREEMENTID' ) );
							/**
							 * mark primary payment as completed & this will also envoke upstroke to setup funnnel
							 */
							$order->payment_complete( $billing_agreement_response['PAYMENTINFO_0_TRANSACTIONID'] );
							$order->save_meta_data();
							/**
							 * If funnel got envoke properly the, this will redirect to the offer
							 */
							$redirect_url = add_query_arg( 'utm_nooverride', '1', $order->get_checkout_order_received_url() );

							// redirect customer to order received page
							wp_safe_redirect( esc_url_raw( $redirect_url ) );
							exit;

						} else {
							$this->handle_api_failures( $order );

						}
					} catch ( Exception $e ) {
						$this->handle_api_failures( $order, $e );
					}


			}
		}

		/**
		 * Handles Payment Gateway API error
		 *
		 * @param $order
		 * @param $e
		 */
		protected function handle_api_failures( $order, $e = '' ) {

			if ( $order instanceof WC_Order ) {

				if ( $e instanceof WFOCU_Payment_Gateway_Exception ) {

					$order->add_order_note( $e->getMessage() );
				}
				$redirect = WFOCU_Core()->public->get_clean_order_received_url( true, true );
				wp_redirect( $redirect );
				exit;
			}
			wp_die( 'Unable to process further. Please contact to the store admin & enquire about the status of your order.' );
			exit;
		}

		/**
		 * Return the parsed response object for the request
		 *
		 * @param string $raw_response_body
		 *
		 * @return object
		 * @since 2.2.0
		 *
		 */
		protected function get_parsed_response( $raw_response_body ) {

			wp_parse_str( urldecode( $raw_response_body ), $this->response_params );

			return $this->response_params;
		}


		/**
		 * Returns the string representation of this request
		 *
		 * @return string the request query string
		 * @see SV_WC_Payment_Gateway_API_Request::to_string()
		 * @since 2.0
		 */
		public function to_string() {
			WFOCU_Core()->log->log( print_r( $this->get_parameters(), true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return http_build_query( $this->get_parameters(), '', '&' );
		}


		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) {

			$get_token = $this->get_token( $order );

			if ( false === $get_token ) {
				return false;
			}

			if ( '' === $get_token ) {
				return false;
			}

			if ( null === $get_token ) {
				return false;
			}

			return true;

		}

		public function get_token( $order ) {
			if ( false === is_null( $this->token ) ) {
				return $this->token;
			}
			$this->token = $order->get_meta( '_paypal_subscription_id' );
			if ( '' === $this->token ) {
				$this->token = WFOCU_Common::get_order_meta( $order, '_paypal_subscription_id' );

			}
			if ( ! empty( $this->token ) ) {
				return $this->token;
			}

			return apply_filters( 'wfocu_front_gateway_integration_get_token', false, $this );
		}


		public function process_charge( $order ) {

			$is_successful = false;

			/**
			 * Check if reference transaction was turned on or not,
			 * If found disabled and code reached at this point meaning express checkout in offer payment JS breaks at some point
			 * we ned to simply return from here with an error
			 */
			if ( false === $this->is_reference_trans_enabled() ) {
				throw new WFOCU_Payment_Gateway_Exception( __( 'A JavaScript Error on offer page caused payment failture.', 'woofunnels-upstroke-one-click-upsell' ), 101, $this->get_key() );
			}
			$response = $this->do_reference_transaction( $this->get_token( $order ), $order, array() );

			if ( $this->has_api_error( $response ) ) {
				WFOCU_Core()->log->log( 'PayPal DoReferenceTransactionCall Failed: Response Below' );
				WFOCU_Core()->log->log( print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$is_successful = false;
				throw new WFOCU_Payment_Gateway_Exception( $this->get_api_error( $response ), 101, $this->get_key() );

			} else {
				WFOCU_Core()->data->set( '_transaction_id', $response['TRANSACTIONID'] );

				$is_successful = true;

			}


			return $this->handle_result( $is_successful );
		}


		/**
		 * Construct an PayPal Express request object
		 *
		 * @param string $api_username the API username
		 * @param string $api_password the API password
		 * @param string $api_signature the API signature
		 * @param string $api_version the API version
		 *
		 * @since 2.0
		 */
		public function populate_credentials( $api_username, $api_password, $api_signature, $api_version ) {

			$this->add_parameters( array(
				'USER'      => $api_username,
				'PWD'       => $api_password,
				'SIGNATURE' => $api_signature,
				'VERSION'   => $api_version,
			) );
		}

		/**
		 * Sets up the express checkout transaction
		 *
		 * @link https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECGettingStarted/#id084RN060BPF
		 * @link https://developer.paypal.com/webapps/developer/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
		 *
		 * @param array $args {
		 *
		 * @type string 'currency'              (Optional) A 3-character currency code (default is store's currency).
		 * @type string 'billing_type'          (Optional) Type of billing agreement for reference transactions. You must have permission from PayPal to use this field. This field must be set to one of the following values: MerchantInitiatedBilling - PayPal creates a billing agreement for each transaction associated with buyer. You must specify version 54.0 or higher to use this option; MerchantInitiatedBillingSingleAgreement - PayPal creates a single billing agreement for all transactions associated with buyer. Use this value unless you need per-transaction billing agreements. You must specify version 58.0 or higher to use this option.
		 * @type string 'billing_description'   (Optional) Description of goods or services associated with the billing agreement. This field is required for each recurring payment billing agreement if using MerchantInitiatedBilling as the billing type, that means you can use a different agreement for each subscription/order. PayPal recommends that the description contain a brief summary of the billing agreement terms and conditions (but this only makes sense when the billing type is MerchantInitiatedBilling, otherwise the terms will be incorrectly displayed for all agreements). For example, buyer is billed at "9.99 per month for 2 years".
		 * @type string 'maximum_amount'        (Optional) The expected maximum total amount of the complete order and future payments, including shipping cost and tax charges. If you pass the expected average transaction amount (default 25.00). PayPal uses this value to validate the buyer's funding source.
		 * @type string 'no_shipping'           (Optional) Determines where or not PayPal displays shipping address fields on the PayPal pages. For digital goods, this field is required, and you must set it to 1. It is one of the following values: 0 – PayPal displays the shipping address on the PayPal pages; 1 – PayPal does not display shipping address fields whatsoever (default); 2 – If you do not pass the shipping address, PayPal obtains it from the buyer's account profile.
		 * @type string 'page_style'            (Optional) Name of the Custom Payment Page Style for payment pages associated with this button or link. It corresponds to the HTML variable page_style for customizing payment pages. It is the same name as the Page Style Name you chose to add or edit the page style in your PayPal Account profile.
		 * @type string 'brand_name'            (Optional) A label that overrides the business name in the PayPal account on the PayPal hosted checkout pages. Default: store name.
		 * @type string 'landing_page'          (Optional) Type of PayPal page to display. It is one of the following values: 'login' – PayPal account login (default); 'Billing' – Non-PayPal account.
		 * @type string 'payment_action'        (Optional) How you want to obtain payment. If the transaction does not include a one-time purchase, this field is ignored. Default 'Sale' – This is a final sale for which you are requesting payment (default). Alternative: 'Authorization' – This payment is a basic authorization subject to settlement with PayPal Authorization and Capture. You cannot set this field to Sale in SetExpressCheckout request and then change the value to Authorization or Order in the DoExpressCheckoutPayment request. If you set the field to Authorization or Order in SetExpressCheckout, you may set the field to Sale.
		 * @type string 'return_url'            (Required) URL to which the buyer's browser is returned after choosing to pay with PayPal.
		 * @type string 'cancel_url'            (Required) URL to which the buyer is returned if the buyer does not approve the use of PayPal to pay you.
		 * @type string 'custom'                (Optional) A free-form field for up to 256 single-byte alphanumeric characters
		 * }
		 * @since 2.0
		 */
		public function set_express_checkout_args( $args, $is_upsell = false ) {

			// translators: placeholder is blogname
			$default_description = sprintf( _x( 'Orders with %s', 'data sent to paypal', 'woofunnels-upstroke-one-click-upsell' ), get_bloginfo( 'name' ) );

			$defaults = array(
				'currency'            => get_woocommerce_currency(),
				'billing_type'        => 'MerchantInitiatedBillingSingleAgreement',
				'billing_description' => html_entity_decode( apply_filters( 'woocommerce_subscriptions_paypal_billing_agreement_description', $default_description, $args ), ENT_NOQUOTES, 'UTF-8' ),
				'maximum_amount'      => null,
				'no_shipping'         => 1,
				'page_style'          => null,
				'brand_name'          => html_entity_decode( get_bloginfo( 'name' ), ENT_NOQUOTES, 'UTF-8' ),
				'landing_page'        => 'login',
				'payment_action'      => 'Sale',
				'custom'              => '',
			);

			$args = wp_parse_args( $args, $defaults );

			$this->set_method( 'SetExpressCheckout' );

			$this->add_parameters( array(

				'RETURNURL'   => $args['return_url'],
				'CANCELURL'   => $args['cancel_url'],
				'PAGESTYLE'   => $args['page_style'],
				'BRANDNAME'   => $args['brand_name'],
				'LANDINGPAGE' => ( 'login' === $args['landing_page'] && $is_upsell === false ) ? 'Login' : 'Billing',
				'NOSHIPPING'  => $args['no_shipping'],
				'MAXAMT'      => $args['maximum_amount'],
			) );

			if ( false === $is_upsell ) {
				$this->add_parameter( 'L_BILLINGTYPE0', $args['billing_type'] );
				$this->add_parameter( 'L_BILLINGAGREEMENTDESCRIPTION0', get_bloginfo( 'name' ) );
				$this->add_parameter( 'L_BILLINGAGREEMENTCUSTOM0', '' );
			}
			// if we have an order, the request is to create a subscription/process a payment (not just check if the PayPal account supports Reference Transactions)
			if ( isset( $args['order'] ) ) {

				if ( true === $is_upsell ) {
					$this->add_payment_details_parameters( $args['order'], $args['payment_action'], false, true );

				} else {
					$this->add_payment_details_parameters( $args['order'], $args['payment_action'] );

				}
			}
			if ( empty( $args['no_shipping'] ) ) {
				$this->maybe_add_shipping_address_params( $args['order'] );

			}
			$set_express_checkout_params = apply_filters( 'wfocu_gateway_paypal_param_setexpresscheckout', $this->get_parameters(), $is_upsell );
			if ( isset( $set_express_checkout_params['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] ) && 2 === strlen( $set_express_checkout_params['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] ) ) {
				$set_express_checkout_params['ADDRESSOVERRIDE'] = '1';
			}
			$this->clean_params();
			$this->add_parameters( $set_express_checkout_params );
		}


		/**
		 * Create a billing agreement, required when a subscription sign-up has no initial payment
		 *
		 * @link https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECReferenceTxns/#id094TB0Y0J5Z__id094TB4003HS
		 * @link https://developer.paypal.com/docs/classic/api/merchant/CreateBillingAgreement_API_Operation_NVP/
		 *
		 * @param string $token token from SetExpressCheckout response
		 *
		 * @since 2.0
		 */
		public function create_billing_agreement( $token ) {

			$this->set_method( 'CreateBillingAgreement' );
			$this->add_parameter( 'TOKEN', $token );
		}

		/**
		 * Charge a payment against a reference token
		 *
		 * @link https://developer.paypal.com/docs/classic/express-checkout/integration-guide/ECReferenceTxns/#id094UM0DA0HS
		 * @link https://developer.paypal.com/docs/classic/api/merchant/DoReferenceTransaction_API_Operation_NVP/
		 *
		 * @param string $reference_id the ID of a reference object, e.g. billing agreement ID.
		 * @param WC_Order $order order object
		 * @param array $args {
		 *
		 * @type string 'payment_type'         (Optional) Specifies type of PayPal payment you require for the billing agreement. It is one of the following values. 'Any' or 'InstantOnly'. Echeck is not supported for DoReferenceTransaction requests.
		 * @type string 'payment_action'       How you want to obtain payment. It is one of the following values: 'Authorization' - this payment is a basic authorization subject to settlement with PayPal Authorization and Capture; or 'Sale' - This is a final sale for which you are requesting payment.
		 * @type string 'return_fraud_filters' (Optional) Flag to indicate whether you want the results returned by Fraud Management Filters. By default, you do not receive this information.
		 * }
		 * @since 2.0
		 */
		public function do_reference_transaction_args( $reference_id, $order, $args = array() ) {
			$get_package = WFOCU_Core()->data->get( '_upsell_package' );

			$defaults = array(
				'amount'               => $get_package['total'],
				'payment_type'         => 'Any',
				'payment_action'       => 'Sale',
				'return_fraud_filters' => 1,
				'notify_url'           => WC()->api_request_url( 'WC_Gateway_Paypal' ),
				'invoice_number'       => $this->get_order_number( $order ),
			);

			$args = wp_parse_args( $args, $defaults );

			$this->set_method( 'DoReferenceTransaction' );


			// set base params
			$this->add_parameters( array(
				'REFERENCEID'      => $reference_id,
				'BUTTONSOURCE'     => 'WooThemes_Cart',
				'RETURNFMFDETAILS' => $args['return_fraud_filters'],
			) );

			$this->add_payment_details_parameters( $order, $args['payment_action'], true, true );
			if ( true === WFOCU_Core()->process_offer->package_needs_shipping() ) {
				$this->maybe_add_shipping_address_params( $order, 'SHIPTO' );
			}

		}

		/**
		 * Set up the payment details for a DoExpressCheckoutPayment or DoReferenceTransaction request
		 *
		 * @param WC_Order $order order object
		 * @param string $type the type of transaction for the payment
		 * @param bool $use_deprecated_params whether to use deprecated PayPal NVP parameters (required for DoReferenceTransaction API calls)
		 *
		 * @since 2.0.9
		 *
		 */
		protected function add_payment_details_parameters( WC_Order $order, $type, $use_deprecated_params = false, $is_offer_charge = false ) {

			$calculated_total = 0;
			$order_subtotal   = 0;
			$item_count       = 0;
			$order_items      = array();

			$offer_package = WFOCU_Core()->data->get( '_upsell_package' );

			if ( true === $is_offer_charge ) {

				foreach ( $offer_package['products'] as $item ) {

					$product = $item['data'];

					$order_items[] = array(
						'NAME'    => $product->get_title(),
						'DESC'    => $this->get_item_description( $product, $is_offer_charge ),
						'AMT'     => $this->round( $item['price'] ),
						'QTY'     => 1,
						'ITEMURL' => $product->get_permalink(),
					);

					$order_subtotal += $item['args']['total'];
				}
			} else {
				// add line items
				foreach ( $order->get_items() as $item ) {

					$product = new WC_Product( $item['product_id'] );

					$order_items[] = array(
						'NAME'    => $product->get_title(),
						'DESC'    => $this->get_item_description( $product, $is_offer_charge ),
						'AMT'     => $this->round( $order->get_item_subtotal( $item ) ),
						'QTY'     => ( ! empty( $item['qty'] ) ) ? absint( $item['qty'] ) : 1,
						'ITEMURL' => $product->get_permalink(),
					);

					$order_subtotal += $item['line_total'];
				}

				// add fees
				foreach ( $order->get_fees() as $fee ) {

					$order_items[] = array(
						'NAME' => ( $fee['name'] ),
						'AMT'  => $this->round( $fee['line_total'] ),
						'QTY'  => 1,
					);

					$order_subtotal += $fee['line_total'];
				}
				if ( $order->get_total_discount() > 0 ) {

					$order_items[] = array(
						'NAME' => __( 'Total Discount', 'woofunnels-upstroke-one-click-upsell' ),
						'QTY'  => 1,
						'AMT'  => - $this->round( $order->get_total_discount() ),
					);
				}
			}

			/**Do things for the main order **/
			if ( false === $is_offer_charge ) {
				if ( $this->skip_line_items( $order ) ) {

					$total_amount = $this->round( $order->get_total() );

					// calculate the total as PayPal would
					$calculated_total += $this->round( $order_subtotal + $order->get_cart_tax() ) + $this->round( $order->get_total_shipping() + $order->get_shipping_tax() );

					// offset the discrepancy between the WooCommerce cart total and PayPal's calculated total by adjusting the order subtotal
					if ( $this->price_format( $total_amount ) !== $this->price_format( $calculated_total ) ) {
						$order_subtotal = $order_subtotal - ( $calculated_total - $total_amount );
					}

					$item_names = array();

					foreach ( $order_items as $item ) {
						$item_names[] = sprintf( '%1$s x %2$s', $item['NAME'], $item['QTY'] );
					}

					// add a single item for the entire order
					$this->add_line_item_parameters( array(
						// translators: placeholder is blogname
						'NAME' => sprintf( __( '%s - Order', 'woofunnels-upstroke-one-click-upsell' ), get_option( 'blogname' ) ),
						'DESC' => $this->get_item_description( implode( ', ', $item_names ) ),
						'AMT'  => $this->round( $order_subtotal + $order->get_cart_tax() ),
						'QTY'  => 1,
					), 0, $use_deprecated_params );

					// add order-level parameters
					//  - Do not send the TAXAMT due to rounding errors
					if ( $use_deprecated_params ) {
						$this->add_parameters( array(
							'AMT'              => $total_amount,
							'CURRENCYCODE'     => WFOCU_WC_Compatibility::get_order_currency( $order ),
							'ITEMAMT'          => $this->round( $order_subtotal + $order->get_cart_tax() ),
							'SHIPPINGAMT'      => $this->round( $order->get_total_shipping() + $order->get_shipping_tax() ),
							'INVNUM'           => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . WFOCU_Common::str_to_ascii( ltrim( $order->get_order_number(), _x( '#', 'hash before the order number. Used as a character to remove from the actual order number', 'woofunnels-upstroke-one-click-upsell' ) ) ),
							'PAYMENTACTION'    => $type,
							'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),
							'CUSTOM'           => wp_json_encode( array(
								'_wfocu_o_id'       => WFOCU_WC_Compatibility::get_order_id( $order ),
								'_wfocu_session_id' => WFOCU_Core()->data->get_transient_key(),
							) ),
						) );
					} else {
						$this->add_payment_parameters( array(
							'AMT'              => $total_amount,
							'CURRENCYCODE'     => WFOCU_WC_Compatibility::get_order_currency( $order ),
							'ITEMAMT'          => $this->round( $order_subtotal + $order->get_cart_tax() ),
							'SHIPPINGAMT'      => $this->round( $order->get_total_shipping() + $order->get_shipping_tax() ),
							'INVNUM'           => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . WFOCU_Common::str_to_ascii( ltrim( $order->get_order_number(), _x( '#', 'hash before the order number. Used as a character to remove from the actual order number', 'woofunnels-upstroke-one-click-upsell' ) ) ),
							'PAYMENTACTION'    => $type,
							'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),
							'CUSTOM'           => wp_json_encode( array(
								'_wfocu_o_id'       => WFOCU_WC_Compatibility::get_order_id( $order ),
								'_wfocu_session_id' => WFOCU_Core()->data->get_transient_key(),
							) ),
						) );
					}
				} else {

					// add individual order items
					foreach ( $order_items as $item ) {
						$this->add_line_item_parameters( $item, $item_count ++, $use_deprecated_params );
					}

					$total_amount = $this->round( $order->get_total() );
					// add order-level parameters
					if ( $use_deprecated_params ) {
						$this->add_parameters( array(
							'AMT'              => $total_amount,
							'CURRENCYCODE'     => WFOCU_WC_Compatibility::get_order_currency( $order ),
							'ITEMAMT'          => $this->round( $order_subtotal ),
							'SHIPPINGAMT'      => $this->round( $order->get_total_shipping() ),
							'TAXAMT'           => $this->round( $order->get_total_tax() ),
							'INVNUM'           => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
							'PAYMENTACTION'    => $type,
							'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),

						) );
					} else {
						$this->add_payment_parameters( array(
							'AMT'              => $total_amount,
							'CURRENCYCODE'     => WFOCU_WC_Compatibility::get_order_currency( $order ),
							'ITEMAMT'          => $this->round( $order_subtotal ),
							'SHIPPINGAMT'      => $this->round( $order->get_total_shipping() ),
							'TAXAMT'           => $this->round( $order->get_total_tax() ),
							'INVNUM'           => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
							'PAYMENTACTION'    => $type,
							'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),
							'CUSTOM'           => wp_json_encode( array(
								'_wfocu_o_id'       => WFOCU_WC_Compatibility::get_order_id( $order ),
								'_wfocu_session_id' => WFOCU_Core()->data->get_transient_key(),

							) ),
						) );
					}
				}
			} /** Handle paypal data setup for the offers */

			else {

				/**
				 * Code for reference transaction
				 */
				$total_amount = $offer_package['total'];

				$item_names = array();

				foreach ( $order_items as $item ) {
					$item_names[] = sprintf( '%1$s x %2$s', $item['NAME'], $item['QTY'] );
				}
				$item_count = 0;
				// add individual order items
				foreach ( $order_items as $item ) {
					$this->add_line_item_parameters( $item, $item_count ++, $use_deprecated_params );
				}

				/**
				 * Check if this is a referencetransaction call then send depreceated params
				 */
				if ( true === $use_deprecated_params && true === $is_offer_charge ) {
					/**
					 * When shipping amount is a negative number, means user opted for free shipping offer
					 * In this case we setup shippingamt as 0 and shipping discount amount is that negative amount that is coming.
					 */
					if ( ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) && 0 > $offer_package['shipping']['diff']['cost'] ) {
						$this->add_parameters( array(
							'AMT'              => $total_amount,
							'CURRENCYCODE'     => WFOCU_WC_Compatibility::get_order_currency( $order ),
							'ITEMAMT'          => $this->round( $order_subtotal ),
							'SHIPPINGAMT'      => 0,
							'SHIPDISCAMT'      => ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) ? $offer_package['shipping']['diff']['cost'] : 0,
							'INVNUM'           => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
							'PAYMENTACTION'    => $type,
							'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),
							'TAXAMT'           => ( isset( $offer_package['taxes'] ) ) ? $offer_package['taxes'] : 0,
							'CUSTOM'           => wp_json_encode( array(
								'_wfocu_o_id'       => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
								'_wfocu_session_id' => WFOCU_Core()->data->get_transient_key(),
							) ),
						) );
					} else {
						$this->add_parameters( array(
							'AMT'              => $total_amount,
							'CURRENCYCODE'     => WFOCU_WC_Compatibility::get_order_currency( $order ),
							'ITEMAMT'          => $this->round( $order_subtotal ),
							'SHIPPINGAMT'      => ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) ? $offer_package['shipping']['diff']['cost'] : 0,
							'INVNUM'           => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
							'PAYMENTACTION'    => $type,
							'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),
							'TAXAMT'           => ( isset( $offer_package['taxes'] ) ) ? $offer_package['taxes'] : 0,
							'CUSTOM'           => wp_json_encode( array(
								'_wfocu_o_id'       => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
								'_wfocu_session_id' => WFOCU_Core()->data->get_transient_key(),
							) ),
						) );
					}
				} else {
					if ( ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) && 0 > $offer_package['shipping']['diff']['cost'] ) {
						$this->add_payment_parameters( array(
							'AMT'              => $total_amount,
							'CURRENCYCODE'     => WFOCU_WC_Compatibility::get_order_currency( $order ),
							'ITEMAMT'          => $this->round( $order_subtotal ),
							'SHIPPINGAMT'      => 0,
							'SHIPDISCAMT'      => ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) ? $offer_package['shipping']['diff']['cost'] : 0,
							'INVNUM'           => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
							'PAYMENTACTION'    => $type,
							'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),
							'TAXAMT'           => ( isset( $offer_package['taxes'] ) ) ? $offer_package['taxes'] : 0,
							'NOTIFYURL'        => WC()->api_request_url( 'WC_Gateway_Paypal' ),
							'CUSTOM'           => wp_json_encode( array(
								'_wfocu_o_id'       => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
								'_wfocu_session_id' => WFOCU_Core()->data->get_transient_key(),
							) ),
						) );
					} else {
						$this->add_payment_parameters( array(
							'AMT'              => $total_amount,
							'CURRENCYCODE'     => WFOCU_WC_Compatibility::get_order_currency( $order ),
							'ITEMAMT'          => $this->round( $order_subtotal ),
							'SHIPPINGAMT'      => ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) ? $offer_package['shipping']['diff']['cost'] : 0,
							'INVNUM'           => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
							'PAYMENTACTION'    => $type,
							'NOTIFYURL'        => WC()->api_request_url( 'WC_Gateway_Paypal' ),
							'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),
							'TAXAMT'           => ( isset( $offer_package['taxes'] ) ) ? $offer_package['taxes'] : 0,
							'CUSTOM'           => wp_json_encode( array(
								'_wfocu_o_id'       => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
								'_wfocu_session_id' => WFOCU_Core()->data->get_transient_key(),
							) ),
						) );
					}
				}
			}
		}



		/** Helper Methods ******************************************************/

		/**
		 * Add a parameter
		 *
		 * @param string $key
		 * @param string|int $value
		 *
		 * @since 2.0
		 */
		public function add_parameter( $key, $value ) {
			$this->parameters[ $key ] = $value;
		}

		/**
		 * Add multiple parameters
		 *
		 * @param array $params
		 *
		 * @since 2.0
		 */
		public function add_parameters( array $params ) {
			foreach ( $params as $key => $value ) {
				$this->add_parameter( $key, $value );
			}
		}

		public function clean_params() {
			$this->parameters = array();
		}

		/**
		 * Set the method for the request, currently using:
		 *
		 * + `SetExpressCheckout` - setup transaction
		 * + `GetExpressCheckout` - gets buyers info from PayPal
		 * + `DoExpressCheckoutPayment` - completes the transaction
		 * + `DoCapture` - captures a previously authorized transaction
		 *
		 * @param string $method
		 *
		 * @since 2.0
		 */
		private function set_method( $method ) {
			$this->add_parameter( 'METHOD', $method );
		}

		/**
		 * Add payment parameters, auto-prefixes the parameter key with `PAYMENTREQUEST_0_`
		 * for convenience and readability
		 *
		 * @param array $params
		 *
		 * @since 2.0
		 */
		private function add_payment_parameters( array $params ) {
			foreach ( $params as $key => $value ) {
				$this->add_parameter( "PAYMENTREQUEST_0_{$key}", $value );
			}
		}

		/**
		 * Adds a line item parameters to the request, auto-prefixes the parameter key
		 * with `L_PAYMENTREQUEST_0_` for convenience and readability
		 *
		 * @param array $params
		 * @param int $item_count current item count
		 *
		 * @since 2.0
		 */
		private function add_line_item_parameters( array $params, $item_count, $use_deprecated_params = false ) {
			foreach ( $params as $key => $value ) {
				if ( $use_deprecated_params ) {
					$this->add_parameter( "L_{$key}{$item_count}", $value );
				} else {
					$this->add_parameter( "L_PAYMENTREQUEST_0_{$key}{$item_count}", $value );
				}
			}
		}

		/**
		 * Helper method to return the item description, which is composed of item
		 * meta flattened into a comma-separated string, if available. Otherwise the
		 * product SKU is included.
		 *
		 * The description is automatically truncated to the 127 char limit.
		 *
		 * @param array $item cart or order item
		 * @param \WC_Product $product product data
		 *
		 * @return string
		 * @since 2.0
		 */
		private function get_item_description( $product_or_str ) {

			if ( is_string( $product_or_str ) ) {
				$str = $product_or_str;
			} else {
				$str = $product_or_str->get_short_description();
			}
			$item_desc = wp_strip_all_tags( wp_specialchars_decode( wp_staticize_emoji( $str ) ) );
			$item_desc = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $item_desc );
			$item_desc = str_replace( "\n", ', ', rtrim( $item_desc ) );
			if ( strlen( $item_desc ) > 127 ) {
				$item_desc = substr( $item_desc, 0, 124 ) . '...';
			}

			return html_entity_decode( $item_desc, ENT_NOQUOTES, 'UTF-8' );

		}

		private function get_country( $country ) {
			if ( 2 === strlen( $country ) ) {
				return $country;
			}

			return substr( $country, 0, 2 );
		}


		/**
		 * Returns the string representation of this request with any and all
		 * sensitive elements masked or removed
		 *
		 * @return string the pretty-printed request array string representation, safe for logging
		 * @see SV_WC_Payment_Gateway_API_Request::to_string_safe()
		 * @since 2.0
		 */
		public function to_string_safe() {

			$request = $this->get_parameters();

			$sensitive_fields = array( 'USER', 'PWD', 'SIGNATURE' );

			foreach ( $sensitive_fields as $field ) {

				if ( isset( $request[ $field ] ) ) {

					$request[ $field ] = str_repeat( '*', strlen( $request[ $field ] ) );
				}
			}

			return print_r( $request, true );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}

		/**
		 * Returns the request parameters after validation & filtering
		 *
		 * @return array request parameters
		 * @throws \Exception invalid amount
		 * @since 2.0
		 */
		public function get_parameters() {

			/**
			 * Filter PPE request parameters.
			 *
			 * Use this to modify the PayPal request parameters prior to validation
			 *
			 * @param array $parameters
			 * @param \WC_PayPal_Express_API_Request $this instance
			 */
			$this->parameters = apply_filters( 'wcs_paypal_request_params', $this->parameters, $this );

			// validate parameters
			foreach ( $this->parameters as $key => $value ) {

				// remove unused params
				if ( '' === $value || is_null( $value ) ) {
					unset( $this->parameters[ $key ] );
				}

				// format and check amounts
				if ( false !== strpos( $key, 'AMT' ) ) {

					// amounts must be 10,000.00 or less for USD
					if ( isset( $this->parameters['PAYMENTREQUEST_0_CURRENCYCODE'] ) && 'USD' === $this->parameters['PAYMENTREQUEST_0_CURRENCYCODE'] && $value > 10000 ) {

						throw new WFOCU_Payment_Gateway_Exception( sprintf( '%s amount of %s must be less than $10,000.00', $key, $value ), 101, $this->get_key() );
					}

					// PayPal requires locale-specific number formats (e.g. USD is 123.45)
					// PayPal requires the decimal separator to be a period (.)
					$this->parameters[ $key ] = $this->price_format( $value );
				}
			}

			return $this->parameters;
		}

		/**
		 * Checks if currency in setting supports 0 decimal places.
		 *
		 * @return bool Returns true if currency supports 0 decimal places
		 * @since 1.2.0
		 *
		 */
		public function is_currency_supports_zero_decimal() {
			return in_array( get_woocommerce_currency(), array( 'HUF', 'JPY', 'TWD' ), true );
		}

		/**
		 * Get number of digits after the decimal point.
		 *
		 * @return int Number of digits after the decimal point. Either 2 or 0
		 * @since 1.2.0
		 *
		 */
		public function get_number_of_decimal_digits() {
			return $this->is_currency_supports_zero_decimal() ? 0 : 2;
		}

		/**
		 * PayPal cannot properly calculate order totals when prices include tax (due
		 * to rounding issues), so line items are skipped and the order is sent as
		 * a single item
		 *
		 * @param WC_Order $order Optional. The WC_Order object. Default null.
		 *
		 * @return bool true if line items should be skipped, false otherwise
		 * @since 2.0.9
		 *
		 */
		private function skip_line_items( $order = null ) {

			$skip_line_items = false;
			// Also check actual totals add up just in case totals have been manually modified to amounts that can not round correctly, see https://github.com/Prospress/woocommerce-subscriptions/issues/2213
			if ( ! is_null( $order ) ) {

				$rounded_total = 0;
				$decimals      = $this->get_number_of_decimal_digits();
				foreach ( $order->get_items() as $values ) {
					$amount        = round( $values['line_subtotal'] / $values['qty'], $decimals );
					$rounded_total += round( $amount * $values['qty'], $decimals );
				}

				$discounts = $order->get_total_discount();

				$items = array();
				foreach ( $order->get_items() as $values ) {
					$amount = round( $values['line_subtotal'] / $values['qty'], $decimals );
					$item   = array(
						'name'     => $values['name'],
						'quantity' => $values['qty'],
						'amount'   => $amount,
					);

					$items[] = $item;
				}
				$details                = array(
					'total_item_amount' => round( $order->get_subtotal(), $decimals ) + $discounts,
					'order_tax'         => round( $order->get_total_tax(), $decimals ),
					'shipping'          => round( $order->get_shipping_total(), $decimals ),
					'items'             => $items,
				);
				$details['order_total'] = round( $details['total_item_amount'] + $details['order_tax'] + $details['shipping'], $decimals );
				if ( (float) $details['order_total'] !== (float) $order->get_total() ) {
					WFOCU_Core()->log->log( "Paypal adjusting totals.Category totals mismatch" );
					$skip_line_items = true;
				}
				if ( $details['total_item_amount'] !== $rounded_total ) {
					WFOCU_Core()->log->log( "Paypal adjusting totals.Category item amount mismatch" );
					$skip_line_items = true;
				}
			}

			/**
			 * Filter whether line items should be skipped or not
			 *
			 * @param bool $skip_line_items True if line items should be skipped, false otherwise
			 * @param WC_Order /null $order The WC_Order object or null.
			 *
			 * @since 3.3.0
			 *
			 */
			return apply_filters( 'wfocu_paypal_skip_line_items', $skip_line_items, $order );
		}

		/**
		 * Round a float
		 *
		 * @param float $number
		 * @param int $precision Optional. The number of decimal digits to round to.
		 *
		 * @since 2.0.9
		 *
		 */
		private function round( $number, $precision = 2 ) {
			return round( (float) $number, $precision );
		}

		/**
		 * Format prices.
		 *
		 * @param float|int $price
		 * @param int $decimals Optional. The number of decimal points.
		 *
		 * @return string
		 * @since 2.2.12
		 *
		 */
		private function price_format( $price, $decimals = 2 ) {
			return number_format( $price, $decimals, '.', '' );
		}

		public function get_value_from_response( $response, $key ) {

			if ( $response && isset( $response[ $key ] ) ) {

				return $response[ $key ];
			}
		}

		public function get_order_from_response( $response ) {

			if ( $response && isset( $response['CUSTOM'] ) ) {
				$getjson = json_decode( $response['CUSTOM'], true );

				return wc_get_order( $getjson['_wfocu_o_id'] );
			}
		}

		public function get_session_from_response( $response ) {

			if ( $response && isset( $response['CUSTOM'] ) ) {
				$getjson = json_decode( $response['CUSTOM'], true );

				return ( $getjson['_wfocu_session_id'] );
			}
		}

		public function has_api_error( $response ) {
			// assume something went wrong if ACK is missing
			if ( ! isset( $response['ACK'] ) ) {
				return true;
			}

			// any non-success ACK is considered an error, see
			// https://developer.paypal.com/docs/classic/api/NVPAPIOverview/#id09C2F0K30L7
			return ( 'Success' !== $this->get_value_from_response( $response, 'ACK' ) && 'SuccessWithWarning' !== $this->get_value_from_response( $response, 'ACK' ) );

		}

		public function get_api_error( $response ) {

			if ( 'Failure' === $this->get_value_from_response( $response, 'ACK' ) ) {
				return $this->get_value_from_response( $response, 'L_LONGMESSAGE0' );
			}

			return '';
		}

		public function get_transaction_id( $response ) {

			if ( is_array( $response ) && isset( $response['PAYMENTINFO_0_TRANSACTIONID'] ) ) {
				return $response['PAYMENTINFO_0_TRANSACTIONID'];
			}

			return '';
		}


		public function is_reference_trans_enabled() {
			$is_reference_transaction_on = WFOCU_Core()->data->get_option( 'paypal_ref_trans' );
			if ( 'yes' === $is_reference_transaction_on ) {

				return true;
			}

			return false;
		}









		/************************************** PAYPAL IN_OFFER TRANSACTION STARTS *********************/


		/**
		 * Tell the system to run without a token or not
		 * @return bool
		 */
		public function is_run_without_token() {
			return true;
		}


		public function maybe_render_in_offer_transaction_scripts() {
			$order = WFOCU_Core()->data->get_current_order();

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( $this->get_key() !== $order->get_payment_method() || true === $this->is_reference_trans_enabled() ) {
				return;
			}

			$environment = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'live';

			?>

            <script src="https://www.paypalobjects.com/api/checkout.js"></script>  <?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
            <script>

                (
                    function ($) {
                        "use strict";

                        var $wcc_ppec = {
                            paypalBucket: null,
                            init: function () {
                                var getButtonsMax = <?php echo $this->button_max_count; ?>;
                                var getButtons = [];
                                for (var i = 0; i <= getButtonsMax; i++) {
                                    getButtons.push('wfocu_paypal_only_' + i);
                                }
                                var getShElems = document.getElementsByClassName('wfocu_paypal_in_context_btn');
                                for (var key in getShElems) {
                                    getButtons.push(getShElems[key]);
                                }

                                window.paypalCheckoutReady = function () {
                                    paypal.checkout.setup(
                                        '<?php echo esc_js( $this->get_payer_id() ); ?>',
                                        {
                                            environment: '<?php echo esc_js( $environment ); ?>',
                                            buttons: getButtons,
                                            locale: '<?php echo esc_js( $this->get_paypal_locale() ); ?>',

                                            click: function () {

                                                if (0 == $wcc_ppec.paypalBucket.getTotal()) {
                                                    wfocu_paypal_standard_paypal_in_transaction_checkout_process($wcc_ppec, $, paypal);
                                                    return;
                                                }

                                                $wcc_ppec.paypalBucket.swal.hide();
                                                paypal.checkout.initXO();


                                                if (null !== $wcc_ppec.paypalBucket.ShippingCall) {

                                                    $wcc_ppec.paypalBucket.ShippingCall.done(function (data) {
                                                        wfocu_paypal_standard_paypal_in_transaction_checkout_process($wcc_ppec, $, paypal)

                                                    });

                                                    $wcc_ppec.paypalBucket.ShippingCall.fail(function (data) {
                                                        wfocu_paypal_standard_paypal_in_transaction_checkout_process($wcc_ppec, $, paypal)

                                                    });

                                                } else {
                                                    wfocu_paypal_standard_paypal_in_transaction_checkout_process($wcc_ppec, $, paypal)
                                                }

                                            }

                                        }
                                    );
                                }
                            }
                        };


                        $(document).on('wfocuBucketCreated', function (e, Bucket) {
                            $wcc_ppec.init();
                            $wcc_ppec.paypalBucket = Bucket;

                        });
                        $(document).on('wfocu_external', function (e, Bucket) {
                            Bucket.inOfferTransaction = true;

                        });

                        $(document).on('wfocuBucketConfirmationRendered', function (e, Bucket) {
                            $wcc_ppec.init();
                            $wcc_ppec.paypalBucket = Bucket;

                        });
                        $(document).on('wfocuBucketLinksConverted', function (e, Bucket) {
                            $wcc_ppec.init();
                            $wcc_ppec.paypalBucket = Bucket;

                        });

                        function wfocu_paypal_standard_paypal_in_transaction_checkout_process($wcc_ppec, $, paypal) {

                            var getBucketData = $wcc_ppec.paypalBucket.getBucketSendData();

                            var postData = $.extend(getBucketData, {action: 'wfocu_front_create_express_checkout_token'});


                            if (typeof wfocu_vars.wc_ajax_url !== "undefined") {
                                var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_create_express_checkout_token'), postData);

                            } else {
                                var action = $.post(wfocu_vars.ajax_url, postData);

                            }

                            action.done(function (data) {

                                if (typeof data.token === "undefined") {
                                    paypal.checkout.closeFlow();

                                    if (typeof data.processed !== "undefined") {
                                        $wcc_ppec.paypalBucket.swal.show({'text': wfocu_vars.messages.offer_success_message_pop, 'type': 'success'});
                                        window.location = data.processed_data.redirect_url;
                                        return;
                                    }

                                    $wcc_ppec.paypalBucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                    if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                        setTimeout(function () {
                                            window.location = data.response.redirect_url;
                                        }, 1500);
                                    } else {
                                        /** move to order received page */
                                        if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                            window.location = wfocu_vars.order_received_url + '&ec=paypal_token_not_found';

                                        }
                                    }
                                } else {
                                    paypal.checkout.startFlow(data.token);
                                }

                            });

                            action.fail(function () {

                                /** move to order received page */
                                if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                    window.location = wfocu_vars.order_received_url + '&ec=' + jqXHR.status;

                                }
                            });
                        }


                    }


                )(jQuery);
            </script>
			<?php
		}


		public function create_express_checkout_token() {


			$get_current_offer      = WFOCU_Core()->data->get( 'current_offer' );
			$get_current_offer_meta = WFOCU_Core()->offers->get_offer_meta( $get_current_offer );
			WFOCU_Core()->data->set( '_offer_result', true );
			$posted_data = WFOCU_Core()->process_offer->parse_posted_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$response = false;

			if ( true === WFOCU_AJAX_Controller::validate_charge_request( $posted_data ) ) {

				WFOCU_Core()->process_offer->execute( $get_current_offer_meta );

				$get_package = WFOCU_Core()->data->get( '_upsell_package' );
				if ( $get_package && 0 === WFOCU_Common::get_amount_for_comparisons( $get_package['total'] ) && true === apply_filters( 'wfocu_allow_free_upsells', true ) ) {
					$data = WFOCU_Core()->process_offer->_handle_upsell_charge( true );
					wp_send_json( array(
						'result'         => 'success',
						'processed'      => true,
						'processed_data' => $data
					) );
				}
				$get_order = WFOCU_Core()->data->get_parent_order();

				$response = $this->set_express_checkout( array(
					'currency'    => WFOCU_WC_Compatibility::get_order_currency( $get_order ),
					'return_url'  => $this->get_callback_url( 'wfocu_paypal_return' ),
					'cancel_url'  => $this->get_callback_url( 'cancel_url' ),
					'notify_url'  => $this->get_callback_url( 'notify_url' ),
					'order'       => $get_order,
					'no_shipping' => WFOCU_Core()->process_offer->package_needs_shipping() ? 0 : 1,
				), true );

				WFOCU_Core()->log->log( 'Result For setExpressCheckout: ' . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( isset( $response['TOKEN'] ) && '' !== $response['TOKEN'] ) {
					WFOCU_Core()->data->set( 'token', $response['TOKEN'], 'paypal' );
					WFOCU_Core()->data->set( 'upsell_package', WFOCU_Core()->data->get( '_upsell_package' ), 'paypal' );
					WFOCU_Core()->data->save( 'paypal' );
					WFOCU_Core()->data->save();
					wp_send_json( array(
						'result' => 'success',
						'token'  => $response['TOKEN'],
					) );
				} else {
					$get_error_str = $this->get_api_error( $response );
					$get_order->add_order_note( sprintf( __( 'Offer payment failed. Reason: %s', 'woofunnels-upstroke-one-click-upsell' ), $get_error_str ) );

					$data     = WFOCU_Core()->process_offer->_handle_upsell_charge( false );
					$response = $data;
				}
			} else {

			}
			wp_send_json( array(
				'result'   => 'error',
				'response' => $response,
			) );

		}


		public function allow_paypal_express_check_action( $actions ) {
			array_push( $actions, 'wfocu_front_create_express_checkout_token' );

			return $actions;
		}


		/**
		 * Get PayPal redirect URL.
		 *
		 * @param string $token Token
		 * @param bool $commit If set to true, 'useraction' parameter will be set
		 *                       to 'commit' which makes PayPal sets the button text
		 *                       to **Pay Now** ont the PayPal _Review your information_
		 *                       page.
		 * @param bool $ppc Whether to use PayPal credit.
		 *
		 * @return string PayPal redirect URL
		 */
		public function get_paypal_redirect_url( $token, $commit = false, $ppc = false ) {
			$url = 'https://www.';

			$url .= 'sandbox.';

			$url .= 'paypal.com/checkoutnow?token=' . rawurlencode( $token );

			if ( $commit ) {
				$url .= '&useraction=commit';
			}

			if ( $ppc ) {
				$url .= '#/checkout/chooseCreditOffer';
			}

			return $url;
		}

		public function handle_api_calls() {
			if ( ! isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			$order = null;
			try {
				switch ( $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

					case 'wfocu_paypal_return':
						$existing_package = WFOCU_Core()->data->get( 'upsell_package', '', 'paypal' );

						if ( isset( $_GET['token'] ) && ! empty( $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

							$express_checkout_details_response = $this->get_express_checkout_details( wc_clean( $_GET['token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							WFOCU_Core()->log->log( '$express_checkout_details_response ' . print_r( $express_checkout_details_response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

							/***
							 * Get session ID from the response from the PayPal
							 */
							$get_session = $this->get_session_from_response( $express_checkout_details_response );

							if ( ! empty( $get_session ) ) {

								WFOCU_Core()->data->transient_key = $get_session;
								WFOCU_Core()->data->load_funnel_from_session();
							}

							$existing_package = WFOCU_Core()->data->get( 'upsell_package', '', 'paypal' );

							if ( empty( $existing_package ) ) {
								WFOCU_Core()->log->log( 'PayPal Express Checkout API return does not have a valid transient set. ' );
								exit;
							}

							$order = WFOCU_Core()->data->get_current_order();
							/**
							 * Setting up necessary data for this api call
							 */
							add_filter( 'wfocu_valid_state_for_data_setup', '__return_true' );
							WFOCU_Core()->template_loader->set_offer_id( WFOCU_Core()->data->get_current_offer() );

							WFOCU_Core()->template_loader->maybe_setup_offer();

							$api_response_result = false;
							/**
							 * get the data we saved while calling setExpressCheckout call
							 */
							$get_paypal_data = WFOCU_Core()->data->get( 'paypal_request_data', array(), 'paypal' );

							/**
							 * Usually We do not process 0 amount process, we can safely assume here that if o amount is passed by the API we can treat it as successful upsell
							 */
							if ( $existing_package['total'] > 0 ) {
								/**
								 * Prepare DoExpessCheckout Call to finally charge the user
								 */
								$do_express_checkout_data = array(
									'TOKEN'   => $express_checkout_details_response['TOKEN'],
									'PAYERID' => $express_checkout_details_response['PAYERID'],
									'METHOD'  => 'DoExpressCheckoutPayment',
								);

								$do_express_checkout_data = wp_parse_args( $do_express_checkout_data, $get_paypal_data );

								$environment      = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'production';
								$api_creds_prefix = '';
								if ( 'sandbox' === $environment ) {
									$api_creds_prefix = 'sandbox_';
								}

								/**
								 * Setup & perform DoExpressCheckout API Call
								 */
								$this->set_api_credentials( $this->get_key(), $environment, $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_username' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_password' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_signature' ) );
								$this->add_parameters( $do_express_checkout_data );
								$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );

								$request         = new stdClass();
								$request->path   = '';
								$request->method = 'POST';
								$request->body   = $this->to_string();

								$response_checkout = $this->perform_request( $request );
								WFOCU_Core()->log->log( 'PayPal In-offer transactions DoexpressCheckout response. ' . print_r( $response_checkout, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

								if ( false === $this->has_api_error( $response_checkout ) ) {
									WFOCU_Core()->data->set( '_transaction_id', $this->get_transaction_id( $response_checkout ) );
									$api_response_result = true;
								}
							} else {
								$api_response_result = true;
							}

							/**** DoExpressCheckout Call Completed ******/

							WFOCU_Core()->data->set( '_offer_result', true );
							WFOCU_Core()->data->save();
							/**
							 * Allow our subscription addon to make subscription request
							 */
							$api_response_result = apply_filters( 'wfocu_gateway_in_offer_transaction_paypal_after_express_checkout_response', $api_response_result, $express_checkout_details_response['TOKEN'], $express_checkout_details_response['PAYERID'], $this );

							/**
							 * Set the upsell package data so that order processing will process this
							 */
							WFOCU_Core()->data->set( '_upsell_package', $existing_package );

							$data = WFOCU_Core()->process_offer->_handle_upsell_charge( $api_response_result );

							wp_redirect( $data['redirect_url'] );
							exit;
						} else {
							/**
							 * Set the upsell package data so that order processing will process this
							 */
							WFOCU_Core()->data->set( '_upsell_package', $existing_package );

							$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );

							wp_redirect( $data['redirect_url'] );
							exit;
						}

						break;

					case 'cancel_url':

						/**
						 * Getting the current URL from the session and loading the same offer again.
						 * User needs to chose "no thanks" if he want to move to upsell/order received.
						 */

						$get_offer = WFOCU_Core()->data->get_current_offer();
						wp_redirect( WFOCU_Core()->public->get_the_upsell_url( $get_offer ) );
						exit;

				}

			} catch ( Exception $e ) {
				$this->handle_api_failures( $order, $e );
			}
		}


		public function maybe_add_id_attribute_to_support_inline_paypal( $attributes, $iteration = 1 ) {

			$get_current_order = WFOCU_Core()->data->get_current_order();

			if ( ! $get_current_order instanceof WC_Order ) {
				return $attributes;
			}


			if ( true === WFOCU_Core()->public->if_is_preview() ) {
				return $attributes;
			}

			if ( false === $this->is_enabled() ) {
				return $attributes;
			}

			if ( $get_current_order->get_payment_method() !== $this->get_key() ) {
				return $attributes;
			}

			if ( true === $this->is_reference_trans_enabled() ) {
				return $attributes;
			}
			$get_offer_settings = WFOCU_Core()->data->get( '_current_offer_data' );
			$current_action     = current_action();

			if ( ( false === $get_offer_settings->settings->ask_confirmation && 'wfocu_front_buy_button_attributes' === $current_action ) || ( true === $get_offer_settings->settings->ask_confirmation && 'wfocu_front_confirmation_button_attributes' === $current_action ) ) {
				$this->button_max_count ++;
				$attributes['id'] = 'wfocu_paypal_only_' . $iteration;
			}

			return $attributes;

		}

		/**
		 * Get payer ID from API.
		 */
		public function get_payer_id() {

			$environment = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'production';

			$api_creds_prefix = '';
			if ( 'sandbox' === $environment ) {
				$api_creds_prefix = 'sandbox_';
			}

			$option_key = 'woocommerce_ppec_payer_id_' . $environment . '_' . md5( $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_username' ) . ':' . $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_password' ) );
			$payer_id   = get_option( $option_key );
			if ( $payer_id ) {
				return $payer_id;
			} else {
				$result = $this->get_pal_details();

				if ( ! empty( $result['PAL'] ) ) {
					update_option( $option_key, wc_clean( $result['PAL'] ) );

					return $payer_id;
				}
			}

			return false;
		}

		public function get_pal_details() {

			$environment      = ( true === $this->get_wc_gateway()->testmode ) ? 'sandbox' : 'production';
			$api_creds_prefix = '';
			if ( 'sandbox' === $environment ) {
				$api_creds_prefix = 'sandbox_';
			}
			$this->set_api_credentials( $this->get_key(), $environment, $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_username' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_password' ), $this->get_wc_gateway()->get_option( $api_creds_prefix . 'api_signature' ) );

			$this->add_parameter( 'METHOD', 'GetPalDetails' );
			$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );
			$request         = new stdClass();
			$request->path   = '';
			$request->method = 'POST';
			$request->body   = $this->to_string();

			return $this->perform_request( $request );

		}

		/**
		 * Get locale for PayPal.
		 *
		 * @return string
		 */
		public function get_paypal_locale() {
			$locale = get_locale();
			if ( ! in_array( $locale, $this->_supported_locales, true ) ) {
				$locale = 'en_US';
			}

			return $locale;
		}

		/********************** PAYPAL IN-OFFER PURCHASE ********************************/


		/**
		 * We have to handle PDT as the return url when funnel runs is not checkout/order-received but offer url
		 * Hence we need to trigger paypal PDT Handler class so that it could process further.
		 */
		public function maybe_handle_pdt( $order_id ) {


			if ( empty( $_REQUEST['cm'] ) || empty( $_REQUEST['tx'] ) || empty( $_REQUEST['st'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			add_action( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'maybe_mark_order_status' ) );

			/**
			 * check if identity token is there and class does not exists then add the class
			 */
			if ( $this->get_wc_gateway()->identity_token && false === class_exists( 'WC_Gateway_Paypal_PDT_Handler' ) ) {
				include_once plugin_dir_path( WC_PLUGIN_FILE ) . 'includes/gateways/paypal/includes/class-wc-gateway-paypal-pdt-handler.php';
			}

			if ( class_exists( 'WC_Gateway_Paypal_PDT_Handler' ) ) {

				WFOCU_Core()->log->log( 'PDT Payment initialized' );

				if ( version_compare( WC()->version, '6.3.1', '>=' ) ) {
					$email = $this->get_wc_gateway()->get_option( 'receiver_email', $this->get_wc_gateway()->get_option( 'email' ) );
					$pdt   = new WC_Gateway_Paypal_PDT_Handler( $this->get_wc_gateway()->testmode, $this->get_wc_gateway()->identity_token );
					$pdt->set_receiver_email( $email );

					$pdt->check_response_for_order( $order_id );
				} else {
					$pdt = new WC_Gateway_Paypal_PDT_Handler( $this->get_wc_gateway()->testmode, $this->get_wc_gateway()->identity_token );
					$pdt->check_response();
				}


				/**
				 * Save Paypal IPN status so that it will be used when we move to correct status once funnel finishes.
				 */
				$status    = wc_clean( strtolower( wp_unslash( $_REQUEST['st'] ) ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$get_order = WFOCU_Core()->data->get_current_order();
				if ( $get_order ) {
					$get_order->update_meta_data( '_wfocu_paypal_ipn_status', $status );
					$get_order->save_meta_data();
				}
			}
		}

		/**
		 * Conditionally triggers to handle PayPal PDT handler callback conditions
		 *
		 * @param $status
		 *
		 * @return array
		 * @see WC_Order::needs_payment()
		 * @see WC_Gateway_Paypal_PDT_Handler::check_response()
		 */
		public function maybe_mark_order_status( $status ) {
			remove_action( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'maybe_mark_order_status' ) );

			$status[] = 'wfocu-pri-order';

			return $status;
		}

		/**
		 * @param WC_Order $order
		 */
		function maybe_add_shipping_address_params( $order, $prefix = 'PAYMENTREQUEST_0_SHIPTO' ) {

			if ( $order->has_shipping_address() ) {
				$shipping_first_name = $order->get_shipping_first_name();
				$shipping_last_name  = $order->get_shipping_last_name();
				$shipping_address_1  = $order->get_shipping_address_1();
				$shipping_address_2  = $order->get_shipping_address_2();
				$shipping_city       = $order->get_shipping_city();
				$shipping_state      = $order->get_shipping_state();
				$shipping_postcode   = $order->get_shipping_postcode();
				$shipping_country    = $order->get_shipping_country();
			} else {
				$shipping_first_name = $order->get_billing_first_name();
				$shipping_last_name  = $order->get_billing_last_name();
				$shipping_address_1  = $order->get_billing_address_1();
				$shipping_address_2  = $order->get_billing_address_2();
				$shipping_city       = $order->get_billing_city();
				$shipping_state      = $order->get_billing_state();
				$shipping_postcode   = $order->get_billing_postcode();
				$shipping_country    = $order->get_billing_country();
			}
			if ( empty( $shipping_country ) ) {
				$shipping_country = WC()->countries->get_base_country();
			}

			$shipping_phone = $order->get_billing_phone();

			$params = array(
				$prefix . 'NAME'        => $shipping_first_name . ' ' . $shipping_last_name,
				$prefix . 'STREET'      => $shipping_address_1,
				$prefix . 'STREET2'     => $shipping_address_2,
				$prefix . 'CITY'        => $shipping_city,
				$prefix . 'STATE'       => $shipping_state,
				$prefix . 'ZIP'         => $shipping_postcode,
				$prefix . 'COUNTRYCODE' => $this->get_country( $shipping_country ),
				$prefix . 'PHONENUM'    => $shipping_phone,
			);
			foreach ( $params as $key => $val ) {
				$this->add_parameter( $key, $val );
			}

		}


		/**
		 * Handling refund offer request
		 *
		 * @param $order
		 *
		 * @return bool
		 */
		public function process_refund_offer( $order ) {
			$refund_data   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order_id      = WFOCU_WC_Compatibility::get_order_id( $order );
			$amount        = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
			$refund_reason = isset( $refund_data['refund_reason'] ) ? $refund_data['refund_reason'] : '';

			$response = false;

			if ( ! is_null( $amount ) && class_exists( 'WC_Gateway_Paypal' ) ) {
				$paypal = $this->get_wc_gateway();
				if ( $paypal->can_refund_order( $order ) ) {

					if ( ! class_exists( 'WC_Gateway_Paypal_API_Handler' ) ) {
						include_once wc()->plugin_path() . '/includes/gateways/paypal/includes/class-wc-gateway-paypal-api-handler.php';  //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomFunction
					}

					WC_Gateway_Paypal_API_Handler::$api_username  = $paypal->testmode ? $paypal->get_option( 'sandbox_api_username' ) : $paypal->get_option( 'api_username' );
					WC_Gateway_Paypal_API_Handler::$api_password  = $paypal->testmode ? $paypal->get_option( 'sandbox_api_password' ) : $paypal->get_option( 'api_password' );
					WC_Gateway_Paypal_API_Handler::$api_signature = $paypal->testmode ? $paypal->get_option( 'sandbox_api_signature' ) : $paypal->get_option( 'api_signature' );
					WC_Gateway_Paypal_API_Handler::$sandbox       = $paypal->testmode;

					$result = WC_Gateway_Paypal_API_Handler::refund_transaction( $order, $amount, $refund_reason );

					if ( is_wp_error( $result ) ) {
						WFOCU_Core()->log->log( "Paypal refund failed for order: {$order_id}, Error: " . print_r( $result->get_error_message(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					} else {
						switch ( strtolower( $result->ACK ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
							case 'success':
							case 'successwithwarning':
								$response = $result->REFUNDTRANSACTIONID;
						}
					}
					if ( isset( $result->L_LONGMESSAGE0 ) ) {
						WFOCU_Core()->log->log( "Paypal refund error message: " . print_r( $result->L_LONGMESSAGE0, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					}
				}
			}

			return $response ? $response : false;
		}

		/**
		 * @hooked over woocommerce_paypal_refund_request
		 *
		 * Changing transaction id in offer refund function to set it of offer transaciton in stead of parent order
		 */
		public function maybe_refund_after_ipn( $order_id ) {
			$get_order = wc_get_order( $order_id );

			if ( ! $get_order instanceof WC_Order ) {
				return;
			}


			$if_pending_refund = $get_order->get_meta( '_wfocu_pending_refund', true );

			if ( 'yes' !== $if_pending_refund ) {
				return;
			}

			wc_create_refund( array(
				'order_id'       => $order_id,
				'amount'         => $get_order->get_total(),
				'reason'         => __( 'Refund Processed', 'woofunnels-upstroke-one-click-upsell' ),
				'refund_payment' => true,
				'restock_items'  => true,
			) );

		}

		public function wfocu_woocommerce_paypal_refund_request_data( $request, $order ) {

			$payment_method = $order->get_payment_method();

			if ( $this->key !== $payment_method ) {
				return $request;
			}

			if ( isset( $_POST['txn_id'] ) && ! empty( $_POST['txn_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$request['TRANSACTIONID'] = wc_clean( $_POST['txn_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			return $request;
		}

		/**
		 *  Creating transaction URL
		 *
		 * @param $transaction_id
		 * @param $order_id
		 *
		 * @return string
		 */
		public function get_transaction_link( $transaction_id, $order_id ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$testmode = $this->get_wc_gateway()->testmode;

			if ( $transaction_id ) {
				if ( $testmode ) {
					$view_transaction_url = sprintf( 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s', $transaction_id );
				} else {
					$view_transaction_url = sprintf( 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s', $transaction_id );
				}
			}

			if ( ! empty( $view_transaction_url ) && ! empty( $transaction_id ) ) {
				$return_url = sprintf( '<a href="%s">%s</a>', $view_transaction_url, $transaction_id );

				return $return_url;
			}

			return $transaction_id;
		}


		public function maybe_save_pending_emails_meta_to_prevent_mails( $order ) {
			/**$args = array(
			 * WFOCU_WC_Compatibility::get_order_id( $order ),
			 * WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' ),
			 * WFOCU_Core()->funnels->get_funnel_option( 'is_cancel_order' ),
			 * WFOCU_Core()->data->get_option( 'send_processing_mail_on_no_batch' ),
			 * WFOCU_Core()->data->get_option( 'send_processing_mail_on_no_batch_cancel' ),
			 * time(),
			 * );**/

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$order->update_meta_data( '_wfocu_prevent_mail_paypal', 'yes' );
			$order->save_meta_data();

		}

		/**
		 * Maybe block IPN operations while we are in a running funnel
		 * before blocking we must need to verify few things, these are
		 * 1. Current IPN request is for the txn_type cart that represents primary checkout
		 * 2. IPN comes with completed payment, any other status doesn't need to be care
		 * 3. If gateway is enabled
		 * 4. If order is same as upstroke funnel order status wc-wfocu-pri-order
		 *
		 * @param WC_Order $order
		 * @param array $posted
		 */
		public function handle_ipn( $order, $posted ) {
			$get_paypal_ipn_hold_meta = $order->get_meta( '_wfocu_paypal_hold_ipn', true );
			remove_filter( 'wfocu_front_payment_gateway_integration_enabled', array(
				WFOCU_Plugin_Compatibilities::get_compatibility_class( 'subscriptions' ),
				'maybe_disable_integration_when_subscription_in_cart'
			), 10 );

			if ( 'cart' !== $posted['txn_type'] || 'completed' !== strtolower( $posted['payment_status'] ) || ! $this->is_enabled( $order ) || empty( $get_paypal_ipn_hold_meta ) ) {
				return;
			}

			WFOCU_Core()->log->log( 'Order #' . $order->get_id() . " :: Prevent IPN to process" );
			WFOCU_Core()->gateways->save_paypal_meta_data( $posted, $order );
			do_action( 'wfocu_paypal_ipn_received', $posted, $order );
			if ( 'order' === $get_paypal_ipn_hold_meta ) {
				WFOCU_Core()->log->log( "Order # " . $order->get_id() . ": Paypal IPN received after funnel ends" );
				$order->payment_complete();
			} /**
			 * Detect if IPN drops when funnel initiated or not, we need to only handle the case when user did not return
			 *
			 */ elseif ( $order->get_status() !== 'wfocu-pri-order' && ! in_array( $order->get_status(), wc_get_is_paid_statuses(), true ) ) {
				WFOCU_Core()->orders->maybe_set_funnel_running_status( $order );

			}
			exit;

		}

		public function handle_return_api() {
			$order_id = filter_input( INPUT_GET, 'wfocu_ord', FILTER_SANITIZE_NUMBER_INT );
			if ( empty( $order_id ) ) {
				return;
			}

			add_action( 'wfocu_schedule_email_data_stored', [ $this, 'maybe_save_pending_emails_meta_to_prevent_mails' ], 10, 1 );
			sleep( 2 );
			$order = wc_get_order( $order_id );
			if ( empty( $order ) ) {
				return;
			}
			$this->maybe_handle_pdt( $order_id );

			if ( 0 === did_action( 'wfocu_front_init_funnel_hooks' ) ) {
				$offer_current = WFOCU_Core()->data->get_current_offer();

				if ( ! empty( $offer_current ) ) {
					WFOCU_Core()->public->modify_order_received_url();
				} else {
					/**
					 * In this case we have to initiate the funnel manually and we do not need to wait for payment complete to perform the action.
					 */
					WFOCU_Core()->public->maybe_setup_upsell( WFOCU_WC_Compatibility::get_order_id( $order ) );

					$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
					$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;

					if ( true === $is_batching_on && 0 !== did_action( 'wfocu_front_init_funnel_hooks' ) ) {
						WFOCU_Core()->orders->maybe_set_funnel_running_status( $order );
					}
				}


			}


			/**
			 * Set return URL for the PayPal, as the data setup completes, we can safely assume that this return URL would be a valid offer URL.
			 */
			$return = $this->get_wc_gateway()->get_return_url( $order );
			WFOCU_Core()->log->log( 'PayPal Return Url after landing: ' . print_R( $return, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			wp_redirect( $return );
			exit;
		}


	}

	WFOCU_Gateway_Integration_PayPal_Standard::get_instance();
}