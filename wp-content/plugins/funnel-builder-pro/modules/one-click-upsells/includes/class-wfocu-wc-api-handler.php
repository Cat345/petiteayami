<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_WC_API_Handler' ) ) {
	/**
	 * Class to Handle API calls using wc-api
	 * Class WFOCU_Templates_Retriever
	 */
	class WFOCU_WC_API_Handler {

		/** @var null */
		private static $ins = null;

		private $api_name = 'wfocu_actions';

		/**
		 * WFOCU_Templates_Retriever constructor.
		 */
		public function __construct() {

			add_action( 'woocommerce_api_' . $this->api_name, array( $this, 'handle_call' ) );
		}

		/**
		 * @return WFOCU_WC_API_Handler|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function handle_call() {

			$get_action = filter_input( INPUT_GET, 'action', FILTER_UNSAFE_RAW );

			if ( false === in_array( $get_action, $this->get_api_actions(), true ) ) {
				/**
				 * some other api call, discard here
				 */
				return;
			}

			if ( true === is_callable( array( $this, $get_action ) ) ) {
				WFOCU_Core()->log->log( "API endpoint: " . $get_action );
				call_user_func( array( $this, $get_action ) );
			}
			die();
		}

		public function get_api_actions() {
			return array( 'api_health_check', 'offer_expiry', 'end_funnel', 'maybe_normalize_orders' );
		}

		public function api_health_check() {
			WFOCU_Core()->log->log( 'API request received' );
		}

		public function maybe_normalize_orders() {
			do_action( 'fk_fb_every_4_minute' );
		}

		/**
		 * @hooked over `woocommerce_api_wfocu_actions`
		 * Check if we have requested for recording offer expiration and/or redirection to the next offer
		 */
		public function offer_expiry() {

			if ( false === WFOCU_Core()->data->has_valid_session() ) {
				return;
			}

			if ( ( isset( $_GET['log_event'] ) && 'no' === $_GET['log_event'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended

				$get_current_offer = WFOCU_Core()->data->get_current_offer();

				$get_type_of_offer    = WFOCU_Core()->data->get( '_current_offer_type' );
				$get_type_index_offer = WFOCU_Core()->data->get( '_current_offer_type_index' );

				$get_order = WFOCU_Core()->data->get_current_order();
				$args      = array(
					'order_id'         => WFOCU_WC_Compatibility::get_order_id( $get_order ),
					'funnel_id'        => WFOCU_Core()->data->get_funnel_id(),
					'offer_id'         => $get_current_offer,
					'funnel_unique_id' => WFOCU_Core()->data->get_funnel_key(),
					'offer_type'       => $get_type_of_offer,
					'offer_index'      => $get_type_index_offer,
					'email'            => WFOCU_Core()->data->get( 'useremail' ),
				);
				do_action( 'wfocu_offer_expired_event', $args );
			}

			/**
			 * get the next upsell
			 */
			$get_offer    = apply_filters( 'wfocu_get_redirect_url_after_expire', WFOCU_Core()->offers->get_the_next_offer( 'yes' ) );
			$redirect_url = WFOCU_Core()->public->get_the_upsell_url( $get_offer );

			WFOCU_Core()->data->set( 'current_offer', $get_offer );
			WFOCU_Core()->data->save();

			WFOCU_Core()->log->log( 'Offer expired API call received : ' . $redirect_url );

			wp_redirect( $redirect_url );
			die();
		}

		/**
		 * @hooked over `woocommerce_api_wfocu_actions`
		 * handle end funnel through api request
		 */
		public function end_funnel() {

			if ( false === WFOCU_Core()->data->has_valid_session() ) {
				return;
			}

			$get_received_url = WFOCU_Core()->public->get_clean_order_received_url( true );
			wp_redirect( $get_received_url );
			die();
		}

		/**
		 * Providing next offer URL when with expired action to redirection in wc-api
		 *
		 * @param string $action
		 * @param array $args
		 *
		 * @return string
		 */
		public function get_api_url( $action = '', $args = array() ) {

			return add_query_arg( array_merge( $args, array(
				'action' => $action,
			) ), WC()->api_request_url( $this->api_name ) );

		}

	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'wc_api', 'WFOCU_WC_API_Handler' );
	}
}