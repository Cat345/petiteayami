<?php
if ( ! class_exists( 'WFOB_WFFN_Compatibility' ) ) {
	class WFOB_WFFN_Compatibility {

		public function __construct() {
			if ( ! did_action( 'wffn_loaded' ) ) {
				add_action( 'wffn_loaded', array( $this, 'add_file' ), 12 );
			} else {
				$this->add_file();
			}
		}

		public function is_enable() {
			if ( class_exists( 'WFFN_Step' ) ) {
				return true;
			}

			return false;
		}

		public function add_file() {
			require_once plugin_dir_path( WFOB_PLUGIN_FILE ) . '/compatibilities/funnel-builder/class-wffn-substep-wc-order_bump.php';
			require_once plugin_dir_path( WFOB_PLUGIN_FILE ) . '/compatibilities/funnel-builder/class-wffn-rest-bump-api-endpoint.php';

		}


	}

	new WFOB_WFFN_Compatibility();
}