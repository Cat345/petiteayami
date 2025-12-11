<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_GeneratePress' ) ) {
	class WFOCU_Compatibility_With_GeneratePress {

		public function __construct() {

			if ( ! $this->is_enable() ) {
				return;
			}
			add_action( 'customize_register', [ $this, 'wfocu_temp_remove_controls' ], 1500 );
			add_action( 'customize_register', [ $this, 'wfocu_temp_remove_theme_helper_controls' ], 1 );
		}

		/**
		 * @param $wp_customize WP_Customize_Manager
		 */
		public function wfocu_temp_remove_controls( $wp_customize ) {

			if ( function_exists( 'WFOCU_Core' ) && ( class_exists( 'Generate_Typography_Customize_Control' ) || class_exists( 'GeneratePress_Pro_Typography_Customize_Control' ) ) && is_object( WFOCU_Core()->template_loader ) && WFOCU_Core()->template_loader->is_customizer_preview() ) {
				$all_controls = $wp_customize->controls();
				foreach ( $all_controls as $id => $control ) {
					if ( $control instanceof Generate_Typography_Customize_Control || $control instanceof GeneratePress_Pro_Typography_Customize_Control ) {
						$wp_customize->remove_control( $id );

					}
				}
			}
		}

		public function wfocu_temp_remove_theme_helper_controls( $wp_customize ) {
			if ( defined( 'GENERATE_VERSION' ) && is_object( WFOCU_Core()->template_loader ) && WFOCU_Core()->template_loader->is_customizer_preview() ) {
				remove_action( 'customize_register', 'generate_customize_register', 20 );
			}
		}

		public function is_enable() {

			if ( defined( 'GENERATE_VERSION' ) ) {
				return true;
			}

			if ( false === class_exists( 'Generate_Typography_Customize_Control' ) && false === class_exists( 'GeneratePress_Pro_Typography_Customize_Control' ) ) {
				return false;
			}

			return true;
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_GeneratePress(), 'generatepress' );

}