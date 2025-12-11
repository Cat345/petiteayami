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
if ( ! class_exists( 'WFOCUKirki_Field_Sortable' ) ) {
	/**
	 * Field overrides.
	 */
	class WFOCUKirki_Field_Sortable extends WFOCUKirki_Field {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfocukirki-sortable';

		}

		/**
		 * Sets the $sanitize_callback.
		 *
		 * @access protected
		 */
		protected function set_sanitize_callback() {

			$this->sanitize_callback = array( $this, 'sanitize' );

		}

		/**
		 * Sanitizes sortable values.
		 *
		 * @access public
		 *
		 * @param array $value The checkbox value.
		 *
		 * @return array
		 */
		public function sanitize( $value = array() ) {

			if ( is_string( $value ) || is_numeric( $value ) ) {
				return array(
					esc_attr( $value ),
				);
			}
			$sanitized_value = array();
			foreach ( $value as $sub_value ) {
				if ( isset( $this->choices[ $sub_value ] ) ) {
					$sanitized_value[] = esc_attr( $sub_value );
				}
			}

			return $sanitized_value;

		}
	}
}