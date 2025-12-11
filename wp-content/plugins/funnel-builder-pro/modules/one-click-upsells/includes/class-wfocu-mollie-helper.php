<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Mollie\Api\MollieApiClient;

/**
 * Class WFOCU_Mollie_Helper
 */
if ( ! class_exists( 'WFOCU_Mollie_Helper' ) ) {
	class WFOCU_Mollie_Helper {

		/**
		 * @var
		 */
		public static $instance;
		public static $slug;

		public $gateway_dir_path = '/gateways/';
		public $class_prefix = 'WFOCU_Gateway_Integration_';
		public $container;
		private $is_funnel_setup = false;

		/**
		 * WFOCU_Mollie_Helper constructor.
		 * @throws Exception
		 */
		public function __construct() {
			$this->init_constants();
			//Including gateways integration files
			spl_autoload_register( array( $this, 'wfocu_mollie_integration_autoload' ) );
			add_action( 'wfocu_loaded', array( $this, 'init_hooks' ) );

		}

		/**
		 * Initializing constants
		 */
		public function init_constants() {
			self::$slug = 'upstroke-woocommerce-one-click-upsell-mollie';
		}


		/**
		 * Auto-loading the payment classes as they called.
		 *
		 * @param $class_name
		 */
		public function wfocu_mollie_integration_autoload( $class_name ) {

			if ( false !== strpos( $class_name, $this->class_prefix ) ) {
				require_once WFOCU_PLUGIN_DIR . $this->gateway_dir_path . 'class-' . WFOCU_Common::slugify_classname( $class_name ) . '.php';
			}
		}

		/**
		 * @return WFOCU_Mollie_Helper
		 * @throws Exception
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Adding functions on hooks
		 */
		public function init_hooks() {

			require_once 'class-wfocu-mollie-helper-compat.php'; // @codingStandardsIgnoreLine
			// Initialize Localization
			add_action( 'init', array( $this, 'wfocu_mollie_localization' ) );


			//Adding mollie gateways on global settings on upstroke admin page
			add_filter( 'wfocu_wc_get_supported_gateways', array( $this, 'wfocu_mollie_integration' ), 10, 1 );

			/**
			 * On API Mollie return try to setup the funnel so that we always know to only setup a funnel when order completed
			 */
			add_action( 'init', function () {
				/**
				 * Below we are trying to find the container that will be responsible to access all the other methods
				 * Right now this seems like a hack but will see if we found a better way to get the container
				 */

				global $wp_filter;
				foreach ( $wp_filter['woocommerce_payment_gateways'][10] as $val ) {
					$closure = $val['function'];
					if ( ! $closure instanceof \Closure ) {
						continue;
					}
					$func     = new ReflectionFunction( $closure );
					$all_vars = $func->getStaticVariables();
					if ( is_array( $all_vars ) && array_key_exists( 'container', $all_vars ) && ( 'Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Container\ReadOnlyContainer' === get_class( $all_vars['container'] ) || 'Inpsyde\Modularity\Container\ReadOnlyContainer' === get_class( $all_vars['container'] ) || 'Mollie\Inpsyde\Modularity\Container\ReadOnlyContainer' === get_class( $all_vars['container'] ) ) ) {

						$this->container = $all_vars['container'];
					}

				}
				add_action( WFOCU_Mollie_Helper_Compat::get_plugin_id( $this->container ) . '_customer_return_payment_success', array( $this, 'maybe_setup_funnel' ) );
				add_filter( WFOCU_Mollie_Helper_Compat::get_plugin_id( $this->container ) . '_return_url', array( $this, 'maybe_append_si_to_return_url' ) );
			}, 999 );
			/**
			 * Hook over every gateway to handle webhook activity for the primary order
			 */
			add_action( 'woocommerce_api_mollie_wc_gateway_directdebit', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_belfius', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_kbc', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_giropay', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_paypal', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_klarnasliceit', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_klarnapaynow', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_klarnapaylater', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_in3', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_applepay', array( $this, 'removeWebhookaction' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_ideal', array( $this, 'onWebhookActionIdeal' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_sofort', array( $this, 'onWebhookActionSofort' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_creditcard', array( $this, 'onWebhookActionCC' ), - 1 );
			add_action( 'woocommerce_api_mollie_wc_gateway_bancontact', array( $this, 'onWebhookActionbancontact' ), - 1 );


			//Adding order note on receiving response from Mollie Credit card (Live and test modes)
			add_action( 'woocommerce_api_batch_mollie_wc_gateway_creditcard', array( $this, 'onBatchWebhookAction_Processor_Credit_Cards' ), - 1 );

			//Adding order note on receiving response from Mollie Ideal (Live modes)
			add_action( 'woocommerce_api_batch_mollie_wc_gateway_ideal', array( $this, 'onBatchWebhookAction_Processor_Ideal' ), - 1 );

			//Adding order note on receiving response from Mollie Ideal (Live modes)
			add_action( 'woocommerce_api_batch_mollie_wc_gateway_sofort', array( $this, 'onBatchWebhookAction_Processor_Sofort' ), - 1 );

			//Adding order note on receiving response from Mollie Bancontact (Live modes)
			add_action( 'woocommerce_api_batch_mollie_wc_gateway_bancontact', array( $this, 'onBatchWebhookAction_Processor_Bancontact' ), - 1 );


			//Storing dismiss notice in usermeta
			add_action( 'admin_init', array( $this, 'wfocu_mollie_notice_dismissed_function' ) );

			/**
			 * Just after normalizing the order we need to run important
			 */
			add_action( 'wfocu_after_normalize_order_status', array( $this, 'maybe_handle_ipn_stasuses' ), 10, 1 );

			add_action( 'woocommerce_order_status_pending_to_failed', array( $this, 'maybe_mark_failed_in_upsell_record' ) );

		}

		public static function wfocu_mollie_localization() {
			load_plugin_textdomain( self::$slug, false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
		}

		/**
		 * Adding gateways name for choosing on UpStroke global settings page
		 */
		public function wfocu_mollie_integration( $gateways ) {
			$get_mollie_gateways = $this->get_mollie_gateways();
			$gateways            = array_merge( $gateways, $get_mollie_gateways );


			return $gateways;
		}

		/**
		 * Receiving mollie webhook response for credit card payments
		 */
		public function onBatchWebhookAction_Processor_Credit_Cards() {
			$wc_mollie_cc = WFOCU_Gateway_Integration_Mollie_Gateway_Credit_Cards::get_instance();
			$wc_mollie_cc->onBatchWebhookAction();
		}

		/**
		 * Receiving mollie webhook response for Ideal payments
		 */
		public function onBatchWebhookAction_Processor_Ideal() {
			$wc_mollie_ideal = WFOCU_Gateway_Integration_Mollie_Gateway_Ideal::get_instance();
			$wc_mollie_ideal->onBatchWebhookAction();
		}

		/**
		 * Receiving mollie webhook response for Sofort payments
		 */
		public function onBatchWebhookAction_Processor_Sofort() {
			$wc_mollie_sofort = WFOCU_Gateway_Integration_Mollie_Gateway_Sofort::get_instance();
			$wc_mollie_sofort->onBatchWebhookAction();
		}

		/**
		 * Receiving mollie webhook response for Bancontact payments
		 */
		public function onBatchWebhookAction_Processor_Bancontact() {
			$wc_mollie_bancontact = WFOCU_Gateway_Integration_Mollie_Gateway_Bancontact::get_instance();
			$wc_mollie_bancontact->onBatchWebhookAction();
		}


		/**
		 * Storing dismiss notice in user meta
		 */
		public function wfocu_mollie_notice_dismissed_function() {
			$user_id = get_current_user_id();
			if ( ! empty( $user_id ) && isset( $_GET['wfocu_mollie_notice_dismissed'] ) ) { // @codingStandardsIgnoreLine
				add_user_meta( $user_id, 'wfocu_mollie_notice_dismissed', 'true', true );
			}
		}

		/**
		 * @param $order
		 *
		 * @return mixed
		 */
		public static function wfocu_create_mollie_customer_for_order( $order ) {
			$billing_first_name = WFOCU_WC_Compatibility::get_billing_first_name( $order );
			$billing_last_name  = WFOCU_WC_Compatibility::get_billing_last_name( $order );
			$billing_email      = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' );


			try {

				$settings_helper = WFOCU_Mollie_Helper_Compat::get_settings_helper( WFOCU_Mollie_Helper::instance()->container );

				$customer = WFOCU_Mollie_Helper_Compat::get_api_client( WFOCU_Mollie_Helper::instance()->container, $settings_helper->isTestModeEnabled() )->customers->create( array(
					'name'     => trim( $billing_first_name ),
					'email'    => trim( $billing_email ),
					'metadata' => array( 'Last Name' => $billing_last_name ),
				) );
				if ( ! empty( $customer->id ) ) {
					$order->update_meta_data( '_mollie_customer_id', $customer->id );
					$order->save();

					return $customer->id;
				}

			} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
				WFOCU_Core()->log->log( "Coundn't create a mollie customer: " . wp_json_encode( $e->getMessage(), true ) );
			}

			return false;
		}

		/**
		 * @param WC_Order $order
		 */
		public function maybe_setup_funnel( $order ) {
			WFOCU_Core()->log->log( __FUNCTION__ );
			/**
			 * Restricted attempt upsell setup only once
			 */
			if ( ( true === $this->is_funnel_setup ) ) {
				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' already attempt upsell for setup' );

				return;
			}

			/**
			 * In this case we have to initiate the funnel manually and we do not need to wait for payment complete to perform the action.
			 */
			WFOCU_Core()->public->maybe_setup_upsell( $order->get_id() );

			$this->is_funnel_setup = true;

			$is_during_upsell = $order->get_meta( '_wfocu_upsell_abandoned', true );
			$funnel_id        = $order->get_meta( '_wfocu_funnel_id', true );

			if ( empty( $is_during_upsell ) && ! empty ( $funnel_id ) ) {
				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' restricted upsell not setup after complete order for mollie ' );

				return;
			}

			$order_behavior   = WFOCU_Core()->funnels->get_funnel_option( 'order_behavior' );
			$is_batching_on   = ( 'batching' === $order_behavior ) ? true : false;
			$order_new_object = wc_get_order( $order->get_id() );
			if ( true === $is_batching_on && 0 !== did_action( 'wfocu_front_init_funnel_hooks' ) ) {
				WFOCU_Core()->orders->maybe_set_funnel_running_status( $order_new_object );
			}


			$get_current_offer = WFOCU_Core()->data->get_current_offer();

			if ( empty( $get_current_offer ) && 0 === did_action( 'wfocu_front_init_funnel_hooks' ) && ( 'wfocu-pri-order' !== $order->get_status() ) ) {
				/**
				 * funnel is not ready to run, unlock webhook receiver to enable webhook actions
				 */
				$get_gateway_integration = WFOCU_Core()->gateways->get_integration( $order->get_payment_method() );
				if ( $get_gateway_integration instanceof WFOCU_Gateway && $get_gateway_integration->is_enabled( $order ) ) {
					$get_gateway_integration->unlock_webhook_receival( $order );
				}

			}
			$gateway = wc_get_payment_gateway_by_order( $order );

			if ( ! empty( $get_current_offer ) && 0 === did_action( 'wfocu_front_init_funnel_hooks' ) ) {
				WFOCU_Core()->log->log( "Order: #" . $order->get_id() . ' Mollie force opening upsells url for the multi redirect case ' );
				$get_upsell_url = WFOCU_Core()->public->get_the_upsell_url( $get_current_offer );
				wp_redirect( $get_upsell_url );
				exit();
			}
			$returnRedirect = $gateway->get_return_url( $order );
			wp_redirect( $returnRedirect );
			exit();

		}

		public function removeWebhookaction() {
			remove_action( 'woocommerce_pre_payment_complete', [ WFOCU_Core()->public, 'maybe_setup_upsell' ], 99 );
		}

		public function onWebhookActionIdeal() {
			remove_action( 'woocommerce_pre_payment_complete', [ WFOCU_Core()->public, 'maybe_setup_upsell' ], 99 );
			$get_integration = WFOCU_Core()->gateways->get_integration( 'mollie_wc_gateway_ideal' );
			$get_integration->onWebhookAction();
		}

		public function onWebhookActionSofort() {
			remove_action( 'woocommerce_pre_payment_complete', [ WFOCU_Core()->public, 'maybe_setup_upsell' ], 99 );
			$get_integration = WFOCU_Core()->gateways->get_integration( 'mollie_wc_gateway_sofort' );
			$get_integration->onWebhookAction();
		}

		public function onWebhookActioncc() {
			remove_action( 'woocommerce_pre_payment_complete', [ WFOCU_Core()->public, 'maybe_setup_upsell' ], 99 );
			$get_integration = WFOCU_Core()->gateways->get_integration( 'mollie_wc_gateway_creditcard' );
			$get_integration->onWebhookAction();
		}

		public function onWebhookActionbancontact() {
			remove_action( 'woocommerce_pre_payment_complete', [ WFOCU_Core()->public, 'maybe_setup_upsell' ], 99 );
			$get_integration = WFOCU_Core()->gateways->get_integration( 'mollie_wc_gateway_bancontact' );
			$get_integration->onWebhookAction();
		}

		/**
		 * @param WC_Order $order
		 * @param $status
		 * @param string $action
		 */
		public function maybe_handle_ipn_stasuses( $order ) {


			$gateway = $order->get_payment_method();
			if ( false === $this->is_mollie_gateway( $gateway ) ) {
				WFOCU_Core()->log->log( "not a mollie gateway in the process." );

				return;
			}
			$get_meta = $order->get_meta( '_wfocu_mollie_hold_ipn', true );
			if ( 'yes' !== $get_meta ) {
				WFOCU_Core()->log->log( "meta not found" );

				return;
			}

			$payment_ID              = $order->get_meta( '_mollie_order_id', true );
			$payment_ID              = ( empty( $payment_ID ) ) ? $order->get_meta( '_mollie_payment_id', true ) : $payment_ID;
			$get_gateway_integration = WFOCU_Core()->gateways->get_integration( $gateway );
			$get_gateway_integration->onWebhookActionDelayed( $order, $payment_ID );
			$get_gateway_integration->unlock_webhook_receival( $order );

		}

		public function is_mollie_gateway( $gateway ) {
			$get_mollie_gateways = $this->get_mollie_gateways();
			if ( in_array( $gateway, array_keys( $get_mollie_gateways ), true ) ) {
				return true;
			}

			return false;

		}

		public function get_mollie_gateways() {
			$gateways                                 = [];
			$gateways['mollie_wc_gateway_creditcard'] = 'WFOCU_Gateway_Integration_Mollie_Gateway_Credit_Cards';
			$gateways['mollie_wc_gateway_ideal']      = 'WFOCU_Gateway_Integration_Mollie_Gateway_Ideal';
			$gateways['mollie_wc_gateway_sofort']     = 'WFOCU_Gateway_Integration_Mollie_Gateway_Sofort';
			$gateways['mollie_wc_gateway_bancontact'] = 'WFOCU_Gateway_Integration_Mollie_Gateway_Bancontact';

			return $gateways;
		}

		public function maybe_append_si_to_return_url( $return_url ) {
			$return_url = add_query_arg( array( 'wfocu-si' => WFOCU_Core()->data->get_transient_key() ), $return_url );

			return $return_url;
		}

		public function maybe_mark_failed_in_upsell_record( $order_id ) {
			$event_id = WFOCU_Core()->track->query_results( array(
				'data'       => array(),
				'where_meta' => array(
					array(
						'type'       => 'meta',
						'meta_key'   => '_new_order',
						'meta_value' => $order_id,
						'operator'   => '=',
					),
				),
				'order_by'   => 'events.id DESC',
				'query_type' => 'get_var',
			) );
			if ( empty( $event_id ) ) {
				return;
			}
			global $wpdb;
			$event = $wpdb->get_row( "SELECT * FROM " . $wpdb->prefix . "wfocu_event WHERE id = " . $event_id . " AND object_type = 'offer'" );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! empty( $event ) ) {
				$sess_total = $wpdb->get_var( "SELECT total FROM " . $wpdb->prefix . "wfocu_session WHERE id = " . $event->sess_id ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sess_total = $sess_total - $event->value;
				$wpdb->update( $wpdb->prefix . "wfocu_event", [ 'action_type_id' => WFOCU_DB_Track::OFFER_PAYMENT_FAILED_ACTION_ID ], [ 'id' => $event_id, 'object_type' => 'offer' ] );
				$wpdb->update( $wpdb->prefix . "wfocu_session", [ 'total' => $sess_total ], [ 'id' => $event->sess_id ] );
			}
		}

		/**
		 * Display a notice to deactivate the FunnelKit Mollie Integration plugin.
		 */
		public function wfocu_mollie_plugin_deactivate_notice() {
			if ( ! current_user_can( 'deactivate_plugins' ) ) {
				return;
			}
			$deactivate_url = 'plugins.php?action=deactivate' . '&amp;plugin=' . rawurlencode( 'upstroke-woocommerce-one-click-upsell-mollie/upstroke-woocommerce-one-click-upsell-mollie.php' );
			$deactivate_url = wp_nonce_url( $deactivate_url, 'deactivate-plugin_' . 'upstroke-woocommerce-one-click-upsell-mollie/upstroke-woocommerce-one-click-upsell-mollie.php' );

			?>
            <div class="notice notice-error is-dismissible">
                <p>
					<?php
					echo wp_kses_post( sprintf( /* translators: %s: Deactivate URL */ __( '<strong>FunnelKit Mollie Integration:</strong> Compatibility of Mollie Payments with FunnelKit One Click Upsells is now part of Funnel Builder Pro. You can safely deactivate and uninstall <strong>FunnelKit One Click Upsell for Mollie</strong>. <a class="button button-secondary" href="%s">Deactivate Now</a>', 'upstroke-woocommerce-one-click-upsell-mollie' ), esc_url( $deactivate_url ) ) );
					?>
                </p>
            </div>
			<?php
		}


	}

	WFOCU_Mollie_Helper::instance();
}