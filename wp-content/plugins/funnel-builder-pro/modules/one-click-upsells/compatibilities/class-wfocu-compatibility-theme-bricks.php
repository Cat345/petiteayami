<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WFOCU_Compatibility_With_Theme_Bricks
 */
if ( ! class_exists( 'WFOCU_Compatibility_With_Theme_Bricks' ) ) {
	class WFOCU_Compatibility_With_Theme_Bricks {

		public function __construct() {
			add_action( 'wfocu_offer_update_template', array( $this, 'update_page_template' ), 99, 1 );
			add_action( 'wfocu_maybe_import_data', array( $this, 'update_page_template_on_import' ), 99, 1 );
		}

		public function is_enable() {
			if ( function_exists( 'bricks_is_builder' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Set default template when bricks theme activated
		 * @param $page_id
		 *
		 * @return void
		 */
		public function update_page_template( $page_id ) {
			if ( true === $this->is_enable() && 'bricks' === get_template() ) {
				update_post_meta( $page_id, '_wp_page_template', '' );
			}
		}

		/**
		 * Set default template when bricks theme activated and upsell created by funnel step
		 * @param $page_id
		 *
		 * @return void
		 */
		public function update_page_template_on_import( $page_id ) {
			if ( true === $this->is_enable() && 'bricks' === get_template() ) {
				update_post_meta( $page_id, '_wp_page_template', '' );
			}
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Theme_Bricks(), 'bricks_theme' );
}

