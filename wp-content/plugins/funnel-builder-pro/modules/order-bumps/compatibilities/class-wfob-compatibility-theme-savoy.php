<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Theme: Savoy
 * Theme URI: http://themeforest.net/item/savoy-minimalist-ajax-woocommerce-theme/12537825
 * class WFOB_Compatibility_With_Theme_Savoy
 *
 */
if ( ! class_exists( 'WFOB_Compatibility_With_Theme_Savoy' ) ) {
	#[AllowDynamicProperties]
	class WFOB_Compatibility_With_Theme_Savoy {

		public function __construct() {
			add_action( 'wp_footer', [ $this, 'internal_css_js' ] );
			add_filter( 'wfob_disable_wc_dropdown_variation_attribute_options', '__return_false' );

		}

		public function internal_css_js() {
			if ( ! is_checkout() ) {
				return;
			}
			?>
            <style>
                body #wfob_qr_model_wrap .single_variation {
                    border: none;
                    padding: 0 !important;
                    line-height: 1.5;
                }
            </style>
            <script>
                window.addEventListener('load', function () {
                    (function ($) {
                        if (typeof wfob_frontend !== "object") {
                            return;
                        }

                        $(document).on('wfob_quick_view_open', function () {

                            var $container = $('.wfob-product-variations');
                            if ($container.length) {
                                if (typeof $.nmThemeInstance !== 'undefined' &&
                                    typeof $.nmThemeInstance.singleProductVariationsInit === 'function') {
                                    $.nmThemeInstance.singleProductVariationsInit($container);
                                }
                            }

                        });
                    })(jQuery);
                });
            </script>
			<?php
		}

	}

	new WFOB_Compatibility_With_Theme_Savoy();
}