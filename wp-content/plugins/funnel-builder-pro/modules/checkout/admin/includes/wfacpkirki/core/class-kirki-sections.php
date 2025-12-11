<?php
/**
 * Additional tweaks for sections.
 *
 * @package     WFACPKirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.17
 */

/**
 * Additional tweaks for sections.
 */
if ( ! class_exists( 'WFACPKirki_Sections' ) ) {
	#[AllowDynamicProperties]
	class WFACPKirki_Sections {

		/**
		 * The object constructor.
		 *
		 * @access public
		 * @since 3.0.17
		 */
		public function __construct() {
			add_action( 'customize_controls_print_footer_scripts', array( $this, 'outer_sections_css' ) );
		}

		/**
		 * Generate CSS for the outer sections.
		 * These are by default hidden, we need to expose them.
		 *
		 * @return void
		 * @since 3.0.17
		 */
		public function outer_sections_css() {
			$css = '';
			if ( ! empty( WFACPKirki::$sections ) ) {
				foreach ( WFACPKirki::$sections as $section_args ) {
					if ( isset( $section_args['id'] ) && isset( $section_args['type'] ) && 'outer' === $section_args['type'] || 'wfacpkirki-outer' === $section_args['type'] ) {
						$css .= '#customize-theme-controls li#accordion-section-' . $section_args['id'] . '{display:list-item!important;}';
					}
				}
			}
			if ( ! empty( $css ) ) {
				echo '<style>' . esc_attr( $css ) . '</style>';
			}
		}
	}
}