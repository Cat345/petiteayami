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
if ( ! class_exists( 'WFOCUKirki_Field_Preset' ) ) {
	/**
	 * Field overrides.
	 */
	class WFOCUKirki_Field_Preset extends WFOCUKirki_Field_Select {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfocukirki-select';

		}

		/**
		 * Set the preset.
		 *
		 * @access protected
		 * @since 3.0.28
		 */
		protected function set_preset() {

			// Set preset from the choices.
			$this->preset = $this->choices;

			// We're using a flat select.
			foreach ( $this->choices as $key => $args ) {
				$this->choices[ $key ] = $args['label'];
			}
		}
	}
}