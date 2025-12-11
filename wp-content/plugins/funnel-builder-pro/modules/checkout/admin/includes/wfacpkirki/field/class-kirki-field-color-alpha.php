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
if ( ! class_exists( 'WFACPKirki_Field_Color_Alpha' ) ) {
	#[AllowDynamicProperties]
	class WFACPKirki_Field_Color_Alpha extends WFACPKirki_Field_Color {

		/**
		 * Sets the $choices
		 *
		 * @access protected
		 */
		protected function set_choices() {

			if ( ! is_array( $this->choices ) ) {
				$this->choices = array();
			}
			$this->choices['alpha'] = true;

		}
	}
}