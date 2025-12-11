<?php
/**
 * Customizer Control: wfocukirki-generic.
 *
 * @package     WFOCUKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCUKirki_Control_Generic' ) ) {
	/**
	 * A generic and pretty abstract control.
	 * Allows for great manipulation using the field's "choices" argumnent.
	 */
	class WFOCUKirki_Control_Generic extends WFOCUKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfocukirki-generic';
	}
}