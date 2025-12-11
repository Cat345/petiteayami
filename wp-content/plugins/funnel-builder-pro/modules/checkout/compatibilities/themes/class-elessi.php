<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Theme Name: Elessi Theme by NasaTheme v.6.3.7
https://elessi.nasatheme.com
*/
if ( ! class_exists( 'WFACP_Compatabilty_Elessi' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatabilty_Elessi {
		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', [ $this, 'remove_action' ] );
			add_action( 'wfacp_internal_css', [ $this, 'internal_css' ] );
		}


		public function remove_action() {

			if ( defined( 'NASA_CHECKOUT_LAYOUT' ) && NASA_CHECKOUT_LAYOUT == 'modern' ) {
				remove_action( 'woocommerce_checkout_after_customer_details', 'elessi_step_billing', 15 );
				remove_action( 'woocommerce_checkout_after_customer_details', 'elessi_checkout_shipping', 20 );
				remove_action( 'woocommerce_review_order_before_payment', 'elessi_checkout_payment_open', 5 );
				remove_action( 'woocommerce_review_order_before_payment', 'elessi_checkout_payment_headling' );
				remove_action( 'woocommerce_checkout_after_customer_details', 'woocommerce_checkout_payment', 25 );
				remove_action( 'woocommerce_review_order_after_payment', 'elessi_checkout_payment_close', 100 );
				if ('shipping' === get_option('woocommerce_ship_to_destination')) {
					remove_action('woocommerce_review_order_before_payment', 'elessi_checkout_modern_billing_detail', 4);
					remove_filter('woocommerce_shipping_fields', 'elessi_checkout_add_shipping_phone');
					remove_filter('woocommerce_checkout_posted_data', 'elessi_checkout_modern_posted_data');
				}
			}
			add_action( 'wp_print_styles', [ $this, 'remove_theme_css_and_scripts' ], 100 );
		}

		public function is_enabled() {
			return function_exists( 'elessi_enqueue_style' );
		}

		public function remove_theme_css_and_scripts() {
			if ( false === $this->is_enabled() ) {
				return;
			}
			global $wp_styles;
			$registered_style = $wp_styles->registered;
			if ( ! empty( $registered_style ) ) {
				foreach ( $registered_style as $handle => $data ) {
					if ( $handle === 'elessi-style-woo-pages' ) {
						wp_dequeue_style( $handle );
					}
				}
			}
		}
		public function internal_css() {
			if ( false === $this->is_enabled() ) {
				return;
			}
			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}
			$bodyClass = "body ";
			if ( 'pre_built' !== $instance->get_template_type() ) {
				$bodyClass = "body #wfacp-e-form ";
			}
			$cssHtml = "<style>";
			$cssHtml .= $bodyClass . ".wfacp_main_form.woocommerce .nsl-container-block .nsl-container-buttons a {margin: 0 0 10px;}";
			$cssHtml .= $bodyClass . ".wfacp_main_form.woocommerce .nsl-container-block .nsl-container-buttons a:last-child {margin-bottom: 0;}";
			$cssHtml .= $bodyClass . ".wfacp_main_form.woocommerce .cfc-carbo-offset-button button:hover:before {width: 100%;}";
			$cssHtml .= $bodyClass . ".wfacp_main_form.woocommerce .cfc-carbo-offset-button button { border-color: #2AA43C; background: #2AA43C;color: #ffffff;-webkit-transition: all 200ms ease; -moz-transition: all 200ms ease; -o-transition: all 200ms ease; transition: all 200ms ease;}";
			$cssHtml .= $bodyClass . ".wfacp_main_form.woocommerce #cfc-learn-more { margin-left: auto;}";
			$cssHtml .= $bodyClass . ".wfacp_main_form.woocommerce .cfc-carbo-offset-button button { width: 100%;height: 45px; line-height: 1;font-weight: bold; border: 2px solid #2AA43C; background: #2AA43C;color: #ffffff; padding: 8px 24px;border-radius: 3px; transition: all .5s ease-out;position: relative;
    z-index: 1;box-sizing: border-box;outline: none; margin-bottom: 0;}";
			$cssHtml .= $bodyClass . ".wfacp_main_form.woocommerce .cfc-carbo-offset-button button:hover {color: #2AA43C;background-color: transparent; border-color: #6a9f1e;}";
			$cssHtml .= $bodyClass . ".cfc-angle-down {border: solid #2AA43C;border-width: 0 3px 3px 0;display: inline-block;padding: 4px;margin-left: 5px;margin-bottom: 8px;vertical-align: middle;transform: rotate(45deg); -webkit-transform: rotate(45deg);}";
			$cssHtml .= $bodyClass . ".wfacp_main_form.woocommerce .cfc-carbo-offset-button button:before {content: '' !important; position: absolute; z-index: -1; width: 0%; height: 100%; top: 0;left: 0; background: #fff;transition: all 0.6s;}";
			$cssHtml .= $bodyClass ." span.nasa-error {display: none;}";
			$cssHtml .= $bodyClass ." .wfacp-row p:nth-child(2n+1) {clear: both;}";
			$cssHtml .= "</style>";
			echo $cssHtml;
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatabilty_Elessi(), 'wfacp-elessi-theme' );
}