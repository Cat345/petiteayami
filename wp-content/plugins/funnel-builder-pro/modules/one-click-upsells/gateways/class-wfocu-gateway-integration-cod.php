<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_Gateway_Integration_COD' ) ) {
	/**
	 * WFOCU_Gateway_Integration_COD class.
	 *
	 * @extends WFOCU_Gateway
	 */
	#[AllowDynamicProperties]
	class WFOCU_Gateway_Integration_COD extends WFOCU_Gateway {


		protected static $ins = null;
		public $key = 'cod';
		public $token = false;

		/**
		 * Constructor
		 */
		public function __construct() {

			/**
			 * Need to setup upsell on this hook in case of COD as COD success do not run WC_Order::payment_complete();
			 */

			add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'maybe_setup_upsell_for_cod' ), 999, 2 );

			parent::__construct();
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_setup_upsell_for_cod( $order_status, $order ) {

			if ( false === is_a( $order, 'WC_Order' ) ) {
				WFOCU_Core()->log->log( 'No valid order' );

				return $order_status;
			}
			do_action( 'wfocu_front_pre_init_funnel_hooks', $order );
			$get_payment_gateway = WFOCU_WC_Compatibility::get_payment_gateway_from_order( $order );

			$get_integration         = WFOCU_Core()->gateways->get_integration( $get_payment_gateway );
			$this->porder            = WFOCU_WC_Compatibility::get_order_id( $order );
			$get_compatibility_class = WFOCU_Plugin_Compatibilities::get_compatibility_class( 'subscriptions' );
			remove_filter( 'wfocu_front_payment_gateway_integration_enabled', array( $get_compatibility_class, 'maybe_disable_integration_when_subscription_in_cart' ), 10 );

			if ( WFOCU_Core()->data->is_funnel_exists() && $get_integration instanceof WFOCU_Gateway && $get_integration->is_enabled( $order ) && $get_integration->has_token( $order ) ) {

				WFOCU_Core()->public->initiate_funnel = true;
				remove_action( 'wfocu_front_init_funnel_hooks', array( WFOCU_Core()->orders, 'register_order_status_to_primary_order' ), 10 );

				do_action( 'wfocu_front_init_funnel_hooks', $order );

				$order_behavior = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
				$is_batching_on = ( 'batching' === $order_behavior ) ? true : false;

				if ( false === $is_batching_on ) {
					WFOCU_Core()->log->log( 'Avoid changing the order status ' . __FUNCTION__ );

					return $order_status;
				}
				do_action( 'wfocu_front_primary_order_status_change', 'wc-wfocu-pri-order', $order_status, $order );

				return 'wc-wfocu-pri-order';

			} else {
				WFOCU_Core()->log->log( 'by passing upsell' );
				$wc_get_order         = $order;
				$is_funnel_exists     = WFOCU_Core()->data->is_funnel_exists();
				$have_gateway         = $get_integration instanceof WFOCU_Gateway;
				$have_enabled_gateway = $have_gateway && $get_integration->is_enabled( $wc_get_order );
				$has_token            = $have_gateway && $get_integration->has_token( $wc_get_order );
				$run_without_token    = $have_gateway && $get_integration->is_run_without_token();

				if ( false === WFOCU_Core()->session_db->get_skip_id() ) {
					if ( ! $have_gateway ) {
						if ( floatval( 0 ) === floatval( $wc_get_order->get_total() ) ) {
							WFOCU_Core()->session_db->set_skip_id( 3 );
						} else {
							WFOCU_Core()->session_db->set_skip_id( 4 );
						}

					} elseif ( ! $have_enabled_gateway ) {
						WFOCU_Core()->session_db->set_skip_id( 5 );
					} elseif ( ! $has_token && ! $run_without_token ) {
						WFOCU_Core()->session_db->set_skip_id( 6 );

					}
				}
				if ( false === WFOCU_Core()->session_db->get_skip_id() ) {
					WFOCU_Core()->session_db->set_skip_id( 0 );
				}

				WFOCU_Core()->public->upsell_skip_reason( $wc_get_order );

				WFOCU_Core()->log->log( 'Order #' . $this->porder . ' Details for skip given below ' . print_r( array( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						'have_funnel'          => $is_funnel_exists,
						'have_gateway'         => $have_gateway,
						'get_payment_method'   => $order->get_payment_method(),
						'have_enabled_gateway' => $have_enabled_gateway,
						'has_token'            => $has_token,
						'run_wihtout_token'    => $run_without_token,
					), true ) );
			}

			return $order_status;
		}

		/**
		 * Try and get the payment token saved by the gateway
		 *
		 * @param WC_Order $order
		 *
		 * @return true on success false otherwise
		 */
		public function has_token( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

			return true;

		}

		public function process_charge( $order ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter,VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

			$is_successful = true;

			return $this->handle_result( $is_successful, '' );
		}


	}


	WFOCU_Gateway_Integration_COD::get_instance();
}