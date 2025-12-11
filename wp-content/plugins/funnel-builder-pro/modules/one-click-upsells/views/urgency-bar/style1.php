<?php

$template_ins     = $this->get_template_ins();
$current_offer_id = WFOCU_Core()->data->get_current_offer();

$show_timer  = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_show_timer' );
$timer_align = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_timer_align' );
$headline    = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_heading' );
$headline    = WFOCU_Common::maybe_parse_merge_tags( $headline );
$headline_fs = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_heading_fs' );

$headline_text_color = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_heading_color' );
$reveal_bar_secs     = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_reveal_bar_secs' );
$urgency_bar_height  = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_height' );


$skin_style  = WFOCU_Common::get_option( 'wfocu_other_ctimer_skin' );
$timer_style = $show_timer ? 'wfocu-ctimer-' . $skin_style : 'wfocu-no-ctimer';


$sec_bg_color                                       = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_bg_color' );
$template_ins->internal_css['urgency_bar_bg_color'] = $sec_bg_color;


$template_ins->internal_css['urgency_bar_text_fs']    = $headline_fs;
$template_ins->internal_css['urgency_bar_text_color'] = $headline_text_color;
$template_ins->internal_css['urgency_bar_height']     = $urgency_bar_height;


$urgency_bar_classes = array();


$urgency_bar_pos = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_position' );
$bar_display_on  = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_display_on' );

if ( $timer_align === 'wfocu-ctimer-left' ) {
	$urgency_bar_classes[] = 'wfocu-ctimer-left';
} elseif ( $timer_align === 'wfocu-ctimer-right' ) {
	$urgency_bar_classes[] = 'wfocu-ctimer-right';
}

if ( true === $show_timer ) {
	$urgency_bar_classes[] = 'wfocu-ctimer-' . $skin_style;
} else {
	$urgency_bar_classes   = array();
	$urgency_bar_classes[] = 'wfocu-no-ctimer';
}

if ( $urgency_bar_pos === 'sticky_header' ) {
	$urgency_bar_classes[] = 'wfocu-urgency-header-bar';
} elseif ( $urgency_bar_pos === 'sticky_footer' ) {
	$urgency_bar_classes[] = 'wfocu-urgency-footer-bar';
} else {
	$urgency_bar_classes[] = 'wfocu-urgency-inline';
}


if ( $urgency_bar_pos === 'sticky_header' || $urgency_bar_pos === 'sticky_footer' ) {
	$urgency_bar_shadow                               = WFOCU_Common::get_option( 'wfocu_urgency_bar_urgency_bar_shadow_color' );
	$template_ins->internal_css['urgency_bar_shadow'] = $urgency_bar_shadow;
}

/*
  Sticky Header : Use wfocu-urgency-header-bar class
  Sticky Footer : Use wfocu-urgency-footer-bar class
 */

if ( is_array( $bar_display_on ) && count( $bar_display_on ) > 0 ) {
	$desk_stat   = ( true === in_array( 'desktop', $bar_display_on, true ) ) ? 'yes' : 'no';
	$mobile_stat = ( true === in_array( 'mobile', $bar_display_on, true ) ) ? 'yes' : 'no';
} else {
	$desk_stat   = 'no';
	$mobile_stat = 'no';
}

?>

<div class="wfocu-urgency-bar <?php echo implode( ' ', $urgency_bar_classes ); ?>" data-scrollto="wfocu_urgency_bar_urgency_bar" data-id="1" data-delay="<?php echo $reveal_bar_secs; ?>" data-offer-id="<?php echo $current_offer_id; ?>" data-on-desktop="<?php echo $desk_stat; ?>" data-on-mobile="<?php echo $mobile_stat; ?>">
    <div class="wfocu-container">
        <div class="wfocu-row">
            <div class="wfocu-col-md-12">

				<?php if ( $timer_align === 'wfocu-ctimer-left' ) { ?>
                    <div class="wfocu-urgency-inner">
                        <div class="wfocu-urgency-col wfocu-urgency-left-col  wfocu-text-center ">
							<?php
							if ( true === $show_timer ) {
								WFOCU_Core()->template_loader->get_template_part( 'countdown-timer' );
							}
							?>
                        </div>
						<?php if ( ! empty( $headline ) ) { ?>
                            <div class="wfocu-urgency-col wfocu-urgency-right-col ">
                                <div class="wfocu-content-div">
                                    <div class="wfocu-h3 wfocu-text-center">
										<?php echo $headline; ?>
                                    </div>
                                </div>
                            </div>
							<?php
						}
						?>
                    </div>
				<?php } elseif ( $timer_align === 'wfocu-ctimer-right' ) { ?>
                    <div class="wfocu-urgency-inner">
						<?php if ( ! empty( $headline ) ) { ?>
                            <div class="wfocu-urgency-col wfocu-urgency-left-col">
                                <div class="wfocu-content-div">
                                    <div class="wfocu-h3 wfocu-text-center">
										<?php echo $headline; ?>
                                    </div>
                                </div>
                            </div>
							<?php
						}
						?>
                        <div class="wfocu-urgency-col wfocu-urgency-right-col wfocu-text-center ">
							<?php
							if ( true === $show_timer ) {
								WFOCU_Core()->template_loader->get_template_part( 'countdown-timer' );
							}
							?>
                        </div>

                    </div>
				<?php } else { ?>
                    <div class="wfocu-urgency-inner">
						<?php if ( ! empty( $headline ) ) { ?>
                            <div class="wfocu-urgency-col">
                                <div class="wfocu-content-div">
                                    <div class="wfocu-h3 wfocu-text-center">
										<?php echo $headline; ?>
                                    </div>
                                </div>
                            </div>
							<?php
						}
						?>
                    </div>
					<?php
				}
				?>

            </div>
        </div>
    </div>

</div>
