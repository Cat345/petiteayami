<?php
/**
 * Customizer Control: wfocukirki-select.
 *
 * @package     WFOCUKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCUKirki_Control_Select' ) ) {
	/**
	 * Select control.
	 */
	class WFOCUKirki_Control_Select extends WFOCUKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfocukirki-select';

		/**
		 * Placeholder text.
		 *
		 * @access public
		 * @since 3.0.21
		 * @var string|false
		 */
		public $placeholder = false;

		/**
		 * Maximum number of options the user will be able to select.
		 * Set to 1 for single-select.
		 *
		 * @access public
		 * @var int
		 */
		public $multiple = 1;

		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @see WP_Customize_Control::to_json()
		 */
		public function to_json() {
			parent::to_json();

			$this->json['multiple']    = $this->multiple;
			$this->json['placeholder'] = $this->placeholder;
		}
	}
}