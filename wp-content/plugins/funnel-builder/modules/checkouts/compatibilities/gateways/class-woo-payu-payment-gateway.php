<?php
/**
 * PayU GPO Payment Gateway for WooCommerce Compatibility
 *
 * Plugin Name: PayU Payment Gateway for WooCommerce
 * Plugin URI: https://github.com/PayU/woo-payu-payment-gateway
 * Version: 2.9.0+
 *
 * @package FunnelKit Checkout
 * @since 3.22.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for PayU GPO Payment Gateway
 *
 * Ensures proper styling and functionality of PayU payment gateway elements
 * within FunnelKit Checkout pages.
 *
 * @since 3.22.1
 */
if ( ! class_exists( 'WFACP_Compatibility_PayU_Payment_Gateway' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_PayU_Payment_Gateway {

		/**
		 * Constructor
		 *
		 * @since 3.22.1
		 */
		public function __construct() {
			// Add internal CSS for PayU payment gateway styling
			add_action( 'wfacp_internal_css', array( $this, 'add_internal_css' ) );
		}


		public function add_internal_css() {
			?>
		<style>
		/* PayU Compatibility - Override FunnelKit Checkout Styles */

		/* 1. PayU Bank List Container */
		#wfacp-e-form .woocommerce-checkout #payment div.payment_box .pbl-container ul.payu-list-banks {
			display: flex;
			flex-wrap: wrap;
			justify-content: center;
			list-style: none;
			margin: 0;
			padding: 0;
		}

		/* 2. Override FunnelKit's li border and padding */
		body #wfacp-e-form #wfacp-sec-wrapper .woocommerce-checkout #payment div.payment_box .pbl-container ul.payu-list-banks li,
		#wfacp-e-form .woocommerce-checkout #payment div.payment_box .pbl-container ul.payu-list-banks li {
			border: none !important;
			padding: 5px 3px 0 !important;
			line-height: 1;
		}

		/* 3. Hide PayU radio inputs */
		#wfacp-e-form .woocommerce-checkout #payment div.payment_box .pbl-container ul.payu-list-banks li input[type="radio"] {
			display: none;
		}

		/* 4. PayU Bank Selection Labels */
		#wfacp-e-form .woocommerce-checkout #payment div.payment_box .pbl-container ul.payu-list-banks li label {
			padding: 10px !important;
			display: flex !important;
			border: 1px solid #bbb;
			transition: all 250ms;
			width: 86px;
			justify-content: center;
			align-items: center;
			position: relative;
			margin: 0 !important;
			cursor: pointer;
		}

		/* 5. PayU Bank Logo Images */
		#wfacp-e-form .woocommerce-checkout #payment div.payment_box .pbl-container .payu-list-banks li label img {
			width: auto !important;
			height: 31px !important;
			object-fit: contain;
			margin: 0 !important;
			padding: 0 !important;
			border-radius: 0 !important;
		}

		/* 6. Hover and Active States */
		#wfacp-e-form .woocommerce-checkout #payment div.payment_box .pbl-container ul.payu-list-banks li label:hover,
		body #wfacp-e-form #wfacp-sec-wrapper #payment div.payment_box .pbl-container .payu-list-banks li label.active {
			background: #ddd !important;
			border-color: #aaa !important;
		}

		/* 7. Mobile Responsive */
		@media (max-width: 767px) {
			#wfacp-e-form .woocommerce-checkout #payment div.payment_box .pbl-container ul.payu-list-banks li label {
				width: 70px !important;
				padding: 8px !important;
			}
			#wfacp-e-form .woocommerce-checkout #payment div.payment_box .pbl-container .payu-list-banks li label img {
				height: 25px !important;
			}
		}
		</style>
			<?php
		}
	}

	// Initialize the compatibility class
	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_PayU_Payment_Gateway(), 'payu-gateway' );
}

