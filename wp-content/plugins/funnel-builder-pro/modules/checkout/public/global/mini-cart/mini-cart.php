<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $widget_id
 */

$instance = wfacp_template();

if ( apply_filters( 'wfacp_disable_mini_cart', false, $instance, $widget_id ) ) {
	do_action( 'wfacp_disable_mini_cart_placeholder' );

	return;
}
$settings = WFACP_Common::get_session( $widget_id );
$instance->set_mini_cart_data( $settings );
$enable_delete_item = $instance->mini_cart_allow_deletion();
$allow_coupon       = $instance->mini_cart_allow_coupon();

$heading = $instance->mini_cart_heading();


add_filter( 'wp_get_attachment_image_attributes', 'WFACP_Common::remove_src_set' );
?>
	<div class="wfacp_wrapper_start wfacp_mini_cart_start_h <?php echo esc_attr( $instance->get_field_label_position() ) . ' ' . esc_attr( $instance->get_template_type() ); ?>" id="wfacp_mini_cart_start_h">

		<?php
		do_action( 'wfacp_mini_cart_top' );
		if ( '' !== $heading ) {
			?>
			<div class="wfacp-order-summary-label"><?php echo wp_kses_post( $heading ); ?></div>
			<?php
		}
		?>
		<?php
		/*
		 * wfacp_mini_cart_elementor is a misnomer: this markup is the same for every builder, and
		 * the builder is identified on the wrapper above via get_template_type(). It is still
		 * printed because customer CSS and the builder modules' own style controls target it.
		 * Our stylesheets use wfacp_mini_cart_wrap so no builder name appears in shared CSS.
		 */
		?>
		<div class="wfacp_anim wfacp_order_summary_container wfacp_min_cart_widget wfacp_mini_cart_wrap wfacp_mini_cart_elementor" data-delete-enabled="<?php echo esc_attr( $enable_delete_item ); ?>">
			<?php
			require __DIR__ . '/mini-cart-items.php';
			do_action( 'wfacp_below_mini_cart_item', $instance );
			if ( true == $allow_coupon ) {
				$instance->get_mini_cart_coupon( $widget_id );
			}
			do_action( 'wfacp_below_mini_cart_coupon', $instance );
			require __DIR__ . '/mini-cart-review-totals.php';
			?>
		</div>
		<?php
		do_action( 'wfacp_mini_cart_bottom' );
		?>
	</div>
<?php

remove_filter( 'wp_get_attachment_image_attributes', 'WFACP_Common::remove_src_set' );