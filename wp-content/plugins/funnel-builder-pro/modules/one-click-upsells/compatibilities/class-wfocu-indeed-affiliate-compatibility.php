<?php
if ( ! class_exists( 'WFOCU_Indeed_Affiliate_WP_Compatibility' ) ) {
	class WFOCU_Indeed_Affiliate_WP_Compatibility {

		public function __construct() {
			add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'wfocu_add_affiliate_on_order' ), 10, 5 );

		}

		public function is_enable() {
			if ( defined( 'UAP_PLUGIN_VER' ) ) {
				return true;
			}

			return false;
		}

		public function wfocu_add_affiliate_on_order( $offer_id, $package, $order, $new_order, $transaction_id ) {
			if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
				define( 'WOOCOMMERCE_CHECKOUT', true );
			}

			if ( class_exists( 'Uap_Woo' ) ) {
				$obj = new Uap_Woo;
				global $indeed_db;
				if ( ! empty( $new_order ) && is_object( $new_order ) ) {
					$order_id = $new_order->get_id();
					$obj->create_referral( $order_id );

					$referral_id = $indeed_db->get_referral_id_for_reference( $order_id );

					WFOCU_Core()->log->log( "Create upsell order with affiliate: referral id #" . $referral_id . ", order id #" . $order_id . "" );

				} else {
					$order_id    = $order->get_id();
					$referral_id = $indeed_db->get_referral_id_for_reference( $order_id );

					if ( $referral_id <= 0 ) {
						return;
					}

					$indeed_db->delete_referrals( $referral_id );
					WFOCU_Core()->log->log( "Delete affiliate referral id and recreate with upsell amount: delete referral id #" . $referral_id . ", order id #" . $order_id . "" );

					$obj->create_referral( $order_id );
					$referral_id = $indeed_db->get_referral_id_for_reference( $order_id );
					WFOCU_Core()->log->log( "New generated referral id #" . $referral_id . ", order id #" . $order_id . "" );
				}
			}

		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Indeed_Affiliate_WP_Compatibility(), 'wfocu_indeed_affiliate_wp' );
}