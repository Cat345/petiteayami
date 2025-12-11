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
 * This is nothing more than an alias for the WFACPKirki_Field_Select class.
 * In older versions of WFACPKirki there was a separate 'select2' field.
 * This exists here just for compatibility purposes.
 */
if ( ! class_exists( 'WFACPKirki_Field_Select2_Multiple' ) ) {
	#[AllowDynamicProperties]
	class WFACPKirki_Field_Select2_Multiple extends WFACPKirki_Field_Select {

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