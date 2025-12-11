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
if ( ! class_exists( 'WFACPKirki_Field_Radio' ) ) {
	class WFACPKirki_Field_Radio extends WFACPKirki_Field {

		/**
		 * Whitelisting for backwards-compatibility.
		 *
		 * @access protected
		 * @var string
		 */
		protected $mode = '';

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfacpkirki-radio';
			// Tweaks for backwards-compatibility:
			// Prior to version 0.8 radio-buttonset & radio-image were part of the radio control.
			if ( in_array( $this->mode, array( 'buttonset', 'image' ), true ) ) {
				/* translators: %1$s represents the field ID where the error occurs. %2%s is buttonset/image. */
				_doing_it_wrong( __METHOD__, sprintf( esc_attr__( 'Error in field %1$s. The "mode" argument has been deprecated since WFACPKirki v0.8. Use the "radio-%2$s" type instead.', 'wfacpkirki' ), esc_attr( $this->settings ), esc_attr( $this->mode ) ), '3.0.10' );
				$this->type = 'radio-' . $this->mode;
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
			$this->sanitize_callback = 'esc_attr';

		}
	}
}