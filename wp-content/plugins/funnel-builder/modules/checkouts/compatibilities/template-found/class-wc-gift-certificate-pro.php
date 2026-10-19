<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_WC_Gift_Certificate_pro' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_WC_Gift_Certificate_pro {
		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'attach_gift_certi_inside_form' ) );
			add_action( 'wfacp_before_process_checkout_template_loader', array( $this, 'attach_gift_certi_inside_form' ) );
			add_action( 'wfacp_internal_css', array( $this, 'internal_css' ) );
		}

		public function attach_gift_certi_inside_form() {
			global $ignite_gift_certs; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $ignite_gift_certs is the Gift Certificates for WooCommerce Pro plugin's own global (guarded by the Ignite_Gift_Certs instanceof check below). The name is theirs and cannot be prefixed.
			if ( $ignite_gift_certs instanceof Ignite_Gift_Certs ) {
				if ( empty( $ignite_gift_certs->admin_settings['checkout_form_placement'] ) || 'after_billing_shipping' == $ignite_gift_certs->admin_settings['checkout_form_placement'] ) {
					remove_action( 'woocommerce_checkout_after_customer_details', array( &$ignite_gift_certs, 'recipient_detail_form' ), 999 );
					add_action( 'woocommerce_before_template_part', array( $this, 'field_display_below_payment_sec' ) );

				}
			}
		}

		public function field_display_below_payment_sec( $template_name ) {
			if ( 'checkout/terms.php' === $template_name ) {

				global $ignite_gift_certs; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- same third-party global as above.
				$ignite_gift_certs->recipient_detail_form();

			}
		}

		public function internal_css() {

			?>
			<style>
				body .wfacp_main_form.woocommerce .gift_cert_field_wrapper {
					background-color: transparent;
				}
			</style>
			<?php
		}
	}

	if ( ! class_exists( 'Ignite_Gift_Certs' ) ) {
		return;
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_WC_Gift_Certificate_pro(), 'wc-gift-certificate-pro' );
}