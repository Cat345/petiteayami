<?php
/**
 * The default section.
 *
 * @package     WFOCUKirki
 * @subpackage  Custom Sections Module
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.0
 */
if ( ! class_exists( 'WFOCUKirki_Sections_Default_Section' ) ) {
	/**
	 * Default Section.
	 */
	class WFOCUKirki_Sections_Default_Section extends WP_Customize_Section {

		/**
		 * The section type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfocukirki-default';

	}
}