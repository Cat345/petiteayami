<?php
/**
 * DPD France Compatibility for FunnelKit Checkout
 *
 * Plugin Name: DPD France by DPD France S.A.S
 * Plugin URI: https://wordpress.org/plugins/woocommerce-dpdfrance/
 *
 * Handles styling and hook relocation for DPD France shipping methods
 * to ensure proper rendering within FunnelKit Checkout templates.
 *
 * @package FunnelKit\Checkout\Compatibilities
 * @since 3.22.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_DPD_France' ) ) {
	/**
	 * DPD France compatibility class
	 *
	 * Ensures DPD France shipping method pickup point selection interface
	 * renders correctly within FunnelKit Checkout layout.
	 *
	 * @since 3.22.1
	 */
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_DPD_France {

		/**
		 * Constructor
		 *
		 * @since 3.22.1
		 */
		public function __construct() {
			add_action( 'wfacp_template_load', [ $this, 'relocate_hooks' ] );
			add_action( 'wfacp_internal_css', [ $this, 'internal_css' ] );
			add_filter( 'wfacp_show_shipping_options', '__return_true' );
		}

		/**
		 * Relocate DPD France hooks to FunnelKit-specific hooks
		 *
		 * Moves the relais checkout controller from standard WooCommerce hook
		 * to FunnelKit hook for proper positioning within shipping table.
		 * Runs on both page load and AJAX updates.
		 *
		 * @since 3.22.1
		 * @return void
		 */
		public function relocate_hooks() {
			if ( function_exists( 'woocommerce_dpdfrance_relais_checkout_controller' ) ) {
				remove_action( 'woocommerce_review_order_after_shipping', 'woocommerce_dpdfrance_relais_checkout_controller' );
				add_action( 'wfacp_woocommerce_review_order_after_shipping', 'woocommerce_dpdfrance_relais_checkout_controller' );
			}
		}

		/**
		 * Load DPD France assets and add custom CSS styling
		 *
		 * Loads DPD France JavaScript and CSS assets, then outputs
		 * custom CSS rules to style the pickup point selection interface.
		 *
		 * @since 3.22.1
		 * @param string $selected_template_slug The current template slug (unused but required by hook).
		 * @return void
		 */
		public function internal_css( $selected_template_slug = '' ) {
			// Load DPD France assets if functions are available.
			if ( function_exists( 'woocommerce_dpdfrance_custom_script' ) ) {
				woocommerce_dpdfrance_custom_script();
			}
			if ( function_exists( 'woocommerce_dpdfrance_relais_load_assets' ) ) {
				woocommerce_dpdfrance_relais_load_assets();
			}
			if ( function_exists( 'woocommerce_dpdfrance_predict_load_assets' ) ) {
				woocommerce_dpdfrance_predict_load_assets();
			}
			?>
			<style>
				/* DPD France Compatibility - Radio button positioning */
				body #wfacp-e-form .wfacp_main_form.woocommerce #order_review input[type=radio]:not(.shipping_method) {
					position: relative;
					left: auto;
					right: auto;
					top: auto;
					bottom: auto;
					margin-right: 5px;
				}

				body #wfacp-e-form .wfacp_main_form.woocommerce #order_review input[type=radio]:not(.shipping_method) + label {
					display: inline-block;
					padding-left: 0;
				}

				/* DPD France Compatibility - Relay selection input */
				body #wfacp-sec-wrapper #wfacp_checkout_form input[name=dpdfrance_relay_id]:not(old) {
					opacity: 0;
				}

				/* DPD France Compatibility - Header styling */
				body #wfacp-sec-wrapper #wfacp_checkout_form div#dpdfrance_div_relais_header p {
					margin: 0 !important;
					color: #fff;
				}

				body #wfacp-sec-wrapper #wfacp_checkout_form #dpdfrance_div_relais_header {
					height: auto;
					padding: 10px;
					color: #fff;
				}

				/* DPD France Compatibility - Modal box headers */
				body #wfacp-sec-wrapper .dpdfrrelaisboxadresseheader,
				body #wfacp-sec-wrapper .dpdfrrelaisboxhorairesheader,
				body #wfacp-sec-wrapper .dpdfrrelaisboxinfosheader {
					font-size: 12px !important;
					line-height: 0 !important;
				}

				body #wfacp-sec-wrapper .dpdfrrelaisboxadresseheader img,
				body #wfacp-sec-wrapper .dpdfrrelaisboxhorairesheader img,
				body #wfacp-sec-wrapper .dpdfrrelaisboxinfosheader img {
					height: 64px;
					width: 64px;
					margin: 5px auto;
				}

				/* DPD France Compatibility - Info box typography */
				body #wfacp-sec-wrapper .dpdfrrelaisboxinfos h5 {
					font-family: 'DPDPlutoSansLight', sans-serif;
					display: inline-block;
					color: #dc0032 !important;
					font-size: 12px;
					font-weight: 400;
					width: 200px;
					margin: 0;
					text-transform: none;
					letter-spacing: 0;
				}

				/* DPD France Compatibility - Opening hours box */
				body #wfacp-sec-wrapper .dpdfrrelaisboxhoraires p {
					padding: 0;
					padding-top: 5px;
					padding-left: 5px;
					line-height: 11px;
					margin: 0 !important;
				}

				body #wfacp-sec-wrapper .dpdfrrelaisboxhoraires {
					text-align: left;
					background: #fff;
					height: auto;
					line-height: 22px;
					font-size: 11px;
					width: 260px;
					left: 250px;
					position: absolute;
				}

				body #wfacp-sec-wrapper .dpdfrrelaisboxhoraires p span {
					font-family: 'DPDPlutoSansLight', sans-serif;
					display: inline-block;
					color: #dc0032 !important;
					font-size: 12px;
					font-weight: 400;
					width: 90px;
					margin: 0;
				}

				/* DPD France Compatibility - General typography */
				body #wfacp-sec-wrapper .dpdfrrelaisboxhoraires *,
				body #wfacp-sec-wrapper .dpdfrrelaisboxinfos *,
				body #wfacp-sec-wrapper div#dpdfrrelaisboxadresse {
					font-size: 12px;
					line-height: 1.5;
				}
			</style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_DPD_France(), 'woocommerce-dpdfrance' );
}
