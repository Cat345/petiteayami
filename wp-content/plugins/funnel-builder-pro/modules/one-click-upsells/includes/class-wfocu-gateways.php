<?php
if ( ! class_exists( 'WFOCU_Gateways' ) ) {
	/**
	 * Handles the operations and usage of gateways in the one click upsell
	 * Class WFOCU_Gateways
	 */
	class WFOCU_Gateways {

		private static $ins = null;

		/**
		 * @var WFOCU_Gateways[]
		 */
		public $integrations = array();
		public $gateway_dir_path = '/gateways/';
		public $class_prefix = 'WFOCU_Gateway_Integration_';


		public function __construct() {

			spl_autoload_register( array( $this, 'integration_autoload' ) );

			add_action( 'wp_loaded', array( $this, 'load_gateway_integrations' ), 5 );

			add_filter( 'woocommerce_payment_gateways', array( $this, 'maybe_add_test_payment_gateway' ), 11 );

			/**
			 * API receiving hook to catch the paypal standard calls response and process billing agreement creation
			 */
			add_action( 'woocommerce_api_wfocu_paypal', array( $this, 'maybe_handle_paypal_api_call' ) );
			add_action( 'woocommerce_api_wfocu_paypal_payments', array( $this, 'handle_paypal_payments_api' ) );
			add_action( 'woocommerce_api_wfocu_mollie_ideal_payments', array( $this, 'handle_mollie_ideal_payments_api' ) );
			add_action( 'woocommerce_api_wfocu_paypal_ppec', array( $this, 'maybe_handle_paypal_ppec_api_call' ) );
			add_action( 'woocommerce_api_wfocu_paypal_return_payment', array( $this, 'maybe_handle_paypal_return_api_call' ) );

			add_filter( 'wfocu_common_default_options', array( $this, 'maybe_unset_default_values' ), 10, 2 );

			add_action( 'wp_ajax_nopriv_wfocu_front_create_express_checkout_token', array( $this, 'create_express_checkout_token' ), 10 );
			add_action( 'wp_ajax_wfocu_front_create_express_checkout_token', array( $this, 'create_express_checkout_token' ), 10 );

			add_action( 'wc_ajax_wfocu_front_create_express_checkout_token', array( $this, 'create_express_checkout_token' ), 10 );

			add_action( 'wp_ajax_nopriv_wfocu_front_create_express_checkout_token_ppec', array( $this, 'create_express_checkout_token_ppec' ), 10 );
			add_action( 'wp_ajax_wfocu_front_create_express_checkout_token_ppec', array( $this, 'create_express_checkout_token_ppec' ), 10 );
			add_action( 'wc_ajax_wfocu_front_create_express_checkout_token_ppec', array( $this, 'create_express_checkout_token_ppec' ), 10 );
			add_action( 'wc_ajax_wfocu_front_handle_stripe_payments', array( $this, 'handle_stripe_payments' ), 10 );
			add_action( 'wc_ajax_wfocu_front_handle_wcpay_payments', array( $this, 'handle_wcpay_payments' ), 10 );

			add_filter( 'wfocu_global_settings', array( $this, 'filter_values_gateways' ), 10, 1 );
			add_action( 'valid-paypal-standard-ipn-request', array( $this, 'handle_paypal_ipn_and_record_response' ), - 1 );
			add_action( 'woocommerce_paypal_express_checkout_valid_ipn_request', array( $this, 'handle_paypal_ipn_and_record_response' ), - 1 );

			add_filter( 'wfocu_front_order_status_after_funnel', array( $this, 'replace_recorded_status_with_ipn_response' ), 10, 2 );
			add_action( 'wfocu_after_normalize_order_status', array( $this, 'modify_paypal_ipn_hold_status' ), 10, 1 );


			add_action( 'wc_ajax_wfocu_front_handle_paypal_payments', array( $this, 'handle_paypal_payments' ) );
			add_action( 'wc_ajax_wfocu_front_handle_paypal_payments', array( $this, 'handle_paypal_payments' ) );

		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_handle_paypal_api_call() {
			$this->get_integration( 'paypal' )->maybe_create_billing();
			$this->get_integration( 'paypal' )->handle_api_calls();
		}

		public function handle_paypal_payments() {
			$this->get_integration( 'ppcp-gateway' )->process_client_order();
		}

		/**
		 * @param string $wc_payment_gateway gateway key in woocommerce
		 *
		 * @return bool|WFOCU_Gateway
		 */
		public function get_integration( $wc_payment_gateway ) {

			$get_supported_gateways = $this->get_supported_gateways();
			if ( is_array( $get_supported_gateways ) && count( $get_supported_gateways ) > 0 && array_key_exists( $wc_payment_gateway, $get_supported_gateways ) ) {
				return $this->get_integration_object( $get_supported_gateways[ $wc_payment_gateway ] );
			}

			return false;
		}

		public function get_supported_gateways() {


			$supported_gateways = array(
				'wfocu_test',
				'cod',
				'bacs',
				'cheque',
				'stripe',
				'authorize_net_cim_credit_card',
				'ppec_paypal',
				'paypal',
				'braintree_credit_card',
				'braintree_paypal',
				'square_credit_card',
				'ppcp-gateway',
				'woocommerce_payments',
				'fkwcs_stripe',
				'fkwcs_stripe_sepa',
				'fkwcs_stripe_affirm',
				'fkwcs_stripe_klarna',
				'fkwcs_stripe_afterpay',
				'fkwcs_stripe_p24',
				'fkwcs_stripe_google_pay',
				'fkwcs_stripe_apple_pay',
				'fkwcs_stripe_alipay',
				'funnelkit_square',
				'braintree',
				'paypal_express',
				'paypal_pro_payflow',
				'nmi_gateway_woocommerce_credit_card',
				'mollie_wc_gateway_creditcard',
				'mollie_wc_gateway_ideal',
				'mollie_wc_gateway_sofort',
				'mollie_wc_gateway_bancontact',
				'cardcom',
				'takbull',
				'payfast',
				'sagepaydirect',
				'paylike',
				'WC_Gateway_Worldpay',
				'wc_gateway_bluesnap_cc',
				'ebanx-credit-card-br',
				'ebanx-credit-card-co',
				'ebanx-credit-card-ar',
				'ebanx-credit-card-mx',
				'eway',
				'bankjp',
				'netbilling',
				'nmi',
				'offline_cc',
				'omise',
				'revcent_payments',
				'dobirka',
				'icredit_payment',
				'payplus-payment-gateway',
				'ppcp',
				'ppcp_card',
				'braintree_cc',
				'braintree_googlepay',
				'braintree_applepay',
				'braintree_venmo',
				'stripe_cc',
				'stripe_googlepay',
				'stripe_applepay',
				'stripe_payment_request',
				'stripe_upm',
				'angelleye_ppcp',
				'angelleye_ppcp_cc',
				'wc_cloudpayments_gateway',
				'frontit_spreedly',
				'wc_ryftpay',
				'fkwcsq_square',
				'fkwcsq_google_pay',
				'fkwcsq_apple_pay',
				'fkwcs_stripe_multibanco',
				'fkwcs_stripe_pix',
				'fkwcs_stripe_cashapp',
				'fkwcppcp_paypal',
				'airwallex_card'
			);

			$gateways = apply_filters( 'wfocu_wc_get_supported_gateways', array(
				'wfocu_test'                    => 'WFOCU_Gateway_Integration_WFOCU_Test',
				'cod'                           => 'WFOCU_Gateway_Integration_COD',
				'bacs'                          => 'WFOCU_Gateway_Integration_Bacs',
				'cheque'                        => 'WFOCU_Gateway_Integration_Cheque',
				'stripe'                        => 'WFOCU_Gateway_Integration_Stripe',
				'mollie_wc_gateway_sofort'      => 'WFOCU_Gateway_Integration_Mollie_Gateway_Sofort',
				'mollie_wc_gateway_bancontact'  => 'WFOCU_Gateway_Integration_Mollie_Gateway_Bancontact',
				'mollie_wc_gateway_creditcard'  => 'WFOCU_Gateway_Integration_Mollie_Gateway_Credit_Cards',
				'mollie_wc_gateway_ideal'       => 'WFOCU_Gateway_Integration_Mollie_Gateway_Ideal',
				'authorize_net_cim_credit_card' => 'WFOCU_Gateway_Integration_Authorize_Net_CIM',
				'ppec_paypal'                   => 'WFOCU_Gateway_Integration_Paypal_Express_Checkout',
				'paypal'                        => 'WFOCU_Gateway_Integration_PayPal_Standard',
				'braintree_credit_card'         => 'WFOCU_Gateway_Integration_Braintree_CC',
				'braintree_paypal'              => 'WFOCU_Gateway_Integration_Braintree_PayPal',
				'square_credit_card'            => 'WFOCU_Gateway_Integration_Square_Credit_Card',
				'ppcp-gateway'                  => 'WFOCU_Gateway_Integration_PayPal_Payments',
				'woocommerce_payments'          => 'WFOCU_Gateway_Integration_WooCommerce_Payments',
			) );


			/**
			 * restrict support for all the known gateway integrations
			 */
			$gateways_keys        = array_keys( $gateways );
			$intersected_gateways = array_intersect( $supported_gateways, $gateways_keys );

			$filtered_gateways = array();
			foreach ( $intersected_gateways as $key ) {
				$filtered_gateways[ $key ] = $gateways[ $key ];
			}


			return $filtered_gateways;
		}

		/**
		 * @param $class_name
		 *
		 * @return Mixed|WFOCU_Gateway
		 */
		public function get_integration_object( $class_name ) {
			if ( isset( $this->integrations[ $class_name ] ) ) {
				return $this->integrations[ $class_name ];
			}

			$this->integrations[ $class_name ] = call_user_func( array( $class_name, 'get_instance' ) );

			return $this->integrations[ $class_name ];
		}

		public function maybe_handle_paypal_ppec_api_call() {

			$this->get_integration( 'ppec_paypal' )->handle_api_calls();
		}

		public function maybe_handle_paypal_return_api_call() {

			$this->get_integration( 'paypal' )->handle_return_api();
		}

		/**
		 * Auto-loading the payment classes as they called.
		 *
		 * @param $class_name
		 */
		public function integration_autoload( $class_name ) {

			if ( false !== strpos( $class_name, $this->class_prefix ) ) {

				require_once WFOCU_PLUGIN_DIR . $this->gateway_dir_path . 'class-' . WFOCU_Common::slugify_classname( $class_name ) . '.php';  // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingCustomConstant

			}
		}

		/**
		 * @param $available_gateways
		 *
		 * @return mixed
		 */
		public function load_gateway_integrations() {

			$available_gateways = $this->get_supported_gateways();
			if ( false === is_array( $available_gateways ) ) {
				return $available_gateways;
			}
			$supported = array_keys( $available_gateways );
			foreach ( $supported as $key ) {

				$this->get_integration( $key );
			}

			return $available_gateways;
		}

		public function maybe_add_test_payment_gateway( $gateways ) {

			WFOCU_Core()->data->setup_options();

			$is_gateway_on = WFOCU_Core()->data->get_option( 'gateway_test' );


			if ( is_array( $is_gateway_on ) && count( $is_gateway_on ) > 0 && 'yes' === $is_gateway_on[0] ) {

				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'includes/class-wfocu-test-gateway.php';
				$gateways[] = 'WC_Gateway_WFOCU_Test';

			}



			return $gateways;
		}


		/**
		 * @hooked over 'wfocu_common_default_options'
		 * Handles few edge cases when we need to unset gateway in case user has it all unchecked/unavailable
		 *
		 * @param $options Options from Default values
		 * @param $options_raw Options from Database
		 *
		 * @return mixed
		 */
		public function maybe_unset_default_values( $options, $options_raw ) {

			if ( $options_raw && is_array( $options_raw ) && ! isset( $options_raw['gateways'] ) ) {
				/**
				 * This is never occurring scenario as database set cannot be unavailable, it will always have some gateways or a blank array
				 * Still handle this scenario by returning blank array so that blank array will get carry forward
				 */
				$options['gateways'] = [];
			}

			return $options;
		}

		public function add_default_gateways_enable( $defaults ) {
			$get_supported_available = $this->get_gateways_list();

		if ( isset( $defaults['gateways'] ) && is_array( $get_supported_available ) && count( $get_supported_available ) > 0 ) {

				$get_keys_list = wp_list_pluck( $get_supported_available, 'value' );

				$defaults['gateways'] = $get_keys_list;
			} else {
				$defaults['gateways'] = array();
			}

			return $defaults;

		}

		/**
		 * Get the Gateway list with nice names
		 * @return array
		 */
		public function get_gateways_list() {
			$get_supported = $this->get_supported_gateways();

			unset( $get_supported['wfocu_test'] );

			$available_gateways               = WC()->payment_gateways->payment_gateways();
			$get_supported_available_gateways = array_keys( array_intersect_key( $get_supported, $available_gateways ) );

			$result = array_map( function ( $short ) use ( $available_gateways ) {
				if ( 'yes' === $available_gateways[ $short ]->enabled ) {
					return array(
						'name'  => $available_gateways[ $short ]->get_method_title(),
						'value' => $short,
					);
				}

			}, $get_supported_available_gateways );

			$result = array_filter( $result );
			$result = array_values( $result );

			return $result;
		}

		public function create_express_checkout_token() {
			$this->get_integration( 'paypal' )->create_express_checkout_token();
		}

		public function create_express_checkout_token_ppec() {
			$this->get_integration( 'ppec_paypal' )->create_express_checkout_token();
		}

		public function handle_stripe_payments() {
			$this->get_integration( 'stripe' )->process_client_payment();
		}

		public function handle_wcpay_payments() {
			$this->get_integration( 'woocommerce_payments' )->process_client_payment();
		}

		public function handle_paypal_payments_api() {

			$this->get_integration( 'ppcp-gateway' )->handle_api_calls();
		}

		public function handle_mollie_ideal_payments_api() {

			$this->get_integration( 'mollie_wc_gateway_ideal' )->handle_api_calls();
		}

		public function filter_values_gateways( $settings ) {
			if ( isset( $settings['gateways'] ) && ! empty( $settings['gateways'] ) ) {

				$settings['gateways'] = array_values( $settings['gateways'] );
			}

			return $settings;
		}

		/**
		 * Save important data from the IPN to the order.
		 *
		 * @param array $posted Posted data.
		 * @param WC_Order $order Order object.
		 */
		public function save_paypal_meta_data( $posted, $order ) {
			if ( ! empty( $posted['payment_type'] ) ) {
				$order->update_meta_data( 'Payment type', wc_clean( $posted['payment_type'] ) );
			}
			if ( ! empty( $posted['txn_id'] ) ) {
				$order->set_transaction_id( wc_clean( $posted['txn_id'] ) );
			}
			if ( ! empty( $posted['payment_status'] ) ) {
				$order->update_meta_data( '_paypal_status', wc_clean( $posted['payment_status'] ) );
			}
			$order->save_meta_data();
		}

		/**
		 * There was a valid response.
		 *
		 * @param array $posted Post data after wp_unslash.
		 */
		public function handle_paypal_ipn_and_record_response( $posted ) {

			WFOCU_Core()->log->log( 'Data collected from IPN' . print_r( $posted, true ) );   // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			remove_action( 'woocommerce_pre_payment_complete', [ WFOCU_Core()->public, 'maybe_setup_upsell' ], 99 );
			if ( ! isset( $posted['custom'] ) ) {
				WFOCU_Core()->log->log( 'IPN Doesn\'t have the correct data to proceed.' . print_r( $posted, true ) );   // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			}
			$custom   = json_decode( $posted['custom'] );
			$order_id = 0;
			if ( $custom && is_object( $custom ) ) {
				$order_id = $custom->order_id;
			}
			$order = wc_get_order( $order_id );
			if ( $order && $order instanceof WC_Order && isset( $posted['payment_status'] ) && in_array( $order->get_payment_method(), [ 'paypal', 'ppec_paypal', 'paypal_express' ], true ) ) {


				$order->update_meta_data( '_wfocu_paypal_ipn_status', $posted['payment_status'] );
				$order->save_meta_data();

				if ( 'paypal' === $order->get_payment_method() ) {
					add_action( 'woocommerce_payment_complete', [ $this->get_integration( 'paypal' ), 'maybe_refund_after_ipn' ], 999 );

				}
			}

			/**
			 * We need to manage this paid status thing as during upstroke run paypal standard code reading the IPN & setting up status accordingly
			 * So we need to prevent this.
			 */
			if ( $order && $order instanceof WC_Order && 'paypal_pro_payflow' === $order->get_payment_method() ) {
				add_filter( 'woocommerce_order_is_paid_statuses', function ( $stasuses ) {
					array_push( $stasuses, 'wfocu-pri-order' );

					return $stasuses;
				} );
			}

			if ( $order instanceof WC_Order ) {
				$gateway_integration = WFOCU_Core()->gateways->get_integration( $order->get_payment_method() );
				if ( true === is_callable( array( $gateway_integration, 'handle_ipn' ) ) ) {
					$gateway_integration->handle_ipn( $order, $posted );
				}
			}
		}


		/**
		 * @param $status
		 * @param WC_Order $order
		 */
		public function replace_recorded_status_with_ipn_response( $status, $order ) {

			$get_meta = $order->get_meta( '_wfocu_paypal_ipn_status', true );

			if ( empty( $get_meta ) ) {
				$get_meta = $order->get_meta( '_paypal_status', true );

			}
			if ( empty( $get_meta ) ) {

				$get_meta = $order->get_meta( '_wfocu_paypal_ipn_status' );
			}
			if ( empty( $get_meta ) ) {
				return $status;
			}

			switch ( $get_meta ) {
				case 'Completed':
				case 'completed':
				case 'pending':
				case 'Pending':
					return apply_filters( 'woocommerce_payment_complete_order_status', $order->needs_processing() ? 'processing' : 'completed', $order->get_id(), $order );
				case 'failed':
				case 'Failed':
				case 'denied':
				case 'Denied':
				case 'Expired':
				case 'expired':
					return 'failed';

			}

			return $status;
		}

		public function modify_paypal_ipn_hold_status( $order ) {
			$get_paypal_ipn_hold_meta = $order->get_meta( '_wfocu_paypal_hold_ipn', true );
			if ( 'funnel' === $get_paypal_ipn_hold_meta ) {
				$order->update_meta_data( '_wfocu_paypal_hold_ipn', 'order', true );
				$order->save_meta_data();
			}
		}


	}


	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'gateways', 'WFOCU_Gateways' );
	}
}
