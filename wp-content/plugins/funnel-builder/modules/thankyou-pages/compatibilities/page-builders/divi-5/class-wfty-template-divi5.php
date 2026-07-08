<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFTY_Template_Divi5' ) ) {
	/**
	 * Wrapper for Thank You Page Divi 5 templates.
	 */
	#[\AllowDynamicProperties]
	class WFTY_Template_Divi5 {
		private static $ins = null;

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}
			return self::$ins;
		}
	}
	return WFTY_Template_Divi5::get_instance();
}
