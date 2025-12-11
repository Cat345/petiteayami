<?php
/** Data */
$product_key = $data['key'];

$template_ins          = $this->get_template_ins();
$sec_heading           = WFOCU_Common::get_option( 'wfocu_features_reasons_heading' );
$sec_heading           = WFOCU_Common::maybe_parse_merge_tags( $sec_heading );
$sec_sub_heading       = WFOCU_Common::get_option( 'wfocu_features_reasons_sub_heading' );
$sec_sub_heading       = WFOCU_Common::maybe_parse_merge_tags( $sec_sub_heading );
$sec_bg_color          = WFOCU_Common::get_option( 'wfocu_features_reasons_bg_color' );
$additional_text       = WFOCU_Common::get_option( 'wfocu_features_reasons_additional_text' );
$additional_text       = WFOCU_Common::maybe_parse_merge_tags( $additional_text, false, false );
$additional_text_align = WFOCU_Common::get_option( 'wfocu_features_reasons_additional_talign' );

$features_list = WFOCU_Common::get_option( 'wfocu_features_reasons_reasons' );
$icon_color    = WFOCU_Common::get_option( 'wfocu_features_reasons_icon_color' );


$feat_override_global = WFOCU_Common::get_option( 'wfocu_features_reasons_override_global' );
if ( true === $feat_override_global ) {
	$feat_head_color     = WFOCU_Common::get_option( 'wfocu_features_reasons_heading_color' );
	$feat_sub_head_color = WFOCU_Common::get_option( 'wfocu_features_reasons_sub_heading_color' );
	$feat_content_color  = WFOCU_Common::get_option( 'wfocu_features_reasons_content_color' );
}


$display_buy_block           = WFOCU_Common::get_option( 'wfocu_features_reasons_display_buy_block' );
$display_buy_block_variation = WFOCU_Common::get_option( 'wfocu_features_reasons_display_buy_block_variation' );

$template_ins->internal_css['feature_bg_color']   = $sec_bg_color;
$template_ins->internal_css['feature_icon_color'] = $icon_color;
if ( true === $feat_override_global ) {
	$template_ins->internal_css['feature_head_color']     = $feat_head_color;
	$template_ins->internal_css['feature_sub_head_color'] = $feat_sub_head_color;
	$template_ins->internal_css['feature_content_color']  = $feat_content_color;

}
?>
<div class="wfocu-landing-section wfocu-feature-section wfocu-feature-sec-style1 wfocu-pt-55 wfocu-pb-55" data-scrollto="wfocu_features_reasons">
    <div class="wfocu-container">
        <div class="wfocu-row">
            <div class="wfocu-col-md-12">
				<?php if ( ! empty( $sec_heading ) || ! empty( $sec_sub_heading ) ) { ?>
                    <div class="wfocu-section-headings">
						<?php echo $sec_heading ? '<div class="wfocu-heading">' . $sec_heading . '</div>' : ''; ?>
						<?php echo $sec_sub_heading ? '<div class="wfocu-sub-heading wfocu-max-845">' . $sec_sub_heading . '</div>' : ''; ?>
                    </div>
				<?php } ?>
            </div>
        </div>
		<?php if ( is_array( $features_list ) && count( $features_list ) > 0 ) { ?>

            <div class="wfocu-row">
                <div class="wfocu-col-lg-6 wfocu-col-lg-offset-3 wfocu-col-md-8 wfocu-col-md-offset-2 wfocu-col-sm-10 wfocu-col-sm-offset-1 wfocu-col-sm-12">
                    <div class="wfocu-feature-sec-wrap ">
                        <div class="wfocu-feature-listing">
                            <ul class="wfocu-check-style">
								<?php
								foreach ( $features_list as $feat_reason ) {
									$freason = $feat_reason['message'];
									if ( $freason !== '' ) {
										?>
                                        <li>
                                            <span class="wfocu-check-icon"><img src="<?php echo WFOCU_PLUGIN_URL; ?>/admin/assets/img/check-icon.svg" alt="" class="wfocu-svgIcon skip-lazy"/></span>
											<?php echo $freason ? '<span class="wfocu-feat-text">' . $freason . '</span>' : ''; ?>
                                        </li>
										<?php
									}
								}
								?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
		<?php } ?>
		<?php if ( $additional_text != '' ) { ?>
            <div class="wfocu-row">
                <div class="wfocu-col-md-12">
                    <div class="wfocu-content-area <?php echo $additional_text_align; ?> wfocu-max-1024">
						<?php echo apply_filters( 'wfocu_the_content', $additional_text ); ?>
                    </div>
                </div>
            </div>
			<?php
		}

		if ( true === $display_buy_block ) {
			$buy_data = array(
				'key'            => $product_key,
				'product'        => $data['product'],
				'show_variation' => false,
			);
			if ( true === $display_buy_block_variation ) {
				$buy_data['show_variation'] = true;
			}
			WFOCU_Core()->template_loader->get_template_part( 'buy-block', $buy_data );
		}
		?>
    </div>


</div>
