<?php

$css = $this->current_template->internal_css;
global $wfocu_css_output, $wfocu_media_css_output, $output, $output_media;

if ( is_array( $output ) ) {
	if ( ! is_array( $wfocu_css_output ) ) {
		$wfocu_css_output = [];
	}
	$wfocu_css_output = array_merge( $wfocu_css_output, $output );
}


if ( is_array( $output_media ) ) {
	if ( ! is_array( $wfocu_media_css_output ) ) {
		$wfocu_media_css_output = [];
	}
	$wfocu_media_css_output = array_merge( $wfocu_media_css_output, $output_media );
}
/** Typography Output */
if ( isset( $css['section_heading_fs'] ) && is_array( $css['section_heading_fs'] ) ) {
	$val = $css['section_heading_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-section-headings .wfocu-heading']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-section-headings .wfocu-heading']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-section-headings .wfocu-heading']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['section_sub_heading_fs'] ) && is_array( $css['section_sub_heading_fs'] ) ) {
	$val = $css['section_sub_heading_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-section-headings .wfocu-sub-heading']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-section-headings .wfocu-sub-heading']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-section-headings .wfocu-sub-heading']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['site_content_fs'] ) && is_array( $css['site_content_fs'] ) ) {
	$val = $css['site_content_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['body']['font-size']       = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['body p']['font-size']     = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['body ul li']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['body ol li']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['body']['font-size']       = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['body p']['font-size']     = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['body ul li']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['body ol li']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['body']['font-size']       = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['body p']['font-size']     = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['body ul li']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['body ol li']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['site_font_family_fs'] ) && ! empty( $css['site_font_family_fs'] )  && 'default' !== $css['site_font_family_fs']) {
	$this->selected_font_family = $css['site_font_family_fs'];
}
/** COLORS */

if ( isset( $css['site_bg_color'] ) && ! empty( $css['site_bg_color'] ) ) {
	$wfocu_css_output['html']['background-color'] = $css['site_bg_color'];
}
if ( isset( $css['section_heading_color'] ) && ! empty( $css['section_heading_color'] ) ) {
	$wfocu_css_output['.wfocu-section-headings .wfocu-heading']['color'] = $css['section_heading_color'];
}
if ( isset( $css['section_sub_heading_color'] ) && ! empty( $css['section_sub_heading_color'] ) ) {
	$wfocu_css_output['.wfocu-section-headings .wfocu-sub-heading']['color'] = $css['section_sub_heading_color'];
}
if ( isset( $css['site_content_color'] ) && ! empty( $css['site_content_color'] ) ) {
	$wfocu_css_output['body']['color']       = $css['site_content_color'];
	$wfocu_css_output['body p']['color']     = $css['site_content_color'];
	$wfocu_css_output['body ul li']['color'] = $css['site_content_color'];
	$wfocu_css_output['body ol li']['color'] = $css['site_content_color'];
}
if ( isset( $css['site_highlight_color'] ) && ! empty( $css['site_highlight_color'] ) ) {
	$wfocu_css_output['body .wfocu-highlight']['color'] = $css['site_highlight_color'];
}
/** LAYOUT */

if ( isset( $css['site_boxed_width'] ) && ! empty( $css['site_boxed_width'] ) ) {
	$wfocu_css_output['body.wfocu-boxed']['max-width']                    = $css['site_boxed_width'] . 'px';
	$wfocu_css_output['body.wfocu-boxed .wfocu-urgency-bar']['max-width'] = $css['site_boxed_width'] . 'px';
}
/** Header Logo */
if ( isset( $css['header_logo_width'] ) && ! empty( $css['header_logo_width'] ) ) {
	$wfocu_css_output['.wfocu-page-logo img']['max-width'] = $css['header_logo_width'] . 'px';
}

/** Progress Bar */
if ( isset( $css['progressbar_bg_color'] ) && ! empty( $css['progressbar_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-section']['background-color'] = $css['progressbar_bg_color'];
}
if ( isset( $css['progress_bar1_base_color'] ) && ! empty( $css['progress_bar1_base_color'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style1 .wfocu-pstep:before']['background-color'] = $css['progress_bar1_base_color'];
	$wfocu_css_output['.wfocu-progressbar-style1 .wfocu-pstep:after']['background-color']  = $css['progress_bar1_base_color'];
}
if ( isset( $css['progress_bar1_progress_color'] ) && ! empty( $css['progress_bar1_progress_color'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style1 .wfocu-pstep.wfocu-completed:after']['background-color']                 = $css['progress_bar1_progress_color'];
	$wfocu_css_output['.wfocu-progressbar-style1 .wfocu-pstep.wfocu-completed + .wfocu-pstep:before']['background-color'] = $css['progress_bar1_progress_color'];
	$wfocu_css_output['.wfocu-progressbar-style1 .wfocu-pstep.wfocu-active:after']['background-color']                    = $css['progress_bar1_progress_color'];
}
if ( isset( $css['progress_bar1_step_tcolor'] ) && ! empty( $css['progress_bar1_step_tcolor'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style1 .wfocu-pstep']['color'] = $css['progress_bar1_step_tcolor'];
}
if ( isset( $css['progress_bar1_step_fs'] ) && ! empty( $css['progress_bar1_step_fs'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style1 .wfocu-pstep']['font-size'] = $css['progress_bar1_step_fs'] . 'px';
}
if ( isset( $css['progress_bar2_base_color'] ) && ! empty( $css['progress_bar2_base_color'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style2  .wfocu-progress-meter']['background-color'] = $css['progress_bar2_base_color'];
}
if ( isset( $css['progress_bar2_progress_color'] ) && ! empty( $css['progress_bar2_progress_color'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style2 .wfocu-progress-meter .wfocu-progress-scale']['background-color'] = $css['progress_bar2_progress_color'];
}
if ( isset( $css['progress_bar2_border_color'] ) && ! empty( $css['progress_bar2_border_color'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style2 .wfocu-progress-meter']['border-color'] = $css['progress_bar2_border_color'];
}
if ( isset( $css['progress_bar2_step_tcolor'] ) && ! empty( $css['progress_bar2_step_tcolor'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style1 .wfocu-progressbar .wfocu-pstep']['color'] = $css['progress_bar2_step_tcolor'];
	$wfocu_css_output['.wfocu-progressbar-style2 .wfocu-current-step-text']['color']        = $css['progress_bar2_step_tcolor'];
}
if ( isset( $css['progress_bar2_step_fs'] ) && ! empty( $css['progress_bar2_step_fs'] ) ) {
	$wfocu_css_output['.wfocu-progressbar-style2 .wfocu-current-step-text']['font-size'] = $css['progress_bar2_step_fs'] . 'px';
	$wfocu_css_output['.wfocu-progressbar-style2 .wfocu-current-step-text']['font-size'] = $css['progress_bar2_step_fs'] . 'px';
}
if ( isset( $css['progress_bar2_percent_val'] ) && ! empty( $css['progress_bar2_percent_val'] ) ) {
	$wfocu_css_output['.wfocu-progressbar .wfocu-progress-meter .wfocu-progress-scale']['width']      = $css['progress_bar2_percent_val'] . '%';
	$wfocu_css_output['.wfocu-progressbar .wfocu-progress-meter .wfocu-progress-scale span']['width'] = $css['progress_bar2_percent_val'] . '%';
}

/** Review */
if ( isset( $css['review_bg_color'] ) && ! empty( $css['review_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-review-section']['background-color'] = $css['review_bg_color'];
}
if ( isset( $css['review_box_heading_fs'] ) && is_array( $css['review_box_heading_fs'] ) ) {
	$val = $css['review_box_heading_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-review-section  .wfocu-review-block .wfocu-review-type']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-review-section  .wfocu-review-block .wfocu-review-type']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-review-section  .wfocu-review-block .wfocu-review-type']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['review_box_meta_fs'] ) && is_array( $css['review_box_meta_fs'] ) ) {
	$val = $css['review_box_meta_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-review-section  .wfocu-review-block .wfocu-review-meta']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-review-section  .wfocu-review-block .wfocu-review-meta']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-review-section  .wfocu-review-block .wfocu-review-meta']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['review_box_heading_color'] ) && ! empty( $css['review_box_heading_color'] ) ) {
	$wfocu_css_output['.wfocu-review-section  .wfocu-review-block .wfocu-review-type']['color'] = $css['review_box_heading_color'];
}
if ( isset( $css['review_box_meta_color'] ) && ! empty( $css['review_box_meta_color'] ) ) {
	$wfocu_css_output['.wfocu-review-section  .wfocu-review-block .wfocu-review-meta']['color'] = $css['review_box_meta_color'];
}
if ( isset( $css['review_box_border_type'] ) && ! empty( $css['review_box_border_type'] ) ) {
	$wfocu_css_output['.wfocu-review-section  .wfocu-review-block ']['border-style'] = $css['review_box_border_type'];
}
if ( isset( $css['review_box_border_color'] ) && ! empty( $css['review_box_border_color'] ) ) {
	$wfocu_css_output['.wfocu-review-section  .wfocu-review-block']['border-color'] = $css['review_box_border_color'];
}
if ( isset( $css['review_box_border_width'] ) && ! empty( $css['review_box_border_width'] ) ) {
	$wfocu_css_output['.wfocu-review-section  .wfocu-review-block']['border-width'] = $css['review_box_border_width'] . 'px';
}
if ( isset( $css['review_head_color'] ) && ! empty( $css['review_head_color'] ) ) {
	$wfocu_css_output['.wfocu-review-section .wfocu-section-headings .wfocu-heading']['color'] = $css['review_head_color'];
}
if ( isset( $css['review_sub_head_color'] ) && ! empty( $css['review_sub_head_color'] ) ) {
	$wfocu_css_output['.wfocu-review-section .wfocu-section-headings .wfocu-sub-heading']['color'] = $css['review_sub_head_color'];
}
if ( isset( $css['review_content_color'] ) && ! empty( $css['review_content_color'] ) ) {
	$wfocu_css_output['.wfocu-review-section .wfocu-review-block .wfocu-review-content p']['color']     = $css['review_content_color'];
	$wfocu_css_output['.wfocu-review-section .wfocu-review-block .wfocu-review-content ul li']['color'] = $css['review_content_color'];
	$wfocu_css_output['.wfocu-review-section .wfocu-review-block .wfocu-review-content ol li']['color'] = $css['review_content_color'];
	$wfocu_css_output['.wfocu-review-section .wfocu-content-area p']['color']                           = $css['review_content_color'];
	$wfocu_css_output['.wfocu-review-section .wfocu-content-area ul li']['color']                       = $css['review_content_color'];
	$wfocu_css_output['.wfocu-review-section .wfocu-content-area ol li']['color']                       = $css['review_content_color'];
	$wfocu_css_output['.wfocu-review-section .wfocu-product-attr-wrapper']['color']                     = $css['review_content_color'];

}
/** Guarantee Output */
if ( isset( $css['guarantee_bg_color'] ) && ! empty( $css['guarantee_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-guarantee-section']['background-color'] = $css['guarantee_bg_color'];
}
if ( isset( $css['guarantee_box_heading_fs'] ) && is_array( $css['guarantee_box_heading_fs'] ) ) {
	$val = $css['guarantee_box_heading_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-heading']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-heading']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-heading']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['guarantee_box_heading_color'] ) && ! empty( $css['guarantee_box_heading_color'] ) ) {
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-heading']['color'] = $css['guarantee_box_heading_color'];
}
if ( isset( $css['guarantee_head_color'] ) && ! empty( $css['guarantee_head_color'] ) ) {
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-section-headings .wfocu-heading']['color'] = $css['guarantee_head_color'];
}
if ( isset( $css['guarantee_sub_head_color'] ) && ! empty( $css['guarantee_sub_head_color'] ) ) {
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-section-headings .wfocu-sub-heading']['color'] = $css['guarantee_sub_head_color'];
}
if ( isset( $css['guarantee_content_color'] ) && ! empty( $css['guarantee_content_color'] ) ) {
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-text p']['color']     = $css['guarantee_content_color'];
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-text ul li']['color'] = $css['guarantee_content_color'];
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-text ol li']['color'] = $css['guarantee_content_color'];
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-content-area p']['color']                        = $css['guarantee_content_color'];
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-content-area ul li']['color']                    = $css['guarantee_content_color'];
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-content-area ol li']['color']                    = $css['guarantee_content_color'];
	$wfocu_css_output['.wfocu-guarantee-section .wfocu-product-attr-wrapper']['color']                  = $css['guarantee_content_color'];

}

/** Features Output */
if ( isset( $css['feature_bg_color'] ) && ! empty( $css['feature_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-feature-section']['background-color'] = $css['feature_bg_color'];
}
if ( isset( $css['feature_icon_color'] ) && ! empty( $css['feature_icon_color'] ) ) {
	$wfocu_css_output['.wfocu-feature-sec-style1 ul.wfocu-check-style li span.wfocu-check-icon svg g']['fill'] = $css['feature_icon_color'];
}
if ( isset( $css['feature_head_color'] ) && ! empty( $css['feature_head_color'] ) ) {
	$wfocu_css_output['.wfocu-feature-section .wfocu-section-headings .wfocu-heading']['color'] = $css['feature_head_color'];
}
if ( isset( $css['feature_sub_head_color'] ) && ! empty( $css['feature_sub_head_color'] ) ) {
	$wfocu_css_output['.wfocu-feature-section .wfocu-section-headings .wfocu-sub-heading']['color'] = $css['feature_sub_head_color'];
}
if ( isset( $css['feature_content_color'] ) && ! empty( $css['feature_content_color'] ) ) {
	$wfocu_css_output['.wfocu-feature-section .wfocu-feature-sec-wrap p']['color']     = $css['feature_content_color'];
	$wfocu_css_output['.wfocu-feature-section .wfocu-feature-sec-wrap ul li']['color'] = $css['feature_content_color'];
	$wfocu_css_output['.wfocu-feature-section .wfocu-feature-sec-wrap ol li']['color'] = $css['feature_content_color'];
	$wfocu_css_output['.wfocu-feature-section .wfocu-content-area p']['color']         = $css['feature_content_color'];
	$wfocu_css_output['.wfocu-feature-section .wfocu-content-area ul li']['color']     = $css['feature_content_color'];
	$wfocu_css_output['.wfocu-feature-section .wfocu-content-area ol li']['color']     = $css['feature_content_color'];
	$wfocu_css_output['.wfocu-feature-section .wfocu-product-attr-wrapper']['color']   = $css['feature_content_color'];

}

/** Video Output */

if ( isset( $css['video_bg_color'] ) && ! empty( $css['video_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-video-section']['background-color'] = $css['video_bg_color'];
}
if ( isset( $css['video_head_color'] ) && ! empty( $css['video_head_color'] ) ) {
	$wfocu_css_output['.wfocu-video-section .wfocu-section-headings .wfocu-heading']['color'] = $css['video_head_color'];
}
if ( isset( $css['video_sub_head_color'] ) && ! empty( $css['video_sub_head_color'] ) ) {
	$wfocu_css_output['.wfocu-video-section .wfocu-section-headings .wfocu-sub-heading']['color'] = $css['video_sub_head_color'];
}
if ( isset( $css['video_content_color'] ) && ! empty( $css['video_content_color'] ) ) {
	$wfocu_css_output['.wfocu-video-section .wfocu-content-area p']['color']       = $css['video_content_color'];
	$wfocu_css_output['.wfocu-video-section .wfocu-content-area ul li']['color']   = $css['video_content_color'];
	$wfocu_css_output['.wfocu-video-section .wfocu-content-area ol li']['color']   = $css['video_content_color'];
	$wfocu_css_output['.wfocu-video-section .wfocu-product-attr-wrapper']['color'] = $css['video_content_color'];

}

/** Buy Block - style1 */
if ( isset( $css['style_1_accept_btn_bg_color'] ) && ! empty( $css['style_1_accept_btn_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button']['background-color'] = $css['style_1_accept_btn_bg_color'];
}
if ( isset( $css['style_1_accept_btn_bg_color_hover'] ) && ! empty( $css['style_1_accept_btn_bg_color_hover'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button:hover']['background-color'] = $css['style_1_accept_btn_bg_color_hover'];
}
if ( isset( $css['style_1_accept_btn_t_color'] ) && ! empty( $css['style_1_accept_btn_t_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button']['color'] = $css['style_1_accept_btn_t_color'];
}
if ( isset( $css['style_1_accept_btn_t_color_hover'] ) && ! empty( $css['style_1_accept_btn_t_color_hover'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button:hover']['color'] = $css['style_1_accept_btn_t_color_hover'];
}
if ( isset( $css['style_1_accept_btn_t_fs'] ) && is_array( $css['style_1_accept_btn_t_fs'] ) ) {
	$val = $css['style_1_accept_btn_t_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-accept-button .wfocu-text']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-accept-button .wfocu-icon']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-accept-button .wfocu-text']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-accept-button .wfocu-icon']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-accept-button .wfocu-text']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-accept-button .wfocu-icon']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['style_1_accept_btn_st_fs'] ) && is_array( $css['style_1_accept_btn_st_fs'] ) ) {
	$val = $css['style_1_accept_btn_st_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-accept-button .wfocu-btn-sub']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-accept-button .wfocu-btn-sub']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-accept-button .wfocu-btn-sub']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['style_1_accept_btn_shadow'] ) && ! empty( $css['style_1_accept_btn_shadow'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button']['box-shadow'] = '0px 4px 0px ' . $css['style_1_accept_btn_shadow'];
}
if ( isset( $css['style_1_click_trigger_t_color'] ) && ! empty( $css['style_1_click_trigger_t_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-click-trigger-text']['color'] = $css['style_1_click_trigger_t_color'];
}
if ( isset( $css['style_1_click_trigger_t_fs'] ) && is_array( $css['style_1_click_trigger_t_fs'] ) ) {
	$val = $css['style_1_click_trigger_t_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-click-trigger-text']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-click-trigger-text']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-click-trigger-text']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['style_1_skip_offer_t_fs'] ) && is_array( $css['style_1_skip_offer_t_fs'] ) ) {
	$val = $css['style_1_skip_offer_t_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-skip-offer-link']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-skip-offer-link']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-skip-offer-link']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['style_1_skip_offer_t_color'] ) && ! empty( $css['style_1_skip_offer_t_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-offer-link']['color'] = $css['style_1_skip_offer_t_color'];
}
if ( isset( $css['style_1_skip_offer_btn_bg_color'] ) && ! empty( $css['style_1_skip_offer_btn_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-offer-btn']['background-color'] = $css['style_1_skip_offer_btn_bg_color'];
}
if ( isset( $css['style_1_skip_offer_t_color_hover'] ) && ! empty( $css['style_1_skip_offer_t_color_hover'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-offer-link:hover']['color'] = $css['style_1_skip_offer_t_color_hover'];
}
if ( isset( $css['style_1_skip_offer_btn_bg_color_hover'] ) && ! empty( $css['style_1_skip_offer_btn_bg_color_hover'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-offer-btn:hover']['background-color'] = $css['style_1_skip_offer_btn_bg_color_hover'];
}
/** Buy Block - style2 */
if ( isset( $css['style_2_accept_btn_bg_color'] ) && ! empty( $css['style_2_accept_btn_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button']['background-color'] = $css['style_2_accept_btn_bg_color'];
}
if ( isset( $css['style_2_accept_btn_bg_color_hover'] ) && ! empty( $css['style_2_accept_btn_bg_color_hover'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button:hover']['background-color'] = $css['style_2_accept_btn_bg_color_hover'];
}
if ( isset( $css['style_2_accept_btn_t_color'] ) && ! empty( $css['style_2_accept_btn_t_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button']['color'] = $css['style_2_accept_btn_t_color'];
}
if ( isset( $css['style_2_accept_btn_t_color_hover'] ) && ! empty( $css['style_2_accept_btn_t_color_hover'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button:hover']['color'] = $css['style_2_accept_btn_t_color_hover'];
}
if ( isset( $css['style_2_accept_btn_t_fs'] ) && is_array( $css['style_2_accept_btn_t_fs'] ) ) {
	$val = $css['style_2_accept_btn_t_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-accept-button .wfocu-text']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-accept-button .wfocu-icon']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-accept-button .wfocu-text']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-accept-button .wfocu-icon']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-accept-button .wfocu-text']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-accept-button .wfocu-icon']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['style_2_accept_btn_shadow'] ) && ! empty( $css['style_2_accept_btn_shadow'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-accept-button']['box-shadow'] = '0px 4px 0px ' . $css['style_2_accept_btn_shadow'];
}
if ( isset( $css['style_2_skip_btn_bg_color'] ) && ! empty( $css['style_2_skip_btn_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-button']['background-color'] = $css['style_2_skip_btn_bg_color'];
}
if ( isset( $css['style_2_skip_btn_bg_color_hover'] ) && ! empty( $css['style_2_skip_btn_bg_color_hover'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-button:hover']['background-color'] = $css['style_2_skip_btn_bg_color_hover'];
}
if ( isset( $css['style_2_skip_btn_t_color'] ) && ! empty( $css['style_2_skip_btn_t_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-button']['color'] = $css['style_2_skip_btn_t_color'];
}
if ( isset( $css['style_2_skip_btn_t_color_hover'] ) && ! empty( $css['style_2_skip_btn_t_color_hover'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-button:hover']['color'] = $css['style_2_skip_btn_t_color_hover'];
}
if ( isset( $css['style_2_skip_btn_t_fs'] ) && is_array( $css['style_2_skip_btn_t_fs'] ) ) {
	$val = $css['style_2_skip_btn_t_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-skip-button .wfocu-text']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-skip-button .wfocu-icon']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-skip-button .wfocu-text']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-skip-button .wfocu-icon']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-skip-button .wfocu-text']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-skip-button .wfocu-icon']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['style_2_skip_btn_shadow'] ) && ! empty( $css['style_2_skip_btn_shadow'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-skip-button']['box-shadow'] = '0px 4px 0px ' . $css['style_2_skip_btn_shadow'];
}
if ( isset( $css['style_2_click_trigger_t_fs'] ) && is_array( $css['style_2_click_trigger_t_fs'] ) ) {
	$val = $css['style_2_click_trigger_t_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-buy-block .wfocu-click-trigger-text']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-buy-block .wfocu-click-trigger-text']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-buy-block .wfocu-click-trigger-text']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['style_2_click_trigger_t_color'] ) && ! empty( $css['style_2_click_trigger_t_color'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-click-trigger-text']['color'] = $css['style_2_click_trigger_t_color'];
}
if ( isset( $css['buy_block_btn_width'] ) && ! empty( $css['buy_block_btn_width'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['width'] = $css['buy_block_btn_width'] . '%';
}
if ( isset( $css['btn_horizontal_gap'] ) && ! empty( $css['btn_horizontal_gap'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['padding-left']  = $css['btn_horizontal_gap'] . 'px';
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['padding-right'] = $css['btn_horizontal_gap'] . 'px';
}
if ( isset( $css['btn_vertical_gap'] ) && ! empty( $css['btn_vertical_gap'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['padding-top']    = $css['btn_vertical_gap'] . 'px';
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['padding-bottom'] = $css['btn_vertical_gap'] . 'px';
}
if ( isset( $css['btn_border_radius'] ) && ! empty( $css['btn_border_radius'] ) ) {
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['border-radius']         = $css['btn_border_radius'] . 'px';
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['-webkit-border-radius'] = $css['btn_border_radius'] . 'px';
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['-moz-border-radius']    = $css['btn_border_radius'] . 'px';
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['-ms-border-radius']     = $css['btn_border_radius'] . 'px';
	$wfocu_css_output['.wfocu-buy-block .wfocu-button']['-o-border-radius']      = $css['btn_border_radius'] . 'px';
}

/** Page Header */
if ( isset( $css['header_top_bgcolor'] ) && ! empty( $css['header_top_bgcolor'] ) ) {
	$wfocu_css_output['.wfocu-page-header-section']['background-color'] = $css['header_top_bgcolor'];
}
/** Page Footer */
if ( isset( $css['footer_bg_color'] ) && ! empty( $css['footer_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-page-footer-section']['background-color'] = $css['footer_bg_color'];
}
if ( isset( $css['footer_text_color'] ) && ! empty( $css['footer_text_color'] ) ) {
	$wfocu_css_output['.wfocu-page-footer-section .wfocu-footer-text']['color'] = $css['footer_text_color'];
}
if ( isset( $css['footer_text_fs'] ) && is_array( $css['footer_text_fs'] ) ) {
	$val = $css['footer_text_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-page-footer-section .wfocu-footer-text']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-page-footer-section .wfocu-footer-text']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-page-footer-section .wfocu-footer-text']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['footer_links_color'] ) && ! empty( $css['footer_links_color'] ) ) {
	$wfocu_css_output['.wfocu-page-footer-section .wfocu-footer-links li a']['color'] = $css['footer_links_color'];
}
if ( isset( $css['footer_links_fs'] ) && is_array( $css['footer_links_fs'] ) ) {
	$val = $css['footer_links_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-page-footer-section .wfocu-footer-links a']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-page-footer-section .wfocu-footer-links a']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-page-footer-section .wfocu-footer-links a']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}

/** Top Headings */
if ( isset( $css['headings_bg_color'] ) && ! empty( $css['headings_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-header-section']['background-color'] = $css['headings_bg_color'];
}
if ( isset( $css['headings_headline_color'] ) && ! empty( $css['headings_headline_color'] ) ) {
	$wfocu_css_output['.wfocu-headers-style1 .wfocu-top-heading']['color'] = $css['headings_headline_color'];
}

if ( isset( $css['headings_headline_fs'] ) && is_array( $css['headings_headline_fs'] ) ) {
	$val = $css['headings_headline_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-headers-style1 .wfocu-top-heading']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-headers-style1 .wfocu-top-heading']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-headers-style1 .wfocu-top-heading']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}

if ( isset( $css['headings_sub_headline_color'] ) && ! empty( $css['headings_sub_headline_color'] ) ) {
	$wfocu_css_output['.wfocu-headers-style1 .wfocu-top-sub-heading']['color'] = $css['headings_sub_headline_color'];
}
if ( isset( $css['headings_sub_headline_fs'] ) && is_array( $css['headings_sub_headline_fs'] ) ) {
	$val = $css['headings_sub_headline_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-headers-style1 .wfocu-top-sub-heading']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-headers-style1 .wfocu-top-sub-heading']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-headers-style1 .wfocu-top-sub-heading']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}

/** Urgency Bar */
if ( isset( $css['urgency_bar_height'] ) && ! empty( $css['urgency_bar_height'] ) ) {
	$wfocu_css_output['.wfocu-urgency-bar .wfocu-urgency-col ']['height'] = $css['urgency_bar_height'] . 'px';
}
if ( isset( $css['urgency_bar_bg_color'] ) && ! empty( $css['urgency_bar_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-urgency-bar']['background-color'] = $css['urgency_bar_bg_color'];
}
if ( isset( $css['urgency_bar_shadow'] ) && ! empty( $css['urgency_bar_shadow'] ) ) {
	$wfocu_css_output['.wfocu-urgency-bar']['box-shadow']         = '0px 0px 8px 0px ' . $css['urgency_bar_shadow'];
	$wfocu_css_output['.wfocu-urgency-bar']['-webkit-box-shadow'] = '0px 0px 8px 0px ' . $css['urgency_bar_shadow'];
	$wfocu_css_output['.wfocu-urgency-bar']['-moz-box-shadow']    = '0px 0px 8px 0px ' . $css['urgency_bar_shadow'];
	$wfocu_css_output['.wfocu-urgency-bar']['-ms-box-shadow']     = '0px 0px 8px 0px ' . $css['urgency_bar_shadow'];
	$wfocu_css_output['.wfocu-urgency-bar']['-o-box-shadow']      = '0px 0px 8px 0px ' . $css['urgency_bar_shadow'];
}

if ( isset( $css['urgency_bar_text_color'] ) && ! empty( $css['urgency_bar_text_color'] ) ) {
	$wfocu_css_output['.wfocu-urgency-bar .wfocu-content-div']['color'] = $css['urgency_bar_text_color'];
}
if ( isset( $css['urgency_bar_text_fs'] ) && is_array( $css['urgency_bar_text_fs'] ) ) {
	$val = $css['urgency_bar_text_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-urgency-bar .wfocu-content-div']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-urgency-bar .wfocu-content-div']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-urgency-bar .wfocu-content-div']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['timer_bg_color'] ) && ! empty( $css['timer_bg_color'] ) ) {
	$wfocu_css_output['.wfocu-countdown-square-ghost .wfocu-timer-wrap .wfocu-square-wrap']['background-color'] = $css['timer_bg_color'];
	$wfocu_css_output['.wfocu-ctimer-style2  .wfocu-countdown-timer-wrap']['background-color']                  = $css['timer_bg_color'];
}
/** Countdown Timer */
if ( isset( $css['ct_timer_text_color'] ) && ! empty( $css['ct_timer_text_color'] ) ) {
	$wfocu_css_output['.wfocu-countdown-highlight .wfocu-countdown-timer-text']['color'] = $css['ct_timer_text_color'];
}
if ( isset( $css['ct_timer_text_fs'] ) && ! empty( $css['ct_timer_text_fs'] ) ) {
	$wfocu_css_output['.wfocu-countdown-highlight .wfocu-countdown-timer-text']['font-size'] = $css['ct_timer_text_fs'] . 'px';
}

if ( isset( $css['ct_digit_color'] ) && ! empty( $css['ct_digit_color'] ) ) {
	$wfocu_css_output['.wfocu-countdown-square-ghost .wfocu-square-wrap .wfocu-timer-digit']['color'] = $css['ct_digit_color'];
	$wfocu_css_output['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-digit']['color'] = $css['ct_digit_color'];
	$wfocu_css_output['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-colon-sep']['color']   = $css['ct_digit_color'];
}
if ( isset( $css['ct_digit_fs'] ) && is_array( $css['ct_digit_fs'] ) ) {
	$val = $css['ct_digit_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-countdown-square-ghost .wfocu-square-wrap .wfocu-timer-digit']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-digit']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-colon-sep']['font-size']   = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-countdown-square-ghost .wfocu-square-wrap .wfocu-timer-digit']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-digit']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-colon-sep']['font-size']   = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-countdown-square-ghost .wfocu-square-wrap .wfocu-timer-digit']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-digit']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-colon-sep']['font-size']   = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
if ( isset( $css['ct_label_color'] ) && ! empty( $css['ct_label_color'] ) ) {
	$wfocu_css_output['.wfocu-countdown-square-ghost  .wfocu-square-wrap  .wfocu-timer-label']['color'] = $css['ct_label_color'];
	$wfocu_css_output['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-label']['color']   = $css['ct_label_color'];
}
if ( isset( $css['ct_label_fs'] ) && is_array( $css['ct_label_fs'] ) ) {
	$val = $css['ct_label_fs'];
	if ( isset( $val['desktop'] ) && ! empty( $val['desktop'] ) ) {
		$wfocu_media_css_output['desktop']['.wfocu-countdown-square-ghost  .wfocu-square-wrap .wfocu-timer-label']['font-size'] = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
		$wfocu_media_css_output['desktop']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-label']['font-size']  = $val['desktop'] . ( ( isset( $val['desktop-unit'] ) ) ? $val['desktop-unit'] : 'px' );
	}
	if ( isset( $val['tablet'] ) && ! empty( $val['tablet'] ) ) {
		$wfocu_media_css_output['tablet']['.wfocu-countdown-square-ghost  .wfocu-square-wrap .wfocu-timer-label']['font-size'] = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
		$wfocu_media_css_output['tablet']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-label']['font-size']  = $val['tablet'] . ( ( isset( $val['tablet-unit'] ) ) ? $val['tablet-unit'] : 'px' );
	}
	if ( isset( $val['mobile'] ) && ! empty( $val['mobile'] ) ) {
		$wfocu_media_css_output['mobile']['.wfocu-countdown-square-ghost  .wfocu-square-wrap .wfocu-timer-label']['font-size'] = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
		$wfocu_media_css_output['mobile']['.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-label']['font-size']  = $val['mobile'] . ( ( isset( $val['mobile-unit'] ) ) ? $val['mobile-unit'] : 'px' );
	}
}
/** Offer confirmation */
if ( isset( $css['offer_confirm_yes_color'] ) && ! empty( $css['offer_confirm_yes_color'] ) ) {
	$wfocu_css_output['.wfocu-sidebar-cart .wfocu-mc-button']['background-color'] = $css['offer_confirm_yes_color']['bg'] . '';
	$wfocu_css_output['.wfocu-sidebar-cart .wfocu-mc-button']['box-shadow']       = '0px 4px 0px ' . $css['offer_confirm_yes_color']['shadow'];
	$wfocu_css_output['.wfocu-sidebar-cart .wfocu-mc-button']['color']            = $css['offer_confirm_yes_color']['text'] . '';
}
if ( isset( $css['offer_confirm_yes_color']['hover'] ) && ! empty( $css['offer_confirm_yes_color']['hover'] ) ) {
	$wfocu_css_output['.wfocu-sidebar-cart .wfocu-mc-button:hover']['background-color'] = $css['offer_confirm_yes_color']['hover']['bg'] . '';
	$wfocu_css_output['.wfocu-sidebar-cart .wfocu-mc-button:hover']['box-shadow']       = '0px 4px 0px ' . $css['offer_confirm_yes_color']['hover']['shadow'];
	$wfocu_css_output['.wfocu-sidebar-cart .wfocu-mc-button:hover']['color']            = $css['offer_confirm_yes_color']['hover']['text'] . '';
}
if ( isset( $css['offer_confirm_no_color'] ) && ! empty( $css['offer_confirm_no_color'] ) ) {
	$wfocu_css_output['.wfocu-sidebar-cart .wfocu-mc-footer-btm-text a']['color']       = $css['offer_confirm_no_color'] . '';
	$wfocu_css_output['.wfocu-sidebar-cart .wfocu-mc-footer-btm-text a:hover']['color'] = $css['offer_confirm_no_color_hover'] . '';
}

if ( isset( $css['cart_opener'] ) && ! empty( $css['cart_opener'] ) ) {
	$wfocu_css_output['.wfocu-confirm-order-btn .wfocu-opener-btn-bg']['background-color'] = $css['cart_opener'];
	$wfocu_css_output['body.single-wfocu_offer .wfocu-confirm-order-btn .wfocu-opener-btn-bg']['background-color'] = $css['cart_opener'];

	$wfocu_css_output['.wfocu-confirm-order-btn .wfocu-left-arrow']['border-right-color']  = $css['cart_opener'];
	$wfocu_css_output['body.single-wfocu_offer .wfocu-confirm-order-btn .wfocu-left-arrow']['border-right-color']  = $css['cart_opener'];

	$wfocu_css_output['.wfocu-confirm-order-btn']['color']       = $css['cart_opener_text_color'] . '';
	$wfocu_css_output['body.single-wfocu_offer .wfocu-confirm-order-btn']['color']       = $css['cart_opener_text_color'] . '';

	$wfocu_css_output['.wfocu-confirm-order-btn:hover']['color'] = $css['cart_opener_text_color'] . '';
	$wfocu_css_output['body.single-wfocu_offer .wfocu-confirm-order-btn:hover']['color'] = $css['cart_opener_text_color'] . '';

	$wfocu_css_output['.wfocu-confirm-order-btn:focus']['color'] = $css['cart_opener_text_color'] . '';
	$wfocu_css_output['body.single-wfocu_offer .wfocu-confirm-order-btn:focus']['color'] = $css['cart_opener_text_color'] . '';

}

/** CSS Output */
if ( ! empty( $wfocu_css_output ) ) {
	$font_css = '';
	if ( isset( $this->selected_font_family ) && ! empty( $this->selected_font_family ) ) {
		$font_url = 'https://fonts.googleapis.com/css?family=' . $this->selected_font_family;
		echo "<link href='" . $font_url . "' rel=stylesheet>";
		$font_css = 'body, body p, h1, h2, h3, h4, h5, h6, .wfocu-progressbar-style1 .wfocu-pstep, .wfocu-progressbar-style2 .wfocu-current-step-text, .wfocu-progressbar-style2 .wfocu-current-step-text{font-family:"' . $this->selected_font_family . '"}';
	}
	echo "<style>\n";
	echo $font_css;

	foreach ( $wfocu_css_output as $elem => $single_css ) {
		echo $elem . '{';
		if ( is_array( $single_css ) && count( $single_css ) > 0 ) {
			foreach ( $single_css as $css_prop => $css_val ) {
				echo $css_prop . ':' . $css_val . ';';
			}
		}
		echo "}\n";
	}
	echo '</style>';
}

/** CSS Media Query Output */
if ( ! empty( $wfocu_media_css_output ) ) {
	echo "<style>\n";
	foreach ( $wfocu_media_css_output as $type => $css ) {
		switch ( $type ) {
			case 'tablet':
				echo "\n@media (max-width: 991px) {\n";
				break;
			case 'mobile':
				echo "\n@media (max-width: 680px) {\n";
				break;
			default:
				echo '';
				break;
		}
		foreach ( $css as $elem => $single_css ) {
			echo $elem . '{';
			if ( is_array( $single_css ) && count( $single_css ) > 0 ) {
				foreach ( $single_css as $css_prop => $css_val ) {
					echo $css_prop . ':' . $css_val . ';';
				}
			}
			if ( 'desktop' === $type ) {
				echo '}';
			} else {
				echo "}\n";
			}
		}
		switch ( $type ) {
			case 'tablet':
			case 'mobile':
				echo '}';
				break;
			default:
				echo '';
				break;
		}
	}
	echo '</style>';
}

