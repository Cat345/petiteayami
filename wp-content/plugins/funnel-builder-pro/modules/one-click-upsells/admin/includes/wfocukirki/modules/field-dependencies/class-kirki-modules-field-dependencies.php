<?php
/**
 * Automatic field-dependencies scripts calculation for WFOCUKirki controls.
 *
 * @package     WFOCUKirki
 * @category    Modules
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCUKirki_Modules_Field_Dependencies' ) ) {
	/**
	 * Adds styles to the customizer.
	 */
	class WFOCUKirki_Modules_Field_Dependencies {

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
		 * Constructor.
		 *
		 * @access protected
		 * @since 3.0.0
		 */
		protected function __construct() {
			add_action( 'customize_controls_enqueue_scripts', array( $this, 'field_dependencies' ) );
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
		 * Enqueues the field-dependencies script
		 * and adds variables to it using the wp_localize_script function.
		 * The rest is handled via JS.
		 *
		 * @access public
		 * @return void
		 */
		public function field_dependencies() {

			wp_enqueue_script( 'wfocukirki_field_dependencies', trailingslashit( WFOCUKirki::$url ) . 'modules/field-dependencies/field-dependencies.js', array(
				'jquery',
				'customize-base',
				'customize-controls'
			), WFOCU_KIRKI_VERSION, true );
		}
	}
}