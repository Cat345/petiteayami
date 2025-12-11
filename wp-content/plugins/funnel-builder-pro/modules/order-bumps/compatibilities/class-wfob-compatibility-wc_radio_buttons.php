<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_WC_variation_btn' ) ) {

	class WFOB_Compatibility_With_WC_variation_btn {
		public function __construct() {

			/* checkout page */
			add_action( 'wp', [ $this, 'dequeue_js' ] );
		}

		public function dequeue_js() {
			if ( class_exists( 'WC_Radio_Buttons' ) && is_checkout() ) {
				global $wp_filter;
				if ( isset( $wp_filter['wp_enqueue_scripts'] ) && ( $wp_filter['wp_enqueue_scripts'] instanceof WP_Hook ) ) {
					$hooks = $wp_filter['wp_enqueue_scripts']->callbacks;
					foreach ( $hooks as $priority => $refrence ) {
						if ( is_array( $refrence ) && count( $refrence ) > 0 ) {
							foreach ( $refrence as $index => $calls ) {
								if ( isset( $calls['function'] ) && is_array( $calls['function'] ) && count( $calls['function'] ) > 0 && ( $calls['function'][0] instanceof WC_Radio_Buttons ) ) {
									unset( $wp_filter['wp_enqueue_scripts']->callbacks[ $priority ][ $index ] );
								}
							}
						}
					}
				}
			}
		}
	}

	new WFOB_Compatibility_With_WC_variation_btn();
}