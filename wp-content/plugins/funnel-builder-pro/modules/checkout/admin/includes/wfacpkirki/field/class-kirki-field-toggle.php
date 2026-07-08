<?php
/**
 * Override field methods
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.7
 */

/**
 * Field overrides.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACPKirki_Field_Toggle' ) ) {
	#[\AllowDynamicProperties]
	class WFACPKirki_Field_Toggle extends WFACPKirki_Field_Checkbox {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfacpkirki-toggle';
		}
	}
}
