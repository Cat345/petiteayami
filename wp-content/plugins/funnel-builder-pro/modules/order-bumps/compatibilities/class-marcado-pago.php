<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Compatibility_With_Brazillian_Gateway' ) ) {
	/**
	 * Mercado Pago payments for WooCommerce
	 * URI: https://github.com/mercadopago/cart-woocommerce *
	 * PropulsePay payment gateway for WooCommerce
	 * https://skillsup.in/
	 **/
	class WFOB_Compatibility_With_Brazillian_Gateway {
		public function __construct() {
			add_action( 'wp_footer', [ $this, 'internal_css_js' ], 99 );
		}

		public function internal_css_js() {
			if ( ! is_checkout() ) {
				return;
			}
			?>
            <script>
                window.addEventListener('load', function () {
                    (function ($) {
                        if (typeof wfob_frontend == "object") {
                            let gateways = ["woo-mercado-pago-custom", "propulsepay-credit-card"];

                            function global_ajax_response() {
                                let selected_method = $('input[name="payment_method"]:checked');
                                if (selected_method.length === 0) {
                                    return;
                                }
                                let gateway_id = selected_method.val();
                                if (gateways.indexOf(gateway_id) > -1) {
                                    $('body').trigger('update_checkout');
                                }
                            }

                            wfob_frontend.hooks.addAction('wfob_ajax_response', global_ajax_response);// Run When Our Action is running
                        }
                    })(jQuery);
                })

            </script>
			<?php
		}

	}

	new WFOB_Compatibility_With_Brazillian_Gateway();


}

