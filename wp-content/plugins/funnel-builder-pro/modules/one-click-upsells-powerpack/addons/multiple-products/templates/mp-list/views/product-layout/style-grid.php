<?php
/**
 * Author PhpStorm.
 */
$product     = $data['product']->data;
$product_key = $data['key'];
$product_id  = $data['product']->id;

$template_ins = $this->get_template_ins();

if ( ! $product instanceof WC_Product ) {
	if ( empty( $template_ins->products_data ) || ! isset( $template_ins->products_data[ $product_key ] ) || ! isset( $template_ins->products_data[ $product_key ]['obj'] ) || ! $template_ins->products_data[ $product_key ]['obj'] instanceof WC_Product ) {
		$product = wc_get_product( $product_id );
	} else {
		$product = $template_ins->products_data[ $product_key ]['obj'];
	}
}

$title_product = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_title' );
$heading       = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_heading' );
$sub_heading   = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_sub_heading' );
$short_desc    = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_desc' );
$short_desc    = WFOCU_Common::maybe_parse_merge_tags( $short_desc, false, false );
$pro_bg_color  = WFOCU_Common::get_option( 'wfocu_product_settings_bg_color' );

/* Multiproduct Block Border Setttings */
$pblock_show_border  = WFOCU_Common::get_option( 'wfocu_product_settings_show_border' );
$pblock_border_type  = WFOCU_Common::get_option( 'wfocu_product_settings_border_type' );
$pblock_border_width = WFOCU_Common::get_option( 'wfocu_product_settings_border_width' );
$pblock_border_color = WFOCU_Common::get_option( 'wfocu_product_settings_border_color' );

$product_override_global = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_override_global' );
if ( true === $product_override_global ) {
	$pblock_content_color = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_content_color' );
}

$border_class = true === $pblock_show_border ? 'wfocu-pblock-border' : '';

/** Rating */
/** Product has variations  */
$display_buy_block_variation = false;
$product_type                = $product->get_type();
if ( in_array( $product_type, WFOCU_Common::get_variable_league_product_types(), true ) ) {
	$display_buy_block_variation = true;
}

/** Gallery */
$product_img_id = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_image' );

/** Price */
$regular_price     = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price key="' . $product_key . '" tag="no"}}' );
$sale_price        = WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price key="' . $product_key . '"}}' );
$sale_price_raw    = WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );
$regular_price_raw = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );

/** CSS */
$title_fs                 = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_title_fs' );
$heading_fs               = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_heading_fs' );
$sub_heading_fs           = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_sub_heading_fs' );
$reg_price_fs             = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_reg_price_fs' );
$sale_price_fs            = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_sale_price_fs' );
$title_color              = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_title_color' );
$pblock_heading_color     = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_heading_color' );
$pblock_sub_heading_color = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_sub_heading_color' );
$reg_price_color          = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_reg_price_color' );
$sale_price_color         = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_sale_price_color' );
$desc_fs                  = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_desc_fs' );
$pblock_bg_color          = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_bg_color' );
$desc_list                = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_desc_list' );
$desc_list_icon           = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_desc_list_icon' );
$desc_type                = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_desc_type' );
$highlight_product        = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_highlight' );
$text_below_price         = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_text_below_price' );
$text_below_price         = apply_filters( 'wfocu_the_content', WFOCU_Common::maybe_parse_merge_tags( $text_below_price, false, false ) );
$text_below_price_fs      = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_text_below_price_fs' );
$text_below_price_color   = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_text_below_price_color' );
$desc_icon_size           = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_desc_icon_size' );

/* highlight Settings */
$highlight_class = '';

if ( true === $highlight_product ) {
	$hl_pblock_heading                        = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_heading' );
	$hl_pblock_badge_text                     = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_badge_text' );
	$hl_pblock_heading                        = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_heading_color' );
	$hl_pblock_border_type                    = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_border_type' );
	$hl_pblock_border_width                   = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_border_width' );
	$hl_pblock_border_color                   = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_border_color' );
	$hl_pblock_badge_tcolor                   = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_badge_tcolor' );
	$hl_pblock_badge_bg_color                 = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_badge_bg_color' );
	$hl_pblock_accept_btn_bg_color            = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_accept_btn_bg_color' );
	$hl_pblock_accept_btn_text_color          = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_accept_btn_text_color' );
	$hl_pblock_accept_btn_bottom_shadow_color = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_accept_btn_bottom_shadow_color' );
	$hl_pblock_accept_btn_bg_color_hover      = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_accept_btn_bg_color_hover' );
	$hl_pblock_accept_btn_text_color_hover    = WFOCU_Common::get_option( 'wfocu_other_hl_pblock_accept_btn_text_color_hover' );
	$highlight_class                          = 'wfocu-highlight-pblock';
}

$template_ins->internal_css['pro_title_font_size'][ $product_key ]           = $title_fs;
$template_ins->internal_css['reg_price_fs'][ $product_key ]                  = $reg_price_fs;
$template_ins->internal_css['sale_price_fs'][ $product_key ]                 = $sale_price_fs;
$template_ins->internal_css['pblock_heading_color'][ $product_key ]          = $pblock_heading_color;
$template_ins->internal_css['pblock_sub_head_color'][ $product_key ]         = $pblock_sub_heading_color;
$template_ins->internal_css['reg_price_color'][ $product_key ]               = $reg_price_color;
$template_ins->internal_css['sale_price_color'][ $product_key ]              = $sale_price_color;
$template_ins->internal_css['pro_bg_color']                                  = $pro_bg_color;
$template_ins->internal_css['pblock_heading_fs'][ $product_key ]             = $heading_fs;
$template_ins->internal_css['pblock_sub_heading_fs'][ $product_key ]         = $sub_heading_fs;
$template_ins->internal_css['pblock_text_below_price_fs'][ $product_key ]    = $text_below_price_fs;
$template_ins->internal_css['pblock_text_below_price_color'][ $product_key ] = $text_below_price_color;
$template_ins->internal_css['pblock_desc_icon_size'][ $product_key ]         = $desc_icon_size;
$template_ins->internal_css['pro_desc_font_size'][ $product_key ]            = $desc_fs;
$template_ins->internal_css['pblock_bg_color'][ $product_key ]               = $pblock_bg_color;
$template_ins->internal_css['pblock_border_color']                           = $pblock_border_color;
$template_ins->internal_css['pblock_border_type']                            = $pblock_border_type;
$template_ins->internal_css['pblock_border_width']                           = $pblock_border_width;

if ( true === $highlight_product ) {

	$template_ins->internal_css['hl_pblock_border_type']               = $hl_pblock_border_type;
	$template_ins->internal_css['hl_pblock_border_width']              = $hl_pblock_border_width;
	$template_ins->internal_css['hl_pblock_border_color']              = $hl_pblock_border_color;
	$template_ins->internal_css['hl_pblock_badge_bg_color']            = $hl_pblock_badge_bg_color;
	$template_ins->internal_css['hl_pblock_badge_tcolor']              = $hl_pblock_badge_tcolor;
	$template_ins->internal_css['hl_pblock_accept_btn_bg_color']       = $hl_pblock_accept_btn_bg_color;
	$template_ins->internal_css['hl_pblock_accept_btn_t_color']        = $hl_pblock_accept_btn_text_color;
	$template_ins->internal_css['hl_pblock_accept_btn_shadow']         = $hl_pblock_accept_btn_bottom_shadow_color;
	$template_ins->internal_css['hl_pblock_accept_btn_bg_color_hover'] = $hl_pblock_accept_btn_bg_color_hover;
	$template_ins->internal_css['hl_pblock_accept_btn_t_color_hover']  = $hl_pblock_accept_btn_text_color_hover;
}

if ( true === $product_override_global ) {
	$template_ins->internal_css['pblock_content_color'][ $product_key ] = $pblock_content_color;
}

/* Get Image Of product */
$product_img = array();
if ( isset( $product_img_id ) && (int) $product_img_id > 0 ) {
	$full_img      = wp_get_attachment_image_src( $product_img_id, 'large' );
	$product_img[] = array(
		'id'  => $product_img_id,
		'src' => $full_img[0],
	);
} else {
	$product_img[] = array(
		'id'  => 0,
		'src' => wc_placeholder_img_src(),
	);
}

/* Fetching All images of Product i.e Main image, gallery images if any and varaition image if product type is variable */
$all_images  = array();
$main_img    = $product->get_image_id();
$gallery_img = $product->get_gallery_image_ids();
/* Check if main image is set or not */
if ( ! empty( $main_img ) ) {
	$full_img                = wp_get_attachment_image_src( $main_img, 'large' );
	$all_images[ $main_img ] = absint( $full_img[0] );
}

/* Check if product type is variable, then get all variations and add its image to all_image array */
if ( isset( $data['product']->variations_data ) && isset( $data['product']->variations_data['images'] ) ) {
	foreach ( $data['product']->variations_data['images'] as $img_id ) {
		if ( false === in_array( absint( $img_id ), $all_images, true ) ) {
			$full_img = wp_get_attachment_image_src( $img_id, 'large' );

			$all_images[ $img_id ] = $full_img[0];
		}
	}
}
?>

<div class="wfocu-mp-block  wfocu-product-block wfocu-pkey-<?php echo esc_attr( $product_key ); ?>" data-key="<?php echo esc_attr( $product_key ); ?>" data-id="<?php echo esc_attr( $product_id ); ?>">
    <div class="wfocu-pblock-inner <?php echo esc_attr( $border_class ); ?> <?php echo esc_attr( $highlight_class ); ?>  wfocu-list-block-table">
		<?php
		if ( true === $highlight_product ) {
			?>
            <div class="wfocu-best-badge">
                <span><?php echo $hl_pblock_badge_text; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?></span>
            </div>
		<?php } ?>
        <div class="wfocu-list-block-cell-left">
            <div class="wfocu-pblock-img" data-def="<?php echo esc_attr( $product_img[0]['src'] ); ?>" data-gallery="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $all_images ) ) ); ?>">

				<?php if ( is_array( $product_img ) && count( $product_img ) > 0 ) { ?>
                    <img data-id="<?php echo esc_attr( $product_img[0]['id'] ); ?>" src="<?php echo esc_attr( $product_img[0]['src'] ); ?>" alt="<?php echo esc_attr( $title_product ); ?>" title="<?php echo esc_attr( $title_product ); ?>"/>
				<?php } ?>

            </div>
        </div>
        <div class="wfocu-list-block-cell-right">
            <div class="wfocu-list-block-cell wfocu-list-block-cell-2l">
                <div class="wfocu-pblock-header">
                    <div class="wfocu-pblock-headerin">
                        <h2 class="wfocu-pblock-heading"><?php echo wp_kses_post( $heading ); ?></h2>
                        <div class="wfocu-pblock-sub-heading"><?php echo wp_kses_post( $sub_heading ); ?></div>
                    </div>
                </div>
                <!--Headings Ends Here-->
                <div class="wfocu-pblock-content">
                    <div class="wfocu_product_heading">
                        <h2 class="wfocu-product-title"><?php echo wp_kses_post( $title_product ); ?></h2>
                    </div>
                    <div class="wfocu-pblock-detail">

						<?php
						if ( 'wfocu-desc-text' === $desc_type ) {
							echo '<div class="wfocu-product-short-description">';
							echo ( ! empty( $short_desc ) ) ? '' . wp_kses_post( apply_filters( 'wfocu_the_content', $short_desc ) ) : '';
							echo '</div>';
						}
						?>
						<?php
						if ( 'wfocu-desc-list' === $desc_type && is_array( $desc_list ) && count( $desc_list ) > 0 ) {
							?>
                            <div class="wfocu-pblock-check-list">
                                <ul>
									<?php
									foreach ( $desc_list as $desc_line ) {
										$list_text = $desc_line['message'];

										$list_text = WFOCU_Common::maybe_parse_merge_tags( $list_text, false, false );
										if ( ! empty( $list_text ) ) {
											?>
                                            <li class="wfocu-clearfix">
                                                <span class="wfocu-licon dashicons <?php echo wp_kses_post( $desc_list_icon ); ?>"></span>
												<?php echo wp_kses_post( $list_text ); ?>
                                            </li>
											<?php
										}
									}
									?>

                                </ul>
                            </div>
						<?php } ?>
                    </div>
                    <!-- pblock details like short description  ends here-->
					<?php
					if ( true === apply_filters( 'wfocu_is_show_variation_form', true ) ) {
						if ( true === $display_buy_block_variation ) {
							WFOCU_Core()->template_loader->get_template_part( 'product/variation-form', $data );
						}
					}

					add_filter( 'wfocu_is_show_variation_form', array( $template_ins, 'remove_qty_attribute_selectors_from_buy_block' ), 10, 1 );

					/** Variation with 'any' attribute selection */
					if ( true === apply_filters( 'wfocu_is_show_attributes_selector', true ) ) {
						WFOCU_Core()->template_loader->get_template_part( 'product/attributes', $data );
					}
					add_filter( 'wfocu_is_show_attributes_selector', array( $template_ins, 'remove_qty_attribute_selectors_from_buy_block' ), 10, 1 );

					/** Variation with 'any' attribute selection */
					if ( true === apply_filters( 'wfocu_is_show_qty_selector', true ) ) {
						WFOCU_Core()->template_loader->get_template_part( 'qty-selector', $data );
					}
					add_filter( 'wfocu_is_show_qty_selector', array( $template_ins, 'remove_qty_attribute_selectors_from_buy_block' ), 10, 1 );
					?>

                </div>
                <!-- pblock content ends here-->
            </div>
            <div class="wfocu-list-block-cell wfocu-list-block-cell-2r">
                <div class="wfocu-pblock-content">
                    <div class="wfocu-pblock-btm">
                        <div class="wfocu-price-wrapper">
                            <div class="wfocu-product-price wfocu-product-on-sale">
								<?php
								$price_output = '';
								if ( $sale_price_raw !== $regular_price_raw ) {
									$price_output .= $regular_price ? '<span class="wfocu-regular-price">' . $regular_price . '</span>' : '';
									$price_output .= $sale_price ? '<span class="wfocu-sale-price">' . $sale_price . '</span>' : '';
								} else {
									if ( 'variable' === $product->get_type() ) {
										$price_output .= sprintf( '<span class="wfocu-regular-price"><span class="wfocu_variable_price_regular" style="display: none;" data-key="%s"></span></span>', $product_key );
										$price_output .= $sale_price ? '<span class="wfocu-sale-price">' . $sale_price . '</span>' : '';
									} else {
										$price_output .= $sale_price ? '<span class="wfocu-sale-price">' . $sale_price . '</span>' : '';
									}
								}
								$get_html_output = apply_filters( 'wfocu_template_price_html', $price_output, $regular_price_raw, $regular_price, $sale_price_raw, $sale_price, $data );
								echo wp_kses_post( $get_html_output );
								?>
                            </div>
                        </div>
						<?php
						echo '<div class="wfocu-text-below-price">';
						echo wp_kses_post( $text_below_price );
						echo '</div>';
						?>
                        <!--Buy block Section starts Here-->
						<?php
						$buy_data = array(
							'key'            => $product_key,
							'product'        => $data['product'],
							'show_variation' => false,
						);
						if ( true === $display_buy_block_variation ) {
							$buy_data['show_variation'] = true;
						}
						WFOCU_Core()->template_loader->get_template_part( 'buy-block', $buy_data );
						?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
remove_filter( 'wfocu_is_show_qty_selector', array( $template_ins, 'remove_qty_attribute_selectors_from_buy_block' ), 10, 1 );
remove_filter( 'wfocu_is_show_attributes_selector', array( $template_ins, 'remove_qty_attribute_selectors_from_buy_block' ), 10, 1 );
remove_filter( 'wfocu_is_show_variation_form', array( $template_ins, 'remove_qty_attribute_selectors_from_buy_block' ), 10, 1 );
?>
