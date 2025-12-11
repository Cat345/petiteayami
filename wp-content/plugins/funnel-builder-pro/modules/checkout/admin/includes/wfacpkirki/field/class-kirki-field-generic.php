<?php
/**
 * Override field methods
 *
 * @package     WFACPKirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.3.2
 */

/**
 * This is simply an alias for the WFACPKirki_Field_WFACPKirki_Generic class.
 */
if ( ! class_exists( 'WFACPKirki_Field_Generic' ) ) {
	#[AllowDynamicProperties]
	class WFACPKirki_Field_Generic extends WFACPKirki_Field_WFACPKirki_Generic {
	}
}