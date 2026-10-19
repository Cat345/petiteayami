<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Envia Shipping ( shipping-system-live-rates-fulfillment-envia )
 *
 * Envia prints its carrier logo block ( .rate-content ) after the rate via
 * woocommerce_after_shipping_rate. Inside Aero's method row that block lands
 * below .wfacp_single_shipping and its stylesheet ( written for the native
 * checkout ul ) breaks the layout. Lay the row out as: radio | logo | label | price.
 *
 * display:flex needs !important against the shimmer skeleton state, and the
 * logo height against Envia's own height:40px!important.
 *
 * @package WFACP
 * @subpackage Compatibilities
 */
if ( ! class_exists( 'WFACP_Compatibility_With_Envia_Shipping' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Envia_Shipping {

		public function __construct() {
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );
		}

		public function internal_css() {
			?>
			<style>
				.wfacp_main_form ul#shipping_method {
					min-width: 0;
					max-width: none;
				}

				.wfacp_main_form ul#shipping_method > li.wfacp_single_shipping_method {
					display: flex !important;
					align-items: center;
					flex-wrap: nowrap;
					gap: 10px;
					position: relative;
				}

				/** Radio and label share a wrapper while the logo is a sibling, so the native
				radio | logo | label order can't come from flex order alone — the radio is
				pinned to the row's left edge instead. Aero's own rules are #wfacp-e-form
				prefixed and it positions the radio inside .wfacp_shipping_radio, so match the
				ID specificity and hand the containing block to the li. */
				#wfacp-e-form .wfacp_main_form ul#shipping_method > li.wfacp_single_shipping_method {
					padding-left: 26px !important;
				}

				/** Free the whole chain so the li is the radio's containing block */
				#wfacp-e-form .wfacp_main_form #shipping_method li .wfacp_shipping_radio,
				#wfacp-e-form .wfacp_main_form #shipping_method li .wfacp_single_shipping {
					position: static !important;
				}

				#wfacp-e-form .wfacp_main_form #shipping_method li .wfacp_shipping_radio input.shipping_method {
					position: absolute !important;
					left: 6px !important;
					top: 50%;
					transform: translateY(-50%);
					margin: 0;
				}

				.wfacp_main_form #shipping_method li .rate-content {
					order: -1;
					width: 34px;
					flex: 0 0 34px;
				}

				.wfacp_main_form #shipping_method li .envia-shipping-carrier-img {
					height: 24px !important;
					padding: 0;
					display: block;
					margin: 0 auto;
				}

				.wfacp_main_form #shipping_method li .wfacp_single_shipping {
					flex: 1 1 auto;
					min-width: 0;
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 8px;
				}

				.wfacp_main_form #shipping_method li label {
					width: auto;
					order: 0;
				}

				.wfacp_main_form #shipping_method li .wfacp_shipping_price .woocommerce-Price-amount {
					white-space: nowrap;
				}
			</style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Envia_Shipping(), 'envia_shipping' );
}
