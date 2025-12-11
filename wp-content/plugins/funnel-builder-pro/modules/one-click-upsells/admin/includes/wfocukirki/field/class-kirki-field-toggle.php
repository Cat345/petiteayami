<?php
/**
 * Override field methods
 *
 * @package     WFOCUKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.7
 */
if ( ! class_exists( 'WFOCUKirki_Field_Toggle' ) ) {
	/**
	 * Field overrides.
	 */
	class WFOCUKirki_Field_Toggle extends WFOCUKirki_Field_Checkbox {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfocukirki-toggle';

		}
	}
}