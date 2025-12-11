<?php
/**
 * Providing compatibility with cost of goods plugin
 *
 * Class WFOCU_Compatibility_With_WC_COG
 */
if ( ! class_exists( 'WFOCU_Compatibility_With_WC_COG' ) ) {
	class WFOCU_Compatibility_With_WC_COG {

		public function __construct() {
			add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'wfocu_maybe_update_cost_of_goods' ), 10, 5 );
		}

		public function is_enable() {
			if ( class_exists( 'WC_COG' ) ) {
				return true;
			}

			return false;
		}

		public function wfocu_maybe_update_cost_of_goods( $offer_id, $package, $order, $new_order, $transaction_id ) {
			if ( ! function_exists( 'wc_cog' ) ) {
				return;
			}
			$order_id = WFOCU_WC_Compatibility::get_order_id( $order );
			if ( $new_order instanceof WC_Order ) {
				$order_id = WFOCU_WC_Compatibility::get_order_id( $new_order );
			}
			if ( $order_id ) {
				wc_cog()->set_order_cost_meta( $order_id );
			}

		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_WC_COG(), 'wfocu_wc_cog' );
}