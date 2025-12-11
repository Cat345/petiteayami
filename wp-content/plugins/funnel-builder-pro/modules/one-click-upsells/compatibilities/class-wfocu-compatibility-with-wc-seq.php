<?php
/**
 * WooCommerce Sequential Order Numbers Pro
 * SkyVerge
 * */
if ( ! class_exists( 'WFOCU_Compatibility_With_Wc_Seq' ) ) {

	class WFOCU_Compatibility_With_Wc_Seq {
		public function __construct() {
			add_filter( 'wfocu_order_copy_meta_keys', [ $this, 'maybe_update_order_meta' ], 10, 2 );
		}

		public function is_enable() {
			if ( function_exists( 'wc_seq_order_number_pro' ) && class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @param $content
		 *
		 * @return array|mixed|string
		 */
		public function maybe_update_order_meta( $meta, $order ) {
			if ( false === $this->is_enable() ) {
				return $meta;
			}

			if ( ! $order instanceof WC_Order ) {
				return $meta;
			}

			if ( ! class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
				return $meta;
			}

			$obj = WC_Seq_Order_Number_Pro::instance();

			if ( empty( $obj ) ) {
				return $meta;
			}
			/*
			 * update order meta for sequential order id
			 */
			$obj->set_sequential_order_number( $order->get_id() );

			return $meta;

		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Wc_Seq(), 'wc_Seq' );
}



