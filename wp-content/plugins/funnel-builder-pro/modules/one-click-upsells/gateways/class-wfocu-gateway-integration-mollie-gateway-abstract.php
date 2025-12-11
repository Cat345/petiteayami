<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Gateway_Integration_Mollie_Gateway_Abstract' ) ) {
	/**
	 * Class WFOCU_Gateway_Integration_Mollie_Gateway_Abstract
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Mollie_Gateway_Abstract extends WFOCU_Gateway {
		public $token = false;
		protected static $ins = null;
		public $test_mode = false;
		public $new_order_collected = null;
		public $item_key;
		public $plugin_id = 'mollie-payments-for-woocommerce';
		public $create_payment_api = 'https://api.mollie.com/v2/payments';

		/**
		 * WFOCU_Gateway_Integration_Mollie_Gateway_Abstract constructor.
		 */
		public function __construct() {
			parent::__construct();
			//Modifying parent payment request data to create mandate
			add_filter( 'woocommerce_' . $this->key . '_args', array( $this, 'wfocu_mollie_modify_payment_args' ), 10, 2 );

			//Collecting newly created order when "Create new Order" option is enabled.
			add_action( 'wfocu_offer_new_order_created_' . $this->get_key(), array( $this, 'maybe_collect_new_order' ), 10, 1 );
			//Getting setting helper to find gateway mode

			if ( class_exists( 'WFOCU_Mollie_Helper_Compat' ) ) {

				$settings_helper = WFOCU_Mollie_Helper_Compat::get_settings_helper( WFOCU_Mollie_Helper::instance()->container );


				// Is test mode enabled?
				$this->test_mode = $settings_helper->isTestModeEnabled();
			}
			//Adding item key with item_id
			add_action( 'wfocu_items_batch_successful', array( $this, 'wfocu_items_batch_successful_add_item_key' ), 10, 1 );

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_mollie_customer_id_to_subscription' ), 10, 3 );

			//Copying _mollie_customer_id to copy in renewal offer for Subscriptions upsell
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_mollie_customer_id_keys_to_copy' ), 10, 2 );

			if ( class_exists( 'WFOCU_Core' ) && defined( 'WFOCU_VERSION' ) ) {
				$wfocu_gt_199 = version_compare( WFOCU_VERSION, '2.0', '>=' );

				if ( $wfocu_gt_199 ) {
					$this->refund_supported = true;
				}
			}

			if ( class_exists( 'WFOCU_Mollie_Helper_Compat' ) ) {
				add_action( WFOCU_Mollie_Helper_Compat::get_plugin_id( WFOCU_Mollie_Helper::instance()->container ) . '_payment_created', array( $this, 'wfocu_mollie_payment_created' ), 10, 2 );
			}
			/**Adding support for upstroke subscription addon **/
			add_filter( 'wfocu_subscriptions_get_supported_gateways', array( $this, 'enable_subscription_upsell_support' ), 10, 1 );
			if ( false === $this->is_sepa_enabled() && 'mollie_wc_gateway_creditcard' !== $this->get_key() ) {
				add_action( 'wfocu_footer_before_print_scripts', array( $this, 'maybe_render_in_offer_transaction_scripts' ), 999 );
				add_filter( 'wfocu_allow_ajax_actions_for_charge_setup', array( $this, 'allow_action' ) );
			}
		}

		/**
		 * Adding this gateway as Subscriptions upsell supported gateway
		 *
		 * @param $gateways
		 *
		 * @return array
		 */
		public function enable_subscription_upsell_support( $gateways ) {
			if ( is_array( $gateways ) ) {
				$gateways[] = $this->key;
			}

			return $gateways;
		}

		public function force_tokenize_by_passing_is_subs_active() {
			return true;
		}

		public function check_if_sepa_direct_debit_enabled() {
			add_filter( 'mollie_wc_subscription_plugin_active', array( $this, 'force_tokenize_by_passing_is_subs_active' ) );

			$test_mode = WFOCU_Mollie_Helper_Compat::get_settings_helper( WFOCU_Mollie_Helper::instance()->container )->isTestModeEnabled();

			$result = WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->getPaymentMethod( 'directdebit', $test_mode );
			remove_filter( 'mollie_wc_subscription_plugin_active', array( $this, 'force_tokenize_by_passing_is_subs_active' ) );

			if ( null !== $result ) {
				return true;
			}

			return false;
		}

		/**
		 * @param $data
		 * @param $order WC_Order
		 *
		 * @return mixed
		 */
		public function wfocu_mollie_modify_payment_args( $data, $order ) {
			if ( false === $this->is_enabled() ) {
				WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") gateway is not enabled in upsell settings." );

				return $data;
			}
			if ( 'mollie_wc_gateway_creditcard' !== $this->get_key() && false === $this->check_if_sepa_direct_debit_enabled() ) {
				WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") gateway enabled but sepa is disabled. " );

				return $data;
			}

			/**
			 * Finding customerID
			 */
			if ( is_user_logged_in() ) {
				$user        = wp_get_current_user();
				$customer_id = $user->mollie_customer_id;
			}
			if ( empty( $customer_id ) ) {
				$customer_id = WFOCU_WC_Compatibility::get_order_data( $order, '_mollie_customer_id' );
			}
			if ( empty( $customer_id ) ) {
				$customer_id = WFOCU_Mollie_Helper::wfocu_create_mollie_customer_for_order( $order );
			}

			/**
			 * If its an order call then we need to pass sequence type and customer id in 'payment' config
			 *
			 */
			if ( $this->is_order_call( $data ) ) {
				if ( ! isset( $data['payment'] ) ) {
					$data['payment'] = array();
				}
				$data['payment']['sequenceType'] = 'first';
				$data['payment']['customerId']   = $customer_id;
				WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") returning data its an order call after" . print_r( $data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $data;
			}

			/**
			 * Its just a fallback, because payment call will only comes when order payment fails
			 */
			if ( ! isset( $data['sequenceType'] ) || ( isset( $data['sequenceType'] ) && empty( $data['sequenceType'] ) ) ) {
				$data['sequenceType'] = 'first';
			}
			if ( ! isset( $data['customerId'] ) || ( isset( $data['customerId'] ) && empty( $data['customerId'] ) ) ) {
				$data['customerId'] = $customer_id;
			}
			if (isset($data['captureMode'])) {
				unset($data['captureMode']);
			}
			if (isset($data['captureDelay'])) {
				unset($data['captureDelay']);
			}
			if ( empty( WFOCU_WC_Compatibility::get_order_data( $order, '_mollie_customer_id' ) ) && ! empty( $customer_id ) ) {
				$order->update_meta_data( '_mollie_customer_id', $customer_id );
				$order->save_meta_data();
			}

			WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") filtered Data:" . print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return $data;

		}

		/**
		 * Check if its a payment API call or not
		 *
		 * @param array $data data going to be posted
		 *
		 * @return bool
		 */
		public function is_order_call( $data ) {

			if ( empty( $data ) || ! is_array( $data ) ) {
				return false;
			}

			return array_key_exists( 'orderNumber', $data );
		}

		/**
		 * @return WFOCU_Gateway_Integration_Mollie_Gateway_Abstract|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) {
			if ( true === $this->is_sepa_enabled() ) {
				if ( false === $this->is_enabled() ) {
					WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") gateway is not enabled in upsell settings, has_token() returned false" );

					return false;
				}

				$this->token = $this->get_token( $order );
				if ( ! empty( $this->token ) ) {
					WFOCU_Core()->log->log( "Mollie Token $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") (customer id) :" . print_r( $this->token, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					return true;
				}
				WFOCU_Core()->log->log( "Mollie Token $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") (customer id) is missing." . print_r( $this->token, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return false;
			}
		}

		public function is_run_without_token() {
			if ( false === $this->is_sepa_enabled() ) {
				return true;
			}

			return false;
		}

		/**
		 * Get token for Mollie payment processing with retry mechanism for mandate validation
		 * 
		 * This method checks for valid customer mandates with up to 3 retry attempts.
		 * It handles cases where mandates become available shortly after initial check.
		 * 
		 * @param WC_Order $order WooCommerce order object
		 * @return bool|mixed Returns true if valid mandate found, false otherwise
		 */
		public function get_token( $order ) {
			try {
				// Get customer ID from logged-in user or order meta
				if ( is_user_logged_in() ) {
					$user        = wp_get_current_user();
					$customer_id = $user->mollie_customer_id;
				}
				if ( empty( $customer_id ) ) {
					$customer_id = $order->get_meta( '_mollie_customer_id', true );
				}
				
				// Initialize retry mechanism variables
				$validMandate = false;  // Flag to track if valid mandate is found
				$max_retries  = 3;      // Maximum number of retry attempts
				$retry_count  = 0;      // Current retry attempt counter
				
				// Retry loop: continue until valid mandate found or max retries reached
				while ( ! $validMandate && $retry_count < $max_retries ) {
					try {
						// Fetch customer mandates from Mollie API
						$mandates = WFOCU_Mollie_Helper_Compat::get_api_client( WFOCU_Mollie_Helper::instance()->container, $this->test_mode )->customers->get( $customer_id )->mandates();
						
						// Check each mandate for valid status
						foreach ( $mandates as $mandate ) {
							if ( 'valid' === $mandate->status ) {
								$validMandate = true;  // Found a valid mandate
								break;                  // Exit loop once valid mandate is found
							}
						}
						
						// If no valid mandate found and we haven't reached max retries, wait and retry
						if ( ! $validMandate && $retry_count < $max_retries - 1 ) {
							WFOCU_Core()->log->log( "No valid mandate found for customer $customer_id, retrying in 1 second. Attempt " . ( $retry_count + 1 ) . " of $max_retries" );
							sleep( 1 );  // Wait 1 second before next attempt
						}
						
					} catch ( Mollie\Api\Exceptions\ApiException $e ) {
						// Log Mollie API exceptions
						WFOCU_Core()->log->log( "Mollie exception for $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") for customer id and valid mandate {$validMandate}: $customer_id: " . print_r( $e->getMessage(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						
						// If API exception occurs and we haven't reached max retries, wait and retry
						if ( $retry_count < $max_retries - 1 ) {
							WFOCU_Core()->log->log( "Retrying after Mollie API exception. Attempt " . ( $retry_count + 1 ) . " of $max_retries" );
							sleep( 1 );  // Wait 1 second before next attempt
						}
					}
					
					$retry_count++;  // Increment retry counter for next iteration
				}
				
				// Log final result with attempt count for debugging
				WFOCU_Core()->log->log( "Get Token for: $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . "), Customer id: $customer_id, Valid Mandate: $validMandate, Attempts: $retry_count" );
				$this->token = $validMandate;  // Store result in instance variable

				return $this->token;  // Return the final result
				
			} catch ( \Throwable $e ) {
				// Catch any unexpected errors and log them
				WFOCU_Core()->log->log( "Error in get_token method for $this->key: " . print_r( $e->getMessage(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				return false;  // Return false on any unexpected error
			}
		}

		/**
		 * Charging the card for which token is saved.
		 *
		 * @param WC_Order $order
		 *
		 * @return true
		 * @throws WC_Data_Exception
		 * @throws \Mollie\Api\Exceptions\ApiException
		 */
		public function process_charge( $order ) {
			if ( 'mollie_wc_gateway_creditcard' === $this->get_key() || true === $this->is_sepa_enabled() ) {

				$is_successful = true;

				$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
				$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;
				$get_package    = WFOCU_Core()->data->get( '_upsell_package' );

				$wfocu_public = WFOCU_Public::get_instance();

				$get_funnel_id      = WFOCU_Core()->data->get_funnel_id();
				$get_offer_id       = WFOCU_Core()->data->get_current_offer();
				$new_order          = null;
				$get_transaction_id = WFOCU_Core()->data->get( '_transaction_id' );

				try {
					if ( false === $is_batching_on ) {
						WFOCU_Core()->public->handle_new_order_creation_on_success( $get_transaction_id, $get_funnel_id, $get_offer_id, false );
						WFOCU_Core()->log->log( "New order Id created for $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") offer is: " . WFOCU_WC_Compatibility::get_order_id( $this->new_order_collected ) );
						remove_action( 'wfocu_front_create_new_order_on_success', array( WFOCU_Core()->public, 'handle_new_order_creation_on_success' ), 10 );

						$new_order = $this->new_order_collected;

						if ( false === $new_order || ! $new_order instanceof WC_Order ) {
							$is_successful = false;
							//@todo transaction failure to handle here
							WFOCU_Core()->log->log( "Unable to create $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") new order: " . print_r( $new_order, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						} else {
							$response           = $this->generate_mollie_charge( $new_order, $order );
							$get_transaction_id = isset( $response->id ) ? $response->id : $get_transaction_id;
						}
					} else { // Batching is on
						$response           = $this->generate_mollie_charge( $get_package, $order );
						$get_transaction_id = isset( $response->id ) ? $response->id : $get_transaction_id;
					}

					WFOCU_Core()->data->set( '_transaction_id', $get_transaction_id );
					WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") Transaction id is updated: " . print_r( $get_transaction_id, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					if ( ! in_array( $response->status, array( 'open', 'paid', 'pending' ), true ) ) {
						$is_successful = false;
						WFOCU_Core()->log->log( "Payment for offer " . print_r( $get_offer_id, true ) . " using mollie customer card is failed. Reason is below: " . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					} else {
						if ( empty( $response->status ) ) {
							$is_successful = false;
							WFOCU_Core()->log->log( "Acknowledge not received from mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") for offer " . print_r( $get_offer_id, true ) . ": Response is below: " . print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						} else {
							$is_successful = true;

							WFOCU_Core()->log->log( "$this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") payment is successful for offer $get_offer_id with status: " . print_r( $response->status, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							if ( in_array( $response->status, array( 'paid' ), true ) ) {
								/**
								 * Removing our action so that the funnel initiation will not trigger once WC_Order::payment_complete() hits
								 */
								remove_action( 'woocommerce_pre_payment_complete', array( $wfocu_public, 'maybe_setup_upsell' ), 99, 1 );

								if ( false === $is_batching_on ) {
									WFOCU_Core()->orders->payment_complete( $get_transaction_id, $new_order );

									$get_transaction_id = empty( $get_transaction_id ) ? WFOCU_Core()->data->get( '_transaction_id' ) : $get_transaction_id;

									$new_order->set_transaction_id( $get_transaction_id );
									$new_order->update_meta_data( '_mollie_order_id', $get_transaction_id );
									$new_order->update_meta_data( '_mollie_payment_id', $get_transaction_id );
									$new_order->save();
								}
							}
							do_action( 'wfocu_mollie_after_upsell_payment', $is_batching_on, $new_order, $response );

							if ( false === $is_batching_on ) {
								$order_note = sprintf( __( 'Upsell Offer Accepted | Offer ID: %s (Transaction ID: %s)', 'upstroke-woocommerce-one-click-upsell-mollie' ), $get_offer_id, $get_transaction_id );
								$order->add_order_note( $order_note );
							}
						}
					}

				} catch ( Exception $e ) {
					$is_successful = false;
					$order_note    = sprintf( __( 'Offer payment failed for offer ID %s. Reason: %s', 'upstroke-woocommerce-one-click-upsell-mollie' ), $get_offer_id, $e->getMessage() );
					$this->handle_api_error( $order_note, $order_note, $order );
				}

				if ( ! $is_successful && false === $is_batching_on ) {
					remove_action( 'wfocu_front_create_new_order_on_failure', array( $wfocu_public, 'handle_new_order_creation_on_failure' ) );
				}

				return $this->handle_result( $is_successful );

			} else {
				$post_data      = isset( $_POST ) ? $_POST : [];// @codingStandardsIgnoreLine
				$get_order      = WFOCU_Core()->data->get_parent_order();
				$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
				$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;
				$totals         = isset( $post_data['totals'] ) ? $post_data['totals'] : [];
				$total_price    = isset( $totals['total'] ) ? floatval( $totals['total'] ) : 0.0;
				if ( $total_price <= 0 ) {
					$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );
					wp_send_json( [ 'status' => false, 'redirect_url' => $data['redirect_url'] ] );
				}
				$offer_package = WFOCU_Core()->data->get( '_upsell_package' );
				$item_key      = md5( wp_json_encode( $offer_package ) );
				WFOCU_Core()->data->set( 'upsell_package', $offer_package, 'gateway' );
				WFOCU_Core()->data->save( 'gateway' );
				WFOCU_Core()->data->save();
				$api_key            = $this->get_mollie_api_key();
				$customer_id        = $this->get_user_mollie_customer_id( $order );
				$get_funnel_id      = WFOCU_Core()->data->get_funnel_id();
				$get_offer_id       = WFOCU_Core()->data->get_current_offer();
				$get_transaction_id = WFOCU_Core()->data->get( '_transaction_id' ) ?? '';
				if ( false === $is_batching_on ) {
					WFOCU_Core()->public->handle_new_order_creation_on_success( $get_transaction_id, $get_funnel_id, $get_offer_id, false );
					$new_order = $this->new_order_collected;
					$order_id  = $new_order->get_id();
					WFOCU_Core()->data->set( 'new_order_id', $new_order->get_id(), 'gateway' );
					WFOCU_Core()->data->save( 'gateway' );
					WFOCU_Core()->data->save();
					if ( ! $new_order || ! $new_order instanceof WC_Order ) {
						WFOCU_Core()->log->log( 'order not created: ' );
						$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );
						wp_send_json( [ 'status' => false, 'redirect_url' => $data['redirect_url'] ] );
					}
					$webhook_url = $this->getWebhookUrl( $new_order );
					foreach ( $new_order->get_items() as $item_id => $item ) {
						wc_add_order_item_meta( $item_id, '_wfocu_batch_offer_key', $item_key );
					}

				} else {
					$webhook_url = $this->getBatchWebhookUrl( $get_order, $item_key );
					$order_id    = $get_order->get_id();
					foreach ( $get_order->get_items() as $item_id => $item ) {
						wc_add_order_item_meta( $item_id, '_wfocu_batch_offer_key', $item_key );
					}
					$this->item_key = $item_key;
				}
				$description = sprintf(
					'Upsell Offer ID: %s, Order ID: %s',
					$get_offer_id,
					$order_id
				);

				$data = [
					'amount'      => [
						'currency' => get_woocommerce_currency(),
						'value'    => $this->format_currency_value( $total_price ),
					],
					'description' => $description,
					'redirectUrl' => add_query_arg( array( 'wfocu-si' => WFOCU_Core()->data->get_transient_key() ), WC()->api_request_url( 'wfocu_mollie_ideal_payments' ) ),
					'webhookUrl'  => $webhook_url,
					'method'      => str_replace('mollie_wc_gateway_', '', $this->key),
					'metadata'    => [ 'order_id' => $order_id ],
					'customerId'  => $customer_id,
				];
				WFOCU_Core()->log->log( 'Mollie Payment Data: ' . wp_json_encode( $data, true ) );

				$arguments = [
					'method'  => 'POST',
					'headers' => [
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $api_key,
					],
					'body'    => wp_json_encode( $data ),
				];
				try {
					$payment_object = $this->get_mollie_api_response_body( $this->create_payment_api, $arguments );
					WFOCU_Core()->log->log( 'Mollie API Response: ' . wp_json_encode( $payment_object, true ) );

					if ( $payment_object ) {
						$redirect_url   = $payment_object['_links']['checkout']['href'];
						$transaction_id = $payment_object['id'];
						if ( false === $is_batching_on ) {
							$get_order->update_meta_data( 'wfocu_ideal_batching_order_current', $transaction_id );
							$get_order->update_meta_data( '_wfocu_new_order_id', $new_order->get_id() );
							$get_order->save();
							$new_order->update_meta_data( 'wfocu_ideal_order_current', $transaction_id );
							$new_order->update_meta_data( '_mollie_order_id', $transaction_id );
							$new_order->update_meta_data( '_mollie_payment_id', $transaction_id );
							$new_order->save();
							$order_note = sprintf( __( 'Upsell Offer Accepted | Offer ID: %s (Transaction ID: %s)', 'upstroke-woocommerce-one-click-upsell-mollie' ), $get_offer_id, $transaction_id );
							$order->add_order_note( $order_note );
						} else {
							$get_order->update_meta_data( 'wfocu_ideal_order_current', $transaction_id );
							$get_order->save();
						}
						wp_send_json( [ 'status' => true, 'redirect_url' => $redirect_url ] );
					}
				} catch ( Exception $e ) {
					WFOCU_Core()->log->log( 'Error in Mollie API Request: ' . $e->getMessage() );
					$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );
					wp_send_json( [ 'status' => false, 'redirect_url' => $data['redirect_url'] ] );
				}

			}
		}

		/**
		 * @param $new_order WC_Order
		 * @param $order WC_Order
		 *
		 * @return \Mollie\Api\Resources\Payment
		 * @throws WFOCU_Payment_Gateway_Exception
		 */
		public function generate_mollie_charge( $new_order, $order ) {
			$get_package  = WFOCU_Core()->data->get( '_upsell_package' );
			$get_offer_id = WFOCU_Core()->data->get( 'current_offer' );
			$order_id     = WFOCU_WC_Compatibility::get_order_id( $order );

			if ( $new_order instanceof WC_Order ) {
				$order_id = WFOCU_WC_Compatibility::get_order_id( $new_order );

				$total       = WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->formatCurrencyValue( $new_order->get_total(), WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->getOrderCurrency( $new_order ) );
				$description = 'Offer Order for new order id:' . $order_id;
				$webhookurl  = $this->getWebhookUrl( $new_order );

			} else {
				$total          = WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->formatCurrencyValue( $get_package['total'], WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->getOrderCurrency( $order ) );
				$description    = "Offer id: $get_offer_id and order_id:" . $order_id;
				$item_key       = md5( wp_json_encode( $get_package ) );
				$webhookurl     = $this->getBatchWebhookUrl( $order, $item_key );
				$this->item_key = $item_key;
			}

			if ( is_user_logged_in() ) {
				$user        = wp_get_current_user();
				$customer_id = $user->mollie_customer_id;
			}
			if ( empty( $customer_id ) ) {
				$customer_id = $order->get_meta( '_mollie_customer_id', true );
			}

			$data                = array();
			$data['amount']      = array(
				'currency' => WFOCU_WC_Compatibility::get_order_currency( $order ),
				'value'    => $total,
			);
			$data['description'] = $description;
			$data['webhookUrl']  = $webhookurl;
			$data['metadata']    = array(
				'order_id' => $order_id
			);

			$data['customerId']   = $customer_id;
			$data['sequenceType'] = 'recurring';

			// Get all mandates for the customer ID
			try {
				WFOCU_Core()->log->log( "Try to get all mandates for renewal order $order_id with customer id: $customer_id" );
				$mandates     = WFOCU_Mollie_Helper_Compat::get_api_client( WFOCU_Mollie_Helper::instance()->container, $this->test_mode )->customers->get( $customer_id )->mandates();
				$validMandate = false;
				foreach ( $mandates as $mandate ) {
					if ( 'valid' === $mandate->status ) {
						$validMandate   = true;
						$data['method'] = $mandate->method;
						break;
					}
				}

			} catch ( Mollie\Api\Exceptions\ApiException $e ) {
				throw new WFOCU_Payment_Gateway_Exception( sprintf( esc_html__( "The customer (%s) could not be used or found in %s. ", 'upstroke-woocommerce-one-click-upsell-mollie' ), esc_html( $customer_id ), esc_html( $this->key ) ), 101, $this->get_key() );// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") Offer payment Request data:" . print_r( $data, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			// Check that there is at least one valid mandate
			try {
				if ( $validMandate ) {
					$payment = WFOCU_Mollie_Helper_Compat::get_api_client( WFOCU_Mollie_Helper::instance()->container, $this->test_mode )->payments->create( $data );
				} else {
					throw new WFOCU_Payment_Gateway_Exception( sprintf( esc_html__( "The customer (%s) does not have a valid mandate in %s. ", 'upstroke-woocommerce-one-click-upsell-mollie' ), esc_html( $customer_id ), esc_html( $this->key ) ), 101, $this->get_key() );// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
			} catch ( Mollie\Api\Exceptions\ApiException $e ) {
				throw new WFOCU_Payment_Gateway_Exception( esc_html( $e->getMessage() ), 101, $this->get_key() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			} catch ( Exception $e ) {
				throw new WFOCU_Payment_Gateway_Exception( esc_html( $e->getMessage() ), 101, $this->get_key() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			return $payment;

		}

		/**
		 * @param WC_Order $order
		 *
		 * @return string
		 */
		protected function getWebhookUrl( WC_Order $order ) {
			$site_url = get_site_url();

			$webhook_url = WC()->api_request_url( $this->key );
			$webhook_url = $this->removeTrailingSlashAfterParamater( $webhook_url );

			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$webhook_url = add_query_arg( array(
					'order_id' => WFOCU_WC_Compatibility::get_order_id( $order ),
					'key'      => $order->order_key,
				), $webhook_url );
			} else {
				$webhook_url = add_query_arg( array(
					'order_id' => $order->get_id(),
					'key'      => $order->get_order_key(),
				), $webhook_url );
			}
			$lang_url    = $this->getSiteUrlWithLanguage();
			$webhook_url = str_replace( $site_url, $lang_url, $webhook_url );

			// Some (multilingual) plugins will add a extra slash to the url (/nl//) causing the URL to redirect and lose it's data.
			// Status updates via webhook will therefor not be processed. The below regex will find and remove those double slashes.
			$webhook_url = preg_replace( '/([^:])(\/{2,})/', '$1/', $webhook_url );

			return apply_filters( WFOCU_Mollie_Helper_Compat::get_plugin_id( WFOCU_Mollie_Helper::instance()->container ) . '_webhook_url', $webhook_url, $order );
		}

		/**
		 * @param WC_Order $order
		 *
		 * @return mixed|void
		 */
		protected function getBatchWebhookUrl( WC_Order $order, $item_key ) {
			$site_url = get_site_url();

			$webhook_url = WC()->api_request_url( 'batch_' . $this->key );
			$webhook_url = $this->removeTrailingSlashAfterParamater( $webhook_url );

			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$webhook_url = add_query_arg( array(
					'order_id' => WFOCU_WC_Compatibility::get_order_id( $order ),
					'key'      => $order->order_key,
				), $webhook_url );
			} else {
				$webhook_url = add_query_arg( array(
					'order_id' => $order->get_id(),
					'key'      => $order->get_order_key(),
				), $webhook_url );
			}
			if ( ! empty( $item_key ) ) {
				$webhook_url = add_query_arg( array(
					'item_key' => $item_key,
				), $webhook_url );
			}

			$lang_url    = $this->getSiteUrlWithLanguage();
			$webhook_url = str_replace( $site_url, $lang_url, $webhook_url );

			// Some (multilingual) plugins will add a extra slash to the url (/nl//) causing the URL to redirect and lose it's data.
			// Status updates via webhook will therefor not be processed. The below regex will find and remove those double slashes.
			$webhook_url = preg_replace( '/([^:])(\/{2,})/', '$1/', $webhook_url );

			return apply_filters( WFOCU_Mollie_Helper_Compat::get_plugin_id( WFOCU_Mollie_Helper::instance()->container ) . '_batch_webhook_url', $webhook_url, $order );
		}

		/**
		 * Remove a trailing slash after a query string if there is one in the WooCommerce API request URL.
		 * For example WPML adds a query string with trailing slash like /?lang=de/ to WC()->api_request_url.
		 * This causes issues when we append to that URL with add_query_arg.
		 *
		 * @return string
		 */
		protected function removeTrailingSlashAfterParamater( $url ) {

			if ( strpos( $url, '?' ) ) {
				$url = untrailingslashit( $url );
			}

			return $url;
		}

		/**
		 * Check if any multi language plugins are enabled and return the correct site url.
		 *
		 * @return string
		 */
		protected function getSiteUrlWithLanguage() {
			/**
			 * function is_plugin_active() is not available. Lets include it to use it.
			 */
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			$site_url          = get_site_url();
			$polylang_fallback = false;

			if ( is_plugin_active( 'polylang/polylang.php' ) || is_plugin_active( 'polylang-pro/polylang.php' ) ) {

				$lang = PLL()->model->get_language( pll_current_language() );

				if ( empty ( $lang->search_url ) ) {
					$polylang_fallback = true;
				} else {
					$polylang_url = $lang->search_url;
					$site_url     = str_replace( $site_url, $polylang_url, $site_url );
				}
			}

			if ( true === $polylang_fallback || is_plugin_active( 'mlang/mlang.php' ) || is_plugin_active( 'mlanguage/mlanguage.php' ) ) {

				$slug = get_bloginfo( 'language' );
				$pos  = strpos( $slug, '-' );
				if ( $pos !== false ) {
					$slug = substr( $slug, 0, $pos );
				}
				$slug     = '/' . $slug;
				$site_url = str_replace( $site_url, $site_url . $slug, $site_url );

			}

			return $site_url;
		}

		/**
		 * @param $new_order
		 * @param $get_transaction_id
		 */
		public function maybe_collect_new_order( $new_order ) {
			$this->new_order_collected = $new_order;
		}

		/**
		 * Handling batchwebhook response from Mollie
		 */
		public function onBatchWebhookAction() {

			// Webhook test by Mollie
			if ( isset( $_GET['testByMollie'] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				WFOCU_Core()->log->log( "Webhook tested by $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . "): " . print_r( $this->key, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return;
			}
			if ( empty( $_GET['order_id'] ) || empty( $_GET['key'] ) || empty( $_GET['item_key'] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				WFOCU_Mollie_Helper_Compat::setHttpReponseCode( WFOCU_Mollie_Helper::instance()->container, 400 );
				WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . "): No order ID or order key or order item_key provided for: " . print_r( $this->key, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return;
			}

			$order_id = sanitize_text_field( $_GET['order_id'] );// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key      = sanitize_text_field( $_GET['key'] );// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$item_key = sanitize_text_field( $_GET['item_key'] );// phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$data_helper = WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container );
			$order       = wc_get_order( $order_id );

			if ( ! $order ) {
				WFOCU_Mollie_Helper_Compat::setHttpReponseCode( WFOCU_Mollie_Helper::instance()->container, 404 );
				WFOCU_Core()->log->log( "$this->key: Could not find order $order_id" );

				return;
			}

			if ( ! $order->key_is_valid( $key ) ) {
				WFOCU_Mollie_Helper_Compat::setHttpReponseCode( WFOCU_Mollie_Helper::instance()->container, 401 );
				WFOCU_Core()->log->log( "$this->key (" . ( $this->test_mode ? 'test' : 'live' ) . "): Invalid key $key for order $order_id." );

				return;
			}

			// No Mollie payment id provided
			if ( empty( $_POST['id'] ) ) {// @codingStandardsIgnoreLine
				WFOCU_Mollie_Helper_Compat::setHttpReponseCode( WFOCU_Mollie_Helper::instance()->container, 400 );
				WFOCU_Core()->log->log( "$this->key (" . ( $this->test_mode ? 'test' : 'live' ) . "): No payment ID is provided." );

				return;
			}

			$payment_id = sanitize_text_field( $_POST['id'] );// @codingStandardsIgnoreLine
			$test_mode  = $data_helper->getActiveMolliePaymentMode( $order_id ) === 'test';
			$api_key = $data_helper->getApiKey($test_mode);

			// Load the payment from Mollie, do not use cache
			$payment = $data_helper->getPayment( $payment_id, $api_key, $use_cache = false );

			// Payment not found
			if ( ! $payment ) {
				WFOCU_Mollie_Helper_Compat::setHttpReponseCode( WFOCU_Mollie_Helper::instance()->container, 404 );
				WFOCU_Core()->log->log( "$this->key (" . ( $this->test_mode ? 'test' : 'live' ) . "): payment not found for payment id: " . print_r( $payment_id, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return;
			}

			$order_items = $order->get_items();

			foreach ( is_array( $order_items ) ? $order_items : array() as $order_item_key => $order_Item ) {
				$batch_item_key = wc_get_order_item_meta( $order_item_key, '_wfocu_batch_offer_key', true );
				if ( $batch_item_key === $item_key ) {
					switch ( $payment->status ) {
						case 'paid':
							$order->add_order_note( __( 'Payment completed for order item: ' . $order_Item->get_name(), 'upstroke-woocommerce-one-click-upsell-mollie' ) );
							break;

						case 'failed':
							$order->add_order_note( __( 'Payment failed for order item: ' . $order_Item->get_name(), 'upstroke-woocommerce-one-click-upsell-mollie' ) );
							break;

						case 'expired':
							$order->add_order_note( __( 'Payment expired for order item: ' . $order_Item->get_name(), 'upstroke-woocommerce-one-click-upsell-mollie' ) );
							break;

						case 'cancelled':
							$order->add_order_note( __( 'Payment cancelled for order item: ' . $order_Item->get_name(), 'upstroke-woocommerce-one-click-upsell-mollie' ) );
							break;
					}
					break;
				}
			}
		}

		/**
		 * @param $items_added
		 *
		 * @throws Exception
		 */
		public function wfocu_items_batch_successful_add_item_key( $items_added ) {
			foreach ( is_array( $items_added ) ? $items_added : array() as $item_id ) {
				wc_add_order_item_meta( $item_id, '_wfocu_batch_offer_key', $this->item_key );
			}
		}

		/**
		 * @param WC_Subscription $subscription
		 * @param $key
		 * @param WC_Order $order
		 */
		public function save_mollie_customer_id_to_subscription( $subscription, $key, $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$get_customer_id = $order->get_meta( '_mollie_customer_id', true );

			if ( ! empty( $get_customer_id ) ) {
				$subscription->update_meta_data( '_mollie_customer_id', $get_customer_id );
				$subscription->save();
			}
		}

		/**
		 * Adding keys to copy to renewal orders
		 *
		 * @param $meta_keys
		 *
		 * @return mixed
		 */
		public function set_mollie_customer_id_keys_to_copy( $meta_keys, $order = null ) {

			if ( $order instanceof WC_Order ) {
				$payment_method = $order->get_payment_method();
				if ( $payment_method === $this->key ) {
					array_push( $meta_keys, '_mollie_customer_id', '_mollie_payment_mode' );
				}
			} else {
				array_push( $meta_keys, '_mollie_customer_id', '_mollie_payment_mode' );
			}


			return $meta_keys;
		}

		/**
		 * Handling refund offer
		 *
		 * @param $order
		 *
		 * @return bool
		 */
		public function process_refund_offer( $order ) {
			// @codingStandardsIgnoreLine
			$refund_data = $_POST;
			$txn_id      = isset( $refund_data['txn_id'] ) ? $refund_data['txn_id'] : '';
			$amount      = isset( $refund_data['amt'] ) ? $refund_data['amt'] : '';
			$offer_id    = isset( $refund_data['offer_id'] ) ? $refund_data['offer_id'] : '';

			$order_id = WFOCU_WC_Compatibility::get_order_id( $order );

			$reason = __( " - Reason: refunded offer ID: $offer_id , Transaction ID: $txn_id and amount: $amount", 'upstroke-woocommerce-one-click-upsell-mollie' );

			try {
				$order = wc_get_order( $order_id );

				if ( ! $order ) {
					WFOCU_Core()->log->log( "$this->key could not find order $order_id for refund." );

					return false;
				}

				$payment_object = WFOCU_Mollie_Helper_Compat::get_payment_object( WFOCU_Mollie_Helper::instance()->container )->getActiveMolliePayment( $order_id );

				if ( ! $payment_object ) {
					WFOCU_Core()->log->log( "$this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") could not find mollie payment for order $order_id to refund." );

					return false;
				}

				if ( ! isset( $payment_object->amount ) ) {
					$payment_object->amount = new stdClass();
				}

				if ( ! isset( $payment_object->settlementAmount ) ) {
					$payment_object->settlementAmount = new stdClass();
				}

				if ( ! isset( $payment_object->amountRefunded ) ) {
					$payment_object->amountRefunded = new stdClass();
				}

				if ( ! isset( $payment_object->amountRemaining ) ) {
					$payment_object->amountRemaining = new stdClass();
				}

				if ( ! isset( $payment_object->metadata ) ) {
					$payment_object->metadata = new stdClass();
				}

				$payment_object->metadata->order_id = $order_id;

				$payment_object->id                      = $txn_id;
				$payment_object->amount->value           = $amount;
				$payment_object->settlementAmount->value = $amount;
				$payment_object->amountRefunded->value   = 0;
				$payment_object->amountRemaining->value  = $amount;
				$payment_object->status                  = $this->get_payment_status( $txn_id );

				// Is test mode enabled?
				$test_mode = WFOCU_Mollie_Helper_Compat::get_settings_helper( WFOCU_Mollie_Helper::instance()->container )->isTestModeEnabled();

				// Send refund to Mollie
				$refund = WFOCU_Mollie_Helper_Compat::get_api_client( WFOCU_Mollie_Helper::instance()->container, $test_mode )->payments->refund( $payment_object, array(
					'amount'      => array(
						'currency' => WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->getOrderCurrency( $order ),
						'value'    => WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->formatCurrencyValue( $amount, WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->getOrderCurrency( $order ) )
					),
					'description' => $reason
				) );

				WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") refund Offer refunded response: " . print_r( $refund, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$order->add_order_note( sprintf( /* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */ __( 'Refunded %s%s%s - Payment: %s, Refund: %s', 'upstroke-woocommerce-one-click-upsell-mollie' ), WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container )->getOrderCurrency( $order ), $amount, ( ! empty( $reason ) ? ' (reason: ' . $reason . ')' : '' ), $refund->paymentId, $refund->id ) );

				return $refund->id;
			} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
				WFOCU_Core()->log->log( "$this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") Offer refund error response: " . print_r( $e->getMessage(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return false;
			}
		}

		/**
		 *  Creating transaction text/URL
		 *
		 * @param $transaction_id
		 * @param $order_id
		 *
		 * @return string
		 * @since 1.7
		 */
		public function get_transaction_link( $transaction_id, $order_id ) {

			$order    = wc_get_order( $order_id );
			$resource = ( $order->get_meta( '_mollie_order_id', true ) ) ? 'payments' : 'orders';

			if ( empty( $resource ) ) {
				$resource = ( get_post_meta( $order_id, '_mollie_order_id', true ) ) ? 'payments' : 'orders';
			}

			$view_transaction_url = 'https://www.mollie.com/dashboard/' . $resource . '/' . $transaction_id;

			if ( ! empty( $view_transaction_url ) && ! empty( $transaction_id ) ) {
				$transaction_url = sprintf( '<a target="_blank" href="%s">%s</a>', $view_transaction_url, $transaction_id );

				return $transaction_url;
			}

			return $transaction_id;

		}

		/**
		 * Providing refund button html for admin order edit page
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

			if ( ! $refunded ) {
				$payment_status = $this->get_payment_status( $transaction_id );

				if ( 'paid' !== $payment_status ) {
					$button_text  = __( 'Pending Payment', 'woofunnels-upstroke-one-click-upsell' );
					$button_class = 'disabled';

					$button_html = sprintf( '<a href="javascript:void(0);" data-funnel_id="%s" data-offer_id="%s" data-amount="%s" data-txn="%s" class="button %s">%s</a>', $funnel_id, $offer_id, $total_charge, $transaction_id, $button_class, $button_text );

					return $button_html;
				}
			}

			return parent::get_refund_button_html( $funnel_id, $offer_id, $total_charge, $transaction_id, $refunded, $event_id );
		}

		/**
		 * Get payment status from Mollie API
		 *
		 * @param $transaction_id
		 *
		 * @return bool|string
		 */
		public function get_payment_status( $transaction_id ) {
			$test_mode = WFOCU_Mollie_Helper_Compat::get_settings_helper( WFOCU_Mollie_Helper::instance()->container )->isTestModeEnabled();
			try {
				$payment = WFOCU_Mollie_Helper_Compat::get_api_client( WFOCU_Mollie_Helper::instance()->container, $test_mode )->payments->get( $transaction_id );
				WFOCU_Core()->log->log( "$this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") API Payment method status for transaction id: $transaction_id: " . print_r( $payment->status, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $payment->status;
			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( "$this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") API Payment method status error: " . print_r( $e->getMessage(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return false;
		}

		/**
		 * @param WC_Order $order
		 *
		 * @return string
		 */
		public function getReturnUrl( $return_url, WC_Order $order ) {
			if ( false === $this->is_enabled() ) {
				return '';
			}

			$return_url = WC()->api_request_url( 'wfocu_mollie_return' );
			$return_url = $this->removeTrailingSlashAfterParamater( $return_url );

			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$return_url = add_query_arg( array(
					'order_id' => WFOCU_WC_Compatibility::get_order_id( $order ),
					'key'      => $order->order_key,
				), $return_url );
			} else {
				$return_url = add_query_arg( array(
					'order_id' => $order->get_id(),
					'key'      => $order->get_order_key(),
				), $return_url );
			}


			return $return_url;
		}

		/**
		 * @param $payment_object
		 * @param WC_Order $order
		 */
		public function wfocu_mollie_payment_created( $payment_object, $order ) {
			if ( false === $this->is_enabled() ) {
				return;
			}

			if ( WFOCU_Core()->data->is_funnel_exists() && $this->get_key() === $order->get_payment_method() ) {
				WFOCU_Core()->public->maybe_setup_funnel_options();
				$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
				$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;
				if ( $is_batching_on ) {
					$this->lock_webhook_receival( $order );
				}
			}
		}

		/**
		 * @param $order WC_Order
		 */
		public function lock_webhook_receival( $order ) {
			set_transient( 'wfocu_hold_ipn_mollie_' . $order->get_id(), 'yes', 5 * ( MINUTE_IN_SECONDS ) );

			$order->update_meta_data( '_wfocu_mollie_hold_ipn', 'yes' );
			$order->save_meta_data();
		}

		/**
		 * @param $order WC_Order
		 */
		public function unlock_webhook_receival( $order ) {
			delete_transient( 'wfocu_hold_ipn_mollie_' . $order->get_id() );
			$order->delete_meta_data( '_wfocu_mollie_hold_ipn' );
			$order->save_meta_data();
		}


		public function onWebhookAction() {
			if ( false === $this->is_enabled() ) {
				return;
			}
			// Webhook test by Mollie
			if ( isset( $_GET['testByMollie'] ) ) {// @codingStandardsIgnoreLine
				WFOCU_Core()->log->log( "Webhook tested by $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . ") " );

				return;
			}

			if ( ! empty( $_GET['order_id'] ) && ! empty( $_GET['key'] ) ) {// @codingStandardsIgnoreLine
				$order_id    = sanitize_text_field( $_GET['order_id'] );// @codingStandardsIgnoreLine
				$data_helper = WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container );// @codingStandardsIgnoreLine
				$order       = wc_get_order( $order_id );

				if ( ! $order instanceof WC_Order ) {
					WFOCU_Mollie_Helper_Compat::setHttpReponseCode( WFOCU_Mollie_Helper::instance()->container, 400 );
					WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . "): No order ID or order key or order item_key provided for: " . print_r( $this->key, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					return;
				}

				$get_meta                        = $order->get_meta( '_wfocu_mollie_hold_ipn', true );
				$get_transient                   = get_transient( 'wfocu_hold_ipn_mollie_' . $order->get_id() );
				$funnel_id_in_meta               = $order->get_meta( '_wfocu_funnel_id', true );
				$order_id_or_payment_id          = sanitize_text_field( $_POST['id'] );// @codingStandardsIgnoreLine
				$get_mollie_order_id_from_meta   = $order->get_meta( '_mollie_order_id', true );
				$get_mollie_payment_id_from_meta = $order->get_meta( '_mollie_payment_id', true );

				/**
				 * Only prevent IPN when we have certain checks met
				 * 1. We have our flag set against the order to hold IPN
				 * 2. We either have funnel ID in the meta or the transient for the 5 minutes, it ensure that we never block IPN infinitely
				 * 3. When Payment ID in the posted data is same we have in the order, excluding cases of other expired payment's notifications
				 */
				if ( 'yes' === $get_meta && ( ! empty( $get_transient ) || ! empty( $funnel_id_in_meta ) ) && ( $order_id_or_payment_id === $get_mollie_order_id_from_meta || $order_id_or_payment_id === $get_mollie_payment_id_from_meta ) ) {

					WFOCU_Mollie_Helper_Compat::setHttpReponseCode( WFOCU_Mollie_Helper::instance()->container, 400 );
					WFOCU_Core()->log->log( "Mollie $this->key (" . ( $this->test_mode ? 'test' : 'live' ) . "): Meta set to restrict IPN for gateway $order_id with key: " . print_r( $this->key, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					/**
					 * We are ready to prevent the IPN but we need to check here if this could be the case of user never returning back to the site
					 * in this case no primary order will be moved and hence no schedule action will run
					 * We need to move to the status now, so that we could fire delayed webhook
					 */

					if ( $order->get_status() !== 'wfocu-pri-order' && ! in_array( $order->get_status(), wc_get_is_paid_statuses(), true ) ) {
						WFOCU_Core()->orders->maybe_set_funnel_running_status( $order );
					}
				} else {
					WFOCU_Mollie_Helper_Compat::onWebHookAction( WFOCU_Mollie_Helper::instance()->container, $this->get_wc_gateway() );

				}
				die();
			}
		}

		/**
		 * @param WC_Order $order
		 */
		public function onWebhookActionDelayed( $order, $paymentObjectID ) {


			// No Mollie payment id provided
			if ( empty( $paymentObjectID ) ) {

				WFOCU_Core()->log->log( __METHOD__ . ': No payment object ID provided.' );

				return;
			}

			$order_id = WFOCU_WC_Compatibility::get_order_id( $order );

			$payment_object_id = $paymentObjectID;
			$data_helper       = WFOCU_Mollie_Helper_Compat::get_data_helper( WFOCU_Mollie_Helper::instance()->container );
			$test_mode         = $data_helper->getActiveMolliePaymentMode( $order_id ) === 'test';

			// Load the payment from Mollie, do not use cache
			$payment_object = WFOCU_Mollie_Helper_Compat::get_payment_factory( WFOCU_Mollie_Helper::instance()->container )->getPaymentObject( $payment_object_id );
			if ( method_exists( $payment_object, 'data' ) ) {
				$payment_data = $payment_object->data();
			} else {
				$payment_data = $payment_object->data;
			}
			$payment = $payment_object->getPaymentObject( $payment_data, $test_mode, false );
			WFOCU_Core()->log->log( $this->get_key() . ": Mollie payment object with payment id: {$payment->id} (" . $payment->mode . ") webhook call for order $order_id: " . print_r( $payment_object, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			// Payment not found
			if ( ! $payment ) {

				WFOCU_Core()->log->log( __METHOD__ . ": payment object $payment_object_id not found." );

				return;
			}

			if ( absint( $order_id ) !== absint( $payment->metadata->order_id ) ) {
				WFOCU_Core()->log->log( __METHOD__ . ": Order ID does not match order_id in payment metadata. Payment ID {$payment->id}, order ID $order_id" );

				return;
			}

			add_filter( 'woocommerce_valid_order_statuses_for_payment', function ( $order_status = [] ) {
				array_push( $order_status, 'wfocu-pri-order' );
				WFOCU_Core()->log->log( "Modified order statuses are: " . print_r( $order_status, true ) );  //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $order_status;
			} );

			add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', function ( $order_status = [] ) {
				array_push( $order_status, 'wfocu-pri-order' );
				WFOCU_Core()->log->log( "Modified (woocommerce_valid_order_statuses_for_payment_complete) order statuses are: " . print_r( $order_status, true ) );  //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $order_status;
			} );

			// Order does not need a payment
			if ( ! $order->needs_payment() ) {
				WFOCU_Core()->log->log( __METHOD__ . ": Order $order_id don\'t needs payment" );

				return;
			}

			// Get payment method title
			$payment_method_title = $this->get_wc_gateway()->method_title;

			// Create the method name based on the payment status
			$method_name = 'onWebhook' . ucfirst( $payment->status );
			remove_action( 'woocommerce_pre_payment_complete', [ WFOCU_Core()->public, 'maybe_setup_upsell' ], 99 );

			if ( method_exists( $payment_object, $method_name ) ) {
				WFOCU_Core()->log->log( "Mollie: method $method_name exists for order id: $order_id" );
				$payment_object->{$method_name}( $order, $payment, $payment_method_title );
			} else {
				WFOCU_Core()->log->log( "Mollie: method $method_name doesn\'t exist for order id: $order_id" );
				$order->add_order_note( sprintf( /* translators: Placeholder 1: payment method title, placeholder 2: payment status, placeholder 3: payment ID */ __( '%s payment %s (%s), not processed.', 'mollie-payments-for-woocommerce' ), $this->get_wc_gateway()->method_title, $payment->status, $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' ) ) );
			}
		}

		/**
		 * @return bool
		 */
		protected function isOrderPaidAndProcessed( WC_Order $order ) {
			$paid_and_processed = $order->get_meta( '_mollie_paid_and_processed', true );

			return $paid_and_processed;
		}

		public function maybe_render_in_offer_transaction_scripts() {
			$order = WFOCU_Core()->data->get_current_order();

			if ( ! $order instanceof WC_Order || $this->get_key() !== $order->get_payment_method() ) {
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

                                var postData = $.extend(getBucketData, {action: 'wfocu_front_charge'});


                                if (typeof wfocu_vars.wc_ajax_url !== "undefined") {
                                    var action = $.post(wfocu_vars.wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_front_charge'), postData);

                                } else {
                                    var action = $.post(wfocu_vars.ajax_url, postData);

                                }

                                action.done(function (data) {

                                    if (data.status === true) {
                                        window.location = data.redirect_url;
                                    } else if (data.status === false) {
                                        Bucket.swal.show({'text': wfocu_vars.messages.offer_msg_pop_failure, 'type': 'warning'});
                                        window.location = wfocu_vars.redirect_url + '&ec=payment_failed';
                                    }
                                    if (data.msg === true) {
                                        Bucket.swal.show({
                                            'text': wfocu_vars.messages.offer_success_message_pop,
                                            'type': 'success'
                                        });

                                        setTimeout(function () {
                                            window.location = wfocu_vars.order_received_url;
                                        }, 1500);
                                    } else if (data.msg === false) {
                                        Bucket.swal.show({
                                            'text': wfocu_vars.messages.offer_msg_pop_failure,
                                            'type': 'warning'
                                        });
                                        console.error('Verification failed, redirecting to fallback page.');

                                        window.location = wfocu_vars.order_received_url + '&ec=payment_failed';
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
            </script>
			<?php
		}

		public function is_sepa_enabled() {
			$is_reference_transaction_on = WFOCU_Core()->data->get_option( 'sepa_gateway_trans' );
			if ( 'yes' === $is_reference_transaction_on ) {

				return true;
			}

			return false;
		}

		/**
		 * Get Mollie customer ID associated with the order.
		 *
		 * @param WC_Order $order WooCommerce order object.
		 *
		 * @return string|null Customer ID or null if not found.
		 */
		private function get_user_mollie_customer_id( $order ) {
			$customer_id = $order->get_meta( '_mollie_customer_id', true );

			if ( ! $customer_id ) {
				// If not found in the order, try retrieving from the WooCommerce customer.
				$customer    = new WC_Customer( $order->get_user_id() );
				$customer_id = $customer->get_meta( 'mollie_customer_id' );
			}

			return $customer_id;
		}

		/**
		 * Format currency value to Mollie's expected format.
		 *
		 * @param float $value Amount to format.
		 * @param string $currency Currency code (e.g., "EUR").
		 *
		 * @return string Formatted currency value.
		 */
		private function format_currency_value( $value ) {
			return number_format( (float) $value, 2, '.', '' );
		}

		/**
		 * Get the response body from Mollie API.
		 *
		 * @param string $url Mollie API endpoint URL.
		 * @param array $arguments Request arguments.
		 *
		 * @return array|null Decoded response body or null on failure.
		 * @throws Exception When the API request fails.
		 */
		private function get_mollie_api_response_body( $url, $arguments ) {
			$response = wp_remote_request( $url, $arguments );

			if ( is_wp_error( $response ) ) {
				throw new Exception( esc_html( $response->get_error_message() ) );
			}

			$body = wp_remote_retrieve_body( $response );

			return json_decode( $body, true );
		}

		public function get_mollie_api_key() {

			$is_live_mode = 'yes' === get_option( $this->plugin_id . '_test_mode_enabled' ) ? false : true;

			if ( $is_live_mode ) {
				$api_key = get_option( $this->plugin_id . '_live_api_key' );
			} else {
				$api_key = get_option( $this->plugin_id . '_test_api_key' );
			}

			return $api_key;
		}

		public function allow_action( $actions ) {
			array_push( $actions, 'wfocu_front_charge' );

			return $actions;
		}

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

			$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
			$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;
			if ( $is_batching_on ) {
				$ideal_transaction_id = $get_order->get_meta( 'wfocu_ideal_order_current' );
				WFOCU_Core()->log->log( 'batching ideal_transaction_id  handle api calls ' . $ideal_transaction_id );
			} else {
				$ideal_transaction_id = $get_order->get_meta( 'wfocu_ideal_batching_order_current' );
				WFOCU_Core()->log->log( 'non batching ideal_transaction_id  handle api calls ' . $ideal_transaction_id );
			}


			WFOCU_Core()->data->set( '_transaction_id', $ideal_transaction_id );

			$wfocu_public = WFOCU_Public::get_instance();

			$existing_package = WFOCU_Core()->data->get( 'upsell_package', '', 'gateway' );
			WFOCU_Core()->data->set( '_upsell_package', $existing_package );

			$api_url               = $this->create_payment_api . '/' . $ideal_transaction_id;
			$api_key               = $this->get_mollie_api_key();
			$arguments             = [
				'method'  => 'POST',
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				]
			];
			$verify_payment_object = $this->get_mollie_api_response_body( $api_url, $arguments );
			if ( isset( $verify_payment_object['status'] ) && $verify_payment_object['status'] === 'pending' || $verify_payment_object['status'] === 'authorized' || $verify_payment_object['status'] === 'paid' || $verify_payment_object['status'] === 'open' ) {
				if ( $is_batching_on ) {
					$data = WFOCU_Core()->process_offer->_handle_upsell_charge( true );
				} else if ( false === $is_batching_on ) {
					$new_order_id = WFOCU_Core()->data->get( 'new_order_id', '', 'gateway' );
					WFOCU_Core()->log->log( 'new order id : ' . $new_order_id );
					$new_order      = wc_get_order( $new_order_id );
					$new_order_data = $new_order->get_data();
					WFOCU_Core()->log->log( 'New order data: ' . wp_json_encode( $new_order_data ) );
					$wfocu_public->new_order = $new_order;
					remove_action( 'wfocu_front_create_new_order_on_success', array( WFOCU_Core()->public, 'handle_new_order_creation_on_success' ), 10 );
					WFOCU_Core()->orders->payment_complete( $ideal_transaction_id, $new_order );
					$data = WFOCU_Core()->process_offer->_handle_upsell_charge( true );

				}
			} else {
				WFOCU_Core()->log->log( 'Payment status is not paid, handling failure.' );
				if ( false === $is_batching_on ) {
					remove_action( 'wfocu_front_create_new_order_on_failure', array( $wfocu_public, 'handle_new_order_creation_on_failure' ) );
				}
				$data = WFOCU_Core()->process_offer->_handle_upsell_charge( false );
			}

			wp_redirect( $data['redirect_url'] );
			exit;
		}

	}
}
