<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WFOCU_Compatibility_With_Theme_OceanWP
 * Customizer -> fix color field issue
 */
if ( ! class_exists( 'WFOCU_Compatibility_With_Theme_OceanWP' ) ) {
	class WFOCU_Compatibility_With_Theme_OceanWP {

		public function __construct() {
			add_action( 'after_setup_theme', [ $this, 'actions' ], 99 );
		}

		public function is_enable() {
			if ( class_exists( 'OCEANWP_Theme_Class' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Remove OceanWP scripts from offer customizer editor
		 */
		public function actions() {

			if ( ! $this->is_enable() ) {
				return;
			}
			if ( class_exists( 'WFOCU_Core' ) && class_exists( 'OceanWP_Customizer' ) && WFOCU_Core()->template_loader->is_customizer_preview() ) {
				WFOCU_Common::remove_actions( 'after_setup_theme', 'OceanWP_Customizer', 'register_options' );
				WFOCU_Common::remove_actions( 'customize_controls_print_footer_scripts', 'OceanWP_Customizer', 'customize_panel_init' );
			}
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Theme_OceanWP(), 'oceanewp_theme' );
}

