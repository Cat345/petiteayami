<?php
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

$title      = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_title' );
$short_desc = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_desc' );

$short_desc       = WFOCU_Common::maybe_parse_merge_tags( $short_desc, false, false );
$show_border      = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_show_border' );
$border_type      = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_border_type' );
$border_width     = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_border_width' );
$border_color     = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_border_color' );
$active_tab_color = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_active_tab_color' );

$product_override_global = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_override_global' );
if ( true === $product_override_global ) {
	$product_content_color = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_content_color' );
}

$border_class = $show_border === true ? 'wfocu-product-border' : '';

/** Rating */
$display_rating = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_display_rating' );
if ( true === $display_rating ) {
	$rating_average = $product->get_average_rating();
	$rating_count   = (int) $product->get_rating_count();
}

/** Product has variations  */
$display_buy_block_variation = false;

$product_type = $product->get_type();
if ( in_array( $product_type, WFOCU_Common::get_variable_league_product_types(), true ) ) {
	$display_buy_block_variation = true;
}

/** Tabs */
$display_tabs  = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_display_tabs' );
$tab_alignment = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_tab_align' );
$custom_tabs   = false;
if ( true === $display_tabs ) {
	$display_mode = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_tab_mode' );
	if ( 'custom' === $display_mode ) {
		$custom_tabs = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_tabs' );
	}
}

/** Gallery */
$gallery = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_images' );

/** Price */
$regular_price     = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price key="' . $product_key . '" tag="no"}}' );
$sale_price        = WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price key="' . $product_key . '"}}' );
$regular_price_raw = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );
$sale_price_raw    = WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );

/** css */
$title_fs         = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_title_fs' );
$reg_price_fs     = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_reg_price_fs' );
$sale_price_fs    = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_sale_price_fs' );
$title_color      = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_title_color' );
$reg_price_color  = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_reg_price_color' );
$sale_price_color = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_sale_price_color' );
$desc_fs          = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_desc_fs' );
$sec_bg_color     = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_bg_color' );
$images_width     = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_images_width' );

$tab_title_fs       = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_tab_title_fs' );
$default_tab_tcolor = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_default_tab_tcolor' );
$active_tab_tcolor  = WFOCU_Common::get_option( 'wfocu_product_product_' . $product_key . '_active_tab_tcolor' );

$template_ins->internal_css['pro_title_font_size'][ $product_key ] = $title_fs;
$template_ins->internal_css['reg_price_fs'][ $product_key ]        = $reg_price_fs;
$template_ins->internal_css['sale_price_fs'][ $product_key ]       = $sale_price_fs;
$template_ins->internal_css['pro_title_color'][ $product_key ]     = $title_color;
$template_ins->internal_css['reg_price_color'][ $product_key ]     = $reg_price_color;
$template_ins->internal_css['sale_price_color'][ $product_key ]    = $sale_price_color;
$template_ins->internal_css['pro_tab_title_fs'][ $product_key ]    = $tab_title_fs;
$template_ins->internal_css['default_tab_tcolor'][ $product_key ]  = $default_tab_tcolor;
$template_ins->internal_css['active_tab_tcolor'][ $product_key ]   = $active_tab_tcolor;

$template_ins->internal_css['pro_desc_font_size'][ $product_key ]   = $desc_fs;
$template_ins->internal_css['pro_bg_color'][ $product_key ]         = $sec_bg_color;
$template_ins->internal_css['pro_border_color'][ $product_key ]     = $border_color;
$template_ins->internal_css['pro_border_type'][ $product_key ]      = $border_type;
$template_ins->internal_css['pro_border_width'][ $product_key ]     = $border_width;
$template_ins->internal_css['pro_active_tab_color'][ $product_key ] = $active_tab_color;

if ( true === $product_override_global ) {
	$template_ins->internal_css['pro_content_color'][ $product_key ] = $product_content_color;
}

$img_section     = (int) $images_width;
$content_section = 12 - $img_section;
?>
<div class="wfocu-landing-section wfocu-product-section wfocu-product-sec-style8 wfocu-pkey-<?php echo $product_key; ?>" data-key="<?php echo $product_key; ?>" data-id="<?php echo $product_id; ?>">
    <div class="wfocu-container">
        <div class="wfocu-product-border-wrap <?php echo $border_class; ?> ">
            <div class="wfocu-product-main wfocu-pro-gallery-pos-right wfocu-clearfix">
                <div class="wfocu-row wfocu-clearfix">
                    <div class="wfocu-col-md-12">
                        <div class="wfocu-product-top-section">
                            <h1 class="wfocu-product-title"><?php echo $title; ?></h1>
                            <div class="wfocu-clearfix"></div>
							<?php if ( true === $display_rating && $rating_count > 0 ) { ?>
                                <div class="wfocu-product-rating">
                                    <div class="wfocu-star-rating">
                                        <span style="width:<?php echo $rating_average * 20; ?>%">Rated <strong class="rating"><?php echo $rating_average; ?></strong> out of 5 based on <span class="rating"><?php echo $rating_count; ?></span> customer ratings</span>
                                    </div>
                                    <a href="javascript:void(0)" class="wfocu-review-link" rel="nofollow">(<span class="count">4</span> customer reviews)</a>
                                </div>
							<?php } ?>
                        </div>
                    </div>
                </div>
                <div class="wfocu-row wfocu-clearfix">
                    <div class="wfocu-col-md-<?php echo $img_section; ?> wfocu-col-md-push-<?php echo $content_section; ?>">
						<?php if ( is_array( $gallery ) && count( $gallery ) > 0 ) { ?>
                            <div class="wfocu-product-gallery-col">
                                <div class="wfocu-product-carousel-container">
									<?php
									$this->get_template_part( 'product/slider', array(
										'key'     => $product_key,
										'gallery' => $gallery,
										'product' => $product,
										'title'   => $title,
										'style'   => 8,
									) );
									?>
                                </div>
                            </div>
						<?php } ?>
                    </div>
                    <div class="wfocu-col-md-<?php echo $content_section; ?> wfocu-col-md-pull-<?php echo $img_section; ?>">
                        <div class="wfocu-product-info-col">
							<?php
							echo ( ! empty( $short_desc ) ) ? '<div class="wfocu-product-short-description">' . apply_filters( 'wfocu_the_content', $short_desc ) . '</div>' : '';
							?>
                            <div class="wfocu-price-wrapper">
                                <div class="wfocu-product-price wfocu-product-on-sale">
									<?php


									$price_output = '';
									if ( round( $sale_price_raw, 2 ) !== round( $regular_price_raw, 2 ) ) {
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
									echo $get_html_output;
									?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wfocu-text-center wfocu-mb-40">
                <div class="wfocu-clearfix"></div>
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
            <div class="wfocu-clearfix"></div>
			<?php
			if ( true === $display_tabs ) {
				WFOCU_Core()->template_loader->get_template_part( 'product/tabs', array(
					'mode'    => $display_mode,
					'custom'  => $custom_tabs,
					'align'   => $tab_alignment,
					'product' => $product,
				) );
			}
			?>
        </div>
    </div>
</div>
