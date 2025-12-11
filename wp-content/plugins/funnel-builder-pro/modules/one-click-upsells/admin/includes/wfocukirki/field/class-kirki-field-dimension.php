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
if ( ! class_exists( 'WFOCUKirki_Field_Dimension' ) ) {
	/**
	 * Field overrides.
	 */
	class WFOCUKirki_Field_Dimension extends WFOCUKirki_Field {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfocukirki-dimension';

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