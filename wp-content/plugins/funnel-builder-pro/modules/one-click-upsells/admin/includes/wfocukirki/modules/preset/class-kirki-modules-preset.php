<?php
/**
 * Automatic preset scripts calculation for WFOCUKirki controls.
 *
 * @package     WFOCUKirki
 * @category    Modules
 * @author      Aristeides Stathopoulos
 * @copyright   Copyright (c) 2017, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       3.0.26
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOCUKirki_Modules_Preset' ) ) {
	/**
	 * Adds styles to the customizer.
	 */
	class WFOCUKirki_Modules_Preset {

		/**
		 * The object instance.
		 *
		 * @static
		 * @access private
		 * @since 3.0.26
		 * @var object
		 */
		private static $instance;

		/**
		 * Constructor.
		 *
		 * @access protected
		 * @since 3.0.26
		 */
		protected function __construct() {
			add_action( 'customize_controls_print_footer_scripts', array( $this, 'customize_controls_print_footer_scripts' ) );
		}

		/**
		 * Gets an instance of this object.
		 * Prevents duplicate instances which avoid artefacts and improves performance.
		 *
		 * @static
		 * @access public
		 * @return object
		 * @since 3.0.26
		 */
		public static function get_instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Enqueue scripts.
		 *
		 * @access public
		 * @since 3.0.26
		 */
		public function customize_controls_print_footer_scripts() {
			wp_enqueue_script( 'wfocukirki-preset', trailingslashit( WFOCUKirki::$url ) . 'modules/preset/preset.js', array( 'jquery' ), WFOCU_KIRKI_VERSION );
		}
	}
}