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
if ( ! class_exists( 'WFACPKirki_Field_Spacing' ) ) {
	#[AllowDynamicProperties]
	class WFACPKirki_Field_Spacing extends WFACPKirki_Field_Dimensions {

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
					'top'    => esc_attr__( 'Top', 'wfacpkirki' ),
					'bottom' => esc_attr__( 'Bottom', 'wfacpkirki' ),
					'left'   => esc_attr__( 'Left', 'wfacpkirki' ),
					'right'  => esc_attr__( 'Right', 'wfacpkirki' ),
				),
			);

			$this->choices = wp_parse_args( $this->choices, $default_args );

		}
	}
}