<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_WPSEO' ) ) {
	class WFOCU_Compatibility_With_WPSEO {

		public function __construct() {
			add_filter( 'wfocu_should_render_scripts', [ $this, 'modify' ], 10, 4 );
		}

		public function is_enable() {
			if ( defined( 'WPSEO_VERSION' ) ) {
				return true;
			}

			return false;
		}

		public function modify( $should_render, $allow_thank_you, $without_offer, $current_action ) {
			if ( false === $this->is_enable() || true === $should_render ) {
				return $should_render;
			}

			if ( $current_action === 'wpseo_head' && ( ( did_action( 'wfocu_front_before_custom_offer_page' ) || did_action( 'wfocu_front_before_single_page_load' ) ) && ( $without_offer === true || ( false === $without_offer && false === WFOCU_Core()->public->is_preview ) ) || ( $allow_thank_you && is_order_received_page() ) ) ) {

				return true;
			}

			return $should_render;

		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_WPSEO(), 'wpseo' );

}

