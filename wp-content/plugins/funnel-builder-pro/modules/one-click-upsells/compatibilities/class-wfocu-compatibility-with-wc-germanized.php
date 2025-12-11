<?php
/**
 * Germanized plugin prevent instant email
 * */
if ( ! class_exists( 'WFOCU_Compatibility_With_WC_Germanized' ) ) {


	class WFOCU_Compatibility_With_WC_Germanized {

		public function __construct() {

			if ( ! $this->is_enable() ) {
				return;
			}
			add_filter( 'woocommerce_gzd_instant_order_confirmation', array( $this, 'restricted_instant_order_confirmation_email' ), 10, 2 );
		}


		public function is_enable() {

			if ( true === defined( 'WC_GERMANIZED_PLUGIN_FILE' ) ) {
				return true;
			}

			return false;
		}

		public function restricted_instant_order_confirmation_email( $instant, $order ) {
			if ( ! $order instanceof WC_Order ) {
				return $instant;
			}

			if ( empty( $order->get_meta( '_wfocu_funnel_id' ) ) ) {
				return $instant;
			}

			return false;
		}

	}
	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_WC_Germanized(), 'wc_germanized' );
}