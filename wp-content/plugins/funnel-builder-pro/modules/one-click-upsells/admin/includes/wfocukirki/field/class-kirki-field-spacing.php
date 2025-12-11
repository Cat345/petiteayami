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
if ( ! class_exists( 'WFOCUKirki_Field_Spacing' ) ) {
	/**
	 * Field overrides.
	 */
	class WFOCUKirki_Field_Spacing extends WFOCUKirki_Field_Dimensions {

		/**
		 * Set the choices.
		 * Adds a pseudo-element "controls" that helps with the JS API.
		 *
		 * @access protected
		 */
		protected function set_choices() {

			$default_args = array(
				'controls' => array(
					'top'    => ( isset( $this->default['top'] ) ),
					'bottom' => ( isset( $this->default['top'] ) ),
					'left'   => ( isset( $this->default['top'] ) ),
					'right'  => ( isset( $this->default['top'] ) ),
				),
				'labels'   => array(
					'top'    => esc_attr__( 'Top', 'wfocukirki' ),
					'bottom' => esc_attr__( 'Bottom', 'wfocukirki' ),
					'left'   => esc_attr__( 'Left', 'wfocukirki' ),
					'right'  => esc_attr__( 'Right', 'wfocukirki' ),
				),
			);

			$this->choices = wp_parse_args( $this->choices, $default_args );

		}
	}
}