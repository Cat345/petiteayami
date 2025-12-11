<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_FBA' ) ) {
	class WFOCU_Compatibility_With_FBA {

		public function __construct() {

			add_action( 'wfocu_front_init_funnel_hooks', array( $this, 'prevent_fba_fulfilment' ) );
			add_action( 'woocommerce_payment_complete', array( $this, 'prevent_fba_fulfilment_multiple' ), - 1 );
			add_action( 'woocommerce_thankyou', array( $this, 'maybe_execute_fulfilment' ) );

		}


		public function is_enable() {
			if ( true === class_exists( 'NS_FBA' ) ) {
				return false;
			}

			return true;
		}

		public function prevent_fba_fulfilment() {

			if ( class_exists( 'NS_FBA' ) ) {
				$fba = NS_FBA::get_instance();
				if ( empty( $fba ) ) {
					return;
				}
				if ( class_exists( 'NS_MCF_Fulfillment' ) ) {
					remove_action( 'woocommerce_payment_complete', array( $fba, 'create_fulfillment_order' ) );
					remove_action( 'woocommerce_payment_complete_order_status_processing', array( $fba, 'check_create_fulfillment_order' ) );
				}

				if ( version_compare( $fba->version, '3.3.6', '>=' ) ) {
					remove_action( 'woocommerce_payment_complete', array( $fba->outbound, 'maybe_send_fulfillment_order' ) );
					remove_action( 'woocommerce_payment_complete_order_status_processing', array( $fba->outbound, 'maybe_send_fulfillment_order' ) );

				} else {
					remove_action( 'woocommerce_payment_complete', array( $fba->outbound, 'send_fulfillment_order' ) );

				}
			}

		}

		public function prevent_fba_fulfilment_multiple( $order_id ) {
			$get_order         = wc_get_order( $order_id );
			$already_attempted = $get_order->get_meta( '_wfocu_fba_attempted', true );
			if ( 'yes' === $already_attempted ) {
				$fba = NS_FBA::get_instance();

				if ( empty( $fba ) ) {
					return;
				}
				if ( class_exists( 'NS_MCF_Fulfillment' ) ) {
					remove_action( 'woocommerce_payment_complete', array( $fba, 'create_fulfillment_order' ) );
					remove_action( 'woocommerce_payment_complete_order_status_processing', array( $fba, 'check_create_fulfillment_order' ) );
				}

				if ( version_compare( $fba->version, '3.3.6', '>=' ) ) {
					remove_action( 'woocommerce_payment_complete', array( $fba->outbound, 'maybe_send_fulfillment_order' ) );
					remove_action( 'woocommerce_payment_complete_order_status_processing', array( $fba->outbound, 'maybe_send_fulfillment_order' ) );
				} else {
					remove_action( 'woocommerce_payment_complete', array( $fba->outbound, 'send_fulfillment_order' ) );
				}


			}
		}

		/**
		 * Extra handling for paypal scenarios to manually send fulfillment for the cases when payment_complete restricted but order status change applies
		 *
		 * @param WC_Order $order_id
		 */
		public function maybe_execute_fulfilment( $order_id ) {

			$get_order        = wc_get_order( $order_id );
			$is_during_upsell = $get_order->get_meta( '_wfocu_upsell_abandoned', true );


			if ( class_exists( 'NS_FBA' ) && ! empty( $is_during_upsell ) ) {

				if ( 'paypal' === $get_order->get_payment_method() && ! $get_order->is_paid() ) {
					return;
				}
				$get_order->update_meta_data( '_wfocu_fba_attempted', 'yes' );
				$get_order->save_meta_data();

				if ( class_exists( 'NS_MCF_Fulfillment' ) ) {
					$fba = new NS_MCF_Fulfillment( NS_FBA::get_instance() );

					if ( ! empty( $fba ) ) {
						$fba = new NS_MCF_Fulfillment( NS_FBA::get_instance() );
						$fba->post_fulfillment_order( $get_order );
					}
				} else {
					$fba = NS_FBA::get_instance();
					$fba->outbound->send_fulfillment_order( $order_id );
				}
			}

		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_FBA(), 'fba' );
}