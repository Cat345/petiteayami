<?php
/**
 * Template file for sandbox/test mode admin notice banner.
 *
 * Shown while the plugin is running in sandbox (test) mode. The notice is
 * dismissible per-user and is never displayed while the plugin is in live mode.
 *
 * @package Stripe\StripeTaxForWooCommerce\templates
 */

defined( 'ABSPATH' ) || exit;

use Stripe\StripeTaxForWooCommerce\WordPress\Hooks;

$stfwc_settings_url  = admin_url( 'admin.php?page=wc-settings&tab=stripe_tax_for_woocommerce' );
$stfwc_dismiss_nonce = wp_create_nonce( Hooks::SANDBOX_NOTICE_DISMISS_ACTION );
$stfwc_ajax_url      = admin_url( 'admin-ajax.php' );
?>
<div class="notice notice-warning is-dismissible stripe-tax-sandbox-notice"
	data-stfwc-dismiss-action="<?php echo esc_attr( Hooks::SANDBOX_NOTICE_DISMISS_ACTION ); ?>"
	data-stfwc-dismiss-nonce="<?php echo esc_attr( $stfwc_dismiss_nonce ); ?>"
	data-stfwc-ajax-url="<?php echo esc_url( $stfwc_ajax_url ); ?>">
	<p>
		<strong><?php echo esc_html__( 'Stripe Tax is in sandbox mode.', 'stripe-tax-for-woocommerce' ); ?></strong>
		<?php echo esc_html__( 'No live tax calculations will be performed. Switch to live mode when you are ready.', 'stripe-tax-for-woocommerce' ); ?>
		<a href="<?php echo esc_url( $stfwc_settings_url ); ?>"><?php echo esc_html__( 'Go to settings', 'stripe-tax-for-woocommerce' ); ?></a>
	</p>
</div>
<script>
	( function () {
		var notice = document.currentScript.previousElementSibling;
		if ( ! notice || ! notice.classList.contains( 'stripe-tax-sandbox-notice' ) ) {
			return;
		}
		notice.addEventListener( 'click', function ( event ) {
			if ( ! event.target.classList.contains( 'notice-dismiss' ) ) {
				return;
			}
			var body = new FormData();
			body.append( 'action', notice.getAttribute( 'data-stfwc-dismiss-action' ) );
			body.append( '_nonce', notice.getAttribute( 'data-stfwc-dismiss-nonce' ) );

			if ( window.fetch ) {
				window.fetch( notice.getAttribute( 'data-stfwc-ajax-url' ), {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				} );
			}
		} );
	} )();
</script>
