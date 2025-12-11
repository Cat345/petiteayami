<?php
if ( ! class_exists( 'WFOCU_Template_Elementor' ) ) {
	/**
	 * Class WFOCU_Template_Elementor
	 * This class used as wrapper class for the elementor JSON templates during the rendering of the template
	 * In woofunnels template design structure every template inherits WFOCU_Template_Common so we need elementor templates to follow the same structure
	 *  */
	class WFOCU_Template_Elementor extends WFOCU_Template_Common {

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

	return WFOCU_Template_Elementor::get_instance();
}
