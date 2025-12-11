<?php
/** Data */
$product_key = $data['key'];

$template_ins = $this->get_template_ins();

$sec_heading           = WFOCU_Common::get_option( 'wfocu_reviews_reviews_heading' );
$sec_heading           = WFOCU_Common::maybe_parse_merge_tags( $sec_heading );
$sec_sub_heading       = WFOCU_Common::get_option( 'wfocu_reviews_reviews_sub_heading' );
$sec_sub_heading       = WFOCU_Common::maybe_parse_merge_tags( $sec_sub_heading );
$sec_bg_color          = WFOCU_Common::get_option( 'wfocu_reviews_reviews_bg_color' );
$additional_text       = WFOCU_Common::get_option( 'wfocu_reviews_reviews_additional_text' );
$additional_text       = WFOCU_Common::maybe_parse_merge_tags( $additional_text, false, false );
$additional_text_align = WFOCU_Common::get_option( 'wfocu_reviews_reviews_additional_talign' );
$rbox_heading_fs       = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rbox_heading_fs' );
$rbox_meta_fs          = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rbox_meta_fs' );
$rbox_heading_color    = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rbox_heading_color' );
$rbox_meta_color       = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rbox_meta_color' );
$rbox_border_type      = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rbox_border_type' );
$rbox_border_width     = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rbox_border_width' );
$rbox_border_color     = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rbox_border_color' );

$display_image     = WFOCU_Common::get_option( 'wfocu_reviews_reviews_display_image' );
$display_rating    = WFOCU_Common::get_option( 'wfocu_reviews_reviews_display_rating' );
$display_auth_date = WFOCU_Common::get_option( 'wfocu_reviews_reviews_display_auth_date' );

$display_buy_block           = WFOCU_Common::get_option( 'wfocu_reviews_reviews_display_buy_block' );
$display_buy_block_variation = WFOCU_Common::get_option( 'wfocu_reviews_reviews_display_buy_block_variation' );

$disp_img_class       = $display_image !== true ? 'wfocu-block-no-img' : '';
$disp_rating_class    = $display_rating !== true ? 'wfocu-hide' : '';
$disp_auth_date_class = $display_auth_date !== true ? 'wfocu-hide' : '';

$review_override_global = WFOCU_Common::get_option( 'wfocu_reviews_reviews_override_global' );
if ( true === $review_override_global ) {
	$review_head_color     = WFOCU_Common::get_option( 'wfocu_reviews_reviews_heading_color' );
	$review_sub_head_color = WFOCU_Common::get_option( 'wfocu_reviews_reviews_sub_heading_color' );
	$review_content_color  = WFOCU_Common::get_option( 'wfocu_reviews_reviews_content_color' );
}

$template_ins->internal_css['review_bg_color']          = $sec_bg_color;
$template_ins->internal_css['review_box_heading_fs']    = $rbox_heading_fs;
$template_ins->internal_css['review_box_meta_fs']       = $rbox_meta_fs;
$template_ins->internal_css['review_box_heading_color'] = $rbox_heading_color;
$template_ins->internal_css['review_box_meta_color']    = $rbox_meta_color;
$template_ins->internal_css['review_box_border_type']   = $rbox_border_type;
$template_ins->internal_css['review_box_border_color']  = $rbox_border_color;
$template_ins->internal_css['review_box_border_width']  = $rbox_border_width;
if ( true === $review_override_global ) {
	$template_ins->internal_css['review_head_color']     = $review_head_color;
	$template_ins->internal_css['review_sub_head_color'] = $review_sub_head_color;
	$template_ins->internal_css['review_content_color']  = $review_content_color;

}
$testimonial_boxes = array();

$review_type = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rtype' );
if ( 'manual' === $review_type ) {
	$testimonial_boxes = WFOCU_Common::get_option( 'wfocu_reviews_reviews_testimonial' );
} else {
	$limit         = WFOCU_Common::get_option( 'wfocu_reviews_reviews_limit' );
	$threshold     = WFOCU_Common::get_option( 'wfocu_reviews_reviews_rthreshold' );
	$products_data = $template_ins->products_data;
	if ( is_array( $products_data ) && count( $products_data ) > 0 ) {
		$pro_id = array();
		foreach ( $products_data as $pro_data ) {
			$pro_id[] = $pro_data['id'];
		}

		$args = array(
			'post__in'   => $pro_id,
			'status'     => 'approve',
			'type'       => 'all',
			'number'     => $limit,
			'meta_query' => array(
				array(
					'key'     => 'rating',
					'value'   => $threshold,
					'compare' => '>=',
				),
			),
			'meta_key'   => 'rating',
			'orderby'    => 'meta_value_num',
			'order'      => 'DESC',
		);


		$comments = get_comments( $args );
		if ( is_array( $comments ) && count( $comments ) > 0 ) {
			$h = 1;
			foreach ( $comments as $comment ) {
				if ( ! empty( $comment->comment_content ) ) {
					$testimonial_boxes[ $comment->comment_ID ]['message'] = $comment->comment_content;
				}
				if ( ! empty( $comment->comment_author ) ) {
					$testimonial_boxes[ $comment->comment_ID ]['name'] = $comment->comment_author;
				}
				$testimonial_boxes[ $comment->comment_ID ]['date']   = $comment->comment_date;
				$testimonial_boxes[ $comment->comment_ID ]['rating'] = get_comment_meta( $comment->comment_ID, 'rating', true );
				$testimonial_boxes[ $comment->comment_ID ]['image']  = $comment->comment_author_email ? get_avatar_url( $comment->comment_author_email, array( 'size' => 96 ) ) : '';
				if ( $h === absint($limit) ) {
					break;
				}
				$h ++;
			}
		}
	}
}


if ( ! is_array( $testimonial_boxes ) || count( $testimonial_boxes ) === 0 ) {
	return;
}
?>
<div class="wfocu-landing-section wfocu-review-section  wfocu-review-sec-style1" data-scrollto="wfocu_reviews_reviews">
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
		<?php if ( is_array( $testimonial_boxes ) && count( $testimonial_boxes ) > 0 ) { ?>
            <div class="wfocu-review-grid">
                <div class="wfocu-row wfocu-review-list-row">
					<?php
					foreach ( $testimonial_boxes as $review_box ) {
						$rbox_heading     = isset( $review_box['heading'] ) ? $review_box['heading'] : '';
						$rbox_text        = isset( $review_box['message'] ) ? $review_box['message'] : '';
						$rbox_img         = isset( $review_box['image'] ) ? $review_box['image'] : '//2.gravatar.com/avatar/2186cb27df23f0c90a1a0109e8d87e76?s=96&d=mm';
						$rbox_name        = isset( $review_box['name'] ) ? $review_box['name'] : '';
						$rbox_date        = isset( $review_box['date'] ) ? $review_box['date'] : '';
						$rbox_rating_val  = $review_box['rating'];
						$rbox_date_format = $rbox_date ? date( 'M d, Y', strtotime( $rbox_date ) ) : '';
						$rbox_rating      = $rbox_rating_val ? ( ( $rbox_rating_val / 5 ) * 100 ) . '%' : '';

						$rbox_meta_arr = array();
						$rbox_meta     = '';
						if ( $rbox_name !== '' ) {
							$rbox_meta_arr[] = $rbox_name;
						}
						if ( $rbox_date !== '' ) {
							$rbox_meta_arr[] = $rbox_date_format;
						}
						if ( is_array( $rbox_meta_arr ) && count( $rbox_meta_arr ) > 0 ) {
							if ( count( $rbox_meta_arr ) === 2 ) {
								$rbox_meta = implode( ' on ', $rbox_meta_arr );
							}
							if ( count( $rbox_meta_arr ) === 1 ) {
								$rbox_meta = implode( '', $rbox_meta_arr );
							}
						}
						/*
						 * Add Class "wfocu-block-no-img" with "wfocu-review-block" to disable image.
						 */
						$rbox_img_src = WFOCU_Common::get_image_source( $rbox_img, 'full' );
						?>
                        <div class="wfocu-col-md-6 wfocu-col-xs-12 wfocu-review-block-col">
                            <div class="wfocu-review-block <?php echo $disp_img_class; ?>">
								<?php if ( $rbox_img !== '' ) { ?>
                                    <div class="wfocu-review-img">
                                        <div class="wfocu-img-cover">
                                            <img class="skip-lazy" src="<?php echo $rbox_img_src; ?>" alt="" title=""/>
                                        </div>
                                    </div>
								<?php } ?>
                                <div class="wfocu-review-content">
                                    <div class="wfocu-review-rating <?php echo $disp_rating_class; ?>">
                                        <div class="wfocu-star-rating"><span style="width: <?php echo $rbox_rating; ?>"></span></div>
                                    </div>
									<?php echo $rbox_heading ? '<div class="wfocu-review-type">' . $rbox_heading . '</div>' : ''; ?>
									<?php if ( $rbox_meta !== '' ) { ?>
                                        <div class="wfocu-review-meta <?php echo $disp_auth_date_class; ?>">
											<?php echo $rbox_meta; ?>
                                        </div>
									<?php } ?>
									<?php echo $rbox_text ? '<div class="wfocu-review-text">' . apply_filters( 'wfocu_the_content', $rbox_text ) . '</div>' : ''; ?>
                                </div>
                            </div>
                        </div>
						<?php
						unset( $rbox_heading );
						unset( $rbox_text );
						unset( $rbox_img );
						unset( $rbox_image_src );
						unset( $rbox_name );
						unset( $rbox_date );
						unset( $rbox_rating_val );
						unset( $rbox_date_format );
						unset( $rbox_rating );
						unset( $rbox_meta_arr );
						unset( $rbox_meta );
					}
					?>
                </div>
            </div>
			<?php
		}
		?>
		<?php

		$additional_text_html = '';
		if ( $additional_text !== '' ) {

			ob_start();
			?>
            <div class="wfocu-row">
                <div class="wfocu-col-md-12">
                    <div class="wfocu-content-area <?php echo $additional_text_align; ?> wfocu-max-1024">
						<?php echo apply_filters( 'wfocu_the_content', $additional_text ); ?>
                    </div>
                </div>
            </div>
			<?php
			$additional_text_html = ob_get_clean();
		}


		$display_additional_text_below_buy_btn = apply_filters( 'wfocu_show_additional_text_below_buy_btn_reviews', false );


		if ( false === $display_additional_text_below_buy_btn ) {
			echo $additional_text_html;
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
		} else {

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
			echo $additional_text_html;
		}


		?>
    </div>
</div>
