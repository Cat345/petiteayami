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
if ( ! class_exists( 'WFACPKirki_Field_Switch' ) ) {
	class WFACPKirki_Field_Switch extends WFACPKirki_Field_Checkbox {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfacpkirki-switch';

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
					'on'    => esc_attr__( 'On', 'wfacpkirki' ),
					'off'   => esc_attr__( 'Off', 'wfacpkirki' ),
					'round' => false,
				) );
		}
	}
}