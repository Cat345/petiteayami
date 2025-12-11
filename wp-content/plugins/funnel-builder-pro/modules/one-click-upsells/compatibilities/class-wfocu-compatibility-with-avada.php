<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Avada' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Avada
	 */
	class WFOCU_Compatibility_With_Avada {

		public function __construct() {
			add_filter( 'elementor/frontend/builder_content_data', [ $this, 'remove_avada_parse_elementor_content' ], 8, 2 );
		}

		public function is_enable() {
			return class_exists( 'FusionBuilder' );
		}

		public function remove_avada_parse_elementor_content( $data, $post_id ) {
			if ( $post_id <= 0 ) {
				return $data;
			}

			$post = get_post( $post_id );
			if ( is_null( $post ) || $post->post_type !== 'wfocu_offer' ) {
				return $data;
			}

			if ( class_exists( 'WFOCU_Core' ) ) {
				WFOCU_Common::remove_actions( 'elementor/frontend/builder_content_data', 'FusionBuilder', 'parse_elementor_content' );
			}

			return $data;
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Avada(), 'avada' );
}