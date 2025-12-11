<?php
$product                     = $data['product'];
$product_key                 = $data['key'];
$display_buy_block_variation = $data['show_variation'];

$template_ins = $this->get_template_ins();

$style = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_style' );

$accept_btn_text     = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_text1' );
$accept_btn_sub_text = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_text2' );

$accept_btn_text = WFOCU_Common::maybe_parse_product_tags( $accept_btn_text, $product_key, $product );

$accept_btn_sub_text = WFOCU_Common::maybe_parse_product_tags( $accept_btn_sub_text, $product_key, $product );


/**
 * Colors
 */
$accept_btn_text_fs          = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_text1_fs' );
$accept_btn_sub_text_fs      = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_text2_fs' );
$accept_btn_bg_color         = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_bg_color' );
$accept_btn_bg_color_hover   = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_bg_color_hover' );
$accept_btn_text_color       = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_text_color' );
$accept_btn_text_color_hover = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_text_color_hover' );
$accept_btn_bottom_shadow    = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_bottom_shadow_color' );


$template_ins->internal_css['style_1_accept_btn_bg_color']       = $accept_btn_bg_color;
$template_ins->internal_css['style_1_accept_btn_bg_color_hover'] = $accept_btn_bg_color_hover;
$template_ins->internal_css['style_1_accept_btn_t_color']        = $accept_btn_text_color;
$template_ins->internal_css['style_1_accept_btn_t_color_hover']  = $accept_btn_text_color_hover;
$template_ins->internal_css['style_1_accept_btn_t_fs']           = $accept_btn_text_fs;
$template_ins->internal_css['style_1_accept_btn_st_fs']          = $accept_btn_sub_text_fs;
$template_ins->internal_css['style_1_accept_btn_shadow']         = $accept_btn_bottom_shadow;

$show_accept_btn_icon = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_show_accept_btn_icon' );

$btn_icon_class = ( true === $show_accept_btn_icon ) ? 'wfocu-icon-show' : 'wfocu-icon-hide';

$accept_btn_icon = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_accept_btn_icon' );


$btn_effect = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_btn_effect' );
if ( 'none' === $btn_effect ) {
	$btn_effect = '';
}

$click_trigger_text = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_click_trigger_text' );
$click_trigger_text = WFOCU_Common::maybe_parse_merge_tags( $click_trigger_text );
if ( ! empty( $click_trigger_text ) ) {
	$click_trigger_text_fs    = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_click_trigger_text_fs' );
	$click_trigger_text_color = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_click_trigger_text_color' );

	$template_ins->internal_css['style_1_click_trigger_t_fs']    = $click_trigger_text_fs;
	$template_ins->internal_css['style_1_click_trigger_t_color'] = $click_trigger_text_color;
}

$skip_btn_class  = '';
$skip_offer_text = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_skip_offer_text' );
$skip_offer_text = WFOCU_Common::maybe_parse_merge_tags( $skip_offer_text );


$skip_offer_text_fs          = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_skip_offer_text_fs' );
$skip_offer_text_color       = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_skip_offer_text_color' );
$skip_offer_text_color_hover = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_skip_offer_text_color_hover' );

$template_ins->internal_css['style_1_skip_offer_t_fs']          = $skip_offer_text_fs;
$template_ins->internal_css['style_1_skip_offer_t_color']       = $skip_offer_text_color;
$template_ins->internal_css['style_1_skip_offer_t_color_hover'] = $skip_offer_text_color_hover;

$skip_offer_btn_style = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_skip_offer_btn_style' );

if ( true === $skip_offer_btn_style ) {
	$skip_offer_btn_bg_color       = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_skip_offer_btn_bg_color' );
	$skip_offer_btn_bg_color_hover = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_skip_offer_btn_bg_color_hover' );

	$template_ins->internal_css['style_1_skip_offer_btn_bg_color']       = $skip_offer_btn_bg_color;
	$template_ins->internal_css['style_1_skip_offer_btn_bg_color_hover'] = $skip_offer_btn_bg_color_hover;

	$skip_btn_class = ' wfocu-skip-offer-btn';
}


$display_payment_icons = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_display_payment_icon' );

$buy_block_btn_type = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_btn_type' );
if ( $buy_block_btn_type === 'wfocu-btn-flexible' ) {
	$buy_block_btn_width                               = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_btn_width' );
	$template_ins->internal_css['buy_block_btn_width'] = $buy_block_btn_width;
}
$btn_vertical_gap                                 = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_btn_vertical_gap' );
$btn_horizontal_gap                               = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_btn_horizontal_gap' );
$btn_radius                                       = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_btn_radius' );
$template_ins->internal_css['btn_vertical_gap']   = $btn_vertical_gap;
$template_ins->internal_css['btn_horizontal_gap'] = $btn_horizontal_gap;
$template_ins->internal_css['btn_border_radius']  = $btn_radius;
do_action('wfocu_add_custom_html_above_accept_button', $product->id, $product_key);
?>
<div class="wfocu-clearfix"></div>

<div class="wfocu-buy-block wfocu-buy-block-style1">
    <div class="wfocu-buy-block-inner">
		<?php
		if ( true === apply_filters( 'wfocu_is_show_variation_form', true ) ) {
			if ( true === $display_buy_block_variation ) {
				WFOCU_Core()->template_loader->get_template_part( 'product/variation-form', $data );
			}
		}

		/** variation with 'any' attribute selection */
		if ( true === apply_filters( 'wfocu_is_show_attributes_selector', true ) ) {
			WFOCU_Core()->template_loader->get_template_part( 'product/attributes', $data );
		}

		/** Facility to add options above quantity selector like Autoships **/
		do_action( 'wfocu_options_template_loader_above_qty_selector', $data );

		if ( true === apply_filters( 'wfocu_is_show_qty_selector', true ) ) {
			WFOCU_Core()->template_loader->get_template_part( 'qty-selector', $data );
		}

		$upsell_btn_classes = apply_filters( 'wfocu_buy_btn_classes', array( 'wfocu_upsell', 'wfocu-button', 'wfocu-accept-button', $buy_block_btn_type, $btn_icon_class, $btn_effect ) );
		?>
        <div class="wfocu-product-bottom-sec wfocu-text-center">

            <div class="wfocu-btn-cover">
                <a href="javascript:void(0);" data-key="<?php echo $product_key; ?>" class="<?php echo implode( ' ', $upsell_btn_classes ); ?>" <?php $this->add_attributes_to_buy_button(); ?>>
			<span class="wfocu-btn-text-cover wfocu-clearfix">
			<?php if ( true === $show_accept_btn_icon ) { ?>
                <span class="wfocu-btn-icon wfocu-icon-left ">
				<i class="wfocu-icon dashicons <?php echo $accept_btn_icon; ?>"></i>
			</span>
			<?php } ?>
				<span class="wfocu-text"><?php echo apply_filters( 'wfocu_buy_block_btn_accept_text', $accept_btn_text, $data ); ?></span>
			</span>
                    <span class="wfocu-btn-sub"><?php echo apply_filters( 'wfocu_buy_block_btn_sub_accept_text', $accept_btn_sub_text, $data ); ?></span>
                </a>
            </div>

			<?php
			echo $click_trigger_text ? '<div class="wfocu-click-trigger-text wfocu-text-center">' . $click_trigger_text . '</div>' : '';

			if ( true === $display_payment_icons ) {
				WFOCU_Core()->template_loader->get_template_part( 'payment-cards', array() );
			}
			?>
            <div class="wfocu-skip-offer-wrap wfocu-text-center">
                <a href="javascript:void(0);" data-key="<?php echo $product_key; ?>" class="wfocu_skip_offer wfocu-skip-offer-link <?php echo $skip_btn_class; ?>">
					<?php echo $skip_offer_text ? $skip_offer_text : __( 'No thanks!', 'woofunnels-upstroke-one-click-upsell' ); ?>
                </a>
            </div>
        </div>
    </div>
	<div id="ppc-button"></div>
</div>
