<?php
$percent_stept1t = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_percent_stept1t' );
$percentage_text = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_percentage_text' );

$template_ins = $this->get_template_ins();

/** css */
$base_color     = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_base_color' );
$progress_color = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_progress_color' );
$border_color   = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_border_color' );
$step_tcolor    = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step_tcolor' );
$step_fs        = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step_fs' );
$percent_val    = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_percent_val' );
$sec_bg_color   = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_bgcolor' );

$template_ins->internal_css['progress_bar2_base_color']     = $base_color;
$template_ins->internal_css['progress_bar2_progress_color'] = $progress_color;
$template_ins->internal_css['progress_bar2_border_color']   = $border_color;
$template_ins->internal_css['progress_bar2_step_tcolor']    = $step_tcolor;
$template_ins->internal_css['progress_bar2_step_fs']        = $step_fs;
$template_ins->internal_css['progress_bar2_percent_val']    = $percent_val;
$template_ins->internal_css['progressbar_bg_color']         = $sec_bg_color;
/** merge tag decode */
$percentage_text = str_replace( '{{percentage}}', '<span>' . $percent_val . '%</span>', $percentage_text );
?>
<div class="wfocu-landing-section wfocu-progressbar-section wfocu-progressbar-style2" data-scrollto="wfocu_header_progress_bar">
    <div class="wfocu-container">
        <div class="wfocu-row">
            <div class="wfocu-col-md-12">
                <div class="wfocu-progressbar-sec-wrap wfocu-max-845">
                    <div class="wfocu-progressbar wfocu-clearfix">
                        <div class="wfocu-current-step-text"><?php echo $percent_stept1t; ?></div>
                        <div class="wfocu-progress-meter">
                            <div class="wfocu-progress-scale"><?php echo $percentage_text; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
