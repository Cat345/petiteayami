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
if ( ! class_exists( 'WFOCUKirki_Field_Switch' ) ) {
	/**
	 * Field overrides.
	 */
	class WFOCUKirki_Field_Switch extends WFOCUKirki_Field_Checkbox {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfocukirki-switch';

		}

		/**
		 * Sets the control choices.
		 *
		 * @access protected
		 */
		protected function set_choices() {

			if ( ! is_array( $this->choices ) ) {
				$this->choices = array();
			}

			$this->choices = wp_parse_args( $this->choices, array(
					'on'    => esc_attr__( 'On', 'wfocukirki' ),
					'off'   => esc_attr__( 'Off', 'wfocukirki' ),
					'round' => false,
				) );
		}
	}
}