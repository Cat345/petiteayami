<?php
if ( ! class_exists( 'WFOCU_Template_Importer' ) ) {
	class WFOCU_Template_Importer {

		private static $ins = null;

		public function __construct() {
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function maybe_import_data( $template_group, $template, $offer, $offer_settings ) {

			$box_template    = WFOCU_Common::get_boxed_template();
			$canvas_template = WFOCU_Common::get_canvas_template();
			$page_template   = get_post_meta( $offer, '_wp_page_template', true );

			if ( in_array( $template_group, [ 'custom', 'custom_page' ], true ) ) {
				if ( $box_template !== $page_template ) {
					update_post_meta( $offer, '_wp_page_template', $box_template );
				}
				do_action( 'wfocu_maybe_import_data', $offer, $template_group, $template );

				return;
			}

			if ( $canvas_template !== $page_template ) {
				update_post_meta( $offer, '_wp_page_template', $canvas_template );

			}

			$group_instance = WFOCU_Core()->template_loader->get_group( $template_group );

			if ( empty( $group_instance ) ) {
				return;
			}

			return $group_instance->update_template( $template, $offer, $offer_settings );
		}

	}


	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'importer', 'WFOCU_Template_Importer' );
	}
}