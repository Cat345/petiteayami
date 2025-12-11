<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_UX_Builder' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_UX_Builder
	 */
	class WFOCU_Compatibility_With_UX_Builder {

		public function __construct() {
			add_action( 'init', function () {
				if ( $this->is_enable() ) {
					add_ux_builder_post_type( 'wfocu_offer' );
				}
			} );


		}

		public function is_enable() {
			if ( function_exists( 'add_ux_builder_post_type' ) ) {
				return true;
			}

			return false;
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_UX_Builder(), 'ux_builder' );
}