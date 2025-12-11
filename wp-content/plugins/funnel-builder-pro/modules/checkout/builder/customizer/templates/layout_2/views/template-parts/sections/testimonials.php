<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $this WFACP_Template_Common
 */
$rbox_border_type = '';
if ( isset( $data['advance_setting']['rbox_border_type'] ) && $data['advance_setting']['rbox_border_type'] != '' ) {
	$rbox_border_type = $data['advance_setting']['rbox_border_type'];
}
?>


<!--   Testimonial Section-->
<div class="<?php echo $section_key . ' ' . $rbox_border_type; ?> div_wrap_sec">

    <div class="wfacp-testing-group clearfix">

		<?php
		if ( isset( $data['heading_section']['heading'] ) && $data['heading_section']['heading'] != '' && $data['heading_section']['enable_heading'] == 1 ) {
			$align_text         = $data['heading_section']['heading_talign'];
			$font_weight        = $data['heading_section']['heading_font_weight'];
			$heading_fs_desktop = $data['heading_section']['heading_fs']['desktop'];
			$heading_fs_tablet  = $data['heading_section']['heading_fs']['tablet'];
			$heading_fs_mobile  = $data['heading_section']['heading_fs']['mobile'];
			?>


            <h2 class="wfacp-list-title wfacp_section_title <?php echo $align_text . ' ' . $font_weight; ?>">
				<?php echo $data['heading_section']['heading']; ?>
            </h2>

			<?php
		}
		?>
		<?php
		if ( isset( $data['testimonial_data']['testimonials'] ) && is_array( $data['testimonial_data']['testimonials'] ) && count( $data['testimonial_data']['testimonials'] ) > 0 ) {
			$layout_type         = $data['testimonial_data']['layout_type'];
			$display_name        = $data['testimonial_data']['hide_name'];
			$display_designation = $data['testimonial_data']['hide_designation'];
			$hide_author_meta    = $data['testimonial_data']['hide_author_meta'];


			$show_review = $data['testimonial_data']['show_review'];
			$tcount      = 0;
			foreach ( $data['testimonial_data']['testimonials'] as $key => $value ) {
				extract( $value );
				$trating = (int) $trating;

				$review_width = $trating / 5 * 100;


				if ( ! empty( $tdate ) ) {
					$timestamp = strtotime( $tdate );
					$tdate     = WFACP_Common::date_i18n( $timestamp );
				}

				$testi_image_cls = '';

				if ( $layout_type == 'alternative' ) {
					$testi_image_cls = 'timage_right';

					if ( $tcount % 2 == 0 ) {
						$testi_image_cls = 'timage_left';
					}
				}

				if ( is_numeric( $timage ) ) {
					$timage_here = wp_get_attachment_image_src( $timage, 'thumbnail' );
					$timage      = $timage_here[0];
				}

				$image_type = 'wfacp-round';

				if ( isset( $data['testimonial_data']['image_type'] ) && $data['testimonial_data']['image_type'] == 'wfacp-square' ) {
					$image_type = 'wfacp-square';
				}
				?>
                <div class="wfacp-testing-list clearfix">

					<?php
					if ( isset( $timage ) && $timage != '' && $data['testimonial_data']['hide_image'] != 1 ) {
						?>

                        <div class="wfacp-testing-img <?php echo $image_type . ' ' . $testi_image_cls; ?>">
                            <img src="<?php echo $timage; ?>">

                        </div>
						<?php
					}
					?>
                    <div class="wfacp-testimonial-detail">
						<?php
						echo sprintf( '<h3 class="wfacp-testing-sub-hd loop_head_sec">%s</h3>', ( isset( $testi_heading ) && $display_name != 1 ) ? "$testi_heading" : '' );

						if ( isset( $review_width ) && $review_width != '' && ( isset( $hide_author_meta ) && $hide_author_meta != 1 ) ) {
							?>
                            <div class="wfacp-rating-wrapper">


                                <div class="wfacp-star-rating"><span style="width: <?php echo $review_width; ?>%"></span></div>
                            </div>
							<?php
						}

						if ( isset( $tmessage ) ) {
							?>

                            <div class="wfacp-testing-text wfacp-testi-content-color"><?php echo apply_filters( 'wfacp_the_content', $tmessage ); ?></div>
							<?php
						}

						if ( ( isset( $hide_author_meta ) && $hide_author_meta != 1 ) ) {
						?>
                        <span class="wfacp-testimani-user-name wfacp-testi-content-color">
								<?php
								if ( isset( $tname ) && $tname != '' ) {
									echo $tname;
								}
								if ( ( isset( $tdate ) && $tdate != '' ) ) {

									echo sprintf( '<span class="wfacp-testimani-user-date wfacp-testi-content-color">&nbsp;-&nbsp;%s</span>', $tdate );
								}
								}
								?>
						</span>
						<?php
						if ( isset( $tdesignation ) && $display_designation != 1 ) {
							?>
                            <div class="wfacp-designation-panel wfacp-testi-content-color"><?php echo $tdesignation; ?></div>
							<?php
						}
						?>


                    </div>
                </div>


				<?php
				$tcount ++;
				unset( $tdate );
			}
		}
		?>


        <!--testing user  -->

    </div>


</div>

<!--   Testimonial Section Closed-->


