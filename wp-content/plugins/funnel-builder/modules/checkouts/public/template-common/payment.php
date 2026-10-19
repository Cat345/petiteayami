<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$wfacp_instance              = wfacp_template();
$selected_template_slug      = $wfacp_instance->get_template_slug();
$payment_des                 = $wfacp_instance->get_payment_desc();
$border_cls                  = $wfacp_instance->get_heading_title_class();
$payment_methods_heading     = $wfacp_instance->payment_heading();
$payment_methods_sub_heading = $wfacp_instance->payment_sub_heading();
$current_step                = $wfacp_instance->get_current_step();
$current_open                = $wfacp_instance->get_current_open_step();
$hide_payment_cls            = '';

$temp_open_checking = false;
if ( 'single_step' !== $current_open ) {
	$temp_open_checking = true;
}

if ( false == $temp_open_checking && 'single_step' !== $current_step ) {
	$hide_payment_cls = 'wfacp_hide_payment_part';
}

if ( WFACP_Core()->public->is_paypal_express_active_session ) {
	$hide_payment_cls = '';
}
if ( WFACP_Core()->public->is_amazon_express_active_session ) {
	$hide_payment_cls = '';
}
$payment_des_class = '';
if ( ! empty( $payment_des ) ) {
	$payment_des_class = 'wfacp-payment-dec-active';
}
?>
<div class="wfacp-section wfacp_payment <?php echo esc_attr( $hide_payment_cls ); ?>  <?php echo esc_attr( $payment_des_class ); ?> form_section_your_order_0_<?php echo esc_attr( $selected_template_slug ); ?> wfacp-section-title wfacp-hg-by-box">
	<div style="clear: both;"></div>
	<div class="wfacp-comm-title <?php echo esc_attr( $border_cls ); ?>">
		<h2 class="wfacp_section_heading wfacp_section_title <?php echo esc_attr( $wfacp_instance->get_heading_class() ); ?> "><?php echo wp_kses_post( $payment_methods_heading ); ?></h2>
		<?php if ( ! empty( $payment_methods_sub_heading ) ) { ?>
		<h4 class="<?php echo esc_attr( $wfacp_instance->get_sub_heading_class() ); ?>"><?php echo wp_kses_post( $payment_methods_sub_heading ); ?></h4>
		<?php } ?>
	</div>
	<?php do_action( 'wfacp_checkout_before_order_review' ); ?>
	<div class="woocommerce-checkout-review-order wfacp-oder-detail clearfix">
		<?php do_action( 'woocommerce_checkout_order_review' ); ?>
		<?php
		if ( $payment_des != '' ) {
			?>
			<div class="wfacp-payment-dec">
				<?php echo wp_kses_post( $payment_des ); ?>
			</div>
			<?php
		}
		?>
	</div>
	<?php do_action( 'wfacp_checkout_after_order_review' ); ?>
</div>
