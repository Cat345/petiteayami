<?php


if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
$instance = wfacp_template();
if ( apply_filters( 'wfacp_disable_mini_cart', false, $instance, '' ) ) {
	do_action( 'wfacp_disable_mini_cart_placeholder' );

	return;
}


$data  = $instance->get_checkout_fields();
$field = isset( $data['advanced']['order_summary'] ) ? $data['advanced']['order_summary'] : [];

$colspan_attr = '';
unset( $data );

if ( apply_filters( 'wfacp_cart_show_product_thumbnail', false ) ) {
	$colspan_attr1    = ' colspan="2"';
	$colspan_attr     = apply_filters( 'wfacp_order_summary_cols_span', $colspan_attr1 );
	$cellpadding_attr = ' cellpadding="20"';
}
$field       = apply_filters( 'wfacp_before_order_summary_html', $field );
$total_col   = 2;
$section_key = '';

$cart_data = [];
if ( isset( $this->customizer_fields_data['wfacp_form_cart'] ) ) {
	$cart_data = $this->customizer_fields_data['wfacp_form_cart'];
}


$rbox_border_type = '';
if ( isset( $cart_data['advance_setting']['rbox_border_type'] ) && $cart_data['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_border_type = $cart_data['advance_setting']['rbox_border_type'];
}
$template               = wfacp_template();
$selected_template_slug = $template->get_template_slug();
$enable_delete_item     = WFACP_Common::get_option( 'wfacp_form_cart_section_' . $selected_template_slug . '_order_delete_item' );
$yes_enable_delete_item = apply_filters( 'wfacp_mini_cart_enable_delete_item', wc_string_to_bool( $enable_delete_item ), [], '' );
$data_enable_delete     = '';
if ( true === $yes_enable_delete_item ) {
	$data_enable_delete = 'data-delete-enabled="1"';
}
?>

<div <?php echo $data_enable_delete ?> class="wfacp_form_cart wfacp_min_cart_widget <?php echo $rbox_border_type; ?> div_wrap_sec" <?php echo WFACP_Common::get_fragments_attr() ?>>
    <div class="wfacp_order_sec wfacp_order_summary_layout_9">
		<?php
		if ( isset( $cart_data['heading_section']['heading'] ) && $cart_data['heading_section']['heading'] != '' && isset( $cart_data['heading_section']['enable_heading'] ) && $cart_data['heading_section']['enable_heading'] == true ) {
			$align_text         = $cart_data['heading_section']['heading_talign'];
			$font_weight        = $cart_data['heading_section']['heading_font_weight'];
			$heading_fs_desktop = $cart_data['heading_section']['heading_fs']['desktop'];
			$heading_fs_tablet  = $cart_data['heading_section']['heading_fs']['tablet'];
			$heading_fs_mobile  = $cart_data['heading_section']['heading_fs']['mobile'];
			?>
            <h2 class="wfacp-list-title wfacp_section_title <?php echo $align_text . ' ' . $font_weight; ?>">
				<?php echo isset( $field['label'] ) ? $field['label'] : __( 'Order summary', 'woocommerce' ); ?>
            </h2>
			<?php
		}

		$orderCouponHide = 'wfacp_sidebar_coupon_show';
		if ( isset( $cart_data['product_cart_coupon'] ) && true === $cart_data['product_cart_coupon'] ) {

			$orderCouponHide = "wfacp_sidebar_coupon_hide";

		}

		?>
        <div class="<?php echo $orderCouponHide; ?>">
			<?php
			include __DIR__ . '/order-review.php';
			do_action( 'wfacp_below_mini_cart_item', $instance );
			include __DIR__ . '/sections/form-coupon.php';
			do_action( 'wfacp_below_mini_cart_coupon', $instance );
			include __DIR__ . '/order-total.php';
			?>
        </div>
    </div>
</div>
