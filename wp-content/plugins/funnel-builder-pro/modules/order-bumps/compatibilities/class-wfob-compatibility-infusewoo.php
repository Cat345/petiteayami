<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Compatibility_With_Active_InfuseWooPro' ) ) {
	class WFOB_Compatibility_With_Active_InfuseWooPro {
		public function __construct() {

			/* checkout page */
			add_action( 'wfob_product_switcher_price_data', [ $this, 'price_data' ], 1, 2 );

		}

		/**
		 * @param $price
		 * @param $pro WC_Product
		 *
		 * @return mixed
		 */
		public function price_data( $price, $pro ) {
			if ( defined( 'INFUSEDWOO_PRO_VER' ) ) {
				$infusionsoft_sub = $pro->get_meta( 'infusionsoft_sub' );
				if ( ! empty( $infusionsoft_sub ) && ( $infusionsoft_sub = absint( $infusionsoft_sub ) ) > 0 ) {

					$infusionsoft_trial       = $pro->get_meta( 'infusionsoft_trial' );
					$infusionsoft_sign_up_fee = $pro->get_meta( 'infusionsoft_sign_up_fee' );

					if ( $infusionsoft_trial > 0 ) {
						$price['regular_org'] = $pro->get_regular_price();
						if ( $infusionsoft_sign_up_fee > 0 ) {
							$price['price'] = $infusionsoft_sign_up_fee;
						} else {
							$price['price'] = 0;
						}
					}
				}
			}

			return $price;
		}

	}

	new WFOB_Compatibility_With_Active_InfuseWooPro();
}