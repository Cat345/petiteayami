<?php
if ( ! class_exists( 'WFOCU_WFOCUKirki' ) ) {
	class WFOCU_WFOCUKirki {

		private static $ins = null;

		public function __construct() {
			// Register our custom control with WFOCUKirki
			add_filter( 'wfocukirki/control_types', function ( $controls ) {
				$controls['radio-image-full']      = 'WFOCU_Radio_Image_Full';
				$controls['radio-icon']            = 'WFOCU_Radio_Icon';
				$controls['radio-image-text']      = 'WFOCU_Radio_Image_Text';
				$controls['wfocu-responsive-font'] = 'WFOCU_Responsive_Font_Text';

				return $controls;
			} );

			add_action( 'customize_register', function ( $wp_customize ) {
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'includes/class-wfocu-wfocukirki-controls.php';
				$wp_customize->register_control_type( 'WFOCU_Radio_Image_Full' );
				$wp_customize->register_control_type( 'WFOCU_Radio_Icon' );
				$wp_customize->register_control_type( 'WFOCU_Radio_Image_Text' );
				$wp_customize->register_control_type( 'WFOCU_Responsive_Font_Text' );
			} );
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

	}

	WFOCU_WFOCUKirki::get_instance();
}