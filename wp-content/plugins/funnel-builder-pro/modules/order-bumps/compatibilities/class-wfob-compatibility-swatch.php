<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_WC_Swatches' ) ) {
	/**
	 * COmpatibility with Theme alien swatches
	 * Class WFOB_WC_swatches
	 */
	class WFOB_WC_Swatches {
		public function __construct() {
			/* checkout page */
			add_action( 'wp_footer', [ $this, 'actions' ] );

		}

		public function actions() {
			if ( function_exists( 'ta_wc_variation_swatches_constructor' ) && is_checkout() ) {
				?>
                <script>
                    window.addEventListener('load', function () {
                        (function ($) {
                            $(document.body).on('wfob_quick_view_open', function () {
                                $('.variations_form').tawcvs_variation_swatches_form();
                            });
                        })(jQuery);
                    });
                </script>
				<?php

			}

		}


	}

	new WFOB_WC_Swatches();
}