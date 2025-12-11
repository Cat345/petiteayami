<?php
if ( ! class_exists( 'WFOB_Compatibilities_Avada_Fusion_Builder' ) ) {
	class WFOB_Compatibilities_Avada_Fusion_Builder {
		public function __construct() {
			add_filter( 'wfob_product_switcher_price_data', [ $this, 'remove_action' ] );
			add_filter( 'wfob_qv_images', [ $this, 'remove_action' ] );
		}

		public function remove_action( $status ) {
			if ( class_exists( 'Avada_Images' ) ) {
				WFOB_Common::remove_actions( 'wp_get_attachment_image_attributes', 'Avada_Images', 'lazy_load_attributes' );
			}

			return $status;
		}


	}

	new WFOB_Compatibilities_Avada_Fusion_Builder();
}