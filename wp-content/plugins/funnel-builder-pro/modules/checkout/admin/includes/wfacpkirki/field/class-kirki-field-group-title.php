<?php
/**
 * Override field methods
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.7
 */

/**
 * Prior to version 0.8 there was a separate 'group-title' field.
 * This exists here just for backwards-compatibility purposes.
 */
if ( ! class_exists( 'WFACPKirki_Field_Group_Title' ) ) {
	#[AllowDynamicProperties]
	class WFACPKirki_Field_Group_Title extends WFACPKirki_Field_Custom {
	}
}