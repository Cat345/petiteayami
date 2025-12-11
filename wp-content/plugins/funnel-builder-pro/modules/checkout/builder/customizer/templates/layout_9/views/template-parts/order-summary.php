<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}

$instance = wfacp_template();
$data     = $instance->get_checkout_fields();

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
if ( isset( $this->customizer_fields_data['wfacp_form'] ) ) {
	$cart_data = $this->customizer_fields_data['wfacp_form'];
}


if ( isset( $this->customizer_fields_data['wfacp_form_cart'] ) ) {
	$wfacp_form_cart = $this->customizer_fields_data['wfacp_form_cart'];
}

$rbox_border_type = '';
if ( isset( $cart_data['advance_setting']['rbox_border_type'] ) && $cart_data['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_border_type = $cart_data['advance_setting']['rbox_border_type'];
}


$rbox_border_type = '';
if ( isset( $wfacp_form_cart['advance_setting']['rbox_border_type'] ) && $wfacp_form_cart['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_mobile = $wfacp_form_cart['advance_setting']['rbox_border_type'];
}


$selected_template_type = $instance->get_template_type();

if ( $selected_template_type != 'pre_built' ) {
	$rbox_mobile = 'none';
}


?>
<div class="wfacp_form_cart <?php echo $rbox_mobile; ?> div_wrap_sec" <?php echo WFACP_Common::get_fragments_attr(); ?> >
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
				<?php echo isset( $field['label'] ) ? $field['label'] : __( 'Order Summary', 'woofunnels-aero-checkout' ); ?>
            </h2>
			<?php
		}

		$orderCouponHide = 'wfacp_sidebar_coupon_show';

		if ( isset( $cart_data['form_data']['enable_coupon'] ) && true === $cart_data['form_data']['enable_coupon'] ) {

			$orderCouponHide = "wfacp_sidebar_coupon_hide";

		}

		$settings          = WFACP_Common::get_page_settings( WFACP_Common::get_id() );
		$is_disable_coupon = ( isset( $settings['disable_coupon'] ) && 'true' == $settings['disable_coupon'] );
		$coupon_class              = 'wfacp_active_coupon';
		$is_disable_coupon_sidebar = apply_filters( 'wfacp_mini_cart_hide_coupon', true );


		if ( false == wc_string_to_bool( $is_disable_coupon ) && false===$is_disable_coupon_sidebar  ) {
			$coupon_class = 'wfacp_in_active_coupon';
		}


		?>
        <div class="<?php echo $orderCouponHide; ?>">
            <div class="<?php echo $coupon_class; ?>">
				<?php

				include __DIR__ . '/order-review.php';
				include __DIR__ . '/form-coupon.php';
				include __DIR__ . '/order-total.php';
				?>
            </div>
        </div>
    </div>
</div>
