<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

/**
 * WooCommerce PayPal Payments by WooCommerce V 3.0.3
 * Plugin URI: https://woocommerce.com/products/woocommerce-paypal-payments/
 */

if ( ! class_exists( 'WFACP_Woocommerce_Paypal_Payments' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Woocommerce_Paypal_Payments {
		protected $placeorder_back_button_text = '';

		public function __construct() {

			add_action( 'wfacp_after_template_found', array( $this, 'action' ) );
			add_action( 'wfacp_internal_css', array( $this, 'add_internal_css' ) );
			add_filter( 'wfacp_exclude_place_order_text_update_order_review', '__return_false' );
			add_filter(
				'wfacp_back_link_text',
				function ( $html ) {

					$this->placeorder_back_button_text = $html;

					return '';
				}
			);
		}

		public function action() {
			add_filter(
				'woocommerce_paypal_payments_checkout_dcc_renderer_hook',
				function () {

					return 'wfacp_woocommerce_review_order_after_submit';
				}
			);

			add_action(
				'woocommerce_review_order_after_payment',
				function () {
					if ( ! empty( $this->placeorder_back_button_text ) ) {
						echo wp_kses_post( $this->placeorder_back_button_text );
					}
				},
				9999
			);

			add_action( 'wp_footer', array( $this, 'axo_customer_details_anchor' ), 5 );
		}

		/**
		 * AXO's initPlacements() requires #customer_details as an anchor; without it,
		 * document.querySelector('#customer_details').insertAdjacentHTML throws
		 * TypeError and the entire Fastlane render halts. FunnelKit emits
		 * <div class="wfacp-left-panel"> instead, so we tag it.
		 */
		public function axo_customer_details_anchor() {
			?>
			<script>
			(function () {
				function tagCustomerDetails() {
					if ( document.getElementById( 'customer_details' ) ) {
						return;
					}
					var anchor = document.querySelector( '#wfacp_checkout_form .wfacp-left-panel' )
						|| document.querySelector( '.wfacp-left-panel' );
					if ( anchor ) {
						anchor.id = 'customer_details';
					}
				}

				tagCustomerDetails();

				if ( window.jQuery ) {
					jQuery( document ).on( 'updated_checkout', tagCustomerDetails );
				}
			})();
			</script>
			<?php
		}

		public function add_internal_css() {

			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}
			$bodyClass = 'pre_built' !== $instance->get_template_type() ? 'body #wfacp-e-form ' : 'body ';

			$css  = $bodyClass . '#wfacp_checkout_form .ppcp-dcc-order-button{float: none;}';
			$css .= '.wfacp_main_wrapper.right #ppcp-hosted-fields .button {float: right;}';
			$css .= ".wfacp_main_wrapper.right #ppcp-hosted-fields:after,.wfacp_main_wrapper.right #ppcp-hosted-fields:before {display: block;content: '';}";
			$css .= '.wfacp_main_wrapper.right #ppcp-hosted-fields:after {clear: both;}';

			/**
			 * AXO's customerDetails.show() jQuery-shows every direct child of
			 * #customer_details; jQuery gives <style>/<script> tags inline
			 * display:block, rendering their text as visible raw CSS/JS.
			 */
			$css .= '.wfacp-left-panel style,.wfacp-left-panel script{display:none !important;}';

			// No esc_html(): entities like &#039; are not decoded inside <style> and break content:'' rules.
			echo '<style>' . $css . '</style>';//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Woocommerce_Paypal_Payments(), 'woocommerce-paypal-payments' );
}