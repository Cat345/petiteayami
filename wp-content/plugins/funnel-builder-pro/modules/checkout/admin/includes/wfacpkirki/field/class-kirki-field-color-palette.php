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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACPKirki_Field_Color_Palette' ) ) {
	#[\AllowDynamicProperties]
	class WFACPKirki_Field_Color_Palette extends WFACPKirki_Field {

		/**
		 * Sets the control type.
		 *
		 * @access protected
		 */
		protected function set_type() {

			$this->type = 'wfacpkirki-color-palette';
		}

		/**
		 * Sets the sanitize callback for color palette values.
		 *
		 * @access protected
		 */
		protected function set_sanitize_callback() {
			if ( ! empty( $this->sanitize_callback ) ) {
				return;
			}
			$this->sanitize_callback = array( 'WFACPKirki_Sanitize_Values', 'color' );
		}
	}
}
