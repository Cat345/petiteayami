<?php
/**
 * Handles CSS output for background-image.
 *
 * @package     WFOCUKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.0
 */
if ( ! class_exists( 'WFOCUKirki_Output_Property_Background_Image' ) ) {
	/**
	 * Output overrides.
	 */
	#[\AllowDynamicProperties]
	class WFOCUKirki_Output_Property_Background_Image extends WFOCUKirki_Output_Property {

		/**
		 * Modifies the value.
		 *
		 * @access protected
		 */
		protected function process_value() {

			if ( is_array( $this->value ) && isset( $this->value['url'] ) ) {
				$this->value = $this->value['url'];
			}
			if ( empty( $this->value ) ) {
				return;
			}
			if ( false === strpos( $this->value, 'gradient' ) ) {
				if ( false === strpos( $this->value, 'url(' ) ) {
					if ( preg_match( '/^\d+$/', $this->value ) ) {
						$this->value = 'url("' . esc_url_raw( set_url_scheme( wp_get_attachment_url( $this->value ) ) ) . '")';
					} else {
						$this->value = 'url("' . esc_url_raw( set_url_scheme( $this->value ) ) . '")';
					}
				} else {
					// Value already contains url() — sanitize the URL inside it.
					$this->value = preg_replace_callback(
						'/url\s*\(\s*["\']?(.*?)["\']?\s*\)/i',
						function ( $m ) {
							return 'url("' . esc_url_raw( $m[1] ) . '")';
						},
						$this->value
					);
				}
			}
		}
	}
}
