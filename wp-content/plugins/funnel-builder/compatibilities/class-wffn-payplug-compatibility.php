<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compatibility: PayPlug — failed/cancelled payments on the thank-you page.
 *
 * PayPlug returns failed payments to the thank-you page too. The order details already show the decline notice
 * with Pay; this resolves a still-pending order with PayPlug's own check and hides the "Thank you [name]" block.
 */
if ( ! class_exists( 'WFFN_PayPlug_Compatibility' ) ) {

	#[\AllowDynamicProperties]
	class WFFN_PayPlug_Compatibility {

		public function __construct() {
			add_action( 'wp', array( $this, 'maybe_validate_pending_order' ), 12 );
			add_action( 'wp', array( $this, 'maybe_hide_thankyou_name' ), 20 );
		}

		public static function get_plugin_nicename() {
			return 'PayPlug pour WooCommerce';
		}

		/**
		 * Current thank-you order when it was paid with PayPlug.
		 *
		 * @return WC_Order|false
		 */
		protected function get_payplug_order() {
			if ( ! class_exists( 'WFTY_Data' ) || ! WFFN_Core()->thank_you_pages->is_wfty_page() ) {
				return false;
			}
			$order   = WFTY_Data::get_instance()->get_order();
			$methods = apply_filters( 'wffn_payplug_payment_methods', array( 'payplug', 'oney_x3_with_fees', 'oney_x4_with_fees', 'oney_x3_without_fees', 'oney_x4_without_fees', 'bancontact', 'apple_pay', 'american_express', 'satispay', 'mybank', 'ideal', 'wero', 'bizum', 'scalapay' ) );

			return ( $order instanceof WC_Order && in_array( $order->get_payment_method(), $methods, true ) ) ? $order : false;
		}

		/**
		 * The shopper can land here before PayPlug's IPN updates the order; PayPlug only checks the payment on the
		 * native order-received endpoint, so run that check here for a still-pending order.
		 */
		public function maybe_validate_pending_order() {
			$order = $this->get_payplug_order();
			if ( ! $order || ! $order->has_status( 'pending' ) ) {
				return;
			}
			$gateways = WC()->payment_gateways()->payment_gateways();
			$gateway  = isset( $gateways[ $order->get_payment_method() ] ) ? $gateways[ $order->get_payment_method() ] : null;
			if ( ! $gateway || ! method_exists( $gateway, 'validate_payment' ) ) {
				return;
			}
			try {
				$gateway->validate_payment( $order->get_id(), true, true );
				WFTY_Data::get_instance()->load_order( wc_get_order( $order->get_id() ) );
			} catch ( Exception | Error $e ) {
				WFFN_Core()->logger->log( 'PayPlug validate_payment on thank-you page failed: ' . $e->getMessage() );
			}
		}

		/**
		 * Failed/cancelled order: mark the name and email shortcodes the "order confirmed" wording is built from
		 * ("Thank you [name]", "…sent to [email]") and hide the heading/text blocks that show them.
		 */
		public function maybe_hide_thankyou_name() {
			global $shortcode_tags;
			$order = $this->get_payplug_order();
			if ( ! $order || ! $order->has_status( apply_filters( 'wffn_payplug_thankyou_hide_statuses', array( 'failed', 'cancelled' ) ) ) ) {
				return;
			}
			$marks = array(
				'wfty_customer_first_name' => 'wffn-ty-name',
				'wfty_customer_email'      => 'wffn-ty-name',
				'wfty_order_number'        => 'wffn-ty-keep',
			);
			foreach ( $marks as $sc => $class ) {
				if ( empty( $shortcode_tags[ $sc ] ) ) {
					continue;
				}
				$callback = $shortcode_tags[ $sc ];
				add_shortcode(
					$sc,
					function ( $atts, $content = null, $tag = '' ) use ( $callback, $class ) {
						return '<span class="' . $class . '">' . call_user_func( $callback, $atts, $content, $tag ) . '</span>';
					}
				);
			}
			add_action(
				'wp_head',
				function () {
					$t = '.elementor-widget-heading, .elementor-widget-text-editor, .wp-block-heading, .bwf-adv-heading, .et_pb_text, .brxe-heading, .brxe-text, .brxe-text-basic, .ct-headline, .ct-text-block, h1, h2, h3, h4, h5, h6, p';
					$c = '.e-con, .elementor-section, .elementor-column, .bwf-section-wrap, .bwf-col, .bwf-inner-col, .wp-block-group, .wp-block-column, .et_pb_row, .et_pb_column, .brxe-container, .brxe-block, .brxe-div, .ct-section, .ct-div-block';
					// Hide the name/email text, its container when it holds no order number, and in a container that does, the other text (e.g. "Your order is confirmed") but not the order number or icons.
					printf( '<style>:is(%1$s, .elementor-widget, .et_pb_module):has(.wffn-ty-name):not(:has(.wfty_wrap)), :is(%2$s):has(.wffn-ty-name):not(:has(.wffn-ty-keep, .wfty_wrap, img, svg, video, iframe)), :is(%2$s):has(.wffn-ty-name):not(:has(.wfty_wrap)) :is(%1$s):not(:has(.wffn-ty-keep)) { display: none !important; }</style>', $t, $c ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
				}
			);
		}
	}

	if ( function_exists( 'wffn_is_wc_active' ) && wffn_is_wc_active() ) {
		WFFN_Plugin_Compatibilities::register( new WFFN_PayPlug_Compatibility(), 'payplug' );
	}
}
