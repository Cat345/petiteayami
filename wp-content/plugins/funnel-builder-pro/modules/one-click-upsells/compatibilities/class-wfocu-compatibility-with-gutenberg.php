<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Gutenberg' ) ) {

	/**
	 * Class WFOCU_Compatibility_With_Oxygen
	 */
	class WFOCU_Compatibility_With_Gutenberg {

		public function __construct() {
			add_action( 'plugins_loaded', [ $this, 'init_upstroke' ], 12 );
		}

		public function is_enable() {
			return true;
		}

		public function init_upstroke() {
			if ( class_exists( 'WFOCU_Template_Group' ) ) {
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/gutenberg/wfocu-template-group-gutenberg.php';
			}
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Gutenberg(), 'gutenberg' );
}