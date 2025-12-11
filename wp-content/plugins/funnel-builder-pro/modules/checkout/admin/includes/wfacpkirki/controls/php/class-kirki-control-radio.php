<?php
/**
 * Customizer Control: wfacpkirki-radio.
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

/**
 * Radio control
 */
if ( ! class_exists( 'WFACPKirki_Control_Radio' ) ) {
	class WFACPKirki_Control_Radio extends WFACPKirki_Control_Base {

		/**
		 * The control type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfacpkirki-radio';
	}
}