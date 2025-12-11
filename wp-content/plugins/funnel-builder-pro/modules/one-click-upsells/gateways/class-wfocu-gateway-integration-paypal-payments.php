<?php
if ( ! class_exists( 'WFOCU_Gateway_Integration_PayPal_Payments' ) ) {
	/**
	 * Integration of PayPal Payments gateway with upsells
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_PayPal_Payments extends WFOCU_Gateway {
		protected $key = 'ppcp-gateway';
		protected static $ins = null;
		protected $container = null;
		protected $payal_order_id = null;

		public function __construct() {
			parent::__construct();

			add_action( 'wfocu_footer_before_print_scripts', array( $this, 'maybe_render_in_offer_transaction_scripts' ), 999 );
			add_filter( 'wfocu_allow_ajax_actions_for_charge_setup', array( $this, 'allow_action' ) );
			$this->refund_supported = true;
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_render_in_offer_transaction_scripts() {
			$order = WFOCU_Core()->data->get_current_order();

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			if ( $this->get_key() !== $order->get_payment_method() ) {
				return;
			}
			if ( ! $this->is_enabled() ) {
				return;
			}
			?>
            <script>
                (
                    function ($) {
                        "use strict";


                        $(document).on('wfocu_external', function (e, Bucket) {

                            /**
                             * Check if we need to mark inoffer transaction to prevent default behavior of page
                             */
                            if (0 !== Bucket.getTotal()) {

                                Bucket.inOfferTransaction = true;
                                var getBucketData = Bucket.getBucketSendData();

                                var postData = $.extend(getBucketData, {action: 'wfocu_front_handle_paypal_payments'});


                                if (typeof wfocu_vars.wc_ajax_url !== "undefined") {
                                    var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_handle_paypal_payments'), postData);

                                } else {
                                    var action = $.post(wfocu_vars.ajax_url, postData);

                                }

                                action.done(function (data) {

                                    if (data.status === true) {
                                        window.location = data.redirect_url;
                                    } else {
                                        Bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                        window.location = wfocu_vars.redirect_url + '&ec=ppec_token_not_found';
                                    }

                                });

                                action.fail(function () {
                                    Bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                    /** move to order received page */
                                    if (typeof wfocu_vars.order_received_url !== 'undefined') {

                                        window.location = wfocu_vars.order_received_url + '&ec=' + jqXHR.status;

                                    }

                                });

                            }


                        });


                    })
                (jQuery);
            </script> <?php
		}

		public function is_run_without_token() {
			return true;
		}


		/**
		 * Process the client order from the JS and try to create order using PayPal REST API
		 */
		public function process_client_order() {

			$get_current_offer      = WFOCU_Core()->data->get( 'current_offer' );
			$get_current_offer_meta = WFOCU_Core()->offers->get_offer_meta( $get_current_offer );
			WFOCU_Core()->data->set( '_offer_result', true );
			$posted_data = WFOCU_Core()->process_offer->parse_posted_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( true === WFOCU_AJAX_Controller::validate_charge_request( $posted_data ) ) {

				WFOCU_Core()->process_offer->execute( $get_current_offer_meta );
				$get_order = WFOCU_Core()->data->get_parent_order();

				$offer_package = WFOCU_Core()->data->get( '_upsell_package' );

				WFOCU_Core()->data->set( 'upsell_package', $offer_package, 'paypal' );
				WFOCU_Core()->data->save( 'paypal' );
				WFOCU_Core()->data->save();
				$data = array(
					'intent'              => 'CAPTURE',
					'purchase_units'      => $this->get_purchase_units( $get_order, $offer_package ),
					'application_context' => array(
						'user_action'  => 'CONTINUE',
						'landing_page' => 'NO_PREFERENCE',
						'brand_name'   => html_entity_decode( get_bloginfo( 'name' ), ENT_NOQUOTES, 'UTF-8' ),
						'return_url'   => add_query_arg( array( 'wfocu-si' => WFOCU_Core()->data->get_transient_key() ), WC()->api_request_url( 'wfocu_paypal_payments' ) ),
						'cancel_url'   => add_query_arg( array( 'wfocu-si' => WFOCU_Core()->data->get_transient_key() ), WFOCU_Core()->public->get_the_upsell_url( WFOCU_Core()->data->get_current_offer() ) ),

					),
					'payment_method'      => array(
						'payee_preferred' => 'UNRESTRICTED',
						'payer_selected'  => 'PAYPAL',
					),
					'payment_instruction' => array(
						'disbursement_mode' => 'INSTANT',

					),

				);
				WFOCU_Core()->log->log( "Order: #" . $get_order->get_id() . " paypal args" . print_r( $data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$arguments = apply_filters( 'wfocu_ppcp_gateway_process_client_order_api_args', array(
					'method'  => 'POST',
					'headers' => array(
						'Content-Type'                  => 'application/json',
						'Authorization'                 => 'Bearer ' . $this->get_bearer( $get_order ),
						'PayPal-Partner-Attribution-Id' => 'BWF_PPCP',
					),
					'body'    => $data,
					'timeout' => 30,
				), $get_order, $posted_data, $offer_package );

				$arguments['body'] = wp_json_encode( $arguments['body'] );

				$payment_env = $get_order->get_meta( '_ppcp_paypal_payment_mode' );
				// Refer https://developer.paypal.com/docs/api/orders/v2/ documentation to generate create order endpoint.
				$url = $this->get_api_base( $payment_env ) . 'v2/checkout/orders';

				$ppcp_resp = wp_remote_get( $url, $arguments ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions
				if ( is_wp_error( $ppcp_resp ) ) {

					$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );

					$json_response = array(
						'status'       => false,
						'redirect_url' => $data['redirect_url'],
					);


					WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Unable to create paypal Order refer error below' . print_r( $ppcp_resp, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					wp_send_json( $json_response );

				} else {

					$retrived_body = wp_remote_retrieve_body( $ppcp_resp );

					$response = json_decode( $retrived_body );

					/*
					 * The call to Orders API to create or initiate a charge using a PayPal account as the payment source results in a PAYER_ACTION_REQUIRED contingency.
					 * Once the buyer has identified their PayPal account, authenticated, and been redirected
					 */
					if ( 'CREATED' === $response->status || 'PAYER_ACTION_REQUIRED' === $response->status ) {

						$approve_link = $response->links[1]->href;

						// Update Order Created ID (PayPal Order ID) in the order.
						$get_order->update_meta_data( 'wfocu_ppcp_order_current', $response->id );
						$get_order->save();

						WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': PayPal Order successfully created' );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

						$json_response = array(
							'status'       => true,
							'redirect_url' => $approve_link,
						);

					} else {
						$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );

						$json_response = array(
							'status'       => false,
							'redirect_url' => $data['redirect_url'],
						);


						WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Unable to create paypal Order refer error below' . print_r( $ppcp_resp, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

						wp_send_json( $json_response );
					}
				}

				wp_send_json( $json_response );

			}

		}

		/**
		 * Retrieves token for payment.
		 *
		 * @param object $order order details.
		 *
		 * @return string
		 */
		public function get_bearer( $order ) {

			$token = '';

			/**        $bearer = get_option( '_transient_ppcp-paypal-bearerppcp-bearer' );
			 *
			 * if ( ! empty( $bearer ) )   {
			 * $bearer = json_decode( $bearer );
			 * $token  = $bearer->access_token;
			 * } **/

			// Generate new token if token does not exists.
			if ( empty( $token ) ) {
				$payment_env   = $order->get_meta( '_ppcp_paypal_payment_mode' );
				$ppcp_settings = $this->get_paypal_options();
				if ( ! isset( $ppcp_settings['client_id'] ) || ! isset( $ppcp_settings['client_secret'] ) ) {
					$ppcp_settings = $this->get_paypal_settings();
				}
				$client_id  = $ppcp_settings['client_id'];
				$secret_key = $ppcp_settings['client_secret'];
				$url        = $this->get_api_base( $payment_env ) . 'v1/oauth2/token?grant_type=client_credentials';
				$args       = array(
					'method'  => 'POST',
					'timeout' => 30,
					'headers' => array(
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
						'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret_key ),
					),
				);

				$response = wp_remote_get( $url, $args ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions

				if ( ! is_wp_error( $response ) ) {
					$res_body = json_decode( $response['body'] );
					$token    = $res_body->access_token;
				}
			}

			return $token;
		}

		/**
		 * Create purchase unite for create order.
		 *
		 * @param WC_Order $order WC Order.
		 * @param array $offer_product upsell/downsell product.
		 * @param object $args Posted and payment gateway setting data.
		 *
		 * @return array $purchase_unit.
		 */
		public function get_purchase_units( $order, $package ) {
			$paypal_settings = get_option( 'woocommerce-ppcp-settings' );
			$invoice_id      = $paypal_settings['prefix'] . '-wfocu-' . $this->get_order_number( $order );
			$total_amount    = $package['total'];
			$purchase_unit   = array(
				'reference_id' => 'default',
				'amount'       => array(
					'currency_code' => $order->get_currency(),
					'value'         => (string) $this->round( $total_amount ),
					'breakdown'     => $this->get_item_breakdown( $order, $package ),
				),
				'description'  => __( 'Special offer OTO', 'woocommerce-one-click-upsells' ), // phpcs:ignore
				'items'        => $this->add_offer_item_data( $order, $package ),
				'payee'        => array(
					'email_address' => $paypal_settings['merchant_email'],
					'merchant_id'   => $paypal_settings['merchant_id'],
				),
				'shipping'     => array(
					'name' => array(
						'full_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					),
				),
				'custom_id'    => $invoice_id,
				'invoice_id'   => $invoice_id,
			);

			return array( $purchase_unit );
		}

		/**
		 * Create breakdown for item amount.
		 *
		 * @param WC_Order $order WC Order.
		 * @param array $offer_product upsell/downsell product.
		 *
		 * @return array $breakdown item amount breakdown.
		 */
		public function get_item_breakdown( $order, $offer_package ) {

			$breakdown      = array();
			$order_subtotal = 0.00;
			foreach ( $offer_package['products'] as $item ) {

				$order_subtotal += $item['args']['total'];
			}
			$breakdown['item_total'] = array(
				'currency_code' => $order->get_currency(),
				'value'         => (string) $this->round( $order_subtotal ),
			);
			$breakdown['tax_total']  = array(
				'currency_code' => $order->get_currency(),
				'value'         => ( isset( $offer_package['taxes'] ) ) ? ( (string) $this->validate_tax( $offer_package ) ) : '0',
			);


			if ( ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) ) {
				/**
				 * It means we have shipping to pass
				 */
				if ( 0 <= $offer_package['shipping']['diff']['cost'] ) {
					$shipping = ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) ? ( (string) $offer_package['shipping']['diff']['cost'] ) : 0;

					if ( ! empty( $shipping ) && 0 < intval( $shipping ) ) {
						$breakdown['shipping'] = array(
							'currency_code' => $order->get_currency(),
							'value'         => (string) $this->round( $shipping ),
						);
					}

				} else {
					$shipping = ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) ? ( (string) $offer_package['shipping']['diff']['cost'] ) : 0;


					$breakdown['shipping_discount'] = array(
						'currency_code' => $order->get_currency(),
						'value'         => (string) abs( $this->round( $shipping ) ),
					);
					$breakdown['shipping']          = array(
						'currency_code' => $order->get_currency(),
						'value'         => '0.00',
					);

				}
			}


			return $breakdown;
		}

		public function allow_action( $actions ) {
			array_push( $actions, 'wfocu_front_handle_paypal_payments' );

			return $actions;
		}

		/**
		 * Add product's item data.
		 *
		 * @param object $order WC Order.
		 * @param array $offer_product upsell/downsell product.
		 *
		 * @return array $offer_items item data.
		 */
		public function add_offer_item_data( $order, $offer_package ) {


			$order_items = [];
			foreach ( $offer_package['products'] as $item ) {

				$product = $item['data'];
				$title   = $product->get_title();
				if ( strlen( $title ) > 127 ) {
					$title = substr( $title, 0, 124 ) . '...';
				}
				$order_items[] = array(
					'name'        => $title,
					'unit_amount' => array(
						'currency_code' => $order->get_currency(),
						'value'         => (string) $this->round( $item['price'] ),
					),
					'quantity'    => 1,
					'description' => $this->get_item_description( $product ),
				);

			};

			return $order_items;
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
		 * Handle API calls
		 * 1. customer return after payment
		 */
		public function handle_api_calls() {
			if ( false === WFOCU_Core()->data->has_valid_session() ) {
				return;
			}
			/**
			 * Setting up necessary data for this api call
			 */
			add_filter( 'wfocu_valid_state_for_data_setup', '__return_true' );
			WFOCU_Core()->template_loader->set_offer_id( WFOCU_Core()->data->get_current_offer() );

			WFOCU_Core()->template_loader->maybe_setup_offer();

			$get_order = WFOCU_Core()->data->get_parent_order();

			if ( ! $get_order instanceof WC_Order ) {
				return;
			}

			$token           = $this->get_bearer( $get_order );
			$paypal_order_id = $get_order->get_meta( 'wfocu_ppcp_order_current' );
			$environment     = $get_order->get_meta( '_ppcp_paypal_payment_mode' );
			$capture_args    = array(
				'method'  => 'POST',
				'timeout' => 30,
				'headers' => array(
					'Authorization'                 => 'Bearer ' . $token,
					'Content-Type'                  => 'application/json',
					'Prefer'                        => 'return=representation',
					'PayPal-Partner-Attribution-Id' => 'BWF_PPCP',
				),
			);


			$capture_url = $this->get_api_base( $environment ) . 'v2/checkout/orders/' . $paypal_order_id . '/capture';


			$captured_resp = wp_remote_get( $capture_url, $capture_args ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions

			$existing_package = WFOCU_Core()->data->get( 'upsell_package', '', 'paypal' );
			WFOCU_Core()->data->set( '_upsell_package', $existing_package );

			if ( is_wp_error( $captured_resp ) ) {

				$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );
				WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Unable to capture paypal Order refer error below' . print_r( $captured_resp, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( isset( $data->details ) && is_array( $data->details ) && ! empty( $data->details[0]->issue ) ) {
					$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: ' . $data->details[0]->description, 'woofunnels-upstroke-one-click-upsell' ), '', $get_order );
				}

			} else {

				$retrived_body = wp_remote_retrieve_body( $captured_resp );

				$resp_body = json_decode( $retrived_body );


				if ( isset( $resp_body->status ) && 'COMPLETED' === $resp_body->status ) {
					if ( isset( $resp_body->payment_source->paypal->attributes->vault->id ) && isset( $resp_body->payment_source->paypal->attributes->vault->status ) && 'CREATED' === $resp_body->payment_source->paypal->attributes->vault->status ) {
						/*
						 * Successfully created vault token
						 * This token can be used in subsequent transactions to charge the buyer's PayPal account, than requiring them to identify and log in for every purchase.
						 */
						$txn_id = $resp_body->payment_source->paypal->attributes->vault->id;

						$get_order->update_meta_data( 'wfocu_ppcp_renewal_payment_token', $txn_id );
						$get_order->save_meta_data();
						WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': vault token created' );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r


					} else {
						$txn_id = $resp_body->purchase_units[0]->payments->captures[0]->id;
					}

					WFOCU_Core()->data->set( '_transaction_id', $txn_id );
					add_action( 'wfocu_db_event_row_created_' . WFOCU_DB_Track::OFFER_ACCEPTED_ACTION_ID, array( $this, 'add_order_id_as_meta' ) );
					add_action( 'wfocu_offer_new_order_created_' . $this->get_key(), array( $this, 'add_paypal_meta_in_new_order' ), 10, 2 );

					$this->payal_order_id = $paypal_order_id;
					$data                 = WFOCU_Core()->process_offer->_handle_upsell_charge( true );


				} elseif ( isset( $resp_body->details ) && is_array( $resp_body->details ) && ( 'ORDER_ALREADY_CAPTURED' === $resp_body->details[0]->issue ) ) {
					$get_offer            = WFOCU_Core()->offers->get_the_next_offer();
					$data                 = [];
					$data['redirect_url'] = WFOCU_Core()->public->get_the_upsell_url( $get_offer );

				} else {
					$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );
					WFOCU_Core()->log->log( 'Order #' . WFOCU_WC_Compatibility::get_order_id( $get_order ) . ': Unable to capture paypal Order refer error below' . print_r( $resp_body, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					if ( isset( $data->details ) && is_array( $data->details ) && ! empty( $data->details[0]->issue ) ) {
						$this->handle_api_error( esc_attr__( 'Offer payment failed. Reason: ' . $data->details[0]->description, 'woofunnels-upstroke-one-click-upsell' ), '', $get_order );
					}
				}
			}

			wp_redirect( $data['redirect_url'] );
			exit;


		}

		public function get_api_base( $mode ) {
			$live_url    = 'https://api-m.paypal.com/';
			$sandbox_url = 'https://api-m.sandbox.paypal.com/';

			// If mode is provided, return URL based on mode
			if ( ! empty( $mode ) ) {
				return ( 'live' === $mode ) ? $live_url : $sandbox_url;
			}

			// Check primary settings
			$ppcp_settings = $this->get_paypal_settings();
			if ( is_array( $ppcp_settings ) && count( $ppcp_settings ) > 0 && isset( $ppcp_settings['sandbox_on'] ) ) {
				return ( true !== $ppcp_settings['sandbox_on'] ) ? $live_url : $sandbox_url;
			}

			// Check secondary settings
			$ppcp_settings = $this->get_paypal_options();
			if ( is_array( $ppcp_settings ) && count( $ppcp_settings ) > 0 && isset( $ppcp_settings['sandbox_on'] ) ) {
				return ( true !== $ppcp_settings['sandbox_on'] ) ? $live_url : $sandbox_url;
			}

			// Default to live URL if no settings found
			return $live_url;
		}

		public function add_order_id_as_meta( $event ) {
			if ( ! empty( $this->payal_order_id ) ) {
				WFOCU_Core()->track->add_meta( $event, '_paypal_order_id', $this->payal_order_id );
			}
		}

		public function add_paypal_meta_in_new_order( $get_order ) {
			if ( ! empty( $this->payal_order_id ) ) {
				$get_order->update_meta_data( '_ppcp_paypal_order_id', $this->payal_order_id );
				$get_order->update_meta_data( '_ppcp_paypal_intent', 'CAPTURE' );
				$get_order->save_meta_data();
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

			$refund_data = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order_id    = WFOCU_WC_Compatibility::get_order_id( $order );
			$amount      = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
			$event_id    = isset( $refund_data['event_id'] ) ? $refund_data['event_id'] : '';
			$txn_id      = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
			$response    = false;

			if ( ! empty( $event_id ) && ! empty( $order_id ) && ! empty( $txn_id ) ) {
				if ( ! is_null( $amount ) ) {
					$environment = $order->get_meta( '_ppcp_paypal_payment_mode' );
					$api_url     = $this->get_api_base( $environment ) . 'v2/payments/captures/' . $txn_id . '/refund';


					$data      = array(
						'amount' => array(
							'currency_code' => $order->get_currency(),
							'value'         => (string) $this->round( $amount ),
						),
					);
					$arguments = array(
						'method'  => 'POST',
						'headers' => array(
							'Content-Type'                  => 'application/json',
							'Authorization'                 => 'Bearer ' . $this->get_bearer( $order ),
							'PayPal-Partner-Attribution-Id' => 'BWF_PPCP',
						),
						'body'    => wp_json_encode( $data ),
					);
					$resp      = wp_remote_post( $api_url, $arguments );
					if ( is_wp_error( $resp ) ) {
						return false;
					}

					$retrived_body = wp_remote_retrieve_body( $resp );

					$resp_body = json_decode( $retrived_body );
					if ( isset( $resp_body->status ) && 'COMPLETED' === $resp_body->status ) {
						return $resp_body->id;
					}
				}
			}

			return $response;
		}

		/**
		 * validate tax amount some time total of items and tax amount mismatch
		 *
		 * @param $offer_package
		 *
		 * @return float|int
		 */
		public function validate_tax( $offer_package ) {
			$tax = $this->round( $offer_package['taxes'] );

			$total_amount = (float) $offer_package['total'];
			$shipping     = ( isset( $offer_package['shipping'] ) && isset( $offer_package['shipping']['diff'] ) ) ? ( (string) $offer_package['shipping']['diff']['cost'] ) : 0;

			$item_total = 0;
			foreach ( $offer_package['products'] as $item ) {
				$item_total += $this->round( $item['price'] );

			};
			if ( $total_amount === ( $item_total + $tax ) ) {
				return $tax;
			}

			if ( $total_amount !== ( $item_total + $tax ) ) {
				$tax += $total_amount - ( $item_total + $tax + $this->round( $shipping ) );
			}
			if ( $tax < 0 ) {
				return $this->round( 0 );
			}

			return $this->round( $tax );
		}

		public function get_paypal_settings() {
			return get_option( 'woocommerce-ppcp-settings' );
		}

		public function get_paypal_options() {
			return get_option( 'woocommerce-ppcp-data-common' );
		}


	}

	WFOCU_Gateway_Integration_PayPal_Payments::get_instance();


}