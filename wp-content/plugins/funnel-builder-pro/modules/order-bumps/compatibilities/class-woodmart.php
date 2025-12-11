<?php
if ( ! class_exists( 'WFOB_Compatibilities_WoodMart_Builder' ) ) {
	class WFOB_Compatibilities_WoodMart_Builder {
		public function __construct() {
			add_filter( 'wfob_product_switcher_price_data', [ $this, 'remove_action' ] );
			add_filter( 'wfob_qv_images', [ $this, 'remove_action' ], - 1 );
			add_action( 'wp_footer', [ $this, 'js' ] );
		}

		public function remove_action( $status ) {
			if ( function_exists( 'woodmart_lazy_attributes' ) ) {
				remove_action( 'wp_get_attachment_image_attributes', 'woodmart_lazy_attributes', 10 );
			}

			return $status;
		}

		public function js() {

			?>
            <script>
                window.addEventListener('load', function () {
                    (function ($) {
                        // Ensure the woodmartThemeModule is defined before triggering

                        if (typeof woodmartThemeModule !== 'undefined' && woodmartThemeModule.$document) {

                            $(document.body).on('wfob_quick_view_open', function () {
                                woodmartThemeModule.$document.trigger('wood-images-loaded');
                            });

                        }

                    })(jQuery);
                });
            </script>
			<?php
		}


	}

	new WFOB_Compatibilities_WoodMart_Builder();
}