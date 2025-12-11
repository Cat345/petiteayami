<?php
if ( ! class_exists( 'WFOCU_Compatibility_With_Optimol' ) ) {
	class WFOCU_Compatibility_With_Optimol {
		public $accepted_ids = [];

		public function __construct() {
			if ( $this->is_enable() ) {
				add_action( 'wfocu_view_before_body_start', array( $this, 'remove_img_tag_replace_func' ) );
			}
		}


		public function is_enable() {
			if ( false === class_exists( 'Optml_Main' ) ) {
				return false;
			}

			return true;
		}

		public function remove_img_tag_replace_func() {
			remove_filter( 'optml_content_images_tags', array( Optml_Main::instance()->manager->tag_replacer, 'process_image_tags' ), 1 );
			add_filter( 'optml_extracted_urls', function () {
				return [];
			}, 9999 );
		}


	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Compatibility_With_Optimol(), 'optimol' );


}