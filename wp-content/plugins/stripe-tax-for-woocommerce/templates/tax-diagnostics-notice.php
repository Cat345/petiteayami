<?php
/**
 * Template file for Stripe Tax diagnostics admin notice.
 *
 * @package Stripe\StripeTaxForWooCommerce\templates
 */

defined( 'ABSPATH' ) || exit;

$stfwc_run_diagnostics_url = wp_nonce_url(
	admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce&stripe_tax_run_diagnostics=1' ),
	'stripe_tax_run_diagnostics',
	'nonce'
);
?>
<div class="notice notice-warning inline">
	<p>
		<strong><?php echo esc_html__( 'Stripe Tax is connected but may not be collecting tax.', 'stripe-tax-for-woocommerce' ); ?></strong>
		<a
			id="stripe_tax_for_woocommerce_run_diagnostics"
			href="<?php echo esc_url( $stfwc_run_diagnostics_url ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'stripe_tax_run_diagnostics' ) ); ?>"
		>
			<?php echo esc_html__( 'Run diagnostics', 'stripe-tax-for-woocommerce' ); ?>
		</a> <?php echo esc_html__( 'to identify configuration issues.', 'stripe-tax-for-woocommerce' ); ?>
	</p>
</div>
