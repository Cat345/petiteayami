<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Gateway_Integration_Stripe' ) ) {
	/**
	 * WC_Gateway_Stripe_Addons class.
	 *
	 * @extends WFOCU_Gateway
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Stripe extends WFOCU_Gateway {


		protected static $ins = null;
		public $key = 'stripe';
		public $token = false;
		public $has_intent_secret = [];
		public $current_intent;

		/**
		 * Constructor
		 */
		public function __construct() {

			parent::__construct();
			add_filter( 'wc_stripe_force_save_source', array( $this, 'should_tokenize_stripe' ), 999 );
			add_filter( 'wc_stripe_display_save_payment_method_checkbox', array( $this, 'maybe_hide_save_payment' ), 999 );
			add_filter( 'wc_stripe_3ds_source', array( $this, 'maybe_modify_3ds_prams' ), 10, 2 );

			add_action( 'wc_gateway_stripe_process_response', array( $this, 'maybe_handle_redirection_stripe' ), 10, 2 );

			add_action( 'wc_gateway_stripe_process_redirect_payment', array( $this, 'maybe_log_process_redirect' ), 1 );

			add_action( 'wfocu_offer_new_order_created_stripe', array( $this, 'add_stripe_payouts_to_new_order' ), 10, 1 );

			add_filter( 'woocommerce_payment_successful_result', array( $this, 'maybe_flag_has_intent_secret' ), 9999, 2 );
			add_filter( 'woocommerce_payment_successful_result', array( $this, 'modify_successful_payment_result_for_upstroke' ), 999910, 2 );

			add_action( 'wfocu_footer_before_print_scripts', array( $this, 'maybe_render_in_offer_transaction_scripts' ), 999 );

			add_filter( 'wfocu_allow_ajax_actions_for_charge_setup', array( $this, 'allow_check_action' ) );
			add_action( 'wfocu_offer_new_order_created_before_complete', array( $this, 'maybe_save_intent' ), 10 );

			/**
			 *  UPE support
			 */
			add_action( 'wc_gateway_stripe_process_response', array( $this, 'maybe_handle_stripe_on_upe' ) );
			add_action( 'woocommerce_review_order_after_submit', array( $this, 'add_input_hidden' ), 9999 );

			$this->refund_supported = true;

		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_hide_save_payment( $is_show ) {
			if ( false !== $this->should_tokenize() ) {
				return false;
			}

			return $is_show;
		}


		public function should_tokenize_stripe( $save_token ) {

			if ( false !== $this->should_tokenize() ) {

				return true;
			}

			return $save_token;
		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) {

			$this->token = WFOCU_Common::get_order_meta( $order, '_wfocu_stripe_source_id' );
			if ( empty( $this->token ) ) {
				$this->token = WFOCU_Common::get_order_meta( $order, '_stripe_source_id' );
				if ( empty( $this->token ) ) {
					$this->token = $order->get_meta( '_stripe_source_id', true );
				}
			}

			if ( ! empty( $this->token ) ) {
				return true;
			}

			return false;

		}

		public function maybe_render_in_offer_transaction_scripts() {
			$order = WFOCU_Core()->data->get_current_order();

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( $this->get_key() !== $order->get_payment_method() ) {
				return;
			}
			?>
            <script src="https://js.stripe.com/v3/?ver=3.0"></script> <?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>

            <script>

                (
                    function ($) {
                        "use strict";
                        var wfocuStripe = Stripe('<?php echo esc_js( $this->get_wc_gateway()->publishable_key ); ?>');

                        var wfocuStripeJS = {
                            bucket: null,
                            initCharge: function () {
                                var getBucketData = this.bucket.getBucketSendData();

                                var postData = $.extend(getBucketData, {action: 'wfocu_front_handle_stripe_payments'});

                                var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_handle_stripe_payments'), postData);

                                action.done(function (data) {

                                    /**
                                     * Process the response for the call to handle client stripe payments
                                     * first handle error state to show failure notice and redirect to thank you
                                     * */
                                    if (data.result !== "success") {

                                        wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                        if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                            setTimeout(function () {
                                                window.location = data.response.redirect_url;
                                            }, 1500);
                                        } else {
                                            /** move to order received page */
                                            if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                                window.location = wfocu_vars.order_received_url + '&ec=stripe_error';

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

                                            wfocuStripe.handleCardPayment(data.intent_secret)
                                                .then(function (response) {
                                                    if (response.error) {
                                                        throw response.error;
                                                    }

                                                    if ('requires_capture' !== response.paymentIntent.status && 'succeeded' !== response.paymentIntent.status) {
                                                        return;
                                                    }
                                                    $(document).trigger('wfocuStripeOnAuthentication', [response, true]);
                                                    return;

                                                })
                                                .catch(function (error) {
                                                    $(document).trigger('wfocuStripeOnAuthentication', [false, false]);
                                                    return;

                                                });
                                            return;
                                        }
                                        /**
                                         * If code reaches here means it no longer require any authentication from the client and we process success
                                         * */

                                        wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_success_message_pop, 'type': 'success'});
                                        if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                            setTimeout(function () {
                                                window.location = data.response.redirect_url;
                                            }, 1500);
                                        } else {
                                            /** move to order received page */
                                            if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                                window.location = wfocu_vars.order_received_url + '&ec=stripe_error';

                                            }
                                        }
                                    }
                                });
                                action.fail(function (data) {

                                    /**
                                     * In case of failure of ajax, process failure
                                     * */
                                    wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                    if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                        setTimeout(function () {
                                            window.location = data.response.redirect_url;
                                        }, 1500);
                                    } else {
                                        /** move to order received page */
                                        if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                            window.location = wfocu_vars.order_received_url + '&ec=stripe_error';

                                        }
                                    }
                                });
                            }
                        }

                        /**
                         * Handle popup authentication results
                         */
                        $(document).on('wfocuStripeOnAuthentication', function (e, response, is_success) {

                            if (is_success) {
                                var postData = $.extend(wfocuStripeJS.bucket.getBucketSendData(), {
                                    action: 'wfocu_front_handle_stripe_payments',
                                    intent: 1,
                                    intent_secret: response.paymentIntent.client_secret
                                });

                            } else {
                                var postData = $.extend(wfocuStripeJS.bucket.getBucketSendData(), {action: 'wfocu_front_handle_stripe_payments', intent: 1, intent_secret: ''});

                            }
                            var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_handle_stripe_payments'), postData);
                            action.done(function (data) {
                                if (data.result !== "success") {
                                    wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                } else {
                                    wfocuStripeJS.bucket.swal.show({'text': wfocu_vars.messages.offer_success_message_pop, 'type': 'success'});
                                }
                                if (typeof data.response !== "undefined" && typeof data.response.redirect_url !== 'undefined') {

                                    setTimeout(function () {
                                        window.location = data.response.redirect_url;
                                    }, 1500);
                                } else {
                                    /** move to order received page */
                                    if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                        window.location = wfocu_vars.order_received_url + '&ec=stripe_error';

                                    }
                                }
                            });
                        });

                        /**
                         * Save the bucket instance at several
                         */
                        $(document).on('wfocuBucketCreated', function (e, Bucket) {
                            wfocuStripeJS.bucket = Bucket;

                        });
                        $(document).on('wfocu_external', function (e, Bucket) {
                            /**
                             * Check if we need to mark inoffer transaction to prevent default behavior of page
                             */
                            if (0 !== Bucket.getTotal()) {
                                Bucket.inOfferTransaction = true;
                                wfocuStripeJS.initCharge();
                            }
                        });

                        $(document).on('wfocuBucketConfirmationRendered', function (e, Bucket) {
                            wfocuStripeJS.bucket = Bucket;

                        });
                        $(document).on('wfocuBucketLinksConverted', function (e, Bucket) {
                            wfocuStripeJS.bucket = Bucket;

                        });
                    })(jQuery);
            </script>
			<?php
		}

		public function allow_check_action( $actions ) {
			array_push( $actions, 'wfocu_front_handle_stripe_payments' );

			return $actions;
		}

		public function verify_intent( $intent_id ) {
			add_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );

			$intent = WC_Stripe_API::request( array(), "payment_intents/$intent_id", 'GET' );
			remove_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );
			$this->current_intent = $intent;
			if ( empty( $intent ) ) {
				return false;
			}
			if ( 'succeeded' === $intent->status || 'requires_capture' === $intent->status ) {

				return $intent;
			}

			return false;
		}

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

			$gateway = $this->get_wc_gateway();

			$source = $gateway->prepare_order_source( $get_order );

			/**
			 * IN case of source is not returned by the gateway class, we try and prepare the data
			 */
			if ( empty( $source ) ) {
				$source = $this->prepare_order_source( $get_order );
			}


			$intent_from_posted = filter_input( INPUT_POST, 'intent', FILTER_SANITIZE_NUMBER_INT );

			/**
			 * If intent flag set found in the posted data from the client then it means we just need to verify the intent status
			 *
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
				 * Verify the intent from stripe API resource to check if its paid or not
				 */
				$intent = $this->verify_intent( $get_intent_id_from_posted_secret );
				if ( false !== $intent ) {
					$response = end( $intent->charges->data );
					WFOCU_Core()->data->set( '_transaction_id', $response->id );
					$this->update_stripe_fees( $get_order, is_string( $response->balance_transaction ) ? $response->balance_transaction : $response->balance_transaction->id );
					wp_send_json( array(
						'result'   => 'success',
						'response' => WFOCU_Core()->process_offer->_handle_upsell_charge( true ),
					) );
				}
				$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: Intent was not authenticated properly.', 'woofunnels-upstroke-one-click-upsell' ), 'Intent was not authenticated properly.', $get_order, true );

			} else {

				try {
					$intent = $this->create_intent( $get_order, $source );
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

				/**
				 * If all good, go ahead and confirm the intent
				 */
				if ( empty( $intent->error ) ) {
					$intent = $this->confirm_intent( $intent, $get_order, $source );
				}

				if ( ! empty( $intent->error ) ) {
					$note = 'Offer payment failed. Reason: ';
					if ( isset( $intent->error->message ) && ! empty( $intent->error->message ) ) {
						$note .= $intent->error->message;
					} else {
						$note .= ( isset( $intent->error->code ) && ! empty( $intent->error->code ) ) ? $intent->error->code : ( isset( $intent->error->type ) ? $intent->error->type : '' );
					}

					$this->handle_api_error( $note, $intent->error, $get_order, true );
				}
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ':confirmed intent is: ' . print_r( $intent, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				/**
				 * Proceed and check intent status
				 */
				if ( ! empty( $intent ) ) {

					// If the intent requires a 3DS flow, redirect to it.
					if ( 'requires_action' === $intent->status ) {

						/**
						 * return intent_secret as the data to the client so that necesary next operations could taken care.
						 */
						wp_send_json( array(
							'result'        => 'success',
							'intent_secret' => $intent->client_secret,
						) );

					}

					try {
						$response = end( $intent->charges->data );
						WFOCU_Core()->data->set( '_transaction_id', $response->id );

						$this->update_stripe_fees( $get_order, is_string( $response->balance_transaction ) ? $response->balance_transaction : $response->balance_transaction->id );
					} catch ( Exception|Error $e ) {
						$this->handle_api_error( $e->getMessage(), 'Error occurred during validating payment, error is: ' . $e->getMessage(), $get_order, true );

					}


				}
			}


			$data = WFOCU_Core()->process_offer->_handle_upsell_charge( true );

			wp_send_json( array(
				'result'   => 'success',
				'response' => $data,
			) );
		}

		/**
		 * This function is placed here as a fallback function when JS client side integration fails mysteriosly
		 * It creates intent and then try to confirm that intent, if successfull then mark success, otherwise failure
		 *
		 * @param WC_Order $order
		 *
		 * @return true
		 * @throws WFOCU_Payment_Gateway_Exception
		 */
		public function process_charge( $order ) {
			$is_successful = false;

			$gateway = $this->get_wc_gateway();

			$source = $gateway->prepare_order_source( $order );

			/**
			 * here we need to create fresh payment intent that we could confirm later
			 */
			$intent = $this->create_intent( $order, $source );
			/**
			 * If all good, go ahead and confirm the intent
			 */
			if ( empty( $intent->error ) ) {
				$intent = $this->confirm_intent( $intent, $order, $source );
			}

			if ( ! empty( $intent->error ) ) {
				$localized_message = '';
				if ( 'card_error' === $intent->error->type ) {
					$localized_message = $intent->error->message;
				}

				$is_successful = false;
				throw new WFOCU_Payment_Gateway_Exception( "Stripe : " . $localized_message, 102, $this->get_key() );

			}
			if ( ! empty( $intent ) ) {

				// If the intent requires a 3DS flow, redirect to it.
				if ( 'requires_action' === $intent->status ) {
					$is_successful = false;
					throw new WFOCU_Payment_Gateway_Exception( "Stripe : Auth required for the charge but unable to complete.", 102, $this->get_key() );
				}
			}

			$response = end( $intent->charges->data );
			if ( is_wp_error( $response ) ) {
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $order ) . ': Payment Failed For Stripe' );
			} else {
				if ( ! empty( $response->error ) ) {
					$is_successful = false;
					throw new WFOCU_Payment_Gateway_Exception( $response->error->message, 102, $this->get_key() );

				} else {
					WFOCU_Core()->data->set( '_transaction_id', $response->id );

					$is_successful = true;
				}
			}

			if ( true === $is_successful ) {
				$this->update_stripe_fees( $order, is_string( $response->balance_transaction ) ? $response->balance_transaction : $response->balance_transaction->id );
			}

			return $this->handle_result( $is_successful );
		}

		public function update_stripe_fees( $order, $balance_transaction_id ) {
			add_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );
			$balance_transaction = WC_Stripe_API::retrieve( 'balance/history/' . $balance_transaction_id );
			remove_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );

			if ( ! empty( $balance_transaction->error ) ) {
				return;
			}

			if ( isset( $balance_transaction ) && isset( $balance_transaction->fee ) ) {


				$fee      = ! empty( $balance_transaction->fee ) ? WC_Stripe_Helper::format_balance_fee( $balance_transaction, 'fee' ) : 0;
				$net      = ! empty( $balance_transaction->net ) ? WC_Stripe_Helper::format_balance_fee( $balance_transaction, 'net' ) : 0;
				$currency = ! empty( $balance_transaction->currency ) ? strtoupper( $balance_transaction->currency ) : null;

				/**
				 * Handling for Stripe Fees
				 */
				$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
				$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;
				if ( true === $is_batching_on ) {
					$fee = (float) $fee + (float) WC_Stripe_Helper::get_stripe_fee( $order );
					$net = (float) $net + (float) WC_Stripe_Helper::get_stripe_net( $order );
					WC_Stripe_Helper::update_stripe_fee( $order, $fee );
					WC_Stripe_Helper::update_stripe_net( $order, $net );
				}
				WFOCU_Core()->data->set( 'wfocu_stripe_fee', $fee );
				WFOCU_Core()->data->set( 'wfocu_stripe_net', $net );
				WFOCU_Core()->data->set( 'wfocu_stripe_currency', $currency );
			}
		}

		protected function create_intent( $order, $prepared_source ) {
			// The request for a charge contains metadata for the intent.
			$full_request = $this->generate_payment_request( $order, $prepared_source );


			$request = array(
				'amount'      => $full_request['amount'],
				'currency'    => $full_request['currency'],
				'description' => $full_request['description'],
				'metadata'    => $full_request['metadata'],

				'capture_method'       => ( 'true' === $full_request['capture'] ) ? 'automatic' : 'manual',
				'payment_method_types' => array(
					'card',
				),
			);
			if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
				$request['payment_method_types'] = $this->get_wc_gateway()->get_upe_enabled_at_checkout_payment_method_ids(); // @phpstan-ignore-line (get_upe_enabled_at_checkout_payment_method_ids is defined in the classes that use this class)
			}
			if ( 0 === strpos( $prepared_source->source, 'src_' ) ) {
				$request['source'] = $prepared_source->source;
			} elseif ( 0 === strpos( $prepared_source->source, 'pm_' ) ) {
				$request['payment_method'] = $prepared_source->source;
			}


			if ( isset( $full_request['statement_descriptor'] ) ) {
				$request['statement_descriptor'] = $full_request['statement_descriptor'];
			}
			if ( $prepared_source->customer ) {
				$request['customer'] = $prepared_source->customer;
			}

			add_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );
			// Create an intent that awaits an action.
			$intent = WC_Stripe_API::request( $request, 'payment_intents' );
			remove_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );

			if ( ! empty( $intent->error ) ) {
				WFOCU_Core()->log->log( 'Order #' . $order->get_id() . " - Offer payment intent create failed, Reason: " . print_r( $intent->error, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $intent;
			}

			$order_id = $order->get_id();
			WFOCU_Core()->log->log( '#Order: ' . $order_id . ' Stripe payment intent created. ' . print_r( $intent, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$this->current_intent = $intent;

			return $intent;
		}

		protected function confirm_intent( $intent, $order, $prepared_source ) {
			if ( 'requires_confirmation' !== $intent->status ) {
				return $intent;
			}
			$confirm_request = [];
			// Try to confirm the intent & capture the charge (if 3DS is not required).

			if ( 0 === strpos( $prepared_source->source, 'src_' ) ) {
				$confirm_request['source'] = $prepared_source->source;
			} elseif ( 0 === strpos( $prepared_source->source, 'pm_' ) ) {
				$confirm_request['payment_method'] = $prepared_source->source;
			}

			add_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );
			$confirmed_intent = WC_Stripe_API::request( $confirm_request, "payment_intents/$intent->id/confirm" );
			remove_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );

			if ( ! empty( $confirmed_intent->error ) ) {
				return $confirmed_intent;
			}

			// Save a note about the status of the intent.
			$order_id = $order->get_id();
			if ( 'succeeded' === $confirmed_intent->status ) {

				WFOCU_Core()->log->log( '#Order: ' . $order_id . 'Stripe PaymentIntent ' . $intent->id . ' succeeded for order' );

			} elseif ( 'requires_action' === $confirmed_intent->status ) {

				WFOCU_Core()->log->log( '#Order: ' . $order_id . " Stripe PaymentIntent $intent->id requires authentication for order" );
			} else {
				WFOCU_Core()->log->log( '#Order: ' . $order_id . " Stripe PaymentIntent $intent->id confirmIntent Response: " . print_r( $confirmed_intent, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}
			$this->current_intent = $confirmed_intent;

			return $confirmed_intent;
		}

		/**
		 * Generate the request for the payment.
		 *
		 * @param WC_Order $order
		 * @param object $source
		 *
		 * @return array()
		 */
		protected function generate_payment_request( $order, $source ) {
			$get_package = WFOCU_Core()->data->get( '_upsell_package' );

			$gateway               = $this->get_wc_gateway();
			$post_data             = array();
			$post_data['currency'] = strtolower( WFOCU_WC_Compatibility::get_order_currency( $order ) );
			$total                 = WC_Stripe_Helper::get_stripe_amount( $get_package['total'], $post_data['currency'] );

			if ( $get_package['total'] * 100 < WC_Stripe_Helper::get_minimum_amount() ) {
				/* translators: 1) dollar amount */
				throw new WFOCU_Payment_Gateway_Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-stripe' ), wc_price( WC_Stripe_Helper::get_minimum_amount() / 100 ) ), 101, $this->get_key() );
			}
			$post_data['amount']      = $total;
			$post_data['description'] = sprintf( __( '%1$s - Order %2$s - 1 click upsell: %3$s', 'woocommerce-gateway-stripe' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number(), 1211 );

			if ( WC_Stripe_Feature_Flags::is_upe_checkout_enabled() ) {
				$settings             = WC_Stripe_Helper::get_stripe_settings();
				$post_data['capture'] = ! empty( $settings['capture'] ) && 'yes' === $settings['capture'] ? 'true' : 'false';
			} else {
				$post_data['capture'] = $gateway->capture ? 'true' : 'false';

			}
			$billing_first_name   = WFOCU_WC_Compatibility::get_billing_first_name( $order );
			$billing_last_name    = WFOCU_WC_Compatibility::get_billing_last_name( $order );
			$billing_email        = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' );
			$settings             = get_option( 'woocommerce_stripe_settings', array() );
			$statement_descriptor = ! empty( $settings['statement_descriptor'] ) ? str_replace( "'", '', $settings['statement_descriptor'] ) : '';
			if ( ! empty( $statement_descriptor ) ) {
				$post_data['statement_descriptor'] = WC_Stripe_Helper::clean_statement_descriptor( $statement_descriptor );
			}
			if ( ! empty( $billing_email ) && apply_filters( 'wc_stripe_send_stripe_receipt', false ) ) {
				$post_data['receipt_email'] = $billing_email;
			}
			$metadata              = array(
				__( 'customer_name', 'woocommerce-gateway-stripe' )  => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
				__( 'customer_email', 'woocommerce-gateway-stripe' ) => sanitize_email( $billing_email ),
				'order_id'                                           => $this->get_order_number( $order ),
			);
			$post_data['expand[]'] = 'balance_transaction';
			$post_data['metadata'] = apply_filters( 'wc_stripe_payment_metadata', $metadata, $order, $source );

			if ( $source->customer ) {
				$post_data['customer'] = $source->customer;
			}

			if ( $source->source ) {

				$get_secondary_source = $order->get_meta( '_wfocu_stripe_source_id', true );
				$post_data['source']  = ( '' !== $get_secondary_source ) ? $get_secondary_source : $source->source;
			}
			WFOCU_Core()->log->log( 'Stripe Request Data:' . print_r( $post_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return apply_filters( 'wc_stripe_generate_payment_request', $post_data, $order, $source );
		}

		public function maybe_modify_3ds_prams( $threds_data, $order ) {

			$order->update_meta_data( '_wfocu_stripe_source_id', $threds_data['three_d_secure']['card'] );
			$order->save();

			return $threds_data;
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
			return add_query_arg( 'action', $action, WC()->api_request_url( 'wfocu_stripe' ) );
		}

		/**
		 * Maybe Handle PayPal Redirection for 3DS Checkout
		 * Let our hooks modify the order received url and redirect user to offer page.
		 *
		 * @param $response
		 * @param WC_Order $order
		 */
		public function maybe_handle_redirection_stripe( $response, $order ) {

			if ( false === $this->is_enabled() ) {
				WFOCU_Core()->log->log( 'Do not initiate redirection for stripe: Stripe is disabled' );

			}

			/**
			 * Validate if its a redirect checkout call for the stripe
			 * Validate if funnel initiation happened.
			 */
			if ( 1 === did_action( 'wfocu_front_init_funnel_hooks' ) && 1 === did_action( 'wc_gateway_stripe_process_redirect_payment' ) ) {
				$get_url = $order->get_checkout_order_received_url();
				wp_redirect( $get_url );
				exit();
			}

		}

		public function maybe_log_process_redirect() {
			WFOCU_Core()->log->log( 'Entering: ' . __CLASS__ . '::' . __FUNCTION__ );
		}

		/**
		 * @param WC_Order $order
		 * @param Integer $transaction
		 */
		public function add_stripe_payouts_to_new_order( $order ) {
			$fee = WFOCU_Core()->data->get( 'wfocu_stripe_fee' );
			$net = WFOCU_Core()->data->get( 'wfocu_stripe_net' );

			$currency = WFOCU_Core()->data->get( 'wfocu_stripe_currency' );
			WC_Stripe_Helper::update_stripe_currency( $order, $currency );
			WC_Stripe_Helper::update_stripe_fee( $order, $fee );
			WC_Stripe_Helper::update_stripe_net( $order, $net );
			$order->save_meta_data();
		}

		/**
		 * Handling refund offer request from admin
		 *
		 * @throws WC_Stripe_Exception
		 */
		public function process_refund_offer( $order ) {
			$refund_data = $_POST;  // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$txn_id = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
			$amnt   = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';

			$order_currency = WFOCU_WC_Compatibility::get_order_currency( $order );

			$request  = array();
			$response = false;

			if ( ! is_null( $amnt ) && class_exists( 'WC_Stripe_Helper' ) ) {
				$request['amount'] = WC_Stripe_Helper::get_stripe_amount( $amnt, $order_currency );
			}

			$request['charge'] = $txn_id;
			$request           = apply_filters( 'wc_stripe_refund_request', $request, $order );

			WFOCU_Core()->log->log( 'Stripe offer refund request data' . print_r( $request, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			if ( class_exists( 'WC_Stripe_API' ) ) {
				add_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );
				$response = WC_Stripe_API::request( $request, 'refunds' );
				remove_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );
			}


			if ( ! empty( $response->error ) || ! $response ) {
				return false;
			} else {

				/**
				 * Lets try and update sripe transaction amounts in the Databse
				 */
				$this->get_wc_gateway()->update_fees( $order, $response->balance_transaction );

				return isset( $response->id ) ? $response->id : true;
			}
		}

		/**
		 * Adding custom note if offer amount is not caputered yet
		 *
		 * @param $order
		 * @param $amnt
		 * @param $refund_id
		 * @param $offer_id
		 * @param $refund_reason
		 */
		public function wfocu_add_order_note( $order, $amnt, $refund_id, $offer_id, $refund_reason ) {
			$captured = WFOCU_WC_Compatibility::get_order_data( $order, '_stripe_charge_captured' );
			if ( isset( $captured ) && 'yes' === $captured ) {
				parent::wfocu_add_order_note( $order, $amnt, $refund_id, $offer_id, $refund_reason );
			} else {
				/* translators: 1) dollar amount 2) transaction id 3) refund message */
				$refund_note = sprintf( __( 'Pre-Authorization Released %1$s <br/>Offer: %2$s(#%3$s) %4$s', 'woofunnels-upstroke-one-click-upsell' ), $amnt, get_the_title( $offer_id ), $offer_id, $refund_reason );
				$order->add_order_note( $refund_note );
			}
		}

		/**
		 *  Creating transaction test/URL
		 *
		 * @param $transaction_id
		 * @param $order_id
		 *
		 * @return string
		 */
		public function get_transaction_link( $transaction_id, $order_id ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

			$testmode = $this->get_wc_gateway()->testmode;

			if ( $transaction_id ) {
				if ( $testmode ) {
					$view_transaction_url = sprintf( 'https://dashboard.stripe.com/test/payments/%s', $transaction_id );
				} else {
					$view_transaction_url = sprintf( 'https://dashboard.stripe.com/payments/%s', $transaction_id );
				}
			}

			if ( ! empty( $view_transaction_url ) && ! empty( $transaction_id ) ) {
				$return_url = sprintf( '<a href="%s">%s</a>', $view_transaction_url, $transaction_id );

				return $return_url;
			}

			return $transaction_id;
		}

		public function maybe_flag_has_intent_secret( $result, $order_id ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			// Only redirects with intents need to be modified.
			if ( isset( $result['intent_secret'] ) || isset( $result['setup_intent_secret'] ) || isset( $result['payment_intent_secret'] ) ) {
				$this->has_intent_secret = $result;
			}


			return $result;
		}

		public function modify_successful_payment_result_for_upstroke( $result, $order_id ) {

			// Only redirects with intents need to be modified.
			if ( empty( $this->has_intent_secret ) ) {
				return $result;
			}

			if ( false === $this->should_tokenize() ) {
				return $result;
			}

			// Put the final thank you page redirect into the verification URL.
			$verification_url = add_query_arg( array(
				'order' => $order_id,
				'nonce' => wp_create_nonce( 'wc_stripe_confirm_pi' ),
			), WC_AJAX::get_endpoint( 'wc_stripe_verify_intent' ) );


			if ( isset( $this->has_intent_secret['payment_intent_secret'] ) ) {
				$redirect = sprintf( '#confirm-pi-%s:%s', $this->has_intent_secret['payment_intent_secret'], rawurlencode( $verification_url ) );
			}
			if ( isset( $this->has_intent_secret['intent_secret'] ) ) {
				$redirect = sprintf( '#confirm-pi-%s:%s', $this->has_intent_secret['intent_secret'], rawurlencode( $verification_url ) );
			} elseif ( isset( $this->has_intent_secret['setup_intent_secret'] ) ) {
				$redirect = sprintf( '#confirm-si-%s:%s', $this->has_intent_secret['setup_intent_secret'], rawurlencode( $verification_url ) );
			}
			$this->has_intent_secret = [];

			return array(
				'result'   => 'success',
				'redirect' => $redirect,
			);
		}


		/**
		 * Get payment source from an order. This could be used in the future for
		 * a subscription as an example, therefore using the current user ID would
		 * not work - the customer won't be logged in :)
		 *
		 * Not using 2.6 tokens for this part since we need a customer AND a card
		 * token, and not just one.
		 *
		 * @param object $order
		 *
		 * @return object
		 * @since 3.1.0
		 * @version 4.0.0
		 */
		public function prepare_order_source( $order = null ) {
			$stripe_customer = new WC_Stripe_Customer();
			$stripe_source   = false;
			$token_id        = false;
			$source_object   = false;

			if ( $order ) {

				$stripe_customer_id = WFOCU_Common::get_order_meta( $order, '_stripe_customer_id' );;

				if ( $stripe_customer_id ) {
					$stripe_customer->set_id( $stripe_customer_id );
				}

				$source_id = $order->get_meta( '_stripe_source_id', true );

				// Since 4.0.0, we changed card to source so we need to account for that.
				if ( empty( $source_id ) ) {
					$source_id = $order->get_meta( '_stripe_card_id', true );

					$order->update_meta_data( '_stripe_source_id', $source_id );

					if ( is_callable( array( $order, 'save' ) ) ) {
						$order->save();
					}
				}

				if ( $source_id ) {
					$stripe_source = $source_id;
					add_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );
					$source_object = WC_Stripe_API::retrieve( 'sources/' . $source_id );
					remove_filter( 'woocommerce_stripe_request_headers', array( $this, 'maybe_modify_version' ) );

				} elseif ( apply_filters( 'wc_stripe_use_default_customer_source', true ) ) {
					/*
					 * We can attempt to charge the customer's default source
					 * by sending empty source id.
					 */
					$stripe_source = '';
				}
			}
			WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $order ) . ': Stripe fallback stripe source created' );

			return (object) array(
				'token_id'      => $token_id,
				'customer'      => $stripe_customer ? $stripe_customer->get_id() : false,
				'source'        => $stripe_source,
				'source_object' => $source_object,
			);
		}

		public function maybe_save_intent( $order ) {
			if ( empty( $this->current_intent ) ) {
				return;
			}
			$this->get_wc_gateway()->save_intent_to_order( $order, $this->current_intent );
			$order->update_meta_data( '_stripe_charge_captured', 'yes' );

		}

		public function maybe_modify_version( $headers ) {
			$headers['Stripe-Version'] = '2022-08-01';

			return $headers;
		}



		public function maybe_handle_stripe_on_upe( $response ) {
			if ( did_action( 'wfocu_front_init_funnel_hooks' ) > 0 ) {
				$captured = ( isset( $response->captured ) && $response->captured ) ? 'yes' : 'no';
				if ( 'yes' === $captured ) {
					if ( 'succeeded' === $response->status ) {
						if ( 'manual_review' !== $this->get_risk_outcome( $response ) ) {
							add_action( 'woocommerce_payment_successful_result', function ( $response, $order_id ) {
								$response['redirect'] = $this->get_wc_gateway()->get_return_url( wc_get_order( $order_id ) );

								return $response;
							}, 10, 2 );
						}
					}
				}

			}
		}

		public function get_risk_outcome( $event_data ) {
			$fraud_type = '';

			if ( isset( $event_data->data->object->outcome->type ) ) { // Gets thCharge.succeeded event.
				$fraud_type = $event_data->data->object->outcome->type;
			} elseif ( isset( $event_data->outcome->type ) ) { // Payment_intent.succeeded event.
				$fraud_type = $event_data->outcome->type;
			}

			return $fraud_type;
		}

		/**
		 * Add a hidden field in stripe to allow plugin to tokenize the payment method
		 * @return void
		 */
		public function add_input_hidden() {

			if ( $this->is_enabled() ) {
				echo '<input type="hidden" name="wc-stripe-new-payment-method" value="1">';

			}

		}


	}

	WFOCU_Gateway_Integration_Stripe::get_instance();
}