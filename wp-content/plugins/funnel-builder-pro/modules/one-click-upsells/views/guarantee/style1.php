<?php
/** Data */
$product_key = $data['key'];

$template_ins = $this->get_template_ins();

$sec_heading           = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_heading' );
$sec_heading           = WFOCU_Common::maybe_parse_merge_tags( $sec_heading );
$sec_sub_heading       = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_sub_heading' );
$sec_sub_heading       = WFOCU_Common::maybe_parse_merge_tags( $sec_sub_heading );
$sec_bg_color          = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_bg_color' );
$additional_text       = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_text' );
$guarantee_boxes       = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_icon_text' );
$additional_text       = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_additional_text' );
$additional_text       = WFOCU_Common::maybe_parse_merge_tags( $additional_text, false, false );
$additional_text_align = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_additional_talign' );
$gbox_heading_fs       = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_gbox_heading_fs' );
$gbox_heading_color    = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_gbox_heading_color' );

$display_image  = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_display_image' );
$disp_img_class = $display_image !== true ? 'wfocu-block-no-img' : '';

$display_buy_block           = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_display_buy_block' );
$display_buy_block_variation = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_display_buy_block_variation' );

$guarantee_override_global = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_override_global' );
if ( true === $guarantee_override_global ) {
	$guarantee_head_color     = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_heading_color' );
	$guarantee_sub_head_color = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_sub_heading_color' );
	$guarantee_content_color  = WFOCU_Common::get_option( 'wfocu_guarantee_guarantee_content_color' );
}

$template_ins->internal_css['guarantee_bg_color']          = $sec_bg_color;
$template_ins->internal_css['guarantee_box_heading_fs']    = $gbox_heading_fs;
$template_ins->internal_css['guarantee_box_heading_color'] = $gbox_heading_color;


if ( true === $guarantee_override_global ) {
	$template_ins->internal_css['guarantee_head_color']     = $guarantee_head_color;
	$template_ins->internal_css['guarantee_sub_head_color'] = $guarantee_sub_head_color;
	$template_ins->internal_css['guarantee_content_color']  = $guarantee_content_color;
}

?>
<div class="wfocu-landing-section wfocu-guarantee-section wfocu-guarantee-sec-style1 wfocu-pt-62 wfocu-pb-55" data-scrollto="wfocu_guarantee_guarantee">
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
		<?php if ( is_array( $guarantee_boxes ) && count( $guarantee_boxes ) > 0 ) { ?>
            <div class="wfocu-guarantee-listing">
                <div class="wfocu-row wfocu-guarantee-list-row">
					<?php
					foreach ( $guarantee_boxes as $guarantee_box ) {
						$gbox_heading  = $guarantee_box['heading'];
						$gbox_text     = $guarantee_box['message'];
						$gbox_builtin  = isset( $guarantee_box['builtin'] ) ? $guarantee_box['builtin'] : '';
						$gbox_img      = isset( $guarantee_box['image'] ) ? $guarantee_box['image'] : '';
						$gbox_img_src  = WFOCU_Common::get_image_source( $gbox_img, 'full' );
						$gbox_icon_src = $gbox_builtin ? $template_ins->img_public_path . 'guarantee/' . $gbox_builtin . '.png' : '';
						$gbox_img_path = $gbox_img ? $gbox_img_src : $gbox_icon_src;
						?>
                        <div class="wfocu-col-md-6 wfocu-col-sm-6 wfocu-col-xs-12 wfocu-guarantee-box-col">
                            <!--  Add Class "wfocu-block-no-img" with "wfocu-guarantee-box" to disable image. -->
                            <div class="wfocu-guarantee-box wfocu-clearfix <?php echo $disp_img_class; ?>">
                                <div class="wfocu-guarantee-img">
                                    <div class="wfocu-img-cover">
                                        <img class="skip-lazy" src="<?php echo $gbox_img_path; ?>" alt="" title=""/>
                                    </div>
                                </div>
                                <div class="wfocu-guarantee-content">
									<?php
									echo $gbox_heading ? ' <div class="wfocu-block-heading">' . $gbox_heading . '</div>' : '';
									echo $gbox_text ? ' <div class="wfocu-block-text">' . apply_filters( 'wfocu_the_content', $gbox_text ) . '</div>' : '';
									?>
                                </div>
                            </div>
                        </div>
						<?php
						unset( $gbox_heading );
						unset( $gbox_text );
						unset( $gbox_builtin );
						unset( $gbox_img );
						unset( $gbox_img_src );
						unset( $gbox_icon_src );
						unset( $gbox_img_path );
					}
					?>
                </div>
            </div>
		<?php } ?>
		<?php if ( $additional_text !== '' ) { ?>
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
