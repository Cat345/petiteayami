<?php
/**
 * PayPal PLUS for WooCommerce
 * By Inpsyde GmbH
 * Class WFOB_Compatibility_Advanced_Coupon_Rymera
 */

if ( ! class_exists( 'WFOB_Compatibility_PayPal_Plus_Gmbh' ) ) {
	class WFOB_Compatibility_PayPal_Plus_Gmbh {
		private $process = false;

		public function __construct() {


			add_action( 'wp_footer', [ $this, 'js' ] );
			add_action( 'woocommerce_checkout_update_order_review', [ $this, 'get_data' ], 5 );
			add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'unset_fragments' ], 900 );
		}

		private function is_enabled() {
			return class_exists( 'WCPayPalPlus\PlusGateway\gateway' );
		}

		public function get_data( $data ) {
			if ( false == $this->is_enabled() ) {
				return $data;
			}
			if ( empty( $data ) ) {
				return $data;
			}
			parse_str( $data, $post_data );
			if ( empty( $post_data ) || ! isset( $post_data['wfob_input_hidden_data'] ) || empty( $post_data['wfob_input_hidden_data'] ) ) {
				return $data;
			}

			$bump_action_data = json_decode( $post_data['wfob_input_hidden_data'], true );

			if ( empty( $bump_action_data ) ) {
				return $data;
			}
			if ( isset( $bump_action_data['gmbh_unset_fragments'] ) ) {
				$this->process = true;
			}
		}


		public function unset_fragments( $fragments ) {
			if ( false == $this->process ) {
				return $fragments;
			}
			foreach ( $fragments as $k => $fragment ) {
				if ( ( false !== strpos( $k, 'wfacp' ) || false !== strpos( $k, 'wfob' ) ) && true == apply_filters( 'wfob_unset_our_fragments_by_paypal_gmbh', true, $k ) ) {
					unset( $fragments[ $k ] );
				}
			}
			unset( $fragments['cart_total'] );

			return $fragments;
		}


		public function js() {
			if ( false == $this->is_enabled() || ! is_checkout() ) {
				return;
			}
			?>
            <script>
                window.addEventListener('load', function () {
                    (function ($) {
                        wfob_frontend.hooks.addFilter('wfob_before_ajax_data_add_order_bump', set_custom_data);
                        wfob_frontend.hooks.addFilter('wfob_before_ajax_data_remove_order_bump', set_custom_data);
                        wfob_frontend.hooks.addAction('wfob_ajax_add_order_bump', trigger_checkout);
                        wfob_frontend.hooks.addAction('wfob_ajax_remove_order_bump', trigger_checkout);

                        function set_custom_data(data) {
                            data['gmbh_unset_fragments'] = 'yes';
                            return data;
                        }

                        function trigger_checkout(rsp) {
                            $(document.body).trigger('update_checkout');
                        }
                    })(jQuery);
                });
            </script>
			<?php
		}
	}


	new WFOB_Compatibility_PayPal_Plus_Gmbh();
}