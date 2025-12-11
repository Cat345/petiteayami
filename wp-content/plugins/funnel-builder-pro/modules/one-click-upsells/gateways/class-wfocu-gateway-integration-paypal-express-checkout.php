<?php

/**
 * READ THIS
 * For the Paypal Express Checkout we just need to manually store the BILLINGAGREEMENTID in the order meta
 * Refer to this artical for API calls https://www.paypal.com/us/selfhelp/article/how-do-i-use-express-checkout-with-reference-transactions-ts1502/3?channel=MTS
 * Probably we can contact the team and ask for the support
 */
if ( ! class_exists( 'WFOCU_Gateway_Integration_Paypal_Express_Checkout' ) ) {
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Paypal_Express_Checkout extends WFOCU_Gateway {
		protected static $ins = null;
		protected $key = 'ppec_paypal';
		public $parameters;
		protected $last_response;
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

			add_filter( 'woocommerce_paypal_express_checkout_request_body', array( $this, 'maybe_modify_paypal_arguments' ), 999 );
			add_action( 'wfocu_footer_before_print_scripts', array( $this, 'maybe_render_in_offer_transaction_scripts' ), 999 );
			add_filter( 'wfocu_allow_ajax_actions_for_charge_setup', array( $this, 'allow_paypal_express_check_action' ) );

			add_filter( 'wfocu_front_buy_button_attributes', array( $this, 'maybe_add_id_attribute_to_support_inline_paypal' ), 10, 2 );
			add_filter( 'wfocu_front_confirmation_button_attributes', array( $this, 'maybe_add_id_attribute_to_support_inline_paypal' ), 10 );

			/**
			 * Changing transaction id in offer refund function to set it of offer transaciton in stead of parent order,
			 * Changing API credentials for express checkout instead of paypal standard
			 */
			add_filter( 'woocommerce_paypal_refund_request', array( $this, 'wfocu_woocommerce_paypal_refund_request_data' ), 10, 3 );

			add_filter( 'woocommerce_get_checkout_order_received_url', array( $this, 'maybe_init_funnel_and_redirect_to_offer' ), 999, 2 );

			$this->refund_supported = true;

		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_modify_paypal_arguments( $array ) {

			/**
			 * Modifying the data so that initial call to set express checkout would tokenize the card
			 */
			if ( true === $this->is_enabled() && true === $this->is_reference_trans_enabled() && $array && isset( $array['METHOD'] ) && 'SetExpressCheckout' === $array['METHOD'] && ! isset( $array['L_BILLINGTYPE0'] ) ) {
				$array['RETURNURL']                      = add_query_arg( array( 'create-billing-agreement' => true ), $array['RETURNURL'] );
				$array['L_BILLINGTYPE0']                 = 'MerchantInitiatedBillingSingleAgreement';
				$array['L_BILLINGAGREEMENTDESCRIPTION0'] = $this->_get_billing_agreement_description();
				$array['L_BILLINGAGREEMENTCUSTOM0']      = '';
			}

			/**
			 * correcting refrerence transaction params to calucalate all the item total and other arguments that take part in totals & pass them to paypal
			 */
			if ( true === $this->is_enabled() && true === $this->is_reference_trans_enabled() && $this->is_enabled() && $array && isset( $array['METHOD'] ) && 'DoReferenceTransaction' === $array['METHOD'] ) {

				$get_package   = WFOCU_Core()->data->get( '_upsell_package' );
				$current_order = WFOCU_core()->data->get_current_order();

				/**
				 * if we do not have the current order set that means its not the upsell accept call but the call containing subscriptions.
				 */
				if ( false === $current_order ) {
					return $array;
				}
				$array['AMT']     = $get_package['total'];
				$array['ITEMAMT'] = $get_package['total'];

				/**
				 * When shipping amount is a negative number, means user opted for free shipping offer
				 * In this case we setup shippingamt as 0 and shipping discount amount is that negative amount that is coming.
				 */
				if ( ( isset( $get_package['shipping'] ) && isset( $get_package['shipping']['diff'] ) ) && 0 > $get_package['shipping']['diff']['cost'] ) {
					$array['SHIPPINGAMT'] = 0;
					$array['SHIPDISCAMT'] = ( isset( $get_package['shipping'] ) && isset( $get_package['shipping']['diff'] ) ) ? $get_package['shipping']['diff']['cost'] : 0;

				} else {
					$array['SHIPPINGAMT'] = ( isset( $get_package['shipping'] ) && isset( $get_package['shipping']['diff'] ) ) ? $get_package['shipping']['diff']['cost'] : 0;
					$array['SHIPDISCAMT'] = 0;
				}

				$array['TAXAMT']       = ( isset( $get_package['taxes'] ) ) ? $this->round( $get_package['taxes'] ) : 0;
				$array['INVNUM']       = 'WC-' . $this->get_order_number( $current_order );
				$array['INSURANCEAMT'] = 0;
				$array['HANDLINGAMT']  = 0;
				$array                 = $this->remove_previous_line_items( $array );

				$item_loop = 0;
				$ITEMAMT   = 0;
				if ( isset( $get_package['products'] ) && is_array( $get_package['products'] ) && count( $get_package['products'] ) > 0 ) {

					foreach ( $get_package['products'] as $product_data ) {

						if ( $product_data['qty'] ) {
							$array[ 'L_NAME' . $item_loop ] = $product_data['data']->get_name();
							$array[ 'L_DESC' . $item_loop ] = wp_trim_words( $product_data['data']->get_description(), 10 );
							$array[ 'L_AMT' . $item_loop ]  = wc_format_decimal( $product_data['price'], 2 );
							$array[ 'L_QTY' . $item_loop ]  = 1;

							$ITEMAMT += $product_data['args']['total'];

							$item_loop ++;
						}
					}
				}
				$array['ITEMAMT'] = $ITEMAMT;
				WFOCU_Core()->log->log( 'PayPal Express DoReferenceTransaction Details Below: ' . print_r( $array, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			}

			if ( true === $this->is_enabled() && true === $this->is_reference_trans_enabled() && isset( $array['METHOD'] ) && 'DoExpressCheckoutPayment' === $array['METHOD'] ) {

				if ( isset( $array['PAYMENTREQUEST_0_CUSTOM'] ) ) {
					$get_custom_attrs = json_decode( $array['PAYMENTREQUEST_0_CUSTOM'] );
					if ( isset( $get_custom_attrs->order_id ) ) {
						$get_order = wc_get_order( $get_custom_attrs->order_id );

						if ( true === $this->is_enabled( $get_order ) ) {
							try {
								$checkout         = wc_gateway_ppec()->checkout;
								$checkout_details = $checkout->get_checkout_details( $array['TOKEN'] );

								$checkout->create_billing_agreement( $get_order, $checkout_details );

								$token = $get_order->get_meta( '_ppec_billing_agreement_id' );
								if ( ! empty( $token ) ) {

									//saving meta by our own
									//do not need to rely over shutdown
									$get_order->update_meta_data( '_ppec_billing_agreement_id', $token );
									$get_order->save_meta_data();
								}
							} catch ( Exception $e ) {
								WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Unable to create a token for express checkout for order' );
								WFOCU_Core()->log->log( 'Details Below: ' . print_r( $e->getMessage(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							}
						}
					}
				}
			}

			return $array;
		}

		public function is_reference_trans_enabled() {
			$is_reference_transaction_on = WFOCU_Core()->data->get_option( 'paypal_ref_trans' );
			if ( 'yes' === $is_reference_transaction_on ) {

				return true;
			}

			return false;
		}

		/**
		 * Get billing agreement description to be passed to PayPal.
		 *
		 * @return string Billing agreement description
		 * @since 1.2.0
		 *
		 */
		protected function _get_billing_agreement_description() {
			/* translators: placeholder is blogname */
			$description = sprintf( _x( 'Orders with %s', 'data sent to PayPal', 'woofunnels-upstroke-one-click-upsell' ), get_bloginfo( 'name' ) );

			if ( strlen( $description ) > 127 ) {
				$description = substr( $description, 0, 124 ) . '...';
			}

			return html_entity_decode( $description, ENT_NOQUOTES, 'UTF-8' );
		}

		public function remove_previous_line_items( $array ) {

			if ( is_array( $array ) && count( $array ) > 0 ) {
				$array_keys = array_keys( $array );
				foreach ( $array_keys as $key ) {
					if ( false !== strpos( strtoupper( $key ), 'L_' ) ) {
						unset( $array[ $key ] );
					}
				}
			}

			return $array;
		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) {
			$token = $order->get_meta( '_ppec_billing_agreement_id' );
			if ( '' === $token ) {
				$token = WFOCU_Common::get_order_meta( $order, '_ppec_billing_agreement_id' );
			}

			return ! empty( $token );
		}

		public function process_charge( $order ) {
			$is_successful = false;
			try {

				$client = wc_gateway_ppec()->client;
				$params = $client->get_do_reference_transaction_params( array(
					'reference_id' => $this->get_token( $order ),
					'amount'       => 0,
					'order_id'     => WFOCU_WC_Compatibility::get_order_id( $order ),
				) );

				if ( false === $order->has_shipping_address() ) {
					unset( $params['SHIPTONAME'] );
					unset( $params['SHIPTOSTREET'] );
					unset( $params['SHIPTOSTREET2'] );
					unset( $params['SHIPTOCITY'] );
					unset( $params['SHIPTOSTATE'] );
					unset( $params['SHIPTOZIP'] );
					unset( $params['SHIPTOCOUNTRY'] );
				}

				$resp                = $client->do_reference_transaction( $params );
				$this->last_response = $resp;
				WFOCU_Core()->log->log( 'Order: ' . WFOCU_WC_Compatibility::get_order_id( $order ) . ': Transaction response for PPEC:' . print_r( $resp, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				add_action( 'wfocu_offer_new_order_created_' . $this->get_key(), array( $this, 'set_txt_meta' ), 10 );
				if ( $client->response_has_success_status( $resp ) ) {
					WFOCU_Core()->data->set( '_transaction_id', $resp['TRANSACTIONID'] );
					$is_successful = true;
				} else {
					$order_note = __( 'Offer Payment failed.', 'upstroke-woocommerce-one-click-upsell-paypal-angell-eye' );
					$l_msg      = isset( $resp['L_LONGMESSAGE0'] ) ? $resp['L_LONGMESSAGE0'] : '';
					$s_msg      = isset( $resp['L_SHORTMESSAGE0'] ) ? $resp['L_SHORTMESSAGE0'] : '';

					$order_note .= ( ! empty( $l_msg ) || ! empty( $s_msg ) ) ? __( ' Reason: ', 'upstroke-woocommerce-one-click-upsell-paypal-angell-eye' ) : '';
					$order_note .= empty( $l_msg ) ? $s_msg : $l_msg;
					$this->handle_api_error( $order_note, $order_note, $order, false );
				}
			} catch ( Exception $e ) {
				$is_successful = false;
			}

			return $this->handle_result( $is_successful );
		}

		public function get_token( $order ) {
			$token = $order->get_meta( '_ppec_billing_agreement_id' );
			if ( '' === $token ) {
				$token = WFOCU_Common::get_order_meta( $order, '_ppec_billing_agreement_id' );
			}
			if ( ! empty( $token ) ) {
				return $token;
			}

			return false;
		}


		/************************************** PAYPAL IN_OFFER TRANSACTION STARTS *********************/


		/** Helper Methods ******************************************************/

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

			return print_r( $request, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
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

						throw new Exception( sprintf( '%s amount of %s must be less than $10,000.00', $key, $value ) );
					}

					// PayPal requires locale-specific number formats (e.g. USD is 123.45)
					// PayPal requires the decimal separator to be a period (.)
					$this->parameters[ $key ] = $this->price_format( $value );
				}
			}

			return $this->parameters;
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

		public function get_order_from_response( $response ) {

			if ( $response && isset( $response['CUSTOM'] ) ) {
				$getjson = json_decode( $response['CUSTOM'], true );

				return wc_get_order( $getjson['order_id'] );
			}
		}

		public function get_session_from_response( $response ) {

			if ( $response && isset( $response['CUSTOM'] ) ) {
				$getjson = json_decode( $response['CUSTOM'], true );

				return ( $getjson['_wfocu_session_id'] );
			}
		}

		public function is_run_without_token() {
			return ! $this->is_reference_trans_enabled();
		}

		public function maybe_render_in_offer_transaction_scripts() {

			$order = WFOCU_Core()->data->get_current_order();

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( $this->get_key() !== $order->get_payment_method() || false === $this->is_enabled() || true === $this->is_reference_trans_enabled() ) {
				return;
			}

			$environment = $this->get_wc_gateway()->get_option( 'environment', 'live' );

			?>

            <script src="https://www.paypalobjects.com/api/checkout.js"></script> <?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
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

                        $(document).on('wfocuBucketConfirmationRendered', function (e, Bucket) {
                            $wcc_ppec.init();
                            $wcc_ppec.paypalBucket = Bucket;

                        });
                        $(document).on('wfocu_external', function (e, Bucket) {
                            Bucket.inOfferTransaction = true;

                        });

                        $(document).on('wfocuBucketLinksConverted', function (e, Bucket) {
                            $wcc_ppec.init();
                            $wcc_ppec.paypalBucket = Bucket;

                        });

                        function wfocu_paypal_standard_paypal_in_transaction_checkout_process($wcc_ppec, $, paypal) {
                            var getBucketData = $wcc_ppec.paypalBucket.getBucketSendData();
                            var postData = $.extend(getBucketData, {action: 'wfocu_front_create_express_checkout_token_ppec'});


                            var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_create_express_checkout_token_ppec'), postData);

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
                                    }
                                }
                                paypal.checkout.startFlow(data.token);
                            });

                            action.fail(function () {
                                paypal.checkout.closeFlow();
                            });
                        }
                    }
                )(jQuery);
            </script>
			<?php
		}

		/**
		 * Get payer ID from API.
		 */
		public function get_payer_id() {
			$client = wc_gateway_ppec()->client;

			return $client->get_payer_id();
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

		public function create_express_checkout_token() {


			$get_current_offer      = WFOCU_Core()->data->get( 'current_offer' );
			$get_current_offer_meta = WFOCU_Core()->offers->get_offer_meta( $get_current_offer );
			WFOCU_Core()->data->set( '_offer_result', true );
			$posted_data = WFOCU_Core()->process_offer->parse_posted_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$response = false;

			if ( true === WFOCU_AJAX_Controller::validate_charge_request( $posted_data ) ) {
				$get_order = WFOCU_Core()->data->get_parent_order();
				try {
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
					// First we need to request an express checkout token for setting up a billing agreement, to do that, we need to pull details of the transaction from the PayPal Standard args and massage them into the Express Checkout params
					$response = $this->set_express_checkout( array(
						'currency'    => WFOCU_WC_Compatibility::get_order_currency( $get_order ),
						'return_url'  => $this->get_callback_url( 'wfocu_paypal_return' ),
						'cancel_url'  => $this->get_callback_url( 'cancel_url' ),
						'notify_url'  => $this->get_callback_url( 'notify_url' ),
						'order'       => $get_order,
						'no_shipping' => WFOCU_Core()->process_offer->package_needs_shipping() ? 0 : 1,
					) );
					WFOCU_Core()->log->log( 'Result For setExpressCheckout: ' . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					if ( isset( $response['TOKEN'] ) && '' !== $response['TOKEN'] ) {
						WFOCU_Core()->data->set( 'token', $response['TOKEN'], 'paypal' );
						WFOCU_Core()->data->set( 'upsell_package', WFOCU_Core()->data->get( '_upsell_package' ), 'paypal' );
						WFOCU_Core()->data->save( 'paypal' );
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
				} catch ( WFOCU_Payment_Gateway_Exception $e ) {
					$order_note = __( 'Offer payment failed.', 'woofunnels-upstroke-one-click-upsell' );
					if ( $e instanceof WFOCU_Payment_Gateway_Exception ) {
						$order_note .= sprintf( __( ' Reason: %s', 'woofunnels-upstroke-one-click-upsell' ), $e->getMessage() );
					}
					$this->handle_api_error( $order_note, $order_note, $get_order, true );
				}
			}
			wp_send_json( array(
				'result'   => 'error',
				'response' => $response,
			) );

		}

		/**
		 * Sets the prams for setExpressCheckout call and executes it
		 *
		 * @param array $args
		 *
		 * @return object
		 * @throws Exception
		 */
		public function set_express_checkout( $args ) {

			$environment = $this->get_wc_gateway()->get_option( 'environment', 'live' );

			if ( 'live' === $environment ) {
				$api_username  = $this->get_wc_gateway()->get_option( 'api_username' );
				$api_password  = $this->get_wc_gateway()->get_option( 'api_password' );
				$api_signature = $this->get_wc_gateway()->get_option( 'api_signature' );
			} else {
				$api_username  = $this->get_wc_gateway()->get_option( 'sandbox_api_username' );
				$api_password  = $this->get_wc_gateway()->get_option( 'sandbox_api_password' );
				$api_signature = $this->get_wc_gateway()->get_option( 'sandbox_api_signature' );

			}

			$this->set_api_credentials( $this->get_key(), $environment, $api_username, $api_password, $api_signature );
			$this->set_express_checkout_args( $args );
			$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );

			$request         = new stdClass();
			$request->path   = '';
			$request->method = 'POST';
			$request->body   = $this->to_string();
			WFOCU_Core()->data->set( 'paypal_request_data', $this->get_parameters(), 'paypal' );

			return $this->perform_request( $request );
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
			$this->request_uri = wc_gateway_ppec()->client->get_endpoint();

			// PayPal requires HTTP 1.1
			$this->request_http_version = '1.1';

			$this->api_username  = $api_username;
			$this->api_password  = $api_password;
			$this->api_signature = $api_signature;
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
		public function set_express_checkout_args( $args ) {

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
				'addressoverride'     => '1',
			);

			$args = wp_parse_args( $args, $defaults );

			$this->set_method( 'SetExpressCheckout' );

			$this->add_parameters( array(

				'RETURNURL'   => $args['return_url'],
				'CANCELURL'   => $args['cancel_url'],
				'PAGESTYLE'   => $args['page_style'],
				'BRANDNAME'   => $args['brand_name'],
				'LANDINGPAGE' => 'Billing',

				'ADDROVERRIDE' => $args['addressoverride'],
				'NOSHIPPING'   => $args['no_shipping'],

				'MAXAMT' => $args['maximum_amount'],
			) );

			// if we have an order, the request is to create a subscription/process a payment (not just check if the PayPal account supports Reference Transactions)
			if ( isset( $args['order'] ) ) {
				$this->add_payment_details_parameters( $args['order'], $args['payment_action'], false );
			}
			if ( empty( $args['no_shipping'] ) ) {

				$this->maybe_add_shipping_address_params( $args['order'] );

			}
			$set_express_checkout_params = apply_filters( 'wfocu_gateway_ppec_param_setexpresscheckout', $this->get_parameters(), true );

			$this->clean_params();
			$this->add_parameters( $set_express_checkout_params );

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
		public function set_method( $method ) {
			$this->add_parameter( 'METHOD', $method );
		}

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
		protected function add_payment_details_parameters( WC_Order $order, $type, $use_deprecated_params = false ) {

			$order_subtotal = 0;
			$item_count     = 0;
			$order_items    = array();

			$offer_package = WFOCU_Core()->data->get( '_upsell_package' );

			foreach ( $offer_package['products'] as $item ) {

				$product = $item['data'];

				$order_items[] = array(
					'NAME'    => $product->get_title(),
					'DESC'    => $this->get_item_description( $item, $product ),
					'AMT'     => $this->round( $item['price'] ),
					'QTY'     => 1,
					'ITEMURL' => $product->get_permalink(),
				);

				$order_subtotal += $item['args']['total'];
			}

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
			 * When shipping amount is a negative number, means user opted for free shipping offer
			 * In this case we setup shippingamt as 0 and shipping discount amount is that negative amount that is coming.
			 */

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
					'PAYMENTREQUESTID' => WFOCU_WC_Compatibility::get_order_id( $order ),
					'TAXAMT'           => ( isset( $offer_package['taxes'] ) ) ? $offer_package['taxes'] : 0,
					'CUSTOM'           => wp_json_encode( array(
						'_wfocu_o_id'       => $this->get_wc_gateway()->get_option( 'invoice_prefix' ) . $this->get_order_number( $order ),
						'_wfocu_session_id' => WFOCU_Core()->data->get_transient_key(),
					) ),
				) );
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
		private function get_item_description( $item, $product ) {

			$item_desc = wp_strip_all_tags( wp_staticize_emoji( $product->get_short_description() ) );
			$item_desc = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $item_desc );
			$item_desc = str_replace( "\n", ', ', rtrim( $item_desc ) );
			if ( strlen( $item_desc ) > 127 ) {
				$item_desc = substr( $item_desc, 0, 124 ) . '...';
			}

			return html_entity_decode( $item_desc, ENT_NOQUOTES, 'UTF-8' );

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
		 * Tell the system to run without a token or not
		 * @return bool
		 */

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

		public function clean_params() {
			$this->parameters = array();
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
		 * Returns the string representation of this request
		 *
		 * @return string the request query string
		 * @see SV_WC_Payment_Gateway_API_Request::to_string()
		 * @since 2.0
		 */
		public function to_string() {
			WFOCU_Core()->log->log( print_r( $this->get_parameters(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return http_build_query( $this->get_parameters(), '', '&' );
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
			return add_query_arg( 'action', $action, WC()->api_request_url( 'wfocu_paypal_ppec' ) );
		}

		public function allow_paypal_express_check_action( $actions ) {
			array_push( $actions, 'wfocu_front_create_express_checkout_token_ppec' );

			return $actions;
		}

		public function handle_api_calls() {
			if ( ! isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			switch ( $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				case 'wfocu_paypal_return':
					$existing_package = WFOCU_Core()->data->get( 'upsell_package', '', 'paypal' );

					if ( isset( $_GET['token'] ) && ! empty( $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

						$express_checkout_details_response = $this->get_express_checkout_details( wc_clean( $_GET['token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						WFOCU_Core()->log->log( '$express_checkout_details_response ' . print_r( $express_checkout_details_response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

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
							 * Prepare DoExpressCheckout Call to finally charge the user
							 */
							$do_express_checkout_data = array(
								'TOKEN'   => $express_checkout_details_response['TOKEN'],
								'PAYERID' => $express_checkout_details_response['PAYERID'],
								'METHOD'  => 'DoExpressCheckoutPayment',
							);
							$do_express_checkout_data = wp_parse_args( $do_express_checkout_data, $get_paypal_data );

							$environment = $this->get_wc_gateway()->get_option( 'environment', 'live' );

							if ( 'live' === $environment ) {
								$api_username  = $this->get_wc_gateway()->get_option( 'api_username' );
								$api_password  = $this->get_wc_gateway()->get_option( 'api_password' );
								$api_signature = $this->get_wc_gateway()->get_option( 'api_signature' );
							} else {
								$api_username  = $this->get_wc_gateway()->get_option( 'sandbox_api_username' );
								$api_password  = $this->get_wc_gateway()->get_option( 'sandbox_api_password' );
								$api_signature = $this->get_wc_gateway()->get_option( 'sandbox_api_signature' );

							}

							$this->set_api_credentials( $this->get_key(), $environment, $api_username, $api_password, $api_signature );

							$this->add_parameters( $do_express_checkout_data );
							$this->populate_credentials( $this->api_username, $this->api_password, $this->api_signature, 124 );

							$request         = new stdClass();
							$request->path   = '';
							$request->method = 'POST';
							$request->body   = $this->to_string();

							$response            = $this->perform_request( $request );
							$this->last_response = $response;
							add_action( 'wfocu_offer_new_order_created_' . $this->get_key(), array( $this, 'set_txt_meta' ), 10 );

							WFOCU_Core()->log->log( 'PayPal In-offer transactions DoexpressCheckout response. ' . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							if ( false === $this->has_api_error( $response ) ) {
								WFOCU_Core()->data->set( '_transaction_id', $this->get_transaction_id( $response ) );
								$api_response_result = true;
							}
						} else {
							$api_response_result = true;
						}

						WFOCU_Core()->data->set( '_offer_result', true );
						WFOCU_Core()->data->save();
						/**
						 * Allow our subscription addon to make subscription request
						 */
						$api_response_result = apply_filters( 'wfocu_gateway_in_offer_transaction_ppec_after_express_checkout_response', $api_response_result, $express_checkout_details_response['TOKEN'], $express_checkout_details_response['PAYERID'], $this );

						/**
						 * Set the upsell package data so that order processing will process this
						 */
						WFOCU_Core()->data->set( '_upsell_package', $existing_package );

						$data = WFOCU_Core()->process_offer->_handle_upsell_charge( $api_response_result );

						wp_redirect( $data['redirect_url'] );
						exit;
						break;
					} else {
						/**
						 * Set the upsell package data so that order processing will process this
						 */
						WFOCU_Core()->data->set( '_upsell_package', $existing_package );

						$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );

						wp_redirect( $data['redirect_url'] );
						exit;
					}
				case 'cancel_url':
					/**
					 * Getting the current URL from the session and loading the same offer again.
					 * User needs to chose "no_url" if he want to move to upsell/order received.
					 */ $get_offer = WFOCU_Core()->data->get_current_offer();
					wp_redirect( WFOCU_Core()->public->get_the_upsell_url( $get_offer ) );
					exit;

			}
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
			$environment = $this->get_wc_gateway()->get_option( 'environment', 'live' );

			if ( 'live' === $environment ) {
				$api_username  = $this->get_wc_gateway()->get_option( 'api_username' );
				$api_password  = $this->get_wc_gateway()->get_option( 'api_password' );
				$api_signature = $this->get_wc_gateway()->get_option( 'api_signature' );
			} else {
				$api_username  = $this->get_wc_gateway()->get_option( 'sandbox_api_username' );
				$api_password  = $this->get_wc_gateway()->get_option( 'sandbox_api_password' );
				$api_signature = $this->get_wc_gateway()->get_option( 'sandbox_api_signature' );

			}

			$this->set_api_credentials( $this->get_key(), $environment, $api_username, $api_password, $api_signature );

			$this->get_express_checkout_args( $token );
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

		public function has_api_error( $response ) {
			// assume something went wrong if ACK is missing
			if ( ! isset( $response['ACK'] ) ) {
				return true;
			}

			// any non-success ACK is considered an error, see
			// https://developer.paypal.com/docs/classic/api/NVPAPIOverview/#id09C2F0K30L7
			return ( 'Success' !== $this->get_value_from_response( $response, 'ACK' ) && 'SuccessWithWarning' !== $this->get_value_from_response( $response, 'ACK' ) );

		}

		public function get_value_from_response( $response, $key ) {

			if ( $response && isset( $response[ $key ] ) ) {

				return $response[ $key ];
			}
		}

		public function get_transaction_id( $response ) {

			if ( is_array( $response ) && isset( $response['PAYMENTINFO_0_TRANSACTIONID'] ) ) {
				return $response['PAYMENTINFO_0_TRANSACTIONID'];
			}

			return '';
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
			if ( ( ( false === $get_offer_settings->settings->ask_confirmation && 'wfocu_front_buy_button_attributes' === $current_action ) || ( true === $get_offer_settings->settings->ask_confirmation && 'wfocu_front_confirmation_button_attributes' === $current_action ) ) ) {
				$this->button_max_count ++;
				$attributes['id'] = 'wfocu_paypal_only_' . $iteration;
			}

			return $attributes;

		}

		/**
		 * Handling refund offer exceptions
		 *
		 * @param $order
		 *
		 * @return bool
		 */
		public function process_refund_offer( $order ) {
			$refund_data = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order_id    = WFOCU_WC_Compatibility::get_order_id( $order );

			$amnt          = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
			$refund_reason = isset( $refund_data['refund_reason'] ) ? $refund_data['refund_reason'] : '';
			$response      = false;

			if ( ! is_null( $amnt ) && class_exists( 'WC_Gateway_Paypal' ) ) {
				$available_gateways = WC()->payment_gateways->payment_gateways();

				if ( isset( $available_gateways['paypal'] ) ) {
					if ( ! class_exists( 'WC_Gateway_Paypal_API_Handler' ) ) {
						include_once wc()->plugin_path() . '/includes/gateways/paypal/includes/class-wc-gateway-paypal-api-handler.php';  //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomFunction
					}

					$environment = $this->get_wc_gateway()->get_option( 'environment', 'live' );
					$test_mode   = ( 'live' !== $environment );

					WC_Gateway_Paypal_API_Handler::$api_username  = $test_mode ? $this->get_wc_gateway()->get_option( 'sandbox_api_username' ) : $this->get_wc_gateway()->get_option( 'api_username' );
					WC_Gateway_Paypal_API_Handler::$api_password  = $test_mode ? $this->get_wc_gateway()->get_option( 'sandbox_api_password' ) : $this->get_wc_gateway()->get_option( 'api_password' );
					WC_Gateway_Paypal_API_Handler::$api_signature = $test_mode ? $this->get_wc_gateway()->get_option( 'sandbox_api_signature' ) : $this->get_wc_gateway()->get_option( 'api_signature' );
					WC_Gateway_Paypal_API_Handler::$sandbox       = $test_mode;

					$result = WC_Gateway_Paypal_API_Handler::refund_transaction( $order, $amnt, $refund_reason );

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
						WFOCU_Core()->log->log( "Paypal Express checkout refund error message: " . print_r( $result->L_LONGMESSAGE0, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					}
				}

				WFOCU_Core()->log->log( 'WFOCU Paypal Express checkout Offer refund response: ' . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return $response ? $response : false;
		}


		/********************** PAYPAL IN-OFFER PURCHASE ********************************/

		/**
		 * @hooked over woocommerce_paypal_refund_request
		 *
		 * Changing transaction id in offer refund function to set it of offer transaciton in stead of parent order
		 */
		public function wfocu_woocommerce_paypal_refund_request_data( $request, $order, $amount ) { //phpcs:ignore

			$payment_method = $order->get_payment_method();

			if ( $this->key !== $payment_method ) {
				return $request;
			}

			WFOCU_Core()->log->log( 'Paypal Express Refund Request: ' . print_r( $request, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			if ( isset( $_POST['txn_id'] ) && ! empty( $_POST['txn_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
				$request['TRANSACTIONID'] = wc_clean( $_POST['txn_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

				$environment = $this->get_wc_gateway()->get_option( 'environment', 'live' );

				if ( 'live' === $environment ) {
					$request['USER']      = $this->get_wc_gateway()->get_option( 'api_username' );
					$request['PWD']       = $this->get_wc_gateway()->get_option( 'api_password' );
					$request['SIGNATURE'] = $this->get_wc_gateway()->get_option( 'api_signature' );
				} else {
					$request['USER']      = $this->get_wc_gateway()->get_option( 'sandbox_api_username' );
					$request['PWD']       = $this->get_wc_gateway()->get_option( 'sandbox_api_password' );
					$request['SIGNATURE'] = $this->get_wc_gateway()->get_option( 'sandbox_api_signature' );

				}
			}

			WFOCU_Core()->log->log( 'Paypal Express Modified Refund Request: ' . print_r( $request, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

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
		public function get_transaction_link( $transaction_id, $order_id ) { //phpcs:ignore

			$testmode = $this->get_wc_gateway()->environment;

			if ( $transaction_id ) {
				if ( 'sandbox' === $testmode ) {
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
				$prefix . 'COUNTRYCODE' => $shipping_country,
				$prefix . 'PHONENUM'    => $shipping_phone,
			);
			foreach ( $params as $key => $val ) {
				$this->add_parameter( $key, $val );
			}

		}

		/**
		 *
		 * @param $url
		 * @param WC_Order $order
		 *
		 * @return mixed
		 */
		function maybe_init_funnel_and_redirect_to_offer( $url, $order ) {

			/**
			 * if order's payment is not the integration one
			 */
			if ( $this->get_key() !== $order->get_payment_method() ) {
				return $url;
			}

			/**
			 * If funnel already started in this session
			 */
			if ( 0 !== did_action( 'wfocu_front_init_funnel_hooks' ) ) {
				return $url;
			}

			/**
			 * If this gateway is not enabled in the settings
			 */
			if ( false === $this->is_enabled( $order ) ) {
				return $url;
			}

			if ( false === $this->should_tokenize() ) {
				return $url;
			}
			/**
			 * If funnel started and this call is after funnel started
			 */

			$get_meta = $order->get_meta( '_wfocu_funnel_key', true );

			if ( ! empty( $get_meta ) ) {
				return $url;
			}

			add_action( 'wfocu_front_init_funnel_hooks', array( $this, 'set_primary_status' ) );
			/**
			 * maybe setup the funnel
			 */
			WFOCU_Core()->public->maybe_setup_upsell( $order->get_id() );

			remove_filter( 'woocommerce_get_checkout_order_received_url', array( $this, 'maybe_init_funnel_and_redirect_to_offer' ), 999 );

			return $order->get_checkout_order_received_url();
		}

		/**
		 *
		 * @param WC_Order $order
		 */
		public function set_primary_status( $order ) {
			WFOCU_Core()->orders->maybe_set_funnel_running_status( $order );
		}

		public function get_api_error( $response ) {

			if ( 'Failure' === $this->get_value_from_response( $response, 'ACK' ) ) {
				return $this->get_value_from_response( $response, 'L_LONGMESSAGE0' );
			}

			return '';
		}

		/**
		 * Maybe block IPN operations while we are in a running funnel
		 * before blocking we must need to verify few things, these are
		 * 1. Current IPN request is for the txn_type cart that represents primary checkout
		 * 2. IPN comes with completed payment, any other status doesn't need to be care
		 * 3. If gateway is enabled
		 * 4. If order contains funnel_id as meta to ensure that upstroke funnel ran/running on this order
		 *
		 * @param WC_Order $order
		 * @param array $posted
		 */
		public function handle_ipn( $order, $posted ) {
			if ( ! in_array( $posted['txn_type'], array( 'cart', 'express_checkout' ), true ) || 'completed' !== strtolower( $posted['payment_status'] ) || ! $this->is_enabled( $order ) ) {
				return;
			}

			$get_meta_funnel_id = $order->get_meta( '_wfocu_funnel_id', true );
			/**
			 * descard all the funnel where we havn't executed
			 */
			if ( empty( $get_meta_funnel_id ) ) {
				return;
			}
			WFOCU_Core()->log->log( 'Order #' . $order->get_id() . " :: Prevent IPN to process" );
			exit;
		}

		public function set_txt_meta( $new_order ) {

			if ( ! empty( $this->last_response ) ) {
				if ( true === $this->is_reference_trans_enabled() ) {
					wc_gateway_ppec_save_transaction_data( $new_order, $this->last_response );
				} else {
					wc_gateway_ppec_save_transaction_data( $new_order, $this->last_response, 'PAYMENTINFO_0_' );
				}

			}

		}
	}

	WFOCU_Gateway_Integration_Paypal_Express_Checkout::get_instance();
}