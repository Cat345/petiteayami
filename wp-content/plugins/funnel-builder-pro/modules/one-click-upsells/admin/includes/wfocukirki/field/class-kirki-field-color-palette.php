<?php
/**
 * Override field methods
 *
 * @package     WFOCUKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.3.2
 */
if ( ! class_exists( 'WFOCUKirki_Field_Color_Palette' ) ) {
	/**
	 * Field overrides.
	 */
	class WFOCUKirki_Field_Color_Palette extends WFOCUKirki_Field {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfocukirki-color-palette';

		}
	}
}