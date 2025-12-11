<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_wfacpkirki' ) ) {
	#[AllowDynamicProperties]
	class WFACP_wfacpkirki {

		private static $ins = null;

		public function __construct() {
			// Register our custom control with wfacpkirki

			add_filter( 'wfacpkirki/control_types', function ( $controls ) {
				$controls['radio-image-full']      = 'WFACP_Radio_Image_Full';
				$controls['radio-icon']            = 'WFACP_Radio_Icon';
				$controls['radio-image-text']      = 'WFACP_Radio_Image_Text';
				$controls['wfacp-responsive-font'] = 'WFACP_Responsive_Font_Text';

				return $controls;
			} );

			add_action( 'customize_register', function ( $wp_customize ) {

				include_once 'class-wfacp-wfacpkirki-controls.php';
				$wp_customize->register_control_type( 'WFACP_Radio_Image_Full' );
				$wp_customize->register_control_type( 'WFACP_Radio_Icon' );
				$wp_customize->register_control_type( 'WFACP_Radio_Image_Text' );
				$wp_customize->register_control_type( 'WFACP_Responsive_Font_Text' );
			} );
		}

		public static function get_instance() {
			if ( null == self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

	}

	WFACP_wfacpkirki::get_instance();
}
