<?php
if ( ! class_exists( 'WFOCU_Ultimate_Membership_PRO_Compatibility' ) ) {
	class WFOCU_Ultimate_Membership_PRO_Compatibility {

		public function __construct() {


			add_action( 'init', array( $this, 'maybe_unhook_and_rehook' ) );

		}

		public function is_enable() {
			if ( defined( 'IHCACTIVATEDMODE' ) ) {
				return true;
			}

			return false;
		}

		public function maybe_unhook_and_rehook() {
			if ( ! $this->is_enable() ) {
				return;
			}


			global $wp_filter;
			$obj = null;
			foreach ( $wp_filter['woocommerce_checkout_order_processed']->callbacks as $key => $val ) {

				if ( 10 !== $key ) {
					continue;
				}

				foreach ( $val as $innerval ) {
					if ( isset( $innerval['function'] ) && is_array( $innerval['function'] ) ) {
						if ( is_a( $innerval['function']['0'], 'IhcPaymentViaWoo' ) ) {
							$obj = $innerval['function']['0'];

							remove_action( 'woocommerce_checkout_order_processed', array( $obj, 'create_order' ) );
							break;
						}
					}
				}
			}

			if ( null !== $obj ) {
				add_action( 'woocommerce_order_status_completed', array( $obj, 'create_order' ), 9 ); /// INSERT ORDER, LEVEL

			}
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Ultimate_Membership_PRO_Compatibility(), 'wfocu_ult_mem_pro' );
}