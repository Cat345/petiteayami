<?php
/**
 * Class WFOCU_Template_Bricks
 * In woofunnels template design structure every template inherits WFOCU_Template_Common,so we need bricks templates to follow the same structure
 *
 *  */
if ( ! class_exists( 'WFOCU_Template_Bricks' ) ) {
	class WFOCU_Template_Bricks extends WFOCU_Template_Common {
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

	return WFOCU_Template_Bricks::get_instance();
}