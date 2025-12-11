<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFOB_Compatibility_With_braintree' ) ) {
	/**
	 * WooCommerce Braintree Gateway
	 * http://docs.woocommerce.com/document/braintree/
	 * Class WFOB_Compatibility_With_braintree
	 */
	class WFOB_Compatibility_With_braintree {
		public function __construct() {
			add_filter( 'wp_footer', [ $this, 'trigger_hidden_field_order_total' ] );
		}

		public function trigger_hidden_field_order_total() {
			if ( ! is_checkout() ) {
				return;
			}
			?>
            <script>
                window.addEventListener('load', function () {
                    (function ($) {
                        if (typeof wfob_frontend !== "object") {
                            return;
                        }

                        function trigger_bump(aero_data) {
                            var el = $("input[name='wc-braintree-credit-card-3d-secure-order-total']");
                            if (el.length > 0) {
                                el.val(aero_data.cart_total);
                            }
                        }

                        wfob_frontend.hooks.addAction('wfob_ajax_add_order_bump', trigger_bump);
                        wfob_frontend.hooks.addAction('wfob_ajax_remove_order_bump', trigger_bump);
                    })(jQuery);
                });
            </script>
			<?php
		}
	}

	new  WFOB_Compatibility_With_braintree();
}