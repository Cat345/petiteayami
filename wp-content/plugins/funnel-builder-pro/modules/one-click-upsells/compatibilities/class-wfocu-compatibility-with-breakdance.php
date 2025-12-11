<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WFOCU_Compatibility_With_BreakDance_Builder
 */
if ( ! class_exists( 'WFOCU_Compatibility_With_BreakDance_Builder' ) ) {
	class WFOCU_Compatibility_With_BreakDance_Builder {

		public function __construct() {
			add_filter( 'body_class', array( $this, 'add_break_dance_class' ), 10, 1 );
		}

		public function is_enable() {
			if ( defined( 'BREAKDANCE_WOO_DIR' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Add breakdown class in body html
		 *
		 * @param $body_class
		 *
		 * @return mixed
		 */
		public function add_break_dance_class( $body_class ) {
			global $post;
			if ( is_object( $post ) && $post instanceof WP_Post && $post->post_type === 'wfocu_offer' ) {
				if ( is_array( $body_class ) && count( $body_class ) > 0 ) {
					if ( ! in_array( 'breakdance', $body_class, true ) ) {
						$body_class[] = 'breakdance';
					}
				}
			}

			return $body_class;
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_BreakDance_Builder(), 'bd_builder' );
}
