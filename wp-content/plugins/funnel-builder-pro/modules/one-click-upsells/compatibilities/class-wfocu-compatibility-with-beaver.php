<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Beaver' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Beaver
	 */
	class WFOCU_Compatibility_With_Beaver {

		public function __construct() {
			add_filter( 'fl_builder_post_types', function ( $post_types ) {
				array_push( $post_types, WFOCU_Common::get_offer_post_type_slug() );

				return $post_types;
			}, 999 );
			add_filter( 'wfocu_should_render_script_jquery', array( $this, 'should_prevent_jq_on_editor' ), 10 );

		}

		public function is_enable() {
			return ( class_exists( 'FLBuilderLoader' ) );
		}

		public function should_prevent_jq_on_editor( $bool ) {
			if ( isset( $_GET['fl_builder'] ) ) {
				return false;
			}

			return $bool;
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Beaver(), 'beaver' );
}