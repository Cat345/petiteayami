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
if ( ! class_exists( 'WFOCUKirki_Field_Group_Title' ) ) {
	/**
	 * Prior to version 0.8 there was a separate 'group-title' field.
	 * This exists here just for backwards-compatibility purposes.
	 */
	class WFOCUKirki_Field_Group_Title extends WFOCUKirki_Field_Custom {
	}
}