<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Divi_Ecommerce_Pro' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Divi_Ecommerce_Pro {

		public function __construct() {
			// Hook-removal (filters + wp_head inline CSS) — scoped via wfacp_after_checkout_page_found.
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'action' ) );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );

			// Stylesheet dequeue — registered in __construct (not inside `action()`) because in
			// the Divi 5 + Theme Builder + child theme combo, `wp_enqueue_scripts` alone is NOT
			// sufficient: Divi 5 enqueues some styles AFTER the wp_enqueue_scripts action has
			// completed. `wp_print_styles` (fires at wp_head@8, right before styles are output)
			// catches those late enqueues. Both hooks call the same method; the second is the
			// safety net for Divi 5's late enqueue path.
			//
			// Runtime scoping (so we only dequeue on WFACP checkout pages, not site-wide) is
			// enforced by the `did_action()` check inside `remove_theme_style()`.
			add_action( 'wp_enqueue_scripts', array( $this, 'remove_theme_style' ), 99999 );
			add_action( 'wp_print_styles', array( $this, 'remove_theme_style' ), 99999 );
		}

		/**
		 * Divi Ecommerce Pro (Aspen Grove Studios) child theme is incompatible with FunnelKit Aero
		 * in three ways:
		 *
		 * 1. It opens `<div class="dsdep-checkout-order">` via `woocommerce_checkout_before_order_review_heading`
		 *    and closes it via `woocommerce_review_order_after_payment`. On stock WC both hooks fire in
		 *    the same template so the div balances. In Aero the two hooks land in DIFFERENT modules
		 *    (mini-cart captures the open, form captures the close), each inside its own ob_start/ob_get_clean.
		 *    The result: form output has an unmatched `</div>` and mini-cart output has an unmatched `<div>`,
		 *    which cascades into column_3_5 closing early and column_2_5 escaping its parent row.
		 *
		 * 2. It echoes a large inline `<style>` block to `wp_head` with `!important` rules targeting
		 *    `#et-boc .et-l .woocommerce input[type=submit]`, `.dsdep-checkout .woocommerce #payment #place_order`,
		 *    `.woocommerce-info`, etc., overriding our checkout form's button/input/notice styling.
		 *
		 * 3. Its main stylesheet (`child-theme-style` → `scss/app.css`, ~5k lines of WC overrides) and the
		 *    WC customizer stylesheet (`woocommerce-style`) also contain rules that conflict with our form.
		 *
		 * See: funnel-builder/bin/tickets/felicisalumi-checkout-layout/MEMORY.md
		 */
		public function action() {
			// (1) Unblock the layout — remove both halves of the split div wrapper.
			remove_filter( 'woocommerce_checkout_before_order_review_heading', 'divi_ecommerce_pro_woocommerce_checkout_open_div' );
			remove_filter( 'woocommerce_review_order_after_payment', 'divi_ecommerce_pro_woocommerce_checkout_close_div' );

			// (2) Strip the inline customizer CSS echoed to wp_head.
			// Source: themes/divi-ecommerce-pro/customizer/customizer.php:645,840
			remove_action( 'wp_head', 'divi_ecommerce_pro_customize_css' );
		}

		/**
		 * Dequeue the child theme stylesheets on WFACP checkout pages only.
		 *
		 * - `child-theme-style` → `scss/app.css` (~5k lines of WC overrides via `_notifications.scss` etc.)
		 * - `woocommerce-style` → `customizer/woocommerce.css` + any inline CSS attached to it
		 *
		 * Runtime scoping: `did_action('wfacp_after_checkout_page_found')` returns 0 when we're
		 * NOT on a FunnelKit checkout page, making this a zero-cost no-op everywhere else.
		 */
		public function remove_theme_style() {
			if ( ! did_action( 'wfacp_after_checkout_page_found' ) ) {
				return;
			}
			wp_dequeue_style( 'child-theme-style' );
			wp_dequeue_style( 'woocommerce-style' );
		}

		/**
		 * Placeholder for targeted CSS overrides once specific residual distortions are identified
		 * (e.g. rules coming from the parent Divi theme that can't be dequeued wholesale).
		 */
		public function internal_css() {
			// Intentionally empty — fill in once we identify rules that survive the dequeues.
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Divi_Ecommerce_Pro(), 'wfacp-divi-ecommerce-pro' );
}
