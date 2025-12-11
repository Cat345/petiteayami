<?php
if ( ! class_exists( 'WFOB_Divi_Theme_Guten' ) ) {
	class WFOB_Divi_Theme_Guten {
		public function __construct() {
			$this->remove_action();
		}

		public function remove_action() {
			if ( method_exists( 'ET_GB_Block_Layout', 'get_the_excerpt' ) ) {
				WFOB_Common::remove_actions( 'get_the_excerpt', 'ET_GB_Block_Layout', 'get_the_excerpt' );
			} else if ( method_exists( 'ET_GB_Block_Layout', 'get_the_post_excerpt' ) ) {
				WFOB_Common::remove_actions( 'get_the_excerpt', 'ET_GB_Block_Layout', 'get_the_post_excerpt' );
			}
		}
	}

	add_action( 'wfob_before_bump_created', function () {
		if ( ! class_exists( 'ET_GB_Block_Layout' ) ) {
			return;
		}
		new WFOB_Divi_Theme_Guten();
	} );

}