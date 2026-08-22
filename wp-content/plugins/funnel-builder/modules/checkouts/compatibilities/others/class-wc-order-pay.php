<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! class_exists( 'WFACP_WC_Order_Pay' ) ) {
	/**
	 * Compatibility for create order by order-pay in admin
	 */
	#[AllowDynamicProperties]
	class WFACP_WC_Order_Pay {
		public function __construct() {
			add_action( 'woocommerce_before_pay_action', array( $this, 'update_aero_id' ), 10, 1 );
			add_filter( 'woocommerce_payment_successful_result', array( $this, 'update_checkout_reporting' ), 10, 2 );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );
		}

		/**
		 * add aero id in order meta
		 *
		 * @param $order WC_Order
		 *
		 * @return void
		 */
		public function update_aero_id( $order ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- Runs on woocommerce_before_pay_action; WooCommerce has already verified the woocommerce-pay nonce, and guest order-pay is authorized by the order key, so a current_user_can() check would break guests paying.
			if ( ! isset( $_POST['_wfacp_post_id'] ) || ! isset( $_POST['woocommerce_pay'] ) ) {
				return;
			}

			$wfacp_id = absint( $_POST['_wfacp_post_id'] );
			if ( $wfacp_id > 0 ) {
				$order->update_meta_data( '_wfacp_post_id', $wfacp_id );
				$order->save();
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing, FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck
		}

		/**
		 * update checkout reporting meta
		 *
		 * @param $result
		 * @param $order_id
		 *
		 * @return mixed
		 */
		public function update_checkout_reporting( $result, $order_id ) {
			$order    = wc_get_order( $order_id );
			$wfacp_id = $order->get_meta( '_wfacp_post_id' );
			if ( empty( $wfacp_id ) ) {
				return $result;
			}

			$posted_data = array(
				'wfacp_post_id'         => $wfacp_id,
				'wfacp_woocommerce_pay' => true,
			);

			WFACP_Core()->reporting->update_reporting_data_in_meta( $order, $posted_data );

			return $result;
		}

		public function internal_css() {
			if ( ! WFACP_Core()->pay->is_order_pay() || ! function_exists( 'wfacp_template' ) ) {
				return;
			}

			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}

			$bodyClass = 'body.woocommerce-order-pay ';

			if ( 'pre_built' !== $instance->get_template_type() ) {

				$bodyClass = 'body.woocommerce-order-pay #wfacp-e-form ';
			}

			$cssHtml = '<style>';

			$cssHtml .= $bodyClass . ".woocommerce ul.order_details:after{content: '';display: table;}";
			$cssHtml .= $bodyClass . ".woocommerce ul.order_details:before{content: '';display: table;}";
			$cssHtml .= $bodyClass . '.woocommerce ul.order_details:after{clear:both;}';
			$cssHtml .= $bodyClass . '.woocommerce ul.order_details li{float: left;margin-right: 2em;text-transform: uppercase;font-size: .715em;line-height: 1.5;border-right: 1px dashed #d3ced2;padding-right: 2em;margin-left: 0;padding-left: 0;list-style-type: none;}';
			$cssHtml .= $bodyClass . '.woocommerce ul.order_details li strong{display: block;font-size: 1.4em;text-transform: none;line-height: 1.5;    word-break: break-all;}';
			$cssHtml .= $bodyClass . '.woocommerce button{display: inline-block;font-weight: 400;color: inherit;text-align: center;white-space: nowrap;-webkit-user-select: none;-moz-user-select: none;-ms-user-select: none;user-select: none;background-color: transparent;border: 1px solid #ededed;padding: 0.5rem 1rem;font-size: 1rem;border-radius: 8px;-webkit-transition: all .3s;-o-transition: all .3s;transition: all .3s;}';

			$cssHtml .= '@media (min-width: 768px) {';
			$cssHtml .= $bodyClass . '.woocommerce ul.order_details{margin: 0 0 3em;}';
			$cssHtml .= $bodyClass . '.woocommerce ul.order_details li:last-child{padding:0;margin:0;border:none;}';
			$cssHtml .= '}';

			$cssHtml .= '@media (max-width: 767px) {';
			$cssHtml .= $bodyClass . '.woocommerce ul.order_details li{width: 50%;margin: 0 0 30px;padding: 0 15px 0 0;}';
			$cssHtml .= $bodyClass . '.woocommerce ul.order_details li:nth-child(2n) {border: none;padding: 0 0 0 15px;    text-align: right;}';
			$cssHtml .= $bodyClass . '.woocommerce ul.order_details ul.order_details{margin: 0;}';
			$cssHtml .= '}';

			// The Elementor page template still renders its mini-cart summary widget
			// (wfacp_form_summary) on the order-pay page, but order-pay has no cart
			// session so get_mini_cart_widget() outputs nothing — leaving just the
			// widget's 1px spacer inside an empty styled box. The real summary here is
			// the order-pay form's own #order_review panel (get_order_pay_summary()),
			// so hide the empty mini-cart widget on order-pay.
			$cssHtml .= 'body.woocommerce-order-pay .elementor-widget-wfacp_form_summary{display:none !important;}';
			$cssHtml .= '</style>';

			echo $cssHtml; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	new WFACP_WC_Order_Pay();
}
