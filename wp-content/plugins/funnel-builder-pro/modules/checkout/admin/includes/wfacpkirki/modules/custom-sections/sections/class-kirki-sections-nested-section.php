<?php
/**
 * Nested section.
 *
 * @package     WFACPKirki
 * @subpackage  Custom Sections Module
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       2.2.0
 */

/**
 * Nested section.
 */
if ( ! class_exists( 'WFACPKirki_Sections_Nested_Section' ) ) {
	class WFACPKirki_Sections_Nested_Section extends WP_Customize_Section {

		/**
		 * The parent section.
		 *
		 * @access public
		 * @since 3.0.0
		 * @var string
		 */
		public $section;

		/**
		 * The section type.
		 *
		 * @access public
		 * @since 3.0.0
		 * @var string
		 */
		public $type = 'wfacpkirki-nested';

		/**
		 * Gather the parameters passed to client JavaScript via JSON.
		 *
		 * @access public
		 * @return array The array to be exported to the client as JSON.
		 * @since 3.0.0
		 */
		public function json() {

			$array = wp_array_slice_assoc( (array) $this, array(
				'id',
				'description',
				'priority',
				'panel',
				'type',
				'description_hidden',
				'section',
			) );

			$array['title']          = html_entity_decode( $this->title, ENT_QUOTES, get_bloginfo( 'charset' ) );
			$array['content']        = $this->get_content();
			$array['active']         = $this->active();
			$array['instanceNumber'] = $this->instance_number;

			$array['customizeAction'] = esc_attr__( 'Customizing', 'wfacpkirki' );
			if ( $this->panel ) {
				/* translators: The title. */
				$array['customizeAction'] = sprintf( esc_attr__( 'Customizing &#9656; %s', 'wfacpkirki' ), esc_html( $this->manager->get_panel( $this->panel )->title ) );
			}

			return $array;
		}
	}
}