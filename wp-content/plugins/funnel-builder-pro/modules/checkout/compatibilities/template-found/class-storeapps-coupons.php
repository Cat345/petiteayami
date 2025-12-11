<?php

/**
 * WooCommerce Smart Coupons by StoreApps
 */
if ( ! class_exists( 'WFACP_Storeapps_Coupons' ) ) {
	class WFACP_Storeapps_Coupons {
		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'handle_aero_coupons' ) );

			add_action( 'wc_sc_before_auto_apply_coupons', [ $this, 'handle_auto_apply' ], 10 );

		}

		/**
		 * Handle Aero coupons processing
		 */
		public function handle_aero_coupons() {
			// Check if direct coupon-code is present in URL
			if ( isset( $_REQUEST['coupon-code'] ) ) {
				$coupon_code = $_REQUEST['coupon-code'];
			} // Otherwise check for aero-coupons parameter
			elseif ( isset( $_REQUEST['aero-coupons'] ) ) {
				$coupon_code             = $_REQUEST['aero-coupons'];
				$_REQUEST['coupon-code'] = $coupon_code;
			} else {
				return;
			}

			// Process coupon if Smart Coupons plugin is active
			if ( class_exists( 'WC_SC_Coupon_Actions' ) && method_exists( 'WC_SC_Coupon_Actions', 'coupon_action' ) ) {
				WC_SC_Coupon_Actions::get_instance()->coupon_action( $coupon_code );
			}
		}

		public function handle_auto_apply() {
			if ( did_action( 'wc_ajax_checkout' ) || ! wp_doing_ajax() || ! did_action( 'wfacp_after_template_found' ) ) {
				return;
			}
			add_filter( 'woocommerce_notice_types', '__return_empty_array' );
		}
	}

	new WFACP_Storeapps_Coupons();
}