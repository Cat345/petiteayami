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
if ( ! class_exists( 'WFACPKirki_Field_Textarea' ) ) {
	#[AllowDynamicProperties]
	class WFACPKirki_Field_Textarea extends WFACPKirki_Field_WFACPKirki_Generic {

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