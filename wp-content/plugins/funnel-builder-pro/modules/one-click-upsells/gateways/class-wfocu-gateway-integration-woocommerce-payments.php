<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Gateway_Integration_WooCommerce_Payments' ) ) {
	/**
	 * WFOCU_Gateway_Integration_WooCommerce_Payments class.
	 *
	 * @extends WFOCU_Gateway
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_WooCommerce_Payments extends WFOCU_Gateway {


		protected static $ins = null;
		public $key = 'woocommerce_payments';
		public $token = false;
		public $has_intent_secret = false;

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->refund_supported = true;

			parent::__construct();
			add_filter( 'wc_payments_display_save_payment_method_checkbox', array( $this, 'should_tokenize_gateway' ) );
			add_action( 'wfocu_front_pre_init_funnel_hooks', array( $this, 'maybe_force_save_token_for_3ds' ), 1 );
			add_action( 'wfocu_footer_before_print_scripts', array( $this, 'maybe_render_in_offer_transaction_scripts' ), 999 );
			add_filter( 'wfocu_allow_ajax_actions_for_charge_setup', array( $this, 'allow_check_action' ) );
			add_action( 'wp_footer', array( $this, 'maybe_render_script_to_allow_tokenization' ) );
			add_filter( 'woocommerce_checkout_posted_data', array( $this, 'prevent_new_method_param_for_other_gateways' ), 10 );
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}


		public function should_tokenize_gateway( $display_method ) {

			if ( false !== $this->is_enabled() ) {
				return false;
			}


			return $display_method;
		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true|false on success false otherwise
		 */
		public function has_token( $order ) {
			$this->token = $this->get_payment_token( $order );


			if ( ! empty( $this->token ) ) {
				return true;
			}

			$get_token = WFOCU_Common::get_order_meta( $order, '_payment_method_id' );
			if ( ! empty( $get_token ) ) {
				return true;
			}

			return false;

		}


		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return WC_Payment_Token|false on success false otherwise
		 */
		public function get_token( $order ) {
			$this->token = $this->get_payment_token( $order );


			if ( ! empty( $this->token ) ) {
				return $this->token;
			}

			return false;

		}

		/**
		 * Retrieve payment token from an ordeer
		 *
		 * @param WC_Order $order Order
		 *
		 * @return null|WC_Payment_Token Last token associated with order
		 */
		protected function get_payment_token( $order ) {
			$order_tokens = $order->get_payment_tokens();
			$token_id     = end( $order_tokens );

			return ! $token_id ? null : WC_Payment_Tokens::get( $token_id );
		}


		/**
		 * Model method to handle the client payments aka In-offer transactions
		 * This primary covers both operations 1) init of client payment
		 * 2) auth of client operations
		 * Also handles further API operation to mark success and failtures
		 * @return void
		 * @throws WFOCU_Payment_Gateway_Exception
		 */
		public function process_client_payment() {

			/**
			 * Prepare and populate client collected data to process further.
			 */
			$get_current_offer      = WFOCU_Core()->data->get( 'current_offer' );
			$get_current_offer_meta = WFOCU_Core()->offers->get_offer_meta( $get_current_offer );
			WFOCU_Core()->data->set( '_offer_result', true );
			$posted_data = WFOCU_Core()->process_offer->parse_posted_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			/**
			 * return if found error in the charge request
			 */
			if ( false === WFOCU_AJAX_Controller::validate_charge_request( $posted_data ) ) {
				wp_send_json( array(
					'result' => 'error',
				) );
			}

			/**
			 * Setup the upsell to initiate the charge process
			 */
			WFOCU_Core()->process_offer->execute( $get_current_offer_meta );

			$get_order = WFOCU_Core()->data->get_parent_order();


			$intent_from_posted = filter_input( INPUT_POST, 'intent', FILTER_SANITIZE_NUMBER_INT );

			/**
			 * If intent flag set found in the posted data from the client then it means we just need to verify the intent status and then process failure or success
			 * if not found it means that its the first initial intent creation call
			 */
			if ( ! empty( $intent_from_posted ) ) {


				/**
				 * process response when user either failed or approve the auth.
				 */
				$intent_secret_from_posted = filter_input( INPUT_POST, 'intent_secret', FILTER_UNSAFE_RAW );

				/**
				 * If not found the intent secret with the flag then fail, there could be few security issues
				 */
				if ( empty( $intent_secret_from_posted ) ) {
					$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: Intent secret missing from auth', 'woofunnels-upstroke-one-click-upsell' ), 'Intent secret missing from auth', $get_order, true );
				}

				/**
				 * get intent ID from the data session
				 */
				$get_intent_id_from_posted_secret = WFOCU_Core()->data->get( 'c_intent_secret_' . $intent_secret_from_posted, '', 'gateway' );
				if ( empty( $get_intent_id_from_posted_secret ) ) {
					$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: Unable to find matching ID for the secret', 'woofunnels-upstroke-one-click-upsell' ), 'Unable to find matching ID for the secret', $get_order, true );
				}

				/**
				 * Get intent from the payment gateway API
				 */
				$intent = WC_Payments::get_payments_api_client()->get_intent( $get_intent_id_from_posted_secret );


				if ( false !== $intent ) {
					WFOCU_Core()->data->set( '_transaction_id', $get_intent_id_from_posted_secret );
					WFOCU_Core()->data->set( '_charge_id', $this->get_charge_id( $intent, $get_order ) );
					WFOCU_Core()->data->set( '_payment_method', $intent->get_payment_method_id() );
					add_action( 'wfocu_offer_new_order_created_woocommerce_payments', array( $this, 'add_meta_to_order' ), 10, 2 );
					wp_send_json( array(
						'result'   => 'success',
						'response' => WFOCU_Core()->process_offer->_handle_upsell_charge( true ),
					) );
				}

			} else {
				/**
				 * get token from the order and try to create and verify intent
				 */
				try {
					$intent = $this->create_intent( $get_order );
				} catch ( Exception $e ) {
					/**
					 * If error captured during charge process, then handle as failure
					 */
					$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: ' . $e->getMessage() . '', 'woofunnels-upstroke-one-click-upsell' ), 'Error Captured: ' . print_r( $e->getMessage() . " <-- Generated on" . $e->getFile() . ":" . $e->getLine(), true ), $get_order, true ); // @codingStandardsIgnoreLine

				}

				/**
				 * Save the is in the session
				 */
				if ( isset( $intent->client_secret ) ) {
					WFOCU_Core()->data->set( 'c_intent_secret_' . $intent->client_secret, $intent->id, 'gateway' );
				}

				WFOCU_Core()->data->save( 'gateway' );

				if ( ! empty( $intent->error ) ) {
					$note = 'Offer payment failed. Reason: ';
					if ( isset( $intent->error->message ) && ! empty( $intent->error->message ) ) {
						$note .= $intent->error->message;
					} else {
						$note .= ( isset( $intent->error->code ) && ! empty( $intent->error->code ) ) ? $intent->error->code : ( isset( $intent->error->type ) ? $intent->error->type : '' );
					}

					$this->handle_api_error( $note, $intent->error, $get_order, true );
				}

				/**
				 * Proceed and check intent status
				 */
				if ( ! empty( $intent ) ) {

					// If the intent requires a 3DS flow, redirect to it.
					if ( 'requires_action' === $intent->status ) {

						/**
						 * return intent_secret as the data to the client so that necessary next operations could taken care.
						 */
						wp_send_json( array(
							'result'        => 'success',
							'intent_secret' => $intent->client_secret,
						) );

					}
					// Use the last charge within the intent to proceed.
					$response = end( $intent->charges->data );
					WFOCU_Core()->data->set( '_transaction_id', $response->id );
					WFOCU_Core()->data->set( '_charge_id', $response->id );
					WFOCU_Core()->data->set( '_payment_method', $intent->payment_method );
					add_action( 'wfocu_offer_new_order_created_woocommerce_payments', array( $this, 'add_meta_to_order' ), 10, 1 );

				}
			}
			$data = WFOCU_Core()->process_offer->_handle_upsell_charge( true );

			wp_send_json( array(
				'result'   => 'success',
				'response' => $data,
			) );
		}

		/**
		 * This function is placed here as a fallback function when JS client side integration fails in any case
		 * It creates intent and then try to confirm that intent, if successful then mark success, otherwise failure
		 *
		 * @param WC_Order $order
		 *
		 * @return true
		 * @throws WFOCU_Payment_Gateway_Exception
		 */
		public function process_charge( $order ) {
			$is_successful = false;

			$intent = $this->create_intent( $order );

			if ( ! empty( $intent->error ) ) {
				$localized_message = '';
				if ( 'card_error' === $intent->error->type ) {
					$localized_message = $intent->error->message;
				}
				throw new WFOCU_Payment_Gateway_Exception( "Stripe : " . $localized_message, 102, $this->get_key() );

			}
			if ( ! empty( $intent ) ) {

				// If the intent requires a 3DS flow, redirect to it.
				if ( 'requires_action' === $intent->status ) {
					throw new WFOCU_Payment_Gateway_Exception( "WC Payment : Auth required for the charge but unable to complete.", 102, $this->get_key() );
				}
			}

			$response = end( $intent->charges->data );
			if ( is_wp_error( $response ) ) {
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $order ) . ': WC Payment Failed process charge ' );
			} else {
				if ( ! empty( $response->error ) ) {
					throw new WFOCU_Payment_Gateway_Exception( $response->error->message, 102, $this->get_key() );

				} else {
					WFOCU_Core()->data->set( '_transaction_id', $response->id );

					$is_successful = true;
				}
			}

			return $this->handle_result( $is_successful );
		}

		/**
		 * @param $order
		 *
		 * @return mixed
		 * @throws WFOCU_Payment_Gateway_Exception
		 */
		protected function create_intent( $order ) {
			$get_package = WFOCU_Core()->data->get( '_upsell_package' );
			$amount      = $get_package['total'];
			$name        = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
			$email       = sanitize_email( $order->get_billing_email() );
			$metadata    = [
				'customer_name'  => $name,
				'customer_email' => $email,
				'site_url'       => esc_url( get_site_url() ),
				'payment_type'   => 'single',
			];

			$request = array(
				'amount'             => WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() ),
				'currency'           => strtolower( $order->get_currency() ),
				'confirm'            => 'true',
				'customer'           => $order->get_meta( '_stripe_customer_id' ),
				'capture_method'     => 'automatic',
				'metadata'           => $metadata,
				'level3'             => [],
				'test_mode'          => method_exists( WC_Payments::get_gateway(), 'is_in_test_mode' ) ? WC_Payments::get_gateway()->is_in_test_mode() : WC_Payments::mode()->is_test(),
				'setup_future_usage' => 'off_session'
			);

			$source = $order->get_meta( '_payment_method_id' );

			if ( 0 === strpos( $source, 'src_' ) ) {
				$request['source'] = $source;
			} elseif ( 0 === strpos( $source, 'pm_' ) ) {
				$request['payment_method'] = $source;
			}

			$blog_id = Jetpack_Options::get_option( 'id' );

			$url                                = 'https://public-api.wordpress.com/wpcom/v2/sites/' . $blog_id . '/wcpay/intentions';
			$body                               = wp_json_encode( $request );
			$args                               = [];
			$args['url']                        = $url;
			$args['method']                     = 'POST';
			$args['connect_timeout']            = 70;
			$args['timeout']                    = 70; //phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			$args['headers']['Content-Type']    = 'application/json; charset=utf-8';
			$args['headers']['User-Agent']      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'; //phpcs:ignore
			$args['headers']['Idempotency-Key'] = $this->uuid();

			$response = Automattic\Jetpack\Connection\Client::remote_request( $args, $body );

			if ( is_wp_error( $response ) || ! is_array( $response ) ) {
				$message = sprintf( // translators: %1: original error message.
					__( 'Http request failed. Reason: %1$s', 'woocommerce-payments' ), $response->get_error_message() );
				WFOCU_Core()->log->log( "#{$order->get_id()} WC Payment failed create intent response " . print_r( $response, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				throw new WFOCU_Payment_Gateway_Exception( "Stripe : " . $message, 102, $this->get_key() );
			}

			$intent = json_decode( $response['body'] );

			if ( ! empty( $intent->error ) ) {
				WFOCU_Core()->log->log( 'Order #' . $order->get_id() . " - Offer WC Payment intent create failed, Reason: " . print_r( $intent->error, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $intent;
			}

			$order_id = $order->get_id();
			WFOCU_Core()->log->log( '#Order: ' . $order_id . ' WC Payment payment intent created. ' . print_r( $intent, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return $intent;
		}

		/**
		 * Handling refund offer request from admin
		 *
		 * @throws WC_Stripe_Exception
		 */
		public function process_refund_offer( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$refund_data = $_POST;  // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$txn_id = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
			$amt    = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';

			if ( ! is_null( $amt ) ) {


				try {

					if ( 0 === strpos( $txn_id, 'ch_' ) ) {
						$charge_id = $txn_id;

					} else {
						$intent    = WC_Payments::get_payments_api_client()->get_intent( $txn_id );
						$charge_id = $this->get_charge_id( $intent, $order );
					}


					if ( method_exists( WC_Payments::get_payments_api_client(), 'refund_charge' ) ) {
						WC_Payments::get_payments_api_client()->refund_charge( $charge_id, WC_Payments_Utils::prepare_amount( $amt, $order->get_currency() ) );

					} else {
						/**
						 * Perform refund API call
						 */

						$refund_request = \WCPay\Core\Server\Request\Refund_Charge::create();
						$refund_request->set_charge( $charge_id );
						if ( null !== $amt ) {
							$refund_request->set_amount( WC_Payments_Utils::prepare_amount( $amt, $order->get_currency() ) );
						}

						$refund_request->send();
					}


					return true;
				} catch ( Exception $e ) {

					WFOCu_Core()->log->log( 'Offer refund failed, reason' . $e->getMessage() );

					return false;
				}
			}

		}


		/**
		 * Render Javascript that is responsible for client side payment
		 */
		public function maybe_render_in_offer_transaction_scripts() {
			$order = WFOCU_Core()->data->get_current_order();

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( $this->get_key() !== $order->get_payment_method() ) {
				return;
			}

			$plugins = get_plugins();

			if ( version_compare( '5.0', $plugins['woocommerce-payments/woocommerce-payments.php']['Version'], '<=' ) ) {
				$all_js_config = WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config();
			} else {
				$all_js_config = $this->get_wc_gateway()->get_payment_fields_js_config();
			}

			?>
            <script type="text/javascript" src="https://js.stripe.com/v3/?ver=3.0"></script> <?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>

            <script type="text/javascript">

                (
                    function ($) {
                        "use strict";
                        var wfocuWCPAY = Stripe('<?php echo esc_js( $all_js_config["publishableKey"] ); ?>', {
                            stripeAccount: '<?php echo esc_js( $all_js_config["accountId"] ); ?>',
                            locale: '<?php echo esc_js( $all_js_config["locale"] ); ?>'
                        });

                        var wfocuWCPAYJS = {
                            bucket: null,
                            initCharge: function () {
                                var getBucketData = this.bucket.getBucketSendData();

                                var postData = $.extend(getBucketData, {action: 'wfocu_front_handle_wcpay_payments'});

                                var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_handle_wcpay_payments'), postData);

                                action.done(function (data) {

                                    /**
                                     * Process the response for the call to handle client stripe payments
                                     * first handle error state to show failure notice and redirect to thank you
                                     * */
                                    if (data.result !== "success") {

                                        wfocuWCPAYJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                        if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                            setTimeout(function () {
                                                window.location = data.response.redirect_url;
                                            }, 1500);
                                        } else {
                                            /** move to order received page */
                                            if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                                window.location = wfocu_vars.order_received_url + '&ec=wc_api1';

                                            }
                                        }
                                    } else {
                                        /**
                                         * There could be two states --
                                         * 1. intent confirmed
                                         * 2. requires action
                                         * */

                                        /**
                                         * handle scenario when authentication requires for the payment intent
                                         * In this case we need to trigger stripe payment intent popups
                                         * */
                                        if (typeof data.intent_secret !== "undefined" && '' !== data.intent_secret) {

                                            wfocuWCPAY.confirmCardPayment(data.intent_secret)
                                                .then(function (response) {
                                                    if (response.error) {
                                                        throw response.error;
                                                    }

                                                    if ('requires_capture' !== response.paymentIntent.status && 'succeeded' !== response.paymentIntent.status) {
                                                        return;
                                                    }
                                                    $(document).trigger('wfocuWCPAYOnAuthentication', [response, true]);
                                                    return;

                                                })
                                                .catch(function (error) {
                                                    $(document).trigger('wfocuWCPAYOnAuthentication', [false, false]);
                                                    return;

                                                });
                                            return;
                                        }
                                        /**
                                         * If code reaches here means it no longer require any authentication from the client and we process success
                                         * */

                                        wfocuWCPAYJS.bucket.swal.show({'text': wfocu_vars.messages.offer_success_message_pop, 'type': 'success'});
                                        if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                            setTimeout(function () {
                                                window.location = data.response.redirect_url;
                                            }, 1500);
                                        } else {
                                            /** move to order received page */
                                            if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                                window.location = wfocu_vars.order_received_url + '&ec=wc_api4';

                                            }
                                        }
                                    }
                                });
                                action.fail(function (data) {

                                    /**
                                     * In case of failure of ajax, process failure
                                     * */
                                    wfocuWCPAYJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                    if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                        setTimeout(function () {
                                            window.location = data.response.redirect_url;
                                        }, 1500);
                                    } else {
                                        /** move to order received page */
                                        if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                            window.location = wfocu_vars.order_received_url + '&ec=wc_api3';

                                        }
                                    }
                                });
                            }
                        }

                        /**
                         * Handle popup authentication results
                         */
                        $(document).on('wfocuWCPAYOnAuthentication', function (e, response, is_success) {

                            if (is_success) {
                                var postData = $.extend(wfocuWCPAYJS.bucket.getBucketSendData(), {
                                    action: 'wfocu_front_handle_wcpay_payments',
                                    intent: 1,
                                    intent_secret: response.paymentIntent.client_secret
                                });

                            } else {
                                var postData = $.extend(wfocuWCPAYJS.bucket.getBucketSendData(), {action: 'wfocu_front_handle_wcpay_payments', intent: 1, intent_secret: ''});

                            }
                            var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_handle_wcpay_payments'), postData);
                            action.done(function (data) {
                                if (data.result !== "success") {
                                    wfocuWCPAYJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                } else {
                                    wfocuWCPAYJS.bucket.swal.show({'text': wfocu_vars.messages.offer_success_message_pop, 'type': 'success'});
                                }
                                if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                    setTimeout(function () {
                                        window.location = data.response.redirect_url;
                                    }, 1500);
                                } else {
                                    /** move to order received page */
                                    if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                        window.location = wfocu_vars.order_received_url + '&ec=stripe_error2';

                                    }
                                }
                            });
                        });

                        /**
                         * Save the bucket instance at several
                         */
                        $(document).on('wfocuBucketCreated', function (e, Bucket) {
                            wfocuWCPAYJS.bucket = Bucket;

                        });
                        $(document).on('wfocu_external', function (e, Bucket) {
                            /**
                             * Check if we need to mark inoffer transaction to prevent default behavior of page
                             */
                            if (0 !== Bucket.getTotal()) {
                                Bucket.inOfferTransaction = true;
                                wfocuWCPAYJS.initCharge();
                            }
                        });

                        $(document).on('wfocuBucketConfirmationRendered', function (e, Bucket) {
                            wfocuWCPAYJS.bucket = Bucket;

                        });
                        $(document).on('wfocuBucketLinksConverted', function (e, Bucket) {
                            wfocuWCPAYJS.bucket = Bucket;

                        });
                    })(jQuery);

            </script>
			<?php
		}

		/**
		 * maybe save token in case of 3ds flow
		 *
		 * @param WC_Order $order
		 */
		public function maybe_force_save_token_for_3ds( $order ) {

			/**
			 * Check if we have the correct ajax process to work onto
			 */
			if ( ( did_action( 'wp_ajax_nopriv_update_order_status' ) || did_action( 'wp_ajax_update_order_status' ) ) && $this->is_enabled( $order ) ) {
				try {
					$payment_method_id = isset( $_POST['payment_method_id'] ) ? wc_clean( wp_unslash( $_POST['payment_method_id'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing
					$database_cache    = null;
					if ( class_exists( 'WCPay\Database_Cache' ) ) {
						$database_cache = new WCPay\Database_Cache();
					}

					if ( version_compare( '8.9.0', WCPAY_VERSION_NUMBER, '<=' ) ) {
						WFOCU_Core()->log->log( $order->get_id() . ' Unable to save early token for 3ds scenarios handle in normal case after version  8.9.0 ' );

						return;
					}

					/**
					 * Handle for different WC versions
					 */
					if ( version_compare( '6.7.1', WCPAY_VERSION_NUMBER, '<=' ) ) {

						$customer_service = WC_Payments::get_customer_service();


						if ( empty( $customer_service ) ) {
							$api_session_service = WC_Payments::get_session_service();
							$customer_service    = new WC_Payments_Customer_Service( WC_Payments::get_payments_api_client(), WC_Payments::get_account_service(), $database_cache, $api_session_service, WC_Payments::get_order_service() );

						}
					} else {
						$database_cache   = is_null( $database_cache ) ? WC_Payments::get_database_cache() : $database_cache;
						$customer_service = new WC_Payments_Customer_Service( WC_Payments::get_payments_api_client(), WC_Payments::get_account_service(), $database_cache, WC_Payments::get_session_service(), WC_Payments::get_order_service() );
					}


					$token_service = new WC_Payments_Token_Service( WC_Payments::get_payments_api_client(), $customer_service );

					/**
					 * tell gateway to save the payment method (token) to the user
					 */
					$token = $token_service->add_payment_method_to_user( $payment_method_id, wp_get_current_user() );
					$this->get_wc_gateway()->add_token_to_order( $order, $token );

					/**
					 * force readmeta data to avoid any object caching scenarios
					 */
					$order->read_meta_data( true );
				} catch ( Exception $e ) {
					// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow
					WFOCU_Core()->log->log( $order->get_id() . ' Unable to save early token for 3ds scenarios' . $e->getMessage() );
				}
			}
		}

		/**
		 * Allow action of wcpayments ajax
		 *
		 * @param array $actions
		 *
		 * @return mixed modified actions
		 */
		public function allow_check_action( $actions ) {
			array_push( $actions, 'wfocu_front_handle_wcpay_payments' );

			return $actions;
		}


		/**
		 * Render script to allow tokenization for the case where save card not enabled from settings
		 * this technique works as a fallback for the above case
		 */
		public function maybe_render_script_to_allow_tokenization() {
			if ( ! $this->is_enabled() || ! is_checkout() ) {
				return;
			}
			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

			if ( ! is_array( $available_gateways ) || ! isset( $available_gateways[ $this->get_key() ] ) || $this->get_wc_gateway()->is_saved_cards_enabled() ) {
				return;
			}

			?>
            <script type="text/javascript">
                (
                    function ($) {
                        "use strict";
                        var wfocu_woocommerce_payments_method = '<input id="wc-woocommerce_payments-new-payment-method" name="wc-woocommerce_payments-new-payment-method" type="checkbox" value="true" style="width:auto;" checked />';
                        jQuery('body').on('updated_checkout', function () {
                            if (jQuery('#wc-woocommerce_payments-new-payment-method').length === 0) {
                                jQuery("#wcpay-payment-method").append(wfocu_woocommerce_payments_method);
                            }


                        });

                    })(jQuery);

            </script>
			<?php

		}

		public function get_nw_card_html() {
			ob_start();
			$this->get_wc_gateway()->save_payment_method_checkbox( true );

			return ob_get_clean();
		}

		/**
		 * @param WC_Order $order
		 * @param $transaction
		 *
		 * @return void
		 */
		public function add_meta_to_order( $order ) {
			$order->update_meta_data( '_charge_id', WFOCU_Core()->data->get( '_charge_id', '' ) );
			$order->update_meta_data( '_payment_method_id', WFOCU_Core()->data->get( '_payment_method', '' ) );
			$order->save_meta_data();;
		}

		public function get_charge_id( $intent, $order ) {
			$plugins = get_plugins();
			if ( version_compare( '4.3.0', $plugins['woocommerce-payments/woocommerce-payments.php']['Version'], '<=' ) ) {
				$charge = ( $intent && ! empty( $intent->get_charge() ) ) ? $intent->get_charge() : null;

				return ( ! empty( $charge ) ) ? $charge->get_id() : $order->get_meta( '_charge_id' );

			} else {
				return $intent->get_charge_id;
			}
		}

		/**
		 * Returns a v4 UUID.
		 *
		 * @return string
		 */
		private function uuid() {
			$arr    = array_values( unpack( 'N1a/n4b/N1c', random_bytes( 16 ) ) );
			$arr[2] = ( $arr[2] & 0x0fff ) | 0x4000;
			$arr[3] = ( $arr[3] & 0x3fff ) | 0x8000;

			return vsprintf( '%08x-%04x-%04x-%04x-%04x%08x', $arr );
		}


		/**
		 * Prevent new payment method param for other gateways
		 * We have found an issue with the new payment method param being sent to other gateways, while it should not, so we are preventing it here
		 *
		 * @param $posted_data array
		 *
		 * @return array
		 */
		public function prevent_new_method_param_for_other_gateways( $posted_data ) {

			$payment_methods = array(
				"bancontact"        => "woocommerce_payments_bancontact",
				"au_becs_debit"     => "woocommerce_payments_au_becs_debit",
				"eps"               => "woocommerce_payments_eps",
				"giropay"           => "woocommerce_payments_giropay",
				"ideal"             => "woocommerce_payments_ideal",
				"p24"               => "woocommerce_payments_p24",
				"sepa_debit"        => "woocommerce_payments_sepa_debit",
				"sofort"            => "woocommerce_payments_sofort",
				"affirm"            => "woocommerce_payments_affirm",
				"afterpay_clearpay" => "woocommerce_payments_afterpay_clearpay",
				"klarna"            => "woocommerce_payments_klarna"
			);

			if ( isset( $_POST['wc-woocommerce_payments-new-payment-method'] ) && in_array( $_POST['wc-woocommerce_payments-new-payment-method'], $payment_methods, true ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
				unset( $_POST['wc-woocommerce_payments-new-payment-method'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			/**
			 * Add the condition for affirm payment selection
			 */

			if ( isset( $_POST['wc-woocommerce_payments-new-payment-method'] ) && isset( $_POST['payment_method'] ) && in_array( $_POST['payment_method'], $payment_methods, true ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
				unset( $_POST['wc-woocommerce_payments-new-payment-method'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			}


			return $posted_data;

		}

	}

	WFOCU_Gateway_Integration_WooCommerce_Payments::get_instance();
}
