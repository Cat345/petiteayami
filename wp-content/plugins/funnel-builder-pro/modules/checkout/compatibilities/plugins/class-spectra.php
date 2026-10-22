<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Spectra' ) ) {

	/**
	 * Spectra (Ultimate Addons for Gutenberg) - Issue #8260, #9540.
	 *
	 * Spectra builds block CSS per post and only for the post it believes is on screen: the
	 * queried page, plus the WooCommerce checkout page on is_checkout(). With Global Checkout the
	 * page on screen is a FunnelKit checkout step rendered inside the native checkout page, so
	 * its Spectra blocks were left unstyled.
	 *
	 * This used to be solved by returning the step ID from pre_option_woocommerce_checkout_page_id.
	 * That rewrote the checkout page for the whole request, so wc_get_checkout_url() became the
	 * step permalink: the subscription renewal "Pay" redirect, the remove-coupon links in the
	 * (recurring) order review and every URL built from them sent customers to the funnel step
	 * instead of the checkout. The step's assets are now enqueued directly, and no option is
	 * touched.
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Spectra {

		/**
		 * Checkout step Aero is rendering on this request.
		 *
		 * @var int
		 */
		private $checkout_id = 0;

		public function __construct() {
			add_action( 'wfacp_checkout_page_found', array( $this, 'setup_spectra_compatibility' ) );
		}

		/**
		 * Setup Spectra compatibility
		 *
		 * Runs on wp:5, from the template loader, only when Aero has taken over the page.
		 *
		 * @param int $checkout_id Checkout step ID.
		 *
		 * @since 3.22.1
		 */
		public function setup_spectra_compatibility( $checkout_id ) {
			if ( WFACP_Common::is_disabled() || ! function_exists( 'uagb_get_post_assets' ) ) {
				return;
			}

			$this->checkout_id = absint( $checkout_id );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_step_assets' ) );
		}

		/**
		 * Enqueue the Spectra assets of the checkout step being rendered.
		 *
		 * On a dedicated checkout URL the step is the queried post and Spectra already loads its
		 * assets, so this only acts when the step is rendered inside another page (Global Checkout,
		 * order-pay).
		 *
		 * @since 3.22.2
		 */
		public function enqueue_checkout_step_assets() {
			if ( $this->checkout_id <= 0 || get_queried_object_id() === $this->checkout_id ) {
				return;
			}

			uagb_get_post_assets( $this->checkout_id )->enqueue_scripts();
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Spectra(), 'spectra' );
}
