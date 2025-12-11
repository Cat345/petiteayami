<?php

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}

if ( ! wc_coupons_enabled() ) { // @codingStandardsIgnoreLine.
	return;
}
$settings          = WFACP_Common::get_page_settings( WFACP_Common::get_id() );
$is_disable_coupon = ( isset( $settings['disable_coupon'] ) && 'true' == $settings['disable_coupon'] );
if ( $is_disable_coupon ) {
	return;
}


$coupon_cls = 'wfacp-col-left-half';
if ( $this->template_type == 'embed_form' ) {
	$coupon_cls = 'wfacp-col-full';
}

$is_disable_coupon_sidebar = apply_filters( 'wfacp_layout_9_sidebar_hide_coupon', true );


$wfacp_sidebar_coupon_text = apply_filters( 'wfacp_sidebar_coupon_text', __( 'Coupon code', 'woocommerce' ) );
$apply_coupon_button_text = apply_filters( 'wfacp_apply_coupon_button_text', __( 'Apply', 'woocommerce' ) );


if ( $is_disable_coupon_sidebar ) {
	$coupon_sidebar = $this->enable_coupon_right_side_coupon();

	$classBlock = 'wfacp_display_block';
	?>
    <div class="wfacp_woocommerce_form_coupon wfacp_template_9_coupon">
        <div class="wfacp-coupon-section wfacp_custom_row_wrap clearfix">
            <div class="wfacp-coupon-page">
				<?php
				if ( true === $coupon_sidebar ) {
					wc_print_notice( apply_filters( 'woocommerce_checkout_coupon_message', '<a class="wfacp_main_showcoupon">' . __( 'Have a coupon?', 'woocommerce' ) . ' ' . __( 'Click here to enter your code', 'woocommerce' ) . '</a>' ), 'notice' );
					if ( count( WC()->cart->applied_coupons ) >= 0 ) {
						$classBlock = 'wfacp_display_none';
					}
				}
				?>
                <div class='wfacp_mini_cart_classes '>
                    <form class="wfacp_layout_shopcheckout checkout_coupon woocommerce-form-coupon <?php echo $classBlock; ?>" method="post">
                        <div class="wfacp-row wfacp_coupon_row">
                            <p class="form-row form-row-first wfacp-form-control-wrapper wfacp-col-left-half wfacp-input-form">

                                <label for="coupon_code" class="wfacp-form-control-label wfacp-coupon-label"><?php echo $wfacp_sidebar_coupon_text; ?> </label>
                                <input type="text" name="coupon_code" class="input-text wfacp-form-control" placeholder="<?php echo $wfacp_sidebar_coupon_text; ?>" id="coupon_code" value=""/>
                            </p>
                            <p class="form-row form-row-last <?php echo $coupon_cls; ?>">
                                <label class="wfacp-form-control-label">&nbsp;</label>
                                <button type="submit" class="button wfacp-coupon-btn" name="apply_coupon" value="<?php echo $apply_coupon_button_text; ?>" disabled="disabled"><?php echo $apply_coupon_button_text; ?></button>
                            </p>
                            <div class="clear"></div>
                        </div>
                        <div class="wfacp-row ">
                            <div class="wfacp_coupon_msg"><div class="woocommerce-message"></div></div>
                            <div class="wfacp_coupon_notices"></div>

                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
	<?php
}


?>
