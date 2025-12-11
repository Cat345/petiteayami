<?php
/**
 * Customizer Control: cropped-image.
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.23
 */

/**
 * Adds the image control.
 */
if ( ! class_exists( 'WFACPKirki_Control_Cropped_Image' ) ) {
	class WFACPKirki_Control_Cropped_Image extends WP_Customize_Cropped_Image_Control {

		/**
		 * Whitelisting the "required" argument.
		 *
		 * @since 3.0.17
		 * @access public
		 * @var array
		 */
		public $required = array();

		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @since 3.0.23
		 *
		 * @uses WP_Customize_Media_Control::to_json()
		 */
		public function to_json() {
			parent::to_json();
			$this->json['required'] = $this->required;
		}
	}
}