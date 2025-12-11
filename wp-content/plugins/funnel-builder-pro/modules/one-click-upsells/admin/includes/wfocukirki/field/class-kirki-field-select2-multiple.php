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
if ( ! class_exists( 'WFOCUKirki_Field_Select2_Multiple' ) ) {
	/**
	 * This is nothing more than an alias for the WFOCUKirki_Field_Select class.
	 * In older versions of WFOCUKirki there was a separate 'select2' field.
	 * This exists here just for compatibility purposes.
	 */
	class WFOCUKirki_Field_Select2_Multiple extends WFOCUKirki_Field_Select {

		/**
		 * Sets the $multiple
		 *
		 * @access protected
		 */
		protected function set_multiple() {

			$this->multiple = 999;

		}
	}
}