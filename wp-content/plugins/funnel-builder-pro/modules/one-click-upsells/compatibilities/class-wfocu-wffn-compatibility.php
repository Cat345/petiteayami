<?php
if ( ! class_exists( 'WFOCU_WFFN_Compatibility' ) ) {
	class WFOCU_WFFN_Compatibility {

		public function __construct() {
			$this->add_upsell_step();
		}

		public function is_enable() {
			if ( class_exists( 'WFFN_Step' ) ) {
				return true;
			}

			return false;
		}

		public function add_upsell_step() {
			if ( did_action( 'wffn_loaded' ) ) {
				require_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/funnel-builder/class-wffn-step-wc-upsells.php';
				require_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/funnel-builder/class-wfocu-compatibility-with-wfty.php';
				require_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'compatibilities/funnel-builder/class-wffn-rest-upsell-api-endpoint.php';

			}
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_WFFN_Compatibility(), 'wfocu_wffn' );
}