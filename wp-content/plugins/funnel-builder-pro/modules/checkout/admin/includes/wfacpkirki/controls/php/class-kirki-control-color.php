<?php
/**
 * Customizer Control: color.
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACPKirki_Control_Color' ) ) {
	/**
	 * Adds a color & color-alpha control
	 *
	 * @see https://github.com/23r9i0/wp-color-picker-alpha
	 */
	class WFACPKirki_Control_Color extends WFACPKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfacpkirki-color';

		/**
		 * Colorpicker palette
		 *
		 * @access public
		 * @var bool
		 */
		public $palette = true;

		/**
		 * Mode.
		 *
		 * @since 3.0.12
		 * @var string
		 */
		public $mode = 'full';

		/**
		 * Refresh the parameters passed to the JavaScript via JSON.
		 *
		 * @access public
		 */
		public function to_json() {
			parent::to_json();

			$this->json['palette']          = $this->palette;
			$this->json['choices']['alpha'] = ( isset( $this->choices['alpha'] ) && $this->choices['alpha'] ) ? 'true' : 'false';
			$this->json['mode']             = $this->mode;
		}
	}
}