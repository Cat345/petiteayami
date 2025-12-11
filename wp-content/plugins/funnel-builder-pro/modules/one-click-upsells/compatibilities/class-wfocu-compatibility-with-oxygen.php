<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Compatibility_With_Oxygen' ) ) {
	/**
	 * Class WFOCU_Compatibility_With_Oxygen
	 */
	class WFOCU_Compatibility_With_Oxygen {

		public function __construct() {
			add_action( 'plugins_loaded', [ $this, 'init_upstroke' ], 12 );
			add_filter( 'wfocu_should_render_script_jquery', array( $this, 'should_prevent_jq_on_editor' ), 10 );
		}

		public function is_enable() {
			return class_exists( 'OxygenElement' );
		}

		public function init_upstroke() {
			if ( class_exists( 'WFOCU_Template_Group' ) ) {
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/page-builders/oxygen/wfocu-template-group-oxygen.php';
			}
		}

		public function should_prevent_jq_on_editor( $bool ) {
			if ( isset( $_GET['ct_builder'] ) ) { //phpcs:ignore
				return false;
			}

			return $bool;
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Oxygen(), 'oxygen' );
}