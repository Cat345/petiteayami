<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Theme_Rey' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Theme_Rey {

		public function __construct() {
			add_action( 'wfacp_template_load', array( $this, 'remove_action' ) );
			add_action( 'wfacp_internal_css', array( $this, 'add_internal_css' ) );
		}

		/**
		 * Remove Rey checkout hooks to prevent conflicts
		 * Removes hooks that cause multiple images and layout distortion
		 */
		public function remove_action() {
			if ( ! class_exists( 'ReyCore\WooCommerce\Tags\Checkout' ) ) {
				return;
			}

			WFACP_Common::remove_actions( 'woocommerce_cart_item_name', 'ReyCore\WooCommerce\Tags\Checkout', 'checkout__classic_add_thumb' );
			WFACP_Common::remove_actions( 'woocommerce_before_checkout_form', 'ReyCore\WooCommerce\Tags\Checkout', 'cart_progress' );
		}

		/**
		 * Add internal CSS to fix layout issues caused by Rey theme
		 */
		public function add_internal_css() {
			?>
			<style>
				/* Reset Rey theme's label text-transform */
				body .wfacp_main_form form label.wfacp-form-control-label {
					text-transform: initial;
				}
				/* Fix coupon form padding in mini cart */
				body .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-col-left-half {
					padding-left: 0;
				}
				body .wfacp_mini_cart_start_h form.checkout_coupon.woocommerce-form-coupon .wfacp-col-full {
					padding-right: 0;
				}
				/* Reset Rey theme's form label styling */
				body .wfacp_main_form form .form-row label,
				body .wfacp_main_form .wccf_field_container label {
					font-weight: normal;
					text-transform: unset;
					margin-bottom: 5px;
				}
				/* Hide Rey's coupon toggle pseudo-element */
				body .wfacp_main_form .woocommerce-form-coupon-toggle a:after {
					display: none;
				}
			</style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Theme_Rey(), 'rey' );
}
