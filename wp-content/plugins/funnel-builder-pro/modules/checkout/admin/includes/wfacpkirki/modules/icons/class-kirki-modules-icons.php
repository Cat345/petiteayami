<?php
/**
 * Try to automatically generate the script necessary for adding icons to panels & section
 *
 * @package     WFACPKirki
 * @category    Core
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds scripts for icons in sections & panels.
 */
if ( ! class_exists( 'WFACPKirki_Modules_Icons' ) ) {
	class WFACPKirki_Modules_Icons {

		/**
		 * The object instance.
		 *
		 * @static
		 * @access private
		 * @since 3.0.0
		 * @var object
		 */
		private static $instance;

		/**
		 * An array of panels and sections with icons.
		 *
		 * @static
		 * @access private
		 * @var string
		 */
		private static $icons = array();

		/**
		 * The class constructor.
		 *
		 * @access protected
		 */
		protected function __construct() {
			add_action( 'customize_controls_enqueue_scripts', array( $this, 'customize_controls_enqueue_scripts' ), 99 );
		}

		/**
		 * Gets an instance of this object.
		 * Prevents duplicate instances which avoid artefacts and improves performance.
		 *
		 * @static
		 * @access public
		 * @return object
		 * @since 3.0.0
		 */
		public static function get_instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Adds icon for a section/panel.
		 *
		 * @access public
		 *
		 * @param string $id The panel or section ID.
		 * @param string $icon The icon to add.
		 * @param string $context Lowercase 'section' or 'panel'.
		 *
		 * @since 3.0.0
		 */
		public function add_icon( $id, $icon, $context = 'section' ) {

			self::$icons[ $context ][ $id ] = trim( $icon );

		}

		/**
		 * Format the script in a way that will be compatible with WordPress.
		 */
		public function customize_controls_enqueue_scripts() {

			$sections = WFACPKirki::$sections;
			$panels   = WFACPKirki::$panels;

			// Parse sections and find ones with icons.
			foreach ( $sections as $section ) {
				if ( isset( $section['icon'] ) ) {
					$this->add_icon( $section['id'], $section['icon'], 'section' );
				}
			}

			// Parse panels and find ones with icons.
			foreach ( $panels as $panel ) {
				if ( isset( $panel['icon'] ) ) {
					$this->add_icon( $panel['id'], $panel['icon'], 'panel' );
				}
			}

			wp_enqueue_script( 'wfacpkirki_panel_and_section_icons', trailingslashit( WFACPKirki::$url ) . 'modules/icons/icons.js', array(
				'jquery',
				'customize-base',
				'customize-controls'
			), WFACP_VERSION, true );
			wp_localize_script( 'wfacpkirki_panel_and_section_icons', 'wfacpkirkiIcons', self::$icons );

		}
	}
}