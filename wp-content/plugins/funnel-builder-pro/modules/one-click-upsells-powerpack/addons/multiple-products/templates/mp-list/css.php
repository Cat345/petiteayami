<?php
$css = $this->internal_css;
global $output, $output_media;

/** Product */
if ( isset( $css['pro_title_font_size'] ) && is_array( $css['pro_title_font_size'] ) && count( $css['pro_title_font_size'] ) > 0 ) {
	foreach ( $css['pro_title_font_size'] as $pro_key => $val ) {
		if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-title' ]['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		}
		if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-title' ]['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		}
		if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-title' ]['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		}
	}
}
if ( isset( $css['pblock_heading_fs'] ) && is_array( $css['pblock_heading_fs'] ) && count( $css['pblock_heading_fs'] ) > 0 ) {
	foreach ( $css['pblock_heading_fs'] as $pro_key => $val ) {
		if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-heading' ]['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		}
		if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-heading' ]['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		}
		if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-heading' ]['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		}
	}
}
if ( isset( $css['pblock_sub_heading_fs'] ) && is_array( $css['pblock_sub_heading_fs'] ) && count( $css['pblock_sub_heading_fs'] ) > 0 ) {
	foreach ( $css['pblock_sub_heading_fs'] as $pro_key => $val ) {
		if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-sub-heading' ]['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		}
		if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-sub-heading' ]['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		}
		if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-sub-heading' ]['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		}
	}
}
if ( isset( $css['reg_price_fs'] ) && is_array( $css['reg_price_fs'] ) && count( $css['reg_price_fs'] ) > 0 ) {
	foreach ( $css['reg_price_fs'] as $pro_key => $val ) {
		if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-regular-price' ]['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		}
		if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-regular-price' ]['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		}
		if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-regular-price' ]['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		}
	}
}
if ( isset( $css['sale_price_fs'] ) && is_array( $css['sale_price_fs'] ) && count( $css['sale_price_fs'] ) > 0 ) {
	foreach ( $css['sale_price_fs'] as $pro_key => $val ) {
		if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-sale-price' ]['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		}
		if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-sale-price' ]['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		}
		if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-sale-price' ]['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		}
	}
}
if ( isset( $css['pblock_heading_color'] ) && is_array( $css['pblock_heading_color'] ) && count( $css['pblock_heading_color'] ) > 0 ) {
	foreach ( $css['pblock_heading_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-heading' ]['color'] = $val;
	}
}
if ( isset( $css['pblock_sub_head_color'] ) && is_array( $css['pblock_sub_head_color'] ) && count( $css['pblock_sub_head_color'] ) > 0 ) {
	foreach ( $css['pblock_sub_head_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-sub-heading' ]['color'] = $val;
	}
}

if ( isset( $css['pro_title_color'] ) && is_array( $css['pro_title_color'] ) && count( $css['pro_title_color'] ) > 0 ) {
	foreach ( $css['pro_title_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-title' ]['color'] = $val;
	}
}
if ( isset( $css['reg_price_color'] ) && is_array( $css['reg_price_color'] ) && count( $css['reg_price_color'] ) > 0 ) {
	foreach ( $css['reg_price_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-regular-price' ]['color'] = $val;
	}
}
if ( isset( $css['sale_price_color'] ) && is_array( $css['sale_price_color'] ) && count( $css['sale_price_color'] ) > 0 ) {
	foreach ( $css['sale_price_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-sale-price' ]['color'] = $val;
	}
}
if ( isset( $css['pro_desc_font_size'] ) && is_array( $css['pro_desc_font_size'] ) && count( $css['pro_desc_font_size'] ) > 0 ) {
	foreach ( $css['pro_desc_font_size'] as $pro_key => $val ) {
		if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description p' ]['font-size']      = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description li' ]['font-size']     = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description' ]['font-size']        = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list p' ]['font-size']              = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list li' ]['font-size']             = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list' ]['font-size']                = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list .wfocu-licon' ]['line-height'] = ( $val['desktop'] * 1.8 ) . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		}
		if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description p' ]['font-size']      = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description li' ]['font-size']     = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description' ]['font-size']        = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list p' ]['font-size']              = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list li' ]['font-size']             = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list' ]['font-size']                = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list .wfocu-licon' ]['line-height'] = ( $val['tablet'] * 1.8 ) . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		}
		if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description p' ]['font-size']      = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description li' ]['font-size']     = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description' ]['font-size']        = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list p' ]['font-size']              = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list li' ]['font-size']             = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list' ]['font-size']                = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list .wfocu-licon' ]['line-height'] = ( $val['mobile'] * 1.8 ) . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		}
	}
}
if ( isset( $css['pblock_bg_color'] ) && is_array( $css['pblock_bg_color'] ) && count( $css['pblock_bg_color'] ) > 0 ) {
	foreach ( $css['pblock_bg_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-inner' ]['background-color'] = $val;
	}
}
/*
if ( isset( $css['pro_bg_color'] ) && is_array( $css['pro_bg_color'] ) && count( $css['pro_bg_color'] ) > 0 ) {
	foreach ( $css['pro_bg_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key ]['background-color'] = $val;
	}
}
  */


if ( isset( $css['pro_bg_color'] ) && ! empty( $css['pro_bg_color'] ) ) {
	$output['.wfocu-mp-product-section']['background-color'] = $css['pro_bg_color'];
}

if ( isset( $css['pro_head_color'] ) && ! empty( $css['pro_head_color'] ) ) {
	$output['.wfocu-product-section .wfocu-section-headings .wfocu-heading']['color'] = $css['pro_head_color'];
}
if ( isset( $css['pro_sub_head_color'] ) && ! empty( $css['pro_sub_head_color'] ) ) {
	$output['.wfocu-product-section .wfocu-section-headings .wfocu-sub-heading']['color'] = $css['pro_sub_head_color'];
}

if ( isset( $css['pro_content_color'] ) && ! empty( $css['pro_content_color'] ) ) {
	$output['.wfocu-product-section .wfocu-top-content-area p']['color']     = $css['pro_content_color'];
	$output['.wfocu-product-section .wfocu-top-content-area ul li']['color'] = $css['pro_content_color'];
	$output['.wfocu-product-section .wfocu-top-content-area ol li']['color'] = $css['pro_content_color'];

	$output['.wfocu-product-section .wfocu-content-area p']['color']     = $css['pro_content_color'];
	$output['.wfocu-product-section .wfocu-content-area ul li']['color'] = $css['pro_content_color'];
	$output['.wfocu-product-section .wfocu-content-area ol li']['color'] = $css['pro_content_color'];
}

if ( isset( $css['pblock_border_color'] ) && ! empty( $css['pblock_border_color'] ) ) {
	$output['.wfocu-mp-wrapper .wfocu-pblock-border']['border-color'] = $css['pblock_border_color'];
}
if ( isset( $css['pblock_border_width'] ) && ! empty( $css['pblock_border_width'] ) ) {
	$output['.wfocu-mp-wrapper .wfocu-pblock-border']['border-width'] = $css['pblock_border_width'] . 'px';
}
if ( isset( $css['pblock_border_type'] ) && ! empty( $css['pblock_border_type'] ) ) {
	$output['.wfocu-mp-wrapper .wfocu-pblock-border']['border-style'] = $css['pblock_border_type'];
}

if ( isset( $css['pblock_content_color'] ) && is_array( $css['pblock_content_color'] ) && count( $css['pblock_content_color'] ) > 0 ) {
	foreach ( $css['pblock_content_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description p' ]['color']     = $val;
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description ul li' ]['color'] = $val;
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-short-description ol li' ]['color'] = $val;

		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list' ]['color']       = $val;
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list ul li' ]['color'] = $val;

		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-product-attr-wrapper' ]['color'] = $val;
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-prod-qty-wrapper' ]['color']     = $val;
	}
}
if ( isset( $css['pblock_text_below_price_color'] ) && is_array( $css['pblock_text_below_price_color'] ) && count( $css['pblock_text_below_price_color'] ) > 0 ) {
	foreach ( $css['pblock_text_below_price_color'] as $pro_key => $val ) {
		$output[ '.wfocu-pkey-' . $pro_key . ' .wfocu-text-below-price p' ]['color'] = $val;
	}
}

if ( isset( $css['pblock_text_below_price_fs'] ) && is_array( $css['pblock_text_below_price_fs'] ) && count( $css['pblock_text_below_price_fs'] ) > 0 ) {
	foreach ( $css['pblock_text_below_price_fs'] as $pro_key => $val ) {
		if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-text-below-price p' ]['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-text-below-price' ]['font-size']   = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		}
		if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-text-below-price p' ]['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-text-below-price' ]['font-size']   = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		}
		if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-text-below-price p' ]['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-text-below-price' ]['font-size']   = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		}
	}
}

if ( isset( $css['pblock_desc_icon_size'] ) && is_array( $css['pblock_desc_icon_size'] ) && count( $css['pblock_desc_icon_size'] ) > 0 ) {
	foreach ( $css['pblock_desc_icon_size'] as $pro_key => $val ) {
		if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
			$output_media['desktop'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list .wfocu-licon' ]['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		}
		if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
			$output_media['tablet'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list .wfocu-licon' ]['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );

		}
		if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
			$output_media['mobile'][ '.wfocu-pkey-' . $pro_key . ' .wfocu-pblock-check-list .wfocu-licon' ]['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		}
	}
}

/** Highlighted Product Block -Tag style */

if ( isset( $css['hl_pblock_badge_bg_color'] ) && ! empty( $css['hl_pblock_badge_bg_color'] ) ) {
	$output['.wfocu-highlight-pblock .wfocu-best-badge:before']['border-top-color'] = $css['hl_pblock_badge_bg_color'];
}
if ( isset( $css['hl_pblock_badge_tcolor'] ) && ! empty( $css['hl_pblock_badge_tcolor'] ) ) {
	$output['.wfocu-highlight-pblock .wfocu-best-badge span']['color'] = $css['hl_pblock_badge_tcolor'];
}


/** Highlighted Product Block - Border style */

if ( isset( $css['hl_pblock_border_color'] ) && ! empty( $css['hl_pblock_border_color'] ) ) {
	$output['.wfocu-mp-wrapper .wfocu-highlight-pblock']['border-color'] = $css['hl_pblock_border_color'];
}
if ( isset( $css['hl_pblock_border_width'] ) && ! empty( $css['hl_pblock_border_width'] ) ) {
	$output['.wfocu-mp-wrapper .wfocu-highlight-pblock']['border-width'] = $css['hl_pblock_border_width'] . 'px';
}
if ( isset( $css['hl_pblock_border_type'] ) && ! empty( $css['hl_pblock_border_type'] ) ) {
	$output['.wfocu-mp-wrapper .wfocu-highlight-pblock']['border-style'] = $css['hl_pblock_border_type'];
}

/** Highlighted Product Block - Button style */
if ( isset( $css['hl_pblock_accept_btn_bg_color'] ) && ! empty( $css['hl_pblock_accept_btn_bg_color'] ) ) {
	$output['.wfocu-highlight-pblock .wfocu-buy-block  .wfocu-accept-button']['background-color'] = $css['hl_pblock_accept_btn_bg_color'];
}
if ( isset( $css['hl_pblock_accept_btn_bg_color_hover'] ) && ! empty( $css['hl_pblock_accept_btn_bg_color_hover'] ) ) {
	$output['.wfocu-highlight-pblock .wfocu-buy-block  .wfocu-accept-button:hover']['background-color'] = $css['hl_pblock_accept_btn_bg_color_hover'];
}
if ( isset( $css['hl_pblock_accept_btn_t_color'] ) && ! empty( $css['hl_pblock_accept_btn_t_color'] ) ) {
	$output['.wfocu-highlight-pblock .wfocu-buy-block  .wfocu-accept-button']['color'] = $css['hl_pblock_accept_btn_t_color'];
}
if ( isset( $css['hl_pblock_accept_btn_t_color_hover'] ) && ! empty( $css['hl_pblock_accept_btn_t_color_hover'] ) ) {
	$output['.wfocu-highlight-pblock .wfocu-buy-block  .wfocu-accept-button:hover']['color'] = $css['hl_pblock_accept_btn_t_color_hover'];
}
if ( isset( $css['hl_pblock_accept_btn_shadow'] ) && ! empty( $css['hl_pblock_accept_btn_shadow'] ) ) {
	$output['.wfocu-highlight-pblock .wfocu-buy-block .wfocu-accept-button']['box-shadow'] = '0px 4px 0px ' . $css['hl_pblock_accept_btn_shadow'];
}
$font_css = '';
if ( isset($this->internal_css) && isset( $this->internal_css->site_font_family_fs ) && ! empty( $this->internal_css->site_font_family_fs ) && 'default' !==  $this->internal_css->site_font_family_fs ) {
	exit;
	$font_url = 'https://fonts.googleapis.com/css?family=' . $this->internal_css->site_font_family_fs;
	echo "<link href='" . $font_url . "' rel=stylesheet>";
	$font_css = 'body, body p, h1, h2, h3, h4, h5, h6, .wfocu-progressbar-style1 .wfocu-pstep, .wfocu-progressbar-style2 .wfocu-current-step-text, .wfocu-progressbar-style2 .wfocu-current-step-text{font-family:"' . $this->selected_font_family . '"}';
}
echo "<style>\n";
echo $font_css;
echo "</style>\n";