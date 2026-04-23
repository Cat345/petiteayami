<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Theme_Woostify' ) ) {

	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Theme_Woostify {

		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'remove_actions' ) );
			add_action( 'wfacp_before_process_checkout_template_loader', array( $this, 'remove_actions' ) );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );
		}

		public function remove_actions() {
			// Remove Woostify layout-3 product image injection.
			if ( function_exists( 'woostify_checkout_product_image' ) ) {
				remove_filter( 'woocommerce_cart_item_name', 'woostify_checkout_product_image', 10 );
			}

			// Remove Woostify layout-2/default product thumbnail injection.
			if ( function_exists( 'woostify_add_product_thumbnail_to_checkout_order' ) ) {
				remove_filter( 'woocommerce_cart_item_name', 'woostify_add_product_thumbnail_to_checkout_order', 10 );
			}

			// Remove Woostify layout-3 quantity removal.
			if ( function_exists( 'woostify_checkout_product_quantity' ) ) {
				remove_filter( 'woocommerce_checkout_cart_item_quantity', 'woostify_checkout_product_quantity', 99 );
			}

			// Remove Woostify layout-3 checkout wrapper hooks.
			remove_action( 'woocommerce_before_checkout_form', 'woostify_checkout_form_distr_free_bg', 0 );
			remove_action( 'woocommerce_before_checkout_form', 'woostify_checkout_options_start', 5 );
			remove_action( 'woocommerce_before_checkout_form', 'woostify_checkout_options_end', 15 );
			remove_action( 'woocommerce_checkout_before_customer_details', 'woostify_checkout_row_start', 0 );
			remove_action( 'woocommerce_checkout_before_customer_details', 'woostify_checkout_col_left_start', 0 );
			remove_action( 'woocommerce_checkout_after_customer_details', 'woostify_checkout_col_left_end', 50 );
			remove_action( 'woocommerce_checkout_after_customer_details', 'woostify_checkout_col_right_start', 55 );
			remove_action( 'woocommerce_after_checkout_form', 'woostify_checkout_col_right_end', 50 );
			remove_action( 'woocommerce_after_checkout_form', 'woostify_checkout_row_end', 50 );

			// Remove Woostify multi-step checkout wrapper hooks.
			remove_action( 'woocommerce_checkout_before_customer_details', 'woostify_multi_checkout_wrapper_start', 10 );
			remove_action( 'woocommerce_checkout_before_customer_details', 'woostify_multi_checkout_first_wrapper_start', 20 );
			remove_action( 'woocommerce_checkout_after_customer_details', 'woostify_multi_checkout_first_wrapper_end', 10 );
			remove_action( 'woocommerce_checkout_after_customer_details', 'woostify_multi_checkout_second', 20 );
			remove_action( 'woocommerce_checkout_after_customer_details', 'woostify_multi_checkout_third', 30 );
			remove_action( 'woocommerce_checkout_after_customer_details', 'woostify_multi_checkout_button_action', 40 );
			remove_action( 'woocommerce_checkout_after_customer_details', 'woostify_multi_checkout_wrapper_end', 100 );
			remove_action( 'woocommerce_checkout_after_order_review', 'woostify_checkout_before_order_review', 10 );
			remove_action( 'woostify_after_header', 'woostify_multi_step_checkout', 10 );

			// Remove Woostify order review wrappers.
			remove_action( 'woocommerce_checkout_order_review', 'woostify_order_review_start', 5 );
			remove_action( 'woocommerce_checkout_order_review', 'woostify_order_review_end', 15 );

			// Remove Woostify back to cart link.
			remove_action( 'woocommerce_before_checkout_billing_form', 'woostify_checkout_back_to_cart_link', 5 );

			// Layout-2: theme hides the default Place Order button (for its own multi-step UI). After we strip
			// multi-step hooks, Aero needs the real button again.
			remove_filter( 'woocommerce_order_button_html', '__return_empty_string', 10 );

			// Woostify layout-3 prints the coupon inside order review; FunnelKit already outputs it on
			// woocommerce_before_checkout_form and the mini cart fires review_order_after_cart_contents → duplicate "Have a coupon?".
			remove_action( 'woocommerce_review_order_after_cart_contents', 'woostify_checkout_coupon_form', 10 );

			// Dequeue Woostify multi-step checkout script.
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_scripts' ), 999 );
		}

		public function dequeue_scripts() {
			wp_dequeue_script( 'woostify-multi-step-checkout' );
		}

		public function internal_css() {
			$instance = wfacp_template();
			if ( ! $instance instanceof WFACP_Template_Common ) {
				return;
			}

			$bodyClass = 'body ';
			if ( 'pre_built' !== $instance->get_template_type() ) {
				$bodyClass = 'body #wfacp-e-form ';
			}

			echo '<style>';
			// Reset Woostify layout-3 column wrappers when they leak into the Aero form.
			echo esc_html( $bodyClass . '.wfacp_main_form .woostify-col-left, ' . $bodyClass . '.wfacp_main_form .woostify-col-right { width: 100%; float: none; }' );
			// Fallback only: remove_actions() clears Woostify cart-item image filters; this covers
			// mini cart + order summary if anything still injects .w-product-thumb or .review-order-product-image.
			// Scoped to .wfacp_main_form — do NOT target .multi-step-checkout-wrapper (FunnelKit uses that class inside #wfacp-e-form for its own multi-step UI).
			echo esc_html( $bodyClass . '.wfacp_main_form .w-product-thumb, ' . $bodyClass . '.wfacp_main_form .review-order-product-image { display: none !important; }' );
			echo '</style>';
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Theme_Woostify(), 'woostify' );
}
