<?php
/**
 * Checkout before customer details
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/flexible-shipping/after_shipping_rate.php
 *
 * @package WPDesk\FS\TableRate\ShippingMethod
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<p class="shipping-method-description">
	<?php echo wp_kses_post( $method_description ); ?>
</p>
<?php if ( ! empty( $method_logo_url ) ) : ?>
	<p class="shipping-method-logo">
		<img
			src="<?php echo esc_url( $method_logo_url ); ?>"
			alt="<?php echo esc_attr( $method_logo_alt ?? '' ); ?>"
			style="max-width:96px;max-height:48px;width:auto;height:auto;"
		/>
	</p>
<?php endif; ?>
