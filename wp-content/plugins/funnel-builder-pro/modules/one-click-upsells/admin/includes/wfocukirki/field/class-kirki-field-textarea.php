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
if ( ! class_exists( 'WFOCUKirki_Field_Textarea' ) ) {
	/**
	 * Field overrides.
	 */
	class WFOCUKirki_Field_Textarea extends WFOCUKirki_Field_WFOCUKirki_Generic {

		/**
		 * Sets the $choices
		 *
		 * @access protected
		 */
		protected function set_choices() {

			$this->choices = array(
				'element' => 'textarea',
				'rows'    => 5,
			);
		}
	}
}