<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Compatibility_WC_Deposit' ) ) {
	class WFOB_Compatibility_WC_Deposit {
		public function __construct() {
			add_action( 'wfob_analytics_custom_order_status', [ $this, 'add_custom_order_status' ] );
			add_filter( 'wfob_maybe_update_order', [ $this, 'maybe_update_parent_order' ] );
		}

		public function add_custom_order_status( $status ) {
			if ( ! class_exists( '\Webtomizer\WCDP\WC_Deposits' ) ) {
				return $status;
			}
			$status[] = 'partially-paid';

			return $status;
		}

		public function maybe_update_parent_order( $order ) {

			if ( ! class_exists( '\Webtomizer\WCDP\WC_Deposits' ) ) {
				return $order;
			}

			if ( ! $order instanceof WC_Order ) {
				return $order;
			}

			if ( $order && $order->get_type() === 'wcdp_payment' ) {
				$order = wc_get_order( $order->get_parent_id() );
			}

			return $order;

		}

	}

	new WFOB_Compatibility_WC_Deposit();
}