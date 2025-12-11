<?php
/**
 * An expanded section.
 *
 * @package     WFACPKirki
 * @subpackage  Custom Sections Module
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.0
 */

/**
 * Expanded Section.
 */
if ( ! class_exists( 'WFACPKirki_Sections_Expanded_Section' ) ) {
	class WFACPKirki_Sections_Expanded_Section extends WP_Customize_Section {

		/**
		 * The section type.
		 *
		 * @access public
		 * @var string
		 */
		public $type = 'wfacpkirki-expanded';

	}
}