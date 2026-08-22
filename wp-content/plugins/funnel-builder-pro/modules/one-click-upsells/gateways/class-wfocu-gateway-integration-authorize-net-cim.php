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


		protected static $ins              = null;
		public $token                      = false;
		public $customer_id                = false;
		public $unset_opaque_value         = false;
		public $order                      = false;
		protected $transaction_description = '';
		protected $key                     = 'authorize_net_cim_credit_card';
		const MB_ENCODING                  = 'UTF-8';
		protected $current_refund          = null;

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
			 * Reinforce forced tokenization at the checkout-form level (final override the framework applies
			 * after seeding the value from the filter above). Mirrors Sublium's approach so the card is always
			 * tokenized when an upsell funnel is active, even on the classic payment form.
			 */
			add_filter( 'wc_' . $this->get_key() . '_payment_form_tokenization_forced', array( $this, 'maybe_force_tokenization' ) );

			/**
			 * For a non logged in mode when accept js is turned off, we just need to tokenize the card after the main charge gets completed
			 * This cb is just placed here to make sure that older version where we have processing without js
			 */
			add_action( 'woocommerce_pre_payment_complete', array( $this, 'maybe_create_token' ), 10, 1 );

			// NOTE: we deliberately do NOT hook '..._process_payment'. Like Sublium, the core gateway owns the
			// charge; the reusable profile is attached AFTER the charge on '..._add_transaction_data' below.
			// (The old takeover that intercepted the charge here caused the E00114 failures on #8975/#8979.)

			add_action(
				'wfocu_front_create_new_order_on_success',
				function () {
					remove_action( 'woocommerce_pre_payment_complete', array( $this, 'maybe_create_token' ), 10, 1 );  // phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UndefinedVariable
				},
				- 1
			);

			// After the charge: capture the token (logged-in) or mint the profile from the transaction (guest).
			// Arg 2 is the API response, needed for the transaction id.
			add_action( 'wc_payment_gateway_' . $this->get_key() . '_add_transaction_data', array( $this, 'capture_cim_token' ), 9, 2 );
			add_action( 'wc_payment_gateway_' . $this->get_key() . '_add_transaction_data', array( $this, 'maybe_add_shipping_address_id_order_for_guests' ) );

			$this->refund_supported = true;

			// Modifying refund request data in case of offer refund to add offer transaction id
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
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

		/**
		 * Get the SkyVerge OrderHelper class name if available.
		 *
		 * CIM v3.10.15+ uses Dynamic_Props/WeakMap to store payment data.
		 * The framework version namespace may vary across CIM versions.
		 *
		 * @return string|false OrderHelper class name or false if unavailable
		 */
		private function get_order_helper_class() {
			static $order_helper_class = null;
			static $resolved           = false;

			if ( $resolved ) {
				return $order_helper_class;
			}

			$resolved = true;

			// Known framework versions bundled with CIM (namespace matches vendor package).
			$known_helpers = array(
				'\SkyVerge\WooCommerce\PluginFramework\v6_1_4\Helpers\OrderHelper',
				'\SkyVerge\WooCommerce\PluginFramework\v6_1_2\Helpers\OrderHelper',
			);
			foreach ( $known_helpers as $helper_class ) {
				if ( class_exists( $helper_class ) ) {
					$order_helper_class = $helper_class;

					return $order_helper_class;
				}
			}

			// Dynamic discovery for other framework versions
			foreach ( get_declared_classes() as $declared_class ) {
				if ( 0 === strpos( $declared_class, 'SkyVerge\\WooCommerce\\PluginFramework\\' ) && false !== strpos( $declared_class, '\\Helpers\\OrderHelper' ) ) {
					$order_helper_class = '\\' . $declared_class;
					break;
				}
			}

			return $order_helper_class;
		}

		/**
		 * SkyVerge Dynamic_Props class (WeakMap on PHP 8.2+). Used when OrderHelper is not discoverable yet.
		 *
		 * @return string|false Fully-qualified class name or false.
		 */
		private function get_dynamic_props_class() {
			static $class_name = null;
			static $done       = false;

			if ( $done ) {
				return $class_name;
			}

			$done = true;

			$known = array(
				'\SkyVerge\WooCommerce\PluginFramework\v6_1_4\Payment_Gateway\Dynamic_Props',
				'\SkyVerge\WooCommerce\PluginFramework\v6_1_2\Payment_Gateway\Dynamic_Props',
			);
			foreach ( $known as $cn ) {
				if ( class_exists( $cn ) ) {
					$class_name = $cn;

					return $class_name;
				}
			}

			foreach ( get_declared_classes() as $declared_class ) {
				if ( 0 === strpos( $declared_class, 'SkyVerge\\WooCommerce\\PluginFramework\\' ) && false !== strpos( $declared_class, '\\Payment_Gateway\\Dynamic_Props' ) ) {
					$class_name = '\\' . $declared_class;

					return $class_name;
				}
			}

			return $class_name;
		}

		/**
		 * Get the payment object from an order, bridging old and new CIM storage.
		 *
		 * CIM v3.10.15+ stores payment data in a WeakMap via OrderHelper.
		 * Older versions use direct $order->payment property access.
		 *
		 * @param WC_Order $order
		 *
		 * @return stdClass
		 */
		private function get_payment_object( $order ) {
			$helper = $this->get_order_helper_class();

			if ( $helper ) {
				return $helper::get_payment( $order );
			}

			if ( ! isset( $order->payment ) || ! is_object( $order->payment ) ) {
				$order->payment = new stdClass();
			}

			return $order->payment;
		}

		/**
		 * Set the payment object on an order, bridging old and new CIM storage.
		 *
		 * @param WC_Order $order
		 * @param stdClass $payment
		 */
		private function set_payment_object( $order, $payment ) {
			$helper = $this->get_order_helper_class();

			if ( $helper ) {
				$helper::set_payment( $order, $payment );

				return;
			}

			$order->payment = $payment;
		}

		/**
		 * Set the CIM customer profile ID on the order the same way SkyVerge does:
		 * payment object + gateway order meta + OrderHelper::set_customer_id (Dynamic_Props / WeakMap on PHP 8.2+).
		 * Never assigns $order->customer_id directly on WC_Order — that is deprecated in PHP 8.2+.
		 * See WC_Payment_Gateway::add_customer_data() in the plugin framework.
		 *
		 * @param WC_Order $order
		 * @param string   $customer_id
		 */
		private function set_customer_id_on_order( $order, $customer_id ) {
			$payment              = $this->get_payment_object( $order );
			$payment->customer_id = $customer_id;
			$this->set_payment_object( $order, $payment );

			$gateway = $this->get_wc_gateway();
			if ( $gateway && is_object( $gateway ) && method_exists( $gateway, 'update_order_meta' ) ) {
				$gateway->update_order_meta( $order, 'customer_id', $customer_id );
			} else {
				$order->update_meta_data( '_wc_' . $this->get_key() . '_customer_id', $customer_id );
				$order->save_meta_data();
			}

			$helper = $this->get_order_helper_class();
			if ( $helper && method_exists( $helper, 'set_customer_id' ) ) {
				$helper::set_customer_id( $order, $customer_id );
			} else {
				$dynamic_props = $this->get_dynamic_props_class();
				if ( $dynamic_props && method_exists( $dynamic_props, 'set' ) ) {
					$dynamic_props::set( $order, 'customer_id', $customer_id );
				}
			}
		}

		/**
		 * Get the CIM customer profile ID: OrderHelper first (what the gateway reads), then payment, then meta.
		 *
		 * @param WC_Order $order
		 *
		 * @return string
		 */
		private function get_customer_id_from_order( $order ) {
			$helper = $this->get_order_helper_class();
			if ( $helper && method_exists( $helper, 'get_customer_id' ) ) {
				$cid = $helper::get_customer_id( $order );
				if ( ! empty( $cid ) ) {
					return (string) $cid;
				}
			}

			$dynamic_props = $this->get_dynamic_props_class();
			if ( $dynamic_props && method_exists( $dynamic_props, 'get' ) ) {
				$cid = $dynamic_props::get( $order, 'customer_id' );
				if ( ! empty( $cid ) ) {
					return (string) $cid;
				}
			}

			$payment = $this->get_payment_object( $order );
			if ( isset( $payment->customer_id ) && ! empty( $payment->customer_id ) ) {
				return (string) $payment->customer_id;
			}

			$gateway = $this->get_wc_gateway();
			if ( $gateway && is_object( $gateway ) && method_exists( $gateway, 'get_order_meta' ) ) {
				$cid = $gateway->get_order_meta( $order, 'customer_id' );
				if ( ! empty( $cid ) ) {
					return (string) $cid;
				}
			}

			$from_meta = WFOCU_Common::get_order_meta( $order, '_wc_' . $this->get_key() . '_customer_id' );
			if ( ! empty( $from_meta ) ) {
				return (string) $from_meta;
			}

			// Fallback: Try legacy meta key format used by older CIM versions.
			$legacy_meta = $order->get_meta( '_wc_authorize_net_cim_credit_card_customer_id' );
			if ( ! empty( $legacy_meta ) ) {
				return (string) $legacy_meta;
			}

			// All proper retrieval methods exhausted - return empty.
			// Note: Direct $order->customer_id access removed to avoid WC 3.0+ deprecation warnings.
			return '';
		}

		public function get_order( $order ) {

			if ( $order instanceof WC_Order && $this->key === $order->get_payment_method() ) {

				$payment = $this->get_payment_object( $order );

				if ( ! is_checkout_pay_page() ) {

					// retrieve the payment token

					// retrieve the optional customer id
					$this->set_customer_id_on_order( $order, $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'customer_id' ) );

					$customer_id_from_session = WFOCU_Core()->data->get( 'authorize_net_cim_customer_id', '', 'gateway' );
					if ( empty( $this->get_customer_id_from_order( $order ) ) && ! empty( $customer_id_from_session ) ) {
						$this->set_customer_id_on_order( $order, $customer_id_from_session );
					}

					$payment->token     = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'payment_token' );
					$token_from_gateway = $this->get_token( $order );
					if ( empty( $payment->token ) && ! empty( $token_from_gateway ) ) {
						$payment->token = $token_from_gateway;
					}
					// set token data on order
					if ( $this->get_wc_gateway()->get_payment_tokens_handler()->user_has_token( $order->get_user_id(), $payment->token ) ) {

						// an existing registered user with a saved payment token
						$token = $this->get_wc_gateway()->get_payment_tokens_handler()->get_token( $order->get_user_id(), $payment->token );

						// account last four
						$payment->account_number = $token->get_last_four();

						if ( $this->get_wc_gateway()->is_credit_card_gateway() ) {

							// card type
							$payment->card_type = $token->get_card_type();

							// exp month/year
							$payment->exp_month = $token->get_exp_month();
							$payment->exp_year  = $token->get_exp_year();

						} elseif ( $this->get_wc_gateway()->is_echeck_gateway() ) {

							// account type (checking/savings)
							$payment->account_type = $token->get_account_type();
						}
					} else {

						// a guest user means that token data must be set from the original order

						// account number
						$payment->account_number = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'account_four' );

						if ( $this->get_wc_gateway()->is_credit_card_gateway() ) {

							// card type
							$payment->card_type = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'card_type' );

							// expiry date
							$expiry_date = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'card_expiry_date' );
							if ( ! empty( $expiry_date ) ) {
								list( $exp_year, $exp_month ) = explode( '-', $expiry_date );
								$payment->exp_month           = $exp_month;
								$payment->exp_year            = $exp_year;
							}
						} elseif ( $this->get_wc_gateway()->is_echeck_gateway() ) {

							// account type
							$payment->account_type = $this->get_wc_gateway()->get_order_meta( WFOCU_WC_Compatibility::get_order_data( $order, 'id' ), 'account_type' );
						}
					}
				}

				$response = intval( $order->get_meta( '_authorize_cim_shipping_address_id' ) );
				if ( ! empty( $response ) ) {
					$payment->shipping_address_id = $response;
				}

				if ( true === $this->unset_opaque_value && isset( $payment->opaque_value ) ) {
					unset( $payment->opaque_value );
				}

				$this->set_payment_object( $order, $payment );
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

			$this->token = WFOCU_Common::get_order_meta( wc_get_order( $get_id ), '_wc_' . $this->get_key() . '_payment_token' );

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
			$this->token = WFOCU_Common::get_order_meta( wc_get_order( $get_secondary_order ), '_wc_' . $this->get_key() . '_payment_token' );

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
			$this->token = WFOCU_Common::get_order_meta( wc_get_order( $get_id ), '_wc_' . $this->get_key() . '_payment_token' );

			if ( ! empty( $this->token ) ) {
				return $this->token;
			}

			/**
			 * Fallback when token is not present in the parent order
			 */
			$get_secondary_order = WFOCU_Core()->data->get( 'authorize_net_cim_order_id', '', 'gateway' );

			if ( ! empty( $get_secondary_order ) ) {
				$this->token = WFOCU_Common::get_order_meta( wc_get_order( $get_secondary_order ), '_wc_' . $this->get_key() . '_payment_token' );

				if ( ! empty( $this->token ) ) {
					return $this->token;
				}
			}

			/**
			 * Final fallback: resolve the CIM payment profile id from the customer's saved WC payment
			 * token. get_token() is read directly for customerPaymentProfileId, so without this a
			 * logged-in customer whose token isn't on the order meta would send an empty payment profile
			 * id and Authorize.Net would reject the upsell — the same failure class as the missing
			 * customerProfileId (E00003). Mirrors get_customer_id()'s user-level fallback. The token's
			 * get_id() is the payment profile id (same value used in create_profile_from_transaction()).
			 */
			$parent_order = ! empty( $get_secondary_order ) ? wc_get_order( $get_secondary_order ) : wc_get_order( $get_id );
			$gateway      = $this->get_wc_gateway();
			if ( $parent_order instanceof WC_Order && is_object( $gateway ) && method_exists( $gateway, 'get_payment_tokens_handler' ) ) {
				$user_id = (int) $parent_order->get_user_id();
				$handler = $gateway->get_payment_tokens_handler();
				if ( $user_id && is_object( $handler ) && method_exists( $handler, 'get_tokens' ) ) {
					$tokens = $handler->get_tokens( $user_id );
					if ( is_array( $tokens ) && ! empty( $tokens ) ) {
						$chosen = null;
						foreach ( $tokens as $token_obj ) {
							if ( is_object( $token_obj ) && method_exists( $token_obj, 'is_default' ) && $token_obj->is_default() ) {
								$chosen = $token_obj;
								break;
							}
						}
						if ( null === $chosen ) {
							$chosen = reset( $tokens );
						}
						if ( is_object( $chosen ) && method_exists( $chosen, 'get_id' ) && ! empty( $chosen->get_id() ) ) {
							$this->token = (string) $chosen->get_id();

							return $this->token;
						}
					}
				}
			}

			return '';
		}

		public function maybe_create_token( $order ) {

			$order_base = wc_get_order( $order );
			if ( $order_base instanceof WC_Order && $this->key === $order_base->get_payment_method() && false === $this->is_accept_js_on() && true === $this->get_wc_gateway()->is_cim_feature_enabled() ) {

				$order = $this->get_wc_gateway()->get_order( $order );

				if ( $this->should_tokenize() && 0 === $order->get_user_id() ) {

					$payment = $this->get_payment_object( $order );
					if ( isset( $payment->token ) && $payment->token ) {

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

								$get_order_by_meta = new WP_Query(
									array(
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
									)
								);

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

						$shipping_address_id = is_numeric( $response ) ? $response : $response->get_shipping_address_id();

						$payment                      = $this->get_payment_object( $order );
						$payment->shipping_address_id = $shipping_address_id;
						$this->set_payment_object( $order, $payment );
						WFOCU_Core()->data->set( 'authorize_net_cim_shipping_id', $payment->shipping_address_id, 'gateway' );
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
				// Build description locally to avoid triggering WC_Abstract_Legacy_Order::__get on $order->description,
				// which logs a "properties should not be accessed directly" notice in CIM v3.10.15+ (Dynamic_Props/WeakMap).
				$this->transaction_description = sprintf(
				/* translators: 1: site name 2: order number */
					__( '%1$s - Order %2$s', 'woocommerce-plugin-framework' ),
					esc_html( $this->get_site_name() ),
					$this->order->get_order_number()
				);
				$request = $this->create_transaction_request( 'capture', $order );
				WFOCU_Core()->log->log( 'AUTHORIZE CIM REQUEST :' . print_r( $request, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$response = wp_safe_remote_request( $url, $this->get_request_attributes( $request ) );
				$body     = wp_remote_retrieve_body( $response );
				$body     = preg_replace( '/\xEF\xBB\xBF/', '', $body );
				$result   = json_decode( $body, true );
				WFOCU_Core()->log->log( 'AUTHORIZE CIM RESPONSE :' . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( is_wp_error( $response ) ) {
					$is_successful = false;
				} elseif ( isset( $result['messages'] ) && isset( $result['messages']['resultCode'] ) && 'Ok' === $result['messages']['resultCode'] && ! empty( $result['directResponse'] ) ) {
						$trans_id = $this->get_transaction_id( $result['directResponse'] );
						WFOCU_Core()->data->set( '_transaction_id', $trans_id );
						$is_successful = true;

				} else {
					WFOCU_Core()->log->log( 'AUTHORIZE CIM ERROR :' . print_r( $result, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$order_note = sprintf( __( 'Authorize.net CIM Transaction Failed (%s)', 'woofunnels-upstroke-one-click-upsell' ), isset( $result['messages']['message']['text'] ) ? $result['messages']['message']['text'] : __( 'Unable to parse error, Check logs for more info', 'woofunnels-upstroke-one-click-upsell' ) );
					$order->add_order_note( $order_note );
					$is_successful = false;
				}
			} catch ( \Throwable $e ) {
				// Catch \Throwable (not just Exception): the charge path runs through SkyVerge's dynamic
				// framework (WeakMap/Dynamic_Props, method_exists bridging) where a fatal \Error (TypeError,
				// method-on-null, etc.) is possible. Without this such a fatal would 500 the upsell request
				// instead of failing cleanly with an order note.
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
			$payment          = $this->get_payment_object( $order );

			/**
			 * Resolve the CIM customer profile ID up front. Both the upsell charge (customerProfileId
			 * below) and create_shipping_address() require it. If none can be resolved (the original
			 * payment never created/stored a CIM profile), abort with a clear message instead of letting
			 * SkyVerge emit the cryptic E00003 "expected customerProfileId" error. This Exception is
			 * caught by process_charge() and recorded as a clean failed-upsell note.
			 */
			$customer_profile_id = $this->get_customer_id( $order );
			if ( empty( $customer_profile_id ) ) {
				throw new Exception( esc_html__( 'No saved Authorize.Net (CIM) customer profile was found for this customer, so the upsell could not be charged.', 'woofunnels-upstroke-one-click-upsell' ) );
			}

			/**
			 * We need to create shipping ID for the current user on Authorize.Net CIM API
			 * As ShippingAddressID is important for the cases when business owner has shipping-filters enabled in their merchant account.
			 */
			$maybe_get_shipping_id_from_session = WFOCU_Core()->data->get( 'authorize_net_cim_shipping_id', '', 'gateway' );

			if ( isset( $payment->shipping_address_id ) && ! empty( $payment->shipping_address_id ) ) {
				$shipping_address_id = $payment->shipping_address_id;
			} elseif ( ! empty( $maybe_get_shipping_id_from_session ) ) {
				$shipping_address_id = $maybe_get_shipping_id_from_session;
			} else {
				/**
				 * Regression fix (commit 90a89200 deferred shipping-address creation to here): SkyVerge's
				 * create_shipping_address() reads the customerProfileId off the order via
				 * OrderHelper::get_customer_id(). The old process_payment override seeded that while the
				 * profile was live on the order; the new "don't override payment" flow does not guarantee
				 * it, so the request goes out without customerProfileId and Authorize.Net returns E00003.
				 * Seed the resolved profile ID onto the order right before the call.
				 */
				$this->set_customer_id_on_order( $order, $customer_profile_id );

				try {
					$response = $this->get_wc_gateway()->get_api()->create_shipping_address( $order );

					WFOCU_Core()->log->log( 'Log for shipping address-' . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

					if ( is_numeric( $response ) ) {
						$shipping_address_id = $response;
					} elseif ( is_object( $response ) && method_exists( $response, 'get_shipping_address_id' ) ) {
						$shipping_address_id = $response->get_shipping_address_id();
					} else {
						$shipping_address_id = (int) WFOCU_Common::get_order_meta( $order, '_authorize_cim_shipping_address_id' );
					}
				} catch ( \Throwable $e ) {
					// A shipping-address failure must not abort the whole upsell. Fall back to the id
					// stored on the order (when present); the charge can still proceed without it unless
					// the merchant has shipping filters enabled. Mirrors the old maybe_create_token() path.
					WFOCU_Core()->log->log( 'CIM create_shipping_address failed, falling back to stored id: ' . $e->getMessage() );
					$shipping_address_id = (int) WFOCU_Common::get_order_meta( $order, '_authorize_cim_shipping_address_id' );
				}
			}

			return apply_filters(
				'wfocu_payment_authorize_transaction_args',
				array(
					'createCustomerProfileTransactionRequest' => array(
						'merchantAuthentication' => array(
							'name'           => bwf_clean( $this->get_wc_gateway()->get_api_login_id() ),
							'transactionKey' => bwf_clean( $this->get_wc_gateway()->get_api_transaction_key() ),
						),
						'refId'                  => $this->get_order_number( $order ),
						'transaction'            => array(
							$transaction_type => array(
								'amount'                   => $this->number_format( $get_package['total'] ),
								'tax'                      => $this->get_taxes(),
								'shipping'                 => $this->get_shipping(),
								'lineItems'                => $this->get_line_items(),
								'customerProfileId'        => $this->get_customer_id( $order ),
								'customerPaymentProfileId' => $this->get_token( $order ),
								'customerShippingAddressId' => $shipping_address_id,
								'order'                    => array(
									'invoiceNumber'       => ltrim( $this->get_order_number( $order ), _x( '#', 'hash before the order number', 'woocommerce-gateway-authorize-net-cim' ) ),
									'description'         => $this->str_truncate( $this->transaction_description . '::' . $this->get_order_number( $order ), 255 ),
									'purchaseOrderNumber' => $this->str_truncate( preg_replace( '/\W/', '', isset( $payment->po_number ) ? $payment->po_number : '' ), 25 ),
								),

							),
						),

						'extraOptions'           => $this->get_extra_options(),

					),
				),
				$this
			);
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

			/**
			 * Guest-checkout fallback: the Customer Profile ID lives in WP user meta when it never made
			 * it onto the order (the user is created mid-checkout). By the time an upsell charge runs the
			 * user-meta entry is populated, so resolve it from there. Mirrors Sublium's resolve flow.
			 */
			$parent_order = wc_get_order( $get_secondary_order );
			$gateway      = $this->get_wc_gateway();
			if ( $parent_order instanceof WC_Order && is_object( $gateway ) && method_exists( $gateway, 'get_customer_id_user_meta_name' ) ) {
				$user_id = (int) $parent_order->get_user_id();
				if ( $user_id ) {
					$this->customer_id = get_user_meta( $user_id, $gateway->get_customer_id_user_meta_name(), true );
					if ( ! empty( $this->customer_id ) ) {
						return $this->customer_id;
					}
				}
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

		/**
		 * Capture the Authorize.net CIM token after the core gateway has charged + tokenized the
		 * primary order. This is the same integration point WooCommerce Subscriptions and Sublium use
		 * ( '..._add_transaction_data' ) instead of taking over process_payment: the core gateway owns
		 * the primary charge, and we simply persist the Customer Profile ID + Payment Profile ID so
		 * one-click upsells can charge the same card later.
		 *
		 * Both IDs are normally written to the order meta by the gateway. For guest checkouts the
		 * Customer Profile ID can be missing from the order (the WP user is created mid-checkout and the
		 * gateway writes the profile id to WP user meta), so we backfill it from the user-meta key the
		 * gateway actually populates — mirroring Sublium's guest-checkout handling.
		 *
		 * @param WC_Order|int $order
		 * @param object|null  $response Core gateway API response (carries the transaction id).
		 */
		public function capture_cim_token( $order, $response = null ) {
			if ( ! $order instanceof WC_Order ) {
				$order = wc_get_order( $order );
			}
			if ( ! $order instanceof WC_Order || $this->key !== $order->get_payment_method() ) {
				return;
			}

			$customer_meta_key = '_wc_' . $this->get_key() . '_customer_id';
			$token_meta_key    = '_wc_' . $this->get_key() . '_payment_token';

			$customer_id   = $order->get_meta( $customer_meta_key );
			$payment_token = $order->get_meta( $token_meta_key );

			// Guest-checkout fallback: read the Customer Profile ID from the user-meta key SkyVerge
			// writes during tokenization, and mirror it onto the order so the upsell charge can find it.
			if ( empty( $customer_id ) ) {
				$user_id = (int) $order->get_user_id();
				$gateway = $this->get_wc_gateway();
				if ( $user_id && is_object( $gateway ) && method_exists( $gateway, 'get_customer_id_user_meta_name' ) ) {
					$customer_id = get_user_meta( $user_id, $gateway->get_customer_id_user_meta_name(), true );
					if ( ! empty( $customer_id ) ) {
						$order->update_meta_data( $customer_meta_key, $customer_id );
						$order->save_meta_data();
					}
				}
			}

			// No native token? (guest, or logged-in with tokenization off). This hook only fires on a
			// successful charge, so mint the profile from that transaction — independent of the gateway's
			// tokenization setting, so upsells work either way.
			if ( empty( $payment_token ) ) {
				$this->create_profile_from_transaction( $order, $response );
				$customer_id   = $order->get_meta( $customer_meta_key );
				$payment_token = $order->get_meta( $token_meta_key );
			}

			if ( empty( $payment_token ) ) {
				WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM add_transaction_data fired but no payment token on order yet; upsells may be unavailable for this order.' );

				return;
			}

			// Make the parent order + profile discoverable to the upsell charge flow (has_token/get_token/get_customer_id).
			WFOCU_Core()->data->set( 'authorize_net_cim_order_id', $order->get_id(), 'gateway' );
			if ( ! empty( $customer_id ) ) {
				WFOCU_Core()->data->set( 'authorize_net_cim_customer_id', $customer_id, 'gateway' );
			}
			WFOCU_Core()->data->save( 'gateway' );

			WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM token captured for upsells (customer profile: ' . $customer_id . ', payment profile: ' . $payment_token . ').' );
		}

		/**
		 * Mint a reusable CIM Customer Profile from a guest's just-completed transaction, so one-click upsells
		 * can charge the same card. Writes the profile/payment/shipping ids to the meta keys the upsell flow
		 * reads (get_token/get_customer_id/get_order). Best-effort: on any failure the order is already
		 * complete, we just skip upsells.
		 *
		 * @param WC_Order    $order
		 * @param object|null $response Core gateway API response (preferred source of the transaction id).
		 *
		 * @return bool
		 */
		private function create_profile_from_transaction( $order, $response = null ) {

			$gateway = $this->get_wc_gateway();
			if ( ! is_object( $gateway ) ) {
				return false;
			}

			$customer_meta_key = '_wc_' . $this->get_key() . '_customer_id';
			$token_meta_key    = '_wc_' . $this->get_key() . '_payment_token';

			if ( ! empty( $order->get_meta( $customer_meta_key ) ) && ! empty( $order->get_meta( $token_meta_key ) ) ) {
				return false;
			}

			$trans_id = '';
			if ( is_object( $response ) && method_exists( $response, 'get_transaction_id' ) && $response->get_transaction_id() ) {
				$trans_id = $response->get_transaction_id();
			}
			if ( empty( $trans_id ) ) {
				$trans_id = $order->get_transaction_id();
			}
			if ( empty( $trans_id ) ) {
				WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM profile-from-transaction skipped: no transaction id available.' );

				return false;
			}

			try {
				$api = $gateway->get_api();
				$url = ( 'production' === $gateway->get_environment() ) ? $api::PRODUCTION_ENDPOINT : $api::TEST_ENDPOINT;

				$request = array(
					'createCustomerProfileFromTransactionRequest' => array(
						'merchantAuthentication' => array(
							'name'           => bwf_clean( $gateway->get_api_login_id() ),
							'transactionKey' => bwf_clean( $gateway->get_api_transaction_key() ),
						),
						'transId'                => (string) $trans_id,
						'customer'               => array(
							'merchantCustomerId' => (string) $order->get_id(),
							'email'              => $order->get_billing_email(),
						),
					),
				);

				$http_response = wp_safe_remote_request( $url, $this->get_request_attributes( $request ) );
				if ( is_wp_error( $http_response ) ) {
					WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM profile-from-transaction HTTP error: ' . $http_response->get_error_message() );

					return false;
				}

				$body   = preg_replace( '/\xEF\xBB\xBF/', '', wp_remote_retrieve_body( $http_response ) );
				$result = json_decode( $body, true );

				// Only the customerProfileId is read from the raw response (a top-level scalar — low risk).
				$customer_profile_id = '';
				$result_code         = isset( $result['messages']['resultCode'] ) ? $result['messages']['resultCode'] : '';
				$message_text        = '';
				if ( isset( $result['messages']['message'][0]['text'] ) ) {
					$message_text = $result['messages']['message'][0]['text'];
				} elseif ( isset( $result['messages']['message']['text'] ) ) {
					$message_text = $result['messages']['message']['text'];
				}

				if ( 'Ok' === $result_code && isset( $result['customerProfileId'] ) ) {
					$customer_profile_id = (string) $result['customerProfileId'];
				} elseif ( preg_match( '/ID (\d+) already exists/i', (string) $message_text, $m ) ) {
					// E00039: a profile already exists for this customer/card — reuse the existing id.
					$customer_profile_id = $m[1];
				}

				if ( empty( $customer_profile_id ) ) {
					WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM profile-from-transaction failed (' . $result_code . '): ' . $message_text . '. Upsells unavailable for this order.' );

					return false;
				}

				// Resolve the payment profile via the core gateway's TYPED client instead of hand-parsing the
				// response id-list — this is the value the upsell charge actually needs. get_payment_tokens()
				// returns token objects keyed however the framework likes; the id is $method->get_id().
				$payment_profile_id = '';
				try {
					$methods = $api->get_tokenized_payment_methods( $customer_profile_id )->get_payment_tokens();
					if ( is_array( $methods ) ) {
						foreach ( $methods as $method ) {
							if ( is_object( $method ) && method_exists( $method, 'get_id' ) && ! empty( $method->get_id() ) ) {
								$payment_profile_id = (string) $method->get_id();
							}
						}
					}
				} catch ( Exception $e ) {
					WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM: could not resolve payment profile for ' . $customer_profile_id . ': ' . $e->getMessage() );
				}

				if ( empty( $payment_profile_id ) ) {
					WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM profile ' . $customer_profile_id . ' created but no payment profile resolved; upsells unavailable.' );

					return false;
				}

				$order->update_meta_data( $customer_meta_key, $customer_profile_id );
				$order->update_meta_data( $token_meta_key, $payment_profile_id );
				$order->save_meta_data();

				// Shipping address id is intentionally not fetched here — the upsell charge (create_transaction_request)
				// creates one on demand when missing, so we avoid an extra API call and another parse.

				WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM profile created from transaction ' . $trans_id . ' (profile: ' . $customer_profile_id . ', payment: ' . $payment_profile_id . ').' );

				return true;
			} catch ( \Throwable $e ) {
				WFOCU_Core()->log->log( 'Order: #' . $order->get_id() . ' CIM profile-from-transaction exception: ' . $e->getMessage() );

				return false;
			}
		}

		/**
		 * @param WC_Order $order
		 */
		public function maybe_add_shipping_address_id_order_for_guests( $order ) {
			$payment = $this->get_payment_object( $order );
			if ( isset( $payment->shipping_address_id ) && ! empty( $payment->shipping_address_id ) ) {
				$order->update_meta_data( '_authorize_cim_shipping_address_id', $payment->shipping_address_id );
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
			$refund_data = $_POST;  // phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck

			$txn_id        = isset( $refund_data['txn_id'] ) ? sanitize_text_field( $refund_data['txn_id'] ) : '';
			$amnt          = isset( $refund_data['amt'] ) ? floatval( $refund_data['amt'] ) : 0.0;
			$api           = $this->get_wc_gateway()->get_api();
			$gateway       = $this->get_wc_gateway();
			$refund_reason = isset( $refund_data['refund_reason'] ) ? sanitize_textarea_field( $refund_data['refund_reason'] ) : '';

			// add refund info — stored on the instance to avoid dynamic property writes on WC_Order (PHP 8.2+)
			$this->current_refund           = new stdClass();
			$this->current_refund->amount   = number_format( $amnt, 2, '.', '' );
			$this->current_refund->reason   = $refund_reason;
			$this->current_refund->trans_id = $txn_id;

			if ( version_compare( WC_Authorize_Net_CIM::VERSION, '3.7.2', '>=' ) ) {
				// set token data on the order
				$transaction                     = $api->get_transaction_details( $this->current_refund->trans_id, $order->get_id() );
				$this->current_refund->last_four = $transaction->get_last_four();

				if ( empty( $this->current_refund->last_four ) ) {
					$this->current_refund->last_four = $this->get_wc_gateway()->get_order_meta( $order, 'account_four' );
				}

				$expiry_date = $this->get_wc_gateway()->get_order_meta( $order, 'card_expiry_date' );

				if ( $expiry_date ) {
					$this->current_refund->expiry_date = gmdate( 'm-Y', strtotime( '20' . $expiry_date ) );
				}

				if ( empty( $this->current_refund->expiry_date ) ) {
					$this->current_refund->expiry_date = 'XXXX';
				}
				$response = $api->refund( $order );

				WFOCU_Core()->log->log( 'WFOCU Authorize Offer refund transaction ID' . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$transaction_id = $response->get_transaction_id();

				if ( ! $transaction_id ) {
					$response                           = $api->void( $order );
					$transaction_id                     = $response->get_transaction_id();
					$this->current_refund->wfocu_voided = true;
					WFOCU_Core()->log->log( "WFOCU Authorize Offer void transaction id: $transaction_id response: " . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				}
			} else {
				$this->current_refund->customer_profile_id         = $gateway->get_order_meta( $order, 'customer_id' );
				$this->current_refund->customer_payment_profile_id = $gateway->get_order_meta( $order, 'payment_token' );

				add_filter( 'wc_authorize_net_cim_api_request_data', array( $this, 'wfocu_modify_refund_request_data' ), 10, 2 );

				$response = $api->refund( $order );

				$transaction_id = $response->get_transaction_id();

				WFOCU_Core()->log->log( "WFOCU Authorize Offer refund transaction ID: $transaction_id response: " . print_r( $response, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				if ( ! $transaction_id ) {
					$response                           = $api->void( $order );
					$transaction_id                     = $response->get_transaction_id();
					$this->current_refund->wfocu_voided = true;
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
			if ( isset( $this->current_refund->wfocu_voided ) && true === $this->current_refund->wfocu_voided ) {
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

			if ( isset( $_POST['action'] ) && 'wfocu_admin_refund_offer' === $_POST['action'] ) {  // phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
				WFOCU_Core()->log->log( 'Auth request data: ' . print_r( $request_data, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$refund_data = $_POST;  // phpcs:ignore WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck

				$offer_id = isset( $refund_data['offer_id'] ) ? absint( $refund_data['offer_id'] ) : 0;
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
		 * @param int    $length total desired length of string, including omission
		 * @param string $omission omission text, defaults to '...'
		 *
		 * @return string
		 * @since 2.2.0
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

		/**
		 * Modify the upsell skip reason and order note for Stripe gateway.
		 *
		 * @param WC_Order $order
		 * @param int      $skip_key
		 * @param array    $reason_messages
		 * @param string   $edit_link
		 * @param string   $contact_support
		 * @param string   $upsell_s_link
		 *
		 * @return array
		 */
		public function filter_upsell_skip_reason( $order, $skip_key, $reason_messages, $edit_link, $contact_support, $upsell_s_link ) {
			$custom_note = '';

			// Skip reason 6 = no reusable payment token/profile was created for this order, so the
			// one-click upsell can't be charged. (The primary order itself was charged normally.)
			if ( $skip_key === 6 ) {

				$custom_note = sprintf(
					'<div style="display:flex;align-items:center;margin-bottom:4px;gap:4px;padding-left:20px !important;background: url(%s) no-repeat left !important;">
                    <strong style="font-size:13px;">%s</strong>
                </div>
                <strong>%s</strong>: %s
                <div style="margin:8px 0px;">%s <a href="%s" target="_blank">%s</a></div>',
					esc_url( WFOCU_PLUGIN_URL . '/admin/assets/img/icon_error.svg' ),
					__( 'Upsell Skipped', 'woofunnels-upstroke-one-click-upsell' ),
					__( 'Authorize.Net CIM gateway', 'woofunnels-upstroke-one-click-upsell' ),
					__( 'No reusable payment token was created for this order, so the one-click upsell could not be charged. The original order was charged normally.', 'woofunnels-upstroke-one-click-upsell' ),
					__( 'Ensure tokenization is enabled for Authorize.Net CIM. If the problem persists,', 'woofunnels-upstroke-one-click-upsell' ),
					esc_url( $contact_support ),
					__( 'contact support', 'woofunnels-upstroke-one-click-upsell' )
				);
			}

			return array(
				'skip_id' => $skip_key,
				'note'    => ! empty( $custom_note ) ? $custom_note : ( $reason_messages[ $skip_key ] ?? '' ),
			);
		}
	}


	WFOCU_Gateway_Integration_Authorize_Net_CIM::get_instance();
}
