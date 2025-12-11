<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Thrive' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Thrive
	 */
	class WFOCU_Compatibility_With_Thrive {

		public function __construct() {


			add_filter( 'wfocu_should_render_script_jquery', array( $this, 'should_prevent_jq_on_editor' ), 10 );
			add_action( 'template_redirect', array( $this, 'prevent_changing_template' ), 1000 );
		}

		public function is_enable() {
			if ( defined( 'TVE_PLUGIN_FILE' ) ) {
				return true;
			}

			return false;
		}

		public function should_prevent_jq_on_editor( $bool ) {
			if ( isset( $_GET['tve'] ) ) {
				return false;
			}

			return $bool;
		}

		public function prevent_changing_template() {
			if ( ! $this->is_enable() ) {
				return;
			}
			global $post;
			if ( $post instanceof WP_Post && WFOCU_Common::get_offer_post_type_slug() === get_post_type( $post ) && tve_post_is_landing_page( $post->ID ) ) {
				WFOCU_Core()->template_loader->maybe_validate_offer();
				remove_filter( 'template_include', array( WFOCU_Core()->template_loader, 'maybe_load' ), 98 ); //phpcs:ignore WordPressVIPMinimum.Variables.VariableAnalysis.UndefinedVariable

			}
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Thrive(), 'thrive' );
}