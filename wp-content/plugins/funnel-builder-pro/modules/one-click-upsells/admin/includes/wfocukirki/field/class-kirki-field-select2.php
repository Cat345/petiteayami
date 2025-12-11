<?php
/**
 * Override field methods
 *
 * @package     WFOCUKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.7
 */
if ( ! class_exists( 'WFOCUKirki_Field_Select2' ) ) {
	/**
	 * This is nothing more than an alias for the WFOCUKirki_Field_Select class.
	 * In older versions of WFOCUKirki there was a separate 'select2' field.
	 * This exists here just for compatibility purposes.
	 */
	class WFOCUKirki_Field_Select2 extends WFOCUKirki_Field_Select {
	}
}