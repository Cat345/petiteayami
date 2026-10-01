<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'WFACP_WC_Payments_GPAY_AND_APAY' ) ) {
	#[AllowDynamicProperties]
	class WFACP_WC_Payments_GPAY_AND_APAY {
		private $instance = null;

		public function __construct() {
			add_filter( 'wfacp_smart_buttons', [ $this, 'add_buttons' ] );
			add_action( 'wfacp_smart_button_container_wc_payment_gpay_apay', [ $this, 'add_wc_payment_gpay_apay_buttons' ] );
			add_action( 'wfacp_smart_button_container_wc_payment_woo_pay', [ $this, 'add_wc_payment_woo_pay_buttons' ] );
			add_action( 'wfacp_internal_css', [ $this, 'add_internal_css' ], 11, 2 );

		}

		public function add_buttons( $buttons ) {
			try {


				if ( true == apply_filters( 'wfacp_disabled_google_apple_pay_button_on_desktop', false, $buttons ) ) {
					if ( ! class_exists( 'WFACP_Mobile_Detect' ) ) {
						return $buttons;
					}
					$detect = WFACP_Mobile_Detect::get_instance();
					if ( ! $detect->isMobile() || empty( $detect ) ) {
						return $buttons;
					}
					add_filter( 'wfacp_template_localize_data', [ $this, 'set_local_data' ] );
				}
				if ( class_exists( 'WC_Payments_Express_Checkout_Button_Display_Handler' ) && method_exists( 'WC_Payments_Express_Checkout_Button_Display_Handler', 'display_express_checkout_buttons' ) ) {
					$this->instance = WFACP_Common::remove_actions( 'woocommerce_checkout_before_customer_details', 'WC_Payments_Express_Checkout_Button_Display_Handler', 'display_express_checkout_buttons' );
				} else {
					$this->instance = WFACP_Common::remove_actions( 'woocommerce_checkout_before_customer_details', 'WC_Payments_Payment_Request_Button_Handler', 'display_payment_request_button_html' );
					WFACP_Common::remove_actions( 'woocommerce_checkout_before_customer_details', 'WC_Payments_Payment_Request_Button_Handler', 'display_payment_request_button_separator_html' );
					if ( method_exists( 'WC_Payments', 'display_express_checkout_separator_if_necessary' ) ) {
						WFACP_Common::remove_actions( 'woocommerce_checkout_before_customer_details', 'WC_Payments', 'display_express_checkout_separator_if_necessary' );
					}
				}

				$buttons['wc_payment_gpay_apay'] = [
					'iframe' => true,
					'name'   => __( 'Woocommerce Payment Request', 'woocommerce-payments' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				];
				$buttons['wc_payment_woo_pay']   = [
					'iframe' => true,
					'name'   => __( 'Woocommerce Payment Request Woo Pay', 'woocommerce-payments' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				];
			} catch ( Exception|Error $e ) {
				wc_get_logger()->error( $e->getMessage(), [ 'source' => 'funnelkit-checkout-render-error' ] );
			}

			return $buttons;
		}

		public function add_wc_payment_gpay_apay_buttons() {
			try {
				$instance = WFACP_Common::remove_actions( 'template_redirect', 'WC_Payments_Express_Checkout_Button_Handler', 'set_session' );
				if ( $instance instanceof WC_Payments_Express_Checkout_Button_Handler && method_exists( 'WC_Payments_Express_Checkout_Button_Handler', 'display_express_checkout_button_html' ) ) {
					$instance->display_express_checkout_button_html();
				} else if ( $this->instance instanceof WC_Payments_Payment_Request_Button_Handler ) {
					$this->instance->display_payment_request_button_html();
				}

			} catch ( Exception|Error $e ) {
				wc_get_logger()->error( $e->getMessage(), [ 'source' => 'funnelkit-checkout-render-error' ] );
			}
		}

		public function add_wc_payment_woo_pay_buttons() {
			try {
				$instance = WFACP_Common::remove_actions( 'wp_ajax_woopay_express_checkout_button_show_error_notice', 'WC_Payments_WooPay_Button_Handler', 'show_error_notice' );

				if ( $instance instanceof WC_Payments_WooPay_Button_Handler && method_exists( 'WC_Payments_WooPay_Button_Handler', 'display_woopay_button_html' ) ) {
					$instance->display_woopay_button_html();
				}
			} catch ( Exception|Error $e ) {
				wc_get_logger()->error( $e->getMessage(), [ 'source' => 'funnelkit-checkout-render-error' ] );
			}
		}

		public function set_local_data( $data ) {
			$data['wc_payment_smart_show_on_desktop'] = 'no';

			return $data;
		}

		public function add_internal_css() {
			?>
            <style>
                #wfacp_smart_button_wc_payment_gpay_apay #wcpay-payment-request-wrapper {
                    padding: 0 !important;
                }

                #wfacp_smart_button_wc_payment_gpay_apay #wcpay-payment-request-button-separator {
                    display: none !important;
                }

                #wfacp_smart_button_wc_payment_gpay_apay #wcpay-express-checkout-button-separator {
                    display: none !important;
                }

                /*
                 * WooPayments packs EVERY enabled wallet (Apple Pay, Google Pay,
                 * Amazon Pay, Link) into the SINGLE #wcpay-express-checkout-element
                 * Stripe element, unlike every other gateway that registers one
                 * smart-button container per wallet. The express row sizes each
                 * container as one equal column (flex-basis: one third of the row),
                 * so this one column has to host the whole wallet strip - Stripe
                 * then squeezes the buttons and drops the ones that no longer fit.
                 *
                 * Give this container its own full-width line so the strip keeps the
                 * room it needs. Scoped to the WooPayments slot by id, so every other
                 * gateway keeps the equal-column layout. The extra id beats the
                 * `.wfacp_smart_button_container` rule in smart_buttons.php on
                 * specificity, so no !important is needed.
                 *
                 * The matching iframe height clamp is lifted for this slot in
                 * smart_buttons.php - the same element also needs to grow past one
                 * row when Stripe wraps the wallets.
                 */
                @media (min-width: 768px) {
                    #wfacp_smart_buttons.wfacp_smart_buttons .wfacp_smart_button_outer_buttons #wfacp_smart_button_wc_payment_gpay_apay {
                        flex-basis: 100%;
                    }
                }

                /*
                 * The WooPay button is an <a>, so the checkout's generic link reset
                 * (`#wfacp-e-form a:not(...)` - transparent background) and the
                 * builder-generated link colour both outrank WooPayments' own
                 * `#wcpay-woopay-button .woopay-express-button[data-theme]` rule and
                 * repaint the button in the theme's link colour on a transparent
                 * background - i.e. invisible on a light checkout.
                 *
                 * Restore WooPayments' own palette. Scoped to #wfacp-e-form rather
                 * than the express row, because WooPayments also renders this button
                 * inside the form when the Express Checkout Buttons optimization is
                 * OFF - there is no #wfacp_smart_buttons wrapper in that case, so a
                 * row-scoped rule would leave the button invisible there.
                 *
                 * Two ids outrank both the checkout reset and the builder link-colour
                 * rule whatever colour the page is set to, so no !important.
                 */
                #wfacp-e-form #wcpay-woopay-button a.woopay-express-button {
                    background-color: #fff;
                    color: #000;
                }

                #wfacp-e-form #wcpay-woopay-button a.woopay-express-button[data-theme="dark"] {
                    background-color: #873eff;
                    color: #fff;
                }

                #wfacp-e-form #wcpay-woopay-button a.woopay-express-button[data-theme="dark"]:not(:disabled):not(.is-placeholder):hover {
                    background-color: #a77eff;
                    color: #fff;
                }

                #wfacp-e-form #wcpay-woopay-button a.woopay-express-button[data-theme="light-outline"] {
                    border: 1px solid #000;
                }
            </style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_WC_Payments_GPAY_AND_APAY(), 'wc-payments-gpay_apay' );
}
