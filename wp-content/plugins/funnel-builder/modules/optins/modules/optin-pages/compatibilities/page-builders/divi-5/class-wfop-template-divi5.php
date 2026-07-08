<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WFOP_Template_Divi5' ) ) {
	/**
	 * Class WFOP_Template_Divi5
	 * This class used as wrapper class for the Divi 5 JSON templates during the rendering of the template
	 * In woofunnels template design structure every template inherits WFOP_Template_Common so we need Divi 5 templates to follow the same structure
	 */
	#[\AllowDynamicProperties]
	class WFOP_Template_Divi5 extends WFOP_Template_Common {

		private static $ins = null;

		public function __construct() {
			parent::__construct();
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}
	}

	return WFOP_Template_Divi5::get_instance();
}
