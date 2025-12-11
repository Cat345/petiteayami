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
if ( ! class_exists( 'WFACPKirki_Field_Color' ) ) {
	class WFACPKirki_Field_Color extends WFACPKirki_Field {

		/**
		 * Backwards compatibility.
		 *
		 * @access protected
		 * @var bool
		 */
		protected $alpha = false;

		/**
		 * Mode (hue)
		 *
		 * @access protected
		 * @var string
		 */
		protected $mode = 'full';

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfacpkirki-color';

		}

		/**
		 * Sets the $choices
		 *
		 * @access protected
		 */
		protected function set_choices() {

			if ( ! is_array( $this->choices ) ) {
				$this->choices = array();
			}
			if ( true === $this->alpha ) {
				_doing_it_wrong( 'WFACPKirki::add_field', esc_attr__( 'Do not use "alpha" as an argument in color controls. Use "choices[alpha]" instead.', 'wfacpkirki' ), '3.0.10' );
				$this->choices['alpha'] = true;
			}
			if ( ! isset( $this->choices['alpha'] ) || true !== $this->choices['alpha'] ) {
				$this->choices['alpha'] = true;
				if ( property_exists( $this, 'default' ) && ! empty( $this->default ) && false === strpos( 'rgba', $this->default ) ) {
					$this->choices['alpha'] = false;
				}
			}

			if ( ( ! isset( $this->choices['mode'] ) ) || ( 'hex' !== $this->choices['mode'] || 'hue' !== $this->choices['mode'] ) ) {
				$this->choices['mode'] = 'hex';
			}
		}

		/**
		 * Sets the $sanitize_callback
		 *
		 * @access protected
		 */
		protected function set_sanitize_callback() {

			// If a custom sanitize_callback has been defined,
			// then we don't need to proceed any further.
			if ( ! empty( $this->sanitize_callback ) ) {
				return;
			}
			if ( 'hue' === $this->mode ) {
				$this->sanitize_callback = 'absint';

				return;
			}
			$this->sanitize_callback = array( 'WFACPKirki_Sanitize_Values', 'color' );

		}
	}
}