<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Theme_Nitro' ) ) {

	class WFOCU_Compatibility_With_Theme_Nitro {
		public function __construct() {
			/* checkout page */
			add_filter( 'wr_nitro_theme_options_definition', [ $this, 'remove_panels' ] );
		}

		public function remove_panels( $theme_options ) {

			if ( WFOCU_Core()->template_loader->is_customizer_preview() ) {
				return [];
			}

			return $theme_options;
		}

		public function is_enable() {
			if ( class_exists( 'WR_Nitro' ) ) {
				return true;
			}

			return false;
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Theme_Nitro(), 'nitro' );
}