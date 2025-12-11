<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * plugin Name: WooPayments by Automattic (v.8.8.0)
 *
 */
if ( ! class_exists( 'WFACP_Compatibility_With_WooCommerce_Payments' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_WooCommerce_Payments {
		public function __construct() {
			add_action( 'wfacp_internal_css', [ $this, 'enqueue_scripts' ] );
			add_action( 'wfacp_outside_header', [ $this, 'detect_woo_payment' ] );
			add_filter( 'wfacp_product_switcher_price_data', [ $this, 'wfacp_product_switcher_price_data' ], 10, 2 );
			add_action( 'wfacp_after_checkout_page_found', [ $this, 'action' ] );
		}

		/*
		 * This code only work if woo_payment enabled from WooCommerce Settings
		 */
		public function detect_woo_payment() {
			$instance = WFACP_Common::remove_actions( 'woocommerce_checkout_billing', 'WC_Payments', 'woopay_fields_before_billing_details' );

			if ( $instance == 'WC_Payments' ) {
				add_action( 'wfacp_internal_css', [ $this, 'css' ] );
				add_filter( 'woocommerce_form_field_args', [ $this, 'add_aero_basic_classes' ], 10, 2 );
			}

		}

		public function css() {
			?>

			<style>
				div#contact_details > h3 { display: none; }
				div#contact_details { clear: both; }

			body #wfacp-sec-wrapper .woopay-save-new-user-container {
				margin: 16px 0 0;
				padding: 0 7px;
			}

			body #wfacp-sec-wrapper #payment .woopay-save-new-user-container input[type="checkbox"] {
				position: relative;
				left: auto;
				right: auto;
				top: auto;
				bottom: auto;
				margin: 0 8px 0 0 !important;
			}

			body #wfacp-sec-wrapper .woopay-save-new-user-container .save-details-header label {
				text-indent: unset;
			}

			body #wfacp-sec-wrapper .woopay-save-new-user-container .save-details-form.form-row {
				overflow-y: hidden;
			}

			body #wfacp-sec-wrapper .woopay-save-new-user-container input[type="text"],
			body #wfacp-sec-wrapper .woopay-save-new-user-container .phone-input {
				padding-top: 12px !important;
				padding-bottom: 12px !important;
				margin: 0;
			}

			body #wfacp-sec-wrapper #payment .woopay-save-new-user-container label,
			body #wfacp-sec-wrapper #payment .woopay-save-new-user-container label span {
				margin: 0;
				line-height: 1.5;
				display: inline-block;
			}
			body .iti__country-list li:empty,
			body .wfacp_main_form.woocommerce #wfacp_checkout_form .iti__country-list li:empty {
				display: none;
			}

			</style>
<?php
		}

		public function add_aero_basic_classes( $field, $key ) {
			if ( $key === 'billing_email' ) {
				$field['input_class'][] = 'wfacp-form-control';
				$tmp                    = [];
				if ( isset( $field['class'] ) && is_array( $field['class'] ) ) {
					$tmp = $field['class'];
				}
				$field['class']         = array_merge( [ 'woopay-billing-email' ], $tmp );
				$field['label_class'][] = 'wfacp-form-control-label';
			}

			return $field;
		}

		public function enqueue_scripts() {
			if ( is_null( WC()->cart ) || WC()->cart->needs_payment() ) {
				return;
			}
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			if ( ! isset( $gateways['woocommerce_payments'] ) ) {
				return;
			}

			$gateway = $gateways['woocommerce_payments'];

			/**
			 * @var $gateway WC_Payment_Gateway_WCPay
			 */
			if ( method_exists( $gateway, 'get_payment_fields_js_config' ) ) {
				wp_localize_script( 'wcpay-checkout', 'wcpay_config', $gateway->get_payment_fields_js_config() );
				wp_enqueue_script( 'wcpay-checkout' );
				wp_enqueue_style( 'wcpay-checkout', plugins_url( 'dist/checkout.css', WCPAY_PLUGIN_FILE ), [], WC_Payments::get_file_version( 'dist/checkout.css' ) );
			}

		}

		/**
		 * @param $price_data
		 * @param $pro WC_Product;
		 *
		 * @return mixed
		 */
		public function wfacp_product_switcher_price_data( $price_data, $pro ) {

			$price_data['regular_org'] = $pro->get_regular_price();
			$price_data['price']       = $pro->get_price();

			return $price_data;
		}

		/**
		 * @param $args
		 * @param $key
		 * @param $billing_fields
		 *
		 * @return mixed one of condition check the required key which was throwing the notice
		 */
		public function action() {
			add_action( 'woocommerce_checkout_fields', [ $this, 'checkout_fields' ], 9 );
			add_action( 'wfacp_before_form', [ $this, 'add_wcpay_hidden_div' ], 99 );
		}

		/**
		 * Add hidden div for WooCommerce Payments compatibility
		 */
		public function add_wcpay_hidden_div() {
			echo '<div id="wcpay-hidden-div" style="position: absolute; clip: rect(0 0 0 0); height: 1px; width: 1px; margin: -1px; padding: 0; border: 0; overflow: hidden;">
    <p class="form-row form-row-first wfacp-form-control-wrapper wfacp-col-left-full">
        <input class="wfacp-form-control" id="wcpay-hidden-input" type="text" value="" style="transition: none;">
        <label id="wcpay-hidden-valid-active-label"></label>
    </p>
    <p class="form-row form-row-first wfacp-form-control-wrapper wfacp-col-left-full">
        <input class="wfacp-form-control" id="wcpay-hidden-invalid-input" type="text" value="">
        <label id="wcpay-hidden-invalid-input"></label>
    </p>
</div>';
		}

		public function checkout_fields( $fields ) {
			if ( ! is_array( $fields ) || count( $fields ) == 0 ) {
				return $fields;
			}


			foreach ( $fields as $i => $field ) {

				if ( $i !== 'billing' && $i !== 'shipping' ) {
					continue;
				}

				foreach ( $field as $k => $value ) {
					if ( ! isset( $fields[ $i ][ $k ]['required'] ) ) {
						$fields[ $i ][ $k ]['required'] = false;
					}
				}

			}


			return $fields;
		}


	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_WooCommerce_Payments(), 'woocommerce_checkout' );

}