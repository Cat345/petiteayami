<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_WCVA' ) ) {
	class WFOCU_Compatibility_With_WCVA {

		public function __construct() {

			add_action( 'wfocu_offer_setup_completed', array( $this, 'maybe_unhook' ) );

		}


		public function is_enable() {
			if ( true === class_exists( 'wcva_direct_variation_link' ) ) {
				return true;
			}

			return false;
		}

		public function maybe_unhook() {

			if ( $this->is_enable() ) {

				global $wp_filter;
				if ( ! isset( $wp_filter['woocommerce_product_get_default_attributes'] ) ) {
					return;
				}

				foreach ( $wp_filter['woocommerce_product_get_default_attributes']->callbacks as $key => $val ) {

					if ( 10 !== $key ) {
						continue;
					}
					foreach ( $val as $innerval ) {
						if ( isset( $innerval['function'] ) && is_array( $innerval['function'] ) ) {
							if ( is_a( $innerval['function']['0'], 'wcva_direct_variation_link' ) ) {
								$mk_customizer = $innerval['function']['0'];
								remove_action( 'woocommerce_product_get_default_attributes', array( $mk_customizer, 'wcva_direct_variation_valueues' ) );
								break;
							}
						}
					}
				}


			}

		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_WCVA(), 'wcva' );


}