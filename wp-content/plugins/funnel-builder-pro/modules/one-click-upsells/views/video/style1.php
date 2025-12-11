<?php
/** Data */
$product_key = $data['key'];

$template_ins          = $this->get_template_ins();
$sec_heading           = WFOCU_Common::get_option( 'wfocu_video_video_heading' );
$sec_heading           = WFOCU_Common::maybe_parse_merge_tags( $sec_heading );
$sec_sub_heading       = WFOCU_Common::get_option( 'wfocu_video_video_sub_heading' );
$sec_sub_heading       = WFOCU_Common::maybe_parse_merge_tags( $sec_sub_heading );
$sec_bg_color          = WFOCU_Common::get_option( 'wfocu_video_video_bg_color' );
$video_type            = WFOCU_Common::get_option( 'wfocu_video_video_vtype' );
$video_autoplay        = WFOCU_Common::get_option( 'wfocu_video_video_autoplay' );
$additional_text       = WFOCU_Common::get_option( 'wfocu_video_video_additional_text' );
$additional_text       = WFOCU_Common::maybe_parse_merge_tags( $additional_text, false, false );
$additional_text_align = WFOCU_Common::get_option( 'wfocu_video_video_additional_talign' );
$video_override_global = WFOCU_Common::get_option( 'wfocu_video_video_override_global' );


if ( true === $video_override_global ) {
	$video_head_color     = WFOCU_Common::get_option( 'wfocu_video_video_heading_color' );
	$video_sub_head_color = WFOCU_Common::get_option( 'wfocu_video_video_sub_heading_color' );
	$video_content_color  = WFOCU_Common::get_option( 'wfocu_video_video_content_color' );
}

$display_buy_block           = WFOCU_Common::get_option( 'wfocu_video_video_display_buy_block' );
$display_buy_block_variation = WFOCU_Common::get_option( 'wfocu_video_video_display_buy_block_variation' );

$template_ins->internal_css['video_bg_color'] = $sec_bg_color;
if ( true === $video_override_global ) {
	$template_ins->internal_css['video_head_color']     = $video_head_color;
	$template_ins->internal_css['video_sub_head_color'] = $video_sub_head_color;
	$template_ins->internal_css['video_content_color']  = $video_content_color;

}

?>
<div class="wfocu-landing-section wfocu-video-section wfocu-video-sec-style1" data-scrollto="wfocu_video_video">
    <div class="wfocu-container">
        <div class="wfocu-row">
            <div class="wfocu-col-md-12">
                <div class="wfocu-video-sec-wrap">
					<?php if ( ! empty( $sec_heading ) || ! empty( $sec_sub_heading ) ) { ?>
                        <div class="wfocu-section-headings">
							<?php echo $sec_heading ? '<div class="wfocu-heading">' . $sec_heading . '</div>' : ''; ?>
							<?php echo $sec_sub_heading ? '<div class="wfocu-sub-heading wfocu-max-845">' . $sec_sub_heading . '</div>' : ''; ?>
                        </div>
					<?php } ?>
                    <div class="wfocu-col-md-10 wfocu-col-md-offset-1">
                        <div class="wfocu-video">
							<?php
							if ( $video_type === 'youtube' ) {
								$youtube_url = WFOCU_Common::get_option( 'wfocu_video_video_youtube_url' );
								$youtube_id  = WFOCU_Common::get_video_id( $youtube_url );
								if ( ! empty( $youtube_id ) ) {
									$youtube_url = "https://www.youtube.com/embed/{$youtube_id}";
								}

								$ytube_settings = WFOCU_Common::get_option( 'wfocu_video_video_ytube_settings' );
								$ytube_set      = array();
								if ( is_array( $ytube_settings ) && count( $ytube_settings ) > 0 ) {
									if ( in_array( 'autoplay', $ytube_settings, true ) ) {
										$ytube_set['autoplay'] = 1;
									}
									if ( in_array( 'showinfo', $ytube_settings, true ) ) {
										$ytube_set['showinfo'] = 0;
									}
									if ( in_array( 'rel', $ytube_settings, true ) ) {
										$ytube_set['rel'] = 0;
									}
									if ( in_array( 'controls', $ytube_settings, true ) ) {
										$ytube_set['controls'] = 0;
									}
								}
								if ( is_array( $ytube_set ) && count( $ytube_set ) > 0 ) {
									$youtube_url_new = add_query_arg( $ytube_set, $youtube_url );

								} else {
									$youtube_url_new = $youtube_url;

								}

								if ( $youtube_url !== '' ) {
									?>
                                    <div class="wfocu-youtube-video">
                                        <div class="wfocu-responsive-iframe">
                                            <iframe src="<?php echo $youtube_url_new; ?>" width="620" height="400" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
                                        </div>
                                    </div>
									<?php
								}
							}
							if ( $video_type === 'vimeo' ) {
								$vimeo_url = WFOCU_Common::get_option( 'wfocu_video_video_vimeo_url' );
								$vimeo_id  = WFOCU_Common::get_video_id( $vimeo_url, 'vimeo' );

								if ( ! empty( $vimeo_id ) ) {
									$vimeo_url = "https://player.vimeo.com/video/{$vimeo_id}";
								}
								$vimeo_settings = WFOCU_Common::get_option( 'wfocu_video_video_vimeo_settings' );
								$vimeo_set      = array();
								if ( is_array( $vimeo_settings ) && count( $vimeo_settings ) > 0 ) {
									if ( in_array( 'autoplay', $vimeo_settings, true ) ) {
										$vimeo_set['autoplay'] = 1;
									}
									if ( in_array( 'loop', $vimeo_settings, true ) ) {
										$vimeo_set['loop'] = 1;
									}
								}
								if ( is_array( $vimeo_set ) && count( $vimeo_set ) > 0 ) {
									$vimeo_url_new = add_query_arg( $vimeo_set, $vimeo_url );

								} else {
									$vimeo_url_new = $vimeo_url;
								}

								if ( $vimeo_url !== '' ) {
									?>
                                    <div class="wfocu-vimeo-video">
                                        <div class="wfocu-responsive-iframe">
                                            <iframe src="<?php echo $vimeo_url_new; ?>" width="620" height="400" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
                                        </div>
                                    </div>
									<?php
								}
							}
							if ( $video_type === 'wistia' ) {
								$wistia_url = WFOCU_Common::get_option( 'wfocu_video_video_wistia_url' );
								$wistia_id  = WFOCU_Common::get_video_id( $wistia_url, 'wistia' );
								if ( ! empty( $wistia_id ) ) {
									$wistia_url = "https://fast.wistia.com/embed/iframe/{$wistia_id}";
								}
								$wistia_settings = WFOCU_Common::get_option( 'wfocu_video_video_wistia_settings' );
								$wistia_set      = array();
								if ( is_array( $wistia_settings ) && count( $wistia_settings ) > 0 ) {
									if ( in_array( 'autoplay', $wistia_settings, true ) ) {
										$wistia_set['autoPlay'] = 1;
									}
									if ( in_array( 'loop', $wistia_settings, true ) ) {
										$wistia_set['endVideoBehavior'] = 'loop';
									}
								}
								if ( is_array( $wistia_set ) && count( $wistia_set ) > 0 ) {
									$wistia_url_new = add_query_arg( $wistia_set, $wistia_url );
								} else {
									$wistia_url_new = $wistia_url;
								}
								if ( $wistia_url !== '' ) {
									?>
                                    <div class="wfocu-wistia-video">
                                        <div class="wfocu-responsive-iframe">
                                            <iframe src="<?php echo $wistia_url_new; ?>" allowtransparency="true" frameborder="0" scrolling="no" class="wistia_embed" name="wistia_embed" allowfullscreen mozallowfullscreen webkitallowfullscreen oallowfullscreen msallowfullscreen width="620" height="400"></iframe>
                                            <script src="//fast.wistia.net/assets/external/E-v1.js" async></script>
                                        </div>
                                    </div>
									<?php
								}
							}
							if ( $video_type === 'html5' ) {
								$mp4_url      = WFOCU_Common::get_option( 'wfocu_video_video_mp4_url' );
								$webm_url     = WFOCU_Common::get_option( 'wfocu_video_video_webm_url' );
								$ogg_url      = WFOCU_Common::get_option( 'wfocu_video_video_ogg_url' );
								$poster_image = WFOCU_Common::get_option( 'wfocu_video_video_poster_image' );

								$html5_settings = WFOCU_Common::get_option( 'wfocu_video_video_html5_settings' );
								if ( is_array( $html5_settings ) && count( $html5_settings ) > 0 ) {
									$html5_param = implode( ' ', $html5_settings );
								} else {
									$html5_param = '';
								}
								?>
                                <div class="wfocu-html5-video">
                                    <video width="320" height="400" <?php echo $html5_param; ?> poster="<?php echo $poster_image ? $poster_image : ''; ?>">
                                        <source src="<?php echo $mp4_url; ?>" type="video/mp4">
                                        <source src="<?php echo $ogg_url; ?>" type="video/ogg">
                                        <source src="<?php echo $webm_url; ?>" type="video/webm">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
							<?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
