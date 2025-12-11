<?php
if ( ! class_exists( 'WFOB_Compatibility_With_Happy_Elementor' ) ) {
	/**
	 * Happy Elementor Addons by weDevs (v.3.2.1)
	 * Plugin Path : https://happyaddons.com
	 */
	class WFOB_Compatibility_With_Happy_Elementor {
		public function __construct() {
			add_filter( 'admin_init', [ $this, 'do_not_execute' ], 2 );
		}

		public function do_not_execute() {
			if ( ! isset( $_REQUEST['page'] ) || $_REQUEST['page'] !== 'wfob' || ! class_exists( 'Happy_Addons\Elementor\Dashboard' ) ) {
				return;
			}

			remove_action( 'admin_enqueue_scripts', [ 'Happy_Addons\Elementor\Dashboard', 'enqueue_scripts' ] );
		}
	}

	new WFOB_Compatibility_With_Happy_Elementor();
}