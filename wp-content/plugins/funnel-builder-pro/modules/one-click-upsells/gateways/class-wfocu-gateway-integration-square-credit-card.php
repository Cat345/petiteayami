<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SkyVerge\WooCommerce\PluginFramework\v5_4_0 as Framework;
use Square\Environment;
use Square\SquareClient;
use SquareConnect\Api\OrdersApi;
use SquareConnect\Api\TransactionsApi;
use SquareConnect\Model;
use SquareConnect\Model\Address;
use SquareConnect\Model\ChargeRequest;


use WooCommerce\Square\Framework\Compatibility\Order_Compatibility;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Utilities\Money_Utility;
use WooCommerce\Square\Gateway\Customer_Helper;
if ( ! class_exists( 'WFOCU_Gateway_Integration_Square_Credit_Card' ) ) {
	/**
	 * Class WFOCU_Gateway_Integration_Square_Credit_Card
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_Square_Credit_Card extends WFOCU_Gateway {
		protected static $ins = null;
		public $key = 'square_credit_card';
		public $token = false;
		public $apiConfig;
		public $access_token = '';
		public $location_id = '';
		public $apiClient = '';
		public $isGuestTokenCall = '';

		/**
		 * WFOCU_Square_Gateway_Credit_Cards constructor.
		 */
		public function __construct() {
			parent::__construct();

			add_filter( 'wc_' . $this->key . '_payment_form_tokenization_forced', [ $this, 'wfocu_square_enable_force_tokenization' ], 10 );

			add_filter( 'wc_payment_gateway_' . $this->key . '_get_order', [ $this, 'square_get_order' ], 10 );
			add_filter( 'wc_payment_gateway_' . $this->key . '_process_payment', [ $this, 'add_square_token' ], 10, 3 );

			add_filter( 'wfocu_subscriptions_get_supported_gateways', array( $this, 'enable_subscription_upsell_support' ), 10, 1 );

			//Copying _wc_square_credit_card_payment_token in renewal offer for Subscriptions upsell
			add_filter( 'wfocu_order_copy_meta_keys', array( $this, 'set_square_payment_token_keys_to_copy' ), 10, 2 );

			add_action( 'wfocu_subscription_created_for_upsell', array( $this, 'save_square_payment_token_to_subscription' ), 10, 3 );

			add_action( 'wcs_create_subscription', [ $this, 'wfcou_square_update_token_in_user_meta' ], 10, 1 );;
		}

		/**
		 * @return WFOCU_Gateway_Integration_Square_Credit_Card|null
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
		 * @return bool|true
		 */
		public function has_token( $order ) {
			$this->token = $order->get_meta( '_wc_square_credit_card_payment_token', true );

			if ( empty( $this->token ) ) {
				$this->token = WFOCU_Common::get_order_meta( $order, '_wc_square_credit_card_payment_token' );
			}
			WFOCU_Core()->log->log( "WFOCU Square: Token is: {$this->token} " );

			if ( ! empty( $this->token ) && $this->is_enabled( $order ) && ( $this->get_key() === $order->get_payment_method() ) ) {
				return true;
			}
			WFOCU_Core()->log->log( "WFOCU Square: Square token is missing or invalid gateway. {$this->token}, config:" . print_r( $this->apiConfig, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			WFOCU_Core()->log->log( "WFOCU Square: Access Token is: {$this->access_token}, ApiClient: " . print_r( $this->apiClient, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return false;
		}

		/**
		 * Charging the card for which token is saved.
		 *
		 * @param WC_Order $order
		 *
		 * @return true
		 */
		public function process_charge( $order ) {
			$is_successful = false;
			$get_offer_id  = WFOCU_Core()->data->get( 'current_offer' );
			$order_id      = WFOCU_WC_Compatibility::get_order_id( $order );

			$result = $this->generate_square_charge( $order, $get_offer_id );

			if ( isset( $result['failed'] ) && $result['failed'] ) {
				WFOCU_Core()->log->log( "WFOCU Square: Order id: #$order_id Payment for offer $get_offer_id using square credit card failed." );
			}

			$response = isset( $result['response'] ) ? $result['response'] : false;

			if ( true === $response ) {
				$is_successful = true;
				if ( isset( $result['transaction_id'] ) ) {
					WFOCU_Core()->data->set( '_transaction_id', $result['transaction_id'] );
				}
				WFOCU_Core()->log->log( "WFOCU Square: Payment for offer $get_offer_id using Square credit card is successful with transaction id: {$result['transaction_id']}." );
			}

			return $this->handle_result( $is_successful );
		}

		/**
		 * @param WC_Order $order
		 * @param $get_offer_id
		 *
		 * @return array
		 */
		public function generate_square_charge( $order, $get_offer_id ) {
			$get_package = WFOCU_Core()->data->get( '_upsell_package' );
			$result      = array();
			try {
				$this->set_square_gateway_config();
				$get_order               = $this->get_order( $order, $get_offer_id, $get_package );
				$order                   = ( $get_order instanceof WC_Order ) ? $get_order : $order;
				$this->location_id       = empty( $this->location_id ) ? WFOCU_WC_Compatibility::get_order_data( $order, '_wc_square_credit_card_square_location_id' ) : $this->location_id;
				$result['location_id_1'] = $this->location_id;
				$result['location_id_2'] = $this->location_id;
				$result['api_config']    = $this->apiConfig;

				$result['api_client'] = $this->apiClient;
				$charge_request_data  = $this->wfocu_get_square_charge_request( $order, $get_offer_id, $get_package );
				$result['request']    = $charge_request_data;


				if ( class_exists( 'SquareConnect\Api\TransactionsApi' ) ) {
					$square_trns_api = new TransactionsApi( $this->apiClient );

					$result['response'] = $square_trns_api->chargeWithHttpInfo( $this->location_id, $charge_request_data );

					if ( is_array( $result['response'] ) && count( $result['response'] ) > 0 ) {
						$response                 = $result['response'][0];
						$transaction              = $response->getTransaction();
						$result['transaction']    = $transaction;
						$result['transaction_id'] = $transaction->getId();
						$result['response']       = true;
					}
				} else {


					/**
					 * Charge using a new method
					 * square v3.0.0 or greater
					 */
					$settings = $this->get_wc_gateway()->get_plugin()->get_settings_handler();
					$client   = new SquareClient( [
						'accessToken' => $settings->get_access_token(),
						'environment' => $settings->is_sandbox() ? Environment::SANDBOX : Environment::PRODUCTION,
					] );

					$request = new \WooCommerce\Square\Gateway\API\Requests\Payments( WFOCU_WC_Compatibility::get_order_data( $order, '_wc_square_credit_card_square_location_id' ), $client );
					$request->set_charge_data( $order );

					$response    = $this->do_request( $request->get_square_api(), $request->get_square_api_method(), array( $charge_request_data ) );
					$transaction = $response->getResult()->getPayment();

					$result['transaction']    = $transaction;
					$result['transaction_id'] = $transaction->getId();
					$result['response']       = true;
				}


			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( "WFOCU Square: Token Payment Failed due to exception: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$result['failed'] = true;
			}
			WFOCU_Core()->log->log( "WFOCU Square: Token payment result: " . print_r( $result, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return $result;
		}

		/**
		 * Gets the order object with offer payment information added.
		 *
		 * @param $order
		 * @param $get_offer_id
		 * @param $get_package
		 *
		 * @return bool
		 */
		public function get_order( $order, $get_offer_id, $get_package ) {
			if ( ! $order instanceof WC_Order ) {
				return false;
			}

			$result = array();

			$order_id           = WFOCU_WC_Compatibility::get_order_id( $order );
			$result['order_id'] = $order_id;

			$order = $this->get_wc_gateway()->get_order( $order );

			$order->square_customer_id    = WFOCU_WC_Compatibility::get_order_data( $order, '_wc_square_credit_card_customer_id' );
			$result['square_customer_id'] = $order->square_customer_id;

			$sq_token = empty( $this->token ) ? $order->get_meta( '_wc_square_credit_card_payment_token' ) : $this->token;
			$sq_token = empty( $sq_token ) ? WFOCU_Common::get_order_meta( $order, '_wc_square_credit_card_payment_token' ) : $sq_token;

			$order->payment        = isset( $order->payment ) ? $order->payment : new stdClass();
			$order->payment->token = ( isset( $order->payment->token ) && ! empty( $order->payment->token ) ) ? $order->payment->token : $sq_token;

			$result['payment_obj'] = $order->payment;

			try {
				$this->set_square_gateway_config();
				$create_order_data = $this->wfocu_create_square_order_data( $order, $get_offer_id, $get_package );
				if ( class_exists( 'SquareConnect\Api\OrdersApi' ) ) {
					$square_orders_api  = new OrdersApi( $this->apiClient );
					$result['response'] = $square_orders_api->createOrderWithHttpInfo( $this->location_id, $create_order_data );

					if ( is_array( $result['response'] ) && count( $result['response'] ) > 0 ) {
						$response = $result['response'][0];

					}
				} else {

					/**
					 * create order using a new method
					 * square v3.0.0 or greater
					 */
					$settings = $this->get_wc_gateway()->get_plugin()->get_settings_handler();
					$client   = new SquareClient( [
						'accessToken' => $settings->get_access_token(),
						'environment' => $settings->is_sandbox() ? Environment::SANDBOX : Environment::PRODUCTION,
					] );
					$request  = new \WooCommerce\Square\Gateway\API\Requests\Orders( $client );

					$request->set_create_order_data( wc_square()->get_settings_handler()->get_location_id(), $order );
					$response        = $this->do_request( $request->get_square_api(), $request->get_square_api_method(), array( $create_order_data ) );
					$responseOrder   = $response->getResult()->getOrder();
					$square_order_id = $responseOrder->getId();

					$square_order_total           = $responseOrder->getTotalMoney()->getAmount();
					$result['square_order_id']    = $square_order_id;
					$result['square_order_total'] = $square_order_total;

					if ( ! empty( $square_order_id ) && ! empty( $square_order_total ) ) {
						$order->square_order_id    = $square_order_id;
						$order->square_order_total = $square_order_total;
					}

				}


			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( "WFOCU Square: Exception in creating square order: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				WFOCU_Core()->log->log( "WFOCU Square: Final exception result for creating square order " . print_r( $result, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $order;
			}
			WFOCU_Core()->log->log( "WFOCU Square: Final result for creating square order " . print_r( $result, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			return $order;
		}

		/**
		 * Sets the data for creating a square order.
		 *
		 * @param WC_Order $order
		 * @param $get_offer_id
		 * @param $get_package
		 *
		 * @return array|SquareModel\CreateOrderRequest
		 */
		public function wfocu_create_square_order_data( \WC_Order $order, $get_offer_id, $get_package ) {
			$result = array();
			try {
				$this->set_square_gateway_config();

				if ( class_exists( 'SquareConnect\Model\CreateOrderRequest' ) ) {
					$square_request = new SquareConnect\Model\CreateOrderRequest();
					$order_model    = new SquareConnect\Model\Order();
				} else {
					$square_request = new \Square\Models\CreateOrderRequest();
					$order_model    = new \Square\Models\Order( wc_square()->get_settings_handler()->get_location_id() );
				}

				$order_model->setReferenceId( $this->get_order_number( $order ) );

				$line_items = array_merge( $this->get_product_line_items( $order, $get_offer_id, $get_package ), $this->get_shipping_line_items( $order, $get_offer_id, $get_package ) );
				WFOCU_Core()->log->log( "WFOCU Square: Order model line_items for offer id: $get_offer_id is: " . print_r( $line_items, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$taxes = $this->get_order_taxes( $order, $get_offer_id, $get_package );

				WFOCU_Core()->log->log( "WFOCU Square: Order model taxes for offer id: $get_offer_id is: " . print_r( $taxes, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$this->apply_taxes( $taxes, $get_offer_id, $line_items );
				WFOCU_Core()->log->log( "WFOCU Square: Taxes applied." );

				$order_model->setLineItems( $line_items );
				WFOCU_Core()->log->log( "WFOCU Square: Line Items set." );

				$order_model->setTaxes( $taxes );
				WFOCU_Core()->log->log( "WFOCU Square: Taxes set." );

				$shipping_cost = ( isset( $get_package['shipping'] ) && isset( $get_package['shipping']['diff'] ) && $get_package['shipping']['diff']['cost'] ) ? $get_package['shipping']['diff']['cost'] : 0;

				if ( $shipping_cost < 0 ) {
					if ( class_exists( 'SquareModel\OrderLineItemDiscount' ) ) {
						$order_model->setDiscounts( [
							new SquareModel\OrderLineItemDiscount( [
								'name'         => __( 'Shipping Refunded', 'woocommerce-square' ),
								'type'         => 'FIXED_AMOUNT',
								'amount_money' => Money_Utility::amount_to_money( abs( $shipping_cost ), $order->get_currency() ),
								'scope'        => 'ORDER',
							] )
						] );
					} else {
						$order_line_item_discount = new \Square\Models\OrderLineItemDiscount();
						$order_line_item_discount->setName( __( 'Shipping Refunded', 'woocommerce-square' ) );
						$order_line_item_discount->setType( 'FIXED_AMOUNT' );
						$order_line_item_discount->setAmountMoney( Money_Utility::amount_to_money( abs( $shipping_cost ), $order->get_currency() ) );
						$order_line_item_discount->setScope( 'ORDER' );

						$order_model->setDiscounts( array( $order_line_item_discount ) );
					}


				}

				$square_request->setIdempotencyKey( wc_square()->get_idempotency_key( $order->unique_transaction_ref ) );
				$square_request->setOrder( $order_model );

				WFOCU_Core()->log->log( "WFOCU Square: Request data in create order for offer id: $get_offer_id is: " . print_r( $square_request, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $square_request;

			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( "WFOCU Square: Square Exception in setting create order request data for offer id: $get_offer_id: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return $result;
		}

		/**
		 * Gets Square line item objects for an offer package items.
		 *
		 * @param WC_Order $order
		 * @param $get_offer_id
		 * @param $get_package
		 *
		 * @return SquareModel\OrderLineItem[]
		 */
		public function get_product_line_items( \WC_Order $order, $get_offer_id, $get_package ) {
			$line_items = [];
			try {
				$this->set_square_gateway_config();
				foreach ( $get_package['products'] as $item ) {
					WFOCU_Core()->log->log( "Offer product id: {$item['id']}, price: {$item['price']} and qty: {$item['qty']} " );


					if ( class_exists( 'SquareConnect\Model\OrderLineItem' ) ) {
						$line_item = new SquareConnect\Model\OrderLineItem();
					} else {
						$line_item = new \Square\Models\OrderLineItem( (string) $item['qty'] );
					}

					$line_item->setQuantity( (string) $item['qty'] );
					$item_price = ( $item['qty'] > 1 ) ? ( $item['price'] / $item['qty'] ) : $item['price'];
					$line_item->setBasePriceMoney( Money_Utility::amount_to_money( $item_price, $order->get_currency() ) );

					$product   = wc_get_product( $item['id'] );
					$is_synced = false;
					if ( $product instanceof WC_Product ) {
						$is_synced = Product::is_synced_with_square( $product );
					}
					$square_catalog_id = get_post_meta( $item['id'], Product::SQUARE_VARIATION_ID_META_KEY, true );
					WFOCU_Core()->log->log( "Offer item Square catalog id: $square_catalog_id for item id: {$item['id']} and is_synced: $is_synced" );

					if ( $is_synced && ! empty( $square_catalog_id ) && strlen( $square_catalog_id ) > 0 ) {
						$line_item->setCatalogObjectId( $square_catalog_id );
					} else {
						$line_item->setName( $item['_offer_data']->name );
					}
					$line_items[] = $line_item;
				}


			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( "WFOCU Square: Square Exception in get_product_line_items for offer id: $get_offer_id is: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return $line_items;
		}

		/**
		 * Gets Square line item objects for an order's shipping items.
		 *
		 * @param WC_Order $order
		 * @param $get_offer_id
		 * @param $get_package
		 *
		 * @return array
		 */
		public function get_shipping_line_items( \WC_Order $order, $get_offer_id, $get_package ) {
			$line_items = [];

			$shipping_cost = ( isset( $get_package['shipping'] ) && isset( $get_package['shipping']['diff'] ) && $get_package['shipping']['diff']['cost'] ) ? $get_package['shipping']['diff']['cost'] : 0;
			WFOCU_Core()->log->log( "WFOCU Square: Shipping Cost for offer id: $get_offer_id is: $shipping_cost" );

			if ( $shipping_cost > 0 ) {
				WFOCU_Core()->log->log( "WFOCU Square: Adding shipping amount: $shipping_cost" );
				$this->set_square_gateway_config();
				try {

					if ( class_exists( 'SquareConnect\Model\OrderLineItem' ) ) {
						$line_item = new SquareConnect\Model\OrderLineItem();
					} else {
						$line_item = new Square\Models\OrderLineItem( 1 );
					}
					$line_item->setQuantity( (string) 1 );
					$line_item->setName( $get_package['shipping']['label'] );
					$line_item->setBasePriceMoney( Money_Utility::amount_to_money( $shipping_cost, $order->get_currency() ) );
					$line_items[] = $line_item;

				} catch ( Exception $e ) {
					WFOCU_Core()->log->log( "WFOCU Square: Exception in get_shipping_line_items for offer id: $get_offer_id is: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				}
			}

			return $line_items;
		}

		/**
		 * Gets the tax line items for an order.
		 *
		 * @param WC_Order $order
		 * @param $get_offer_id
		 * @param $get_package
		 *
		 * @return array
		 */
		public function get_order_taxes( \WC_Order $order, $get_offer_id, $get_package ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$taxes = [];
			try {
				$this->set_square_gateway_config();
				foreach ( $order->get_taxes() as $tax ) {
					if ( class_exists( 'SquareConnect\Model\OrderLineItemTax' ) ) {
						$tax_item = new SquareConnect\Model\OrderLineItemTax( [
							'uid'   => uniqid(),
							'name'  => $tax->get_name(),
							'type'  => 'ADDITIVE',
							'scope' => 'LINE_ITEM',
						] );

					} else {
						$tax_item = new \Square\Models\OrderLineItemTax();
						$tax_item->setUid( uniqid() );
						$tax_item->setName( $tax->get_name() );
						$tax_item->setType( 'ADDITIVE' );
						$tax_item->setScope( 'LINE_ITEM' );
					}


					$pre_tax_total = (float) $order->get_total() - (float) $order->get_total_tax();
					$total_tax     = (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();

					$percentage = ( $total_tax / $pre_tax_total ) * 100;

					if ( class_exists( 'SkyVerge\WooCommerce\PluginFramework\v5_4_0\SV_WC_Helper' ) ) {
						$tax_item->setPercentage( Framework\SV_WC_Helper::number_format( $percentage ) );

					} else {

						$tax_item->setPercentage( \WooCommerce\Square\Framework\Square_Helper::number_format( $percentage ) );
					}


					$taxes[] = $tax_item;
				}
			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( "WFOCU Square: Exception in get_order_taxes for offer Id: $get_offer_id is: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return $taxes;
		}

		/**
		 * Applies taxes on each Square line item.
		 *
		 * @param SquareModel\OrderLineItemTax[] $taxes
		 * @param $get_offer_id
		 * @param SquareModel\OrderLineItem[] $line_items
		 */
		public function apply_taxes( $taxes, $get_offer_id, $line_items ) {
			try {
				$this->set_square_gateway_config();
				foreach ( $line_items as $line_item ) {
					$applied_taxes = [];
					foreach ( $taxes as $tax ) {
						if ( class_exists( 'SquareConnect\Model\OrderLineItemAppliedTax' ) ) {
							$applied_taxes[] = new SquareConnect\Model\OrderLineItemAppliedTax( [
								'tax_uid' => $tax->getUid(),
							] );
						} else {
							$applied_taxes[] = new \Square\Models\OrderLineItemAppliedTax( $tax->getUid() );

						}

					}
					$line_item->setAppliedTaxes( $applied_taxes );
				}
			} catch ( Exception $e ) {
				WFOCU_Core()->log->log( "WFOCU Square: Exception in apply_taxes for offer id: $get_offer_id is: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}
		}

		/**
		 * @param $order
		 * @param $offer_id
		 * @param $offer_package
		 *
		 * @return ChargeRequest
		 */
		public function wfocu_get_square_charge_request( $order, $offer_id, $offer_package ) {

			if ( class_exists( 'SquareConnect\Model\ChargeRequest' ) ) {
				$square_charge_request = new ChargeRequest();
				$square_charge_request->setIdempotencyKey( wc_square()->get_idempotency_key( $order->unique_transaction_ref ) );

			} else {
				$order->unique_transaction_ref = ltrim( $this->get_order_number( $order ), esc_html_x( '#', 'hash before order number', 'woocommerce-square' ) );

				$square_charge_request = new \Square\Models\CreatePaymentRequest( ! empty( $order->payment->token ) ? $order->payment->token : $order->payment->nonce, wc_square()->get_idempotency_key( $order->unique_transaction_ref, false ), Money_Utility::amount_to_money( $order->payment_total, $order->get_currency() ) );
			}
			$this->set_square_gateway_config();


			$order_id = WFOCU_WC_Compatibility::get_order_id( $order );

			$square_charge_request->setReferenceId( $this->get_order_number( $order ) );

			$description = $order->description . sprintf( __( ' Offer id: %s', 'upstroke-woocommerce-one-click-upsell-square' ), $offer_id );
			if ( class_exists( 'SkyVerge\WooCommerce\PluginFramework\v5_4_0\SV_WC_Helper' ) ) {
				$square_charge_request->setNote( Framework\SV_WC_Helper::str_truncate( $description, 60 ) );
			} else {
				$square_charge_request->setNote( \WooCommerce\Square\Framework\Square_Helper::str_truncate( $description, 60 ) );
			}

			if ( method_exists( $square_charge_request, 'setDelayCapture' ) ) {
				$square_charge_request->setDelayCapture( false );
			}


			if ( isset( $order->square_customer_id ) ) {
				$square_charge_request->setCustomerId( $order->square_customer_id );
			}

			// payment token (card ID) or card nonce (from JS)
			if ( isset( $order->payment->token ) && method_exists( $square_charge_request, 'setCustomerCardId' ) ) {
				$square_charge_request->setCustomerCardId( $order->payment->token );
			}

			if ( class_exists( 'SquareConnect\Model\Address' ) ) {
				$billing_address = new Address();
			} else {
				$billing_address = new \Square\Models\Address();
			}

			$billing_address->setAddressLine1( $order->get_billing_address_1() );
			$billing_address->setAddressLine2( $order->get_billing_address_2() );
			$billing_address->setLocality( $order->get_billing_city() );
			$billing_address->setAdministrativeDistrictLevel1( $order->get_billing_state() );
			$billing_address->setPostalCode( $order->get_billing_postcode() );
			$billing_address->setCountry( $order->get_billing_country() );

			$square_charge_request->setBillingAddress( $billing_address );

			if ( class_exists( 'SkyVerge\WooCommerce\PluginFramework\v5_4_0\SV_WC_Order_Compatibility' ) ) {
				if ( Framework\SV_WC_Order_Compatibility::has_shipping_address( $order ) ) {

					if ( class_exists( 'SquareConnect\Model\Address' ) ) {
						$shipping_address = new Address();
					} else {
						$shipping_address = new \Square\Models\Address();
					}
					$shipping_address->setAddressLine1( $order->get_shipping_address_1() );
					$shipping_address->setAddressLine2( $order->get_shipping_address_2() );
					$shipping_address->setLocality( $order->get_shipping_city() );
					$shipping_address->setAdministrativeDistrictLevel1( $order->get_shipping_state() );
					$shipping_address->setPostalCode( $order->get_shipping_postcode() );
					$shipping_address->setCountry( $order->get_shipping_country() );

					$square_charge_request->setShippingAddress( $shipping_address );
				}
			} else {
				if ( Order_Compatibility::has_shipping_address( $order ) ) {

					if ( class_exists( 'SquareConnect\Model\Address' ) ) {
						$shipping_address = new Address();
					} else {
						$shipping_address = new \Square\Models\Address();
					}
					$shipping_address->setAddressLine1( $order->get_shipping_address_1() );
					$shipping_address->setAddressLine2( $order->get_shipping_address_2() );
					$shipping_address->setLocality( $order->get_shipping_city() );
					$shipping_address->setAdministrativeDistrictLevel1( $order->get_shipping_state() );
					$shipping_address->setPostalCode( $order->get_shipping_postcode() );
					$shipping_address->setCountry( $order->get_shipping_country() );

					$square_charge_request->setShippingAddress( $shipping_address );
				}
			}


			$square_charge_request->setBuyerEmailAddress( $order->get_billing_email() );


			$amount = isset( $offer_package['total'] ) ? $offer_package['total'] : 0;
			WFOCU_Core()->log->log( "WFOCU Square: upsell package total: $amount for wc order id: $order_id has been set in square charge request." );
			$square_charge_request->setAmountMoney( Money_Utility::amount_to_money( $amount, $order->get_currency() ) );


			return $square_charge_request;
		}

		public function wfocu_square_enable_force_tokenization( $forced ) {
			if ( $this->is_enabled() ) {

				return true;
			}

			return $forced;
		}

		public function add_square_token( $process_payment, $order_id, $gateway ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$order = wc_get_order( $order_id );
			if ( $this->is_enabled( $order ) ) {
				$this->set_square_gateway_config();

				$order = $this->get_wc_gateway()->get_order( $order );

				$is_checkout_nonce_present_in_request = isset( $_REQUEST['wc_square_credit_card_checkout_validate_nonce'] ) ? wc_clean( $_REQUEST['wc_square_credit_card_checkout_validate_nonce'] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( empty( $order->payment->token ) && empty( $is_checkout_nonce_present_in_request ) && $order->get_customer_id() < 1 ) {
					try {

						/**
						 * Create customer call multi time send by square if woocommerce_square_customers table have multiple entries with same email
						 * because square only use an indexed customer ID if there was a single one returned from table, otherwise they can't handel
						 * in this case primary checkout order not process and failed
						 *
						 * Below cases will be run in multiple entries
						 * 1. Prevent create token because square only use an indexed customer ID if there was a single one returned, otherwise they can't handel
						 * 2. Upsell not open for guest user but checkout smoothly process
						 * 3. It's a temporary solution when till square not fix issue
						 */
						$create_token = true;
						if ( class_exists( 'WooCommerce\Square\Gateway\Customer_Helper' ) ) {
							$indexed_customers = Customer_Helper::get_customers_by_email( $order->get_billing_email() );
							if ( is_array( $indexed_customers ) && count( $indexed_customers ) > 1 ) {
								WFOCU_Core()->log->log( "Order: #" . $order->get_id() . "WFOCU Square: failed Creating token multiple user exists in table " . $order->get_billing_email() ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								$create_token = false;
							}
						}

						if ( $create_token ) {
							WFOCU_Core()->log->log( "Order: #" . $order->get_id() . "WFOCU Square: Creating token for the guest user" ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							$order = $this->get_wc_gateway()->get_payment_tokens_handler()->create_token( $order );
						}

						/**
						 * Unset verification token from the post data as we have no other way to prevent verification token to pass in the payment call
						 */
						$this->isGuestTokenCall = true;
						if ( isset( $_POST[ 'wc-' . $this->get_wc_gateway()->get_id_dasherized() . '-buyer-verification-token' ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
							unset( $_POST[ 'wc-' . $this->get_wc_gateway()->get_id_dasherized() . '-buyer-verification-token' ] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
						}
					} catch ( Exception $e ) {
						WFOCU_Core()->log->log( "WFOCU Square: Exception in creating token in primary order payment: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						throw new Exception( $e->getMessage() );
					}
				}

			}

			return $process_payment;
		}

		/**
		 * @param $order
		 * @param WC_Payment_Gateway $gateway
		 *
		 * @return mixed
		 */
		public function square_get_order( $order ) {
			if ( $this->is_enabled( $order ) ) {
				$this->set_square_gateway_config();
				if ( ! isset( $order->payment->token ) ) {
					$order->payment->token = WFOCU_Common::get_order_meta( $order, '_wc_square_credit_card_payment_token' );
				}

				/**
				 * Unset verification token from the post data as we have no other way to prevent verification token to pass in the payment call
				 */
				if ( true === $this->isGuestTokenCall && isset( $order->payment->verification_token ) && ! empty( $order->payment->verification_token ) ) {
					$order->payment->verification_token = null;
					if ( isset( $_POST[ 'wc-' . $this->get_wc_gateway()->get_id_dasherized() . '-buyer-verification-token' ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
						unset( $_POST[ 'wc-' . $this->get_wc_gateway()->get_id_dasherized() . '-buyer-verification-token' ] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
					}
				}
			}

			return $order;
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
				$gateways[] = $this->get_key();
			}

			return $gateways;
		}

		/**
		 * Adding keys to copy to renewal orders
		 *
		 * @param $meta_keys
		 * @param WC_Order $order
		 *
		 * @return mixed
		 */
		public function set_square_payment_token_keys_to_copy( $meta_keys, $order = null ) {

			if ( $order instanceof WC_Order ) {
				$payment_method = $order->get_payment_method();
				if ( $payment_method === $this->get_key() ) {
					array_push( $meta_keys, '_wc_square_credit_card_payment_token', '_wc_square_credit_card_customer_id' );
				}
			} else {
				array_push( $meta_keys, '_wc_square_credit_card_payment_token', '_wc_square_credit_card_customer_id' );
			}

			return $meta_keys;
		}

		/**
		 * @param WC_Subscription $subscription
		 * @param $key
		 * @param WC_Order $order
		 */
		public function save_square_payment_token_to_subscription( $subscription, $key, $order ) {

			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$get_token      = $order->get_meta( '_wc_square_credit_card_payment_token', true );
			$sq_customer_id = $order->get_meta( '_wc_square_credit_card_customer_id', true );

			if ( ! empty( $get_token ) ) {
				$subscription->update_meta_data( '_wc_square_credit_card_payment_token', $get_token );
				$subscription->update_meta_data( '_wc_square_credit_card_customer_id', $sq_customer_id );
				$subscription->save();
			}
		}

		/**
		 * @param WC_Subscription $subscription
		 */
		public function wfcou_square_update_token_in_user_meta( $subscription ) {

			$customer_id = WFOCU_Common::get_order_meta( $subscription, '_customer_user' );;
			$parent_order    = $subscription->get_parent();
			$parent_order_id = WFOCU_WC_Compatibility::get_order_id( $parent_order );
			if ( $parent_order instanceof WC_Order && $customer_id > 0 && $this->get_key() === $parent_order->get_payment_method() ) {
				WFOCU_Core()->log->log( "WFOCU Square: Updating token for Customer id: $customer_id in subscription with id: {$subscription->get_id()} and parent order id: $parent_order_id" );
				$sq_token_id    = wcs_get_objects_property( $parent_order, '_wc_square_credit_card_payment_token' );
				$exp_month_year = wcs_get_objects_property( $parent_order, '_wc_square_credit_card_card_expiry_date' );
				$exp_month_year = explode( '-', $exp_month_year );
				$exp_month      = ( is_array( $exp_month_year ) && count( $exp_month_year ) > 1 ) ? $exp_month_year[1] : '';
				$exp_year       = empty( $exp_month ) ? '' : $exp_month_year[0];
				$sq_token_data  = array();

				$sq_token_data[ $sq_token_id ] = array(
					'type'      => 'credit_card',
					'card_type' => wcs_get_objects_property( $parent_order, '_wc_square_credit_card_card_type' ),
					'last_four' => wcs_get_objects_property( $parent_order, '_wc_square_credit_card_account_four' ),
					'exp_month' => $exp_month,
					'exp_year'  => $exp_year,
				);
				WFOCU_Core()->log->log( "WFOCU Square: Current token: $sq_token_id and all token obj: " . print_r( $sq_token_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				update_user_meta( $customer_id, '_wc_square_credit_card_payment_tokens', $sq_token_data ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_update_user_meta
			}
		}

		public function set_square_gateway_config() {


			if ( class_exists( 'SquareConnect\Configuration' ) ) {
				try {
					$this->apiConfig    = SquareConnect\Configuration::getDefaultConfiguration();
					$this->access_token = $this->get_wc_gateway()->get_plugin()->get_settings_handler()->get_access_token();
					if ( $this->get_wc_gateway()->get_plugin()->get_settings_handler()->is_sandbox_setting_enabled() ) {
						$this->apiConfig->setHost( 'https://connect.squareupsandbox.com' );
						$this->access_token = empty( $this->access_token ) ? $this->get_wc_gateway()->get_plugin()->get_settings_handler()->get_option( 'sandbox_token' ) : $this->access_token;
					}
					$this->apiConfig->setAccessToken( $this->access_token );
					$this->location_id = $this->get_wc_gateway()->get_plugin()->get_settings_handler()->get_location_id();
					$this->apiClient   = new SquareConnect\ApiClient( $this->apiConfig );
				} catch ( Exception $e ) {
					WFOCU_Core()->log->log( "WFOCU Square: Exception in setting apiClient: " . print_r( $e->getMessage(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				}
			} else {
				$this->get_wc_gateway()->get_api();
			}


		}

		public function do_request( $square_api, $method, $args ) {
			return call_user_func_array( array( $square_api, $method ), $args );
		}


	}

	WFOCU_Gateway_Integration_Square_Credit_Card::get_instance();
}