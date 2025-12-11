<?php
/**
 * Override field methods
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.3.2
 */

/**
 * Field overrides.
 */
if ( ! class_exists( 'WFACPKirki_Field_Dimension' ) ) {
	class WFACPKirki_Field_Dimension extends WFACPKirki_Field {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfacpkirki-dimension';

		}

		/**
		 * Sanitizes the value.
		 *
		 * @access public
		 *
		 * @param string $value The value.
		 *
		 * @return string
		 */
		public function sanitize( $value ) {
			return sanitize_text_field( $value );
		}
	}
}