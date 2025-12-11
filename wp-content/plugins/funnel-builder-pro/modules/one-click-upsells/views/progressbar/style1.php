<?php
$step_1_text = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step1t' );
$step_2_text = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step2t' );
$step_3_text = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step3t' );

$template_ins = $this->get_template_ins();

/** css */
$base_color     = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_base_color' );
$progress_color = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_progress_color' );
$step_tcolor    = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step_tcolor' );
$step_fs        = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step_fs' );

$sec_bg_color = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_bgcolor' );

$template_ins->internal_css['progress_bar1_base_color']     = $base_color;
$template_ins->internal_css['progress_bar1_progress_color'] = $progress_color;
$template_ins->internal_css['progress_bar1_step_tcolor']    = $step_tcolor;
$template_ins->internal_css['progress_bar1_step_fs']        = $step_fs;

$template_ins->internal_css['progressbar_bg_color'] = $sec_bg_color;

/** Customizer preview html */
if ( $this->is_customizer_preview() ) {

} else {

}
?>
<div class="wfocu-landing-section wfocu-progressbar-section wfocu-progressbar-style1" data-scrollto="wfocu_header_progress_bar">
    <div class="wfocu-container">
        <div class="wfocu-row">
            <div class="wfocu-col-md-12">
                <div class="wfocu-progressbar-sec-wrap wfocu-max-845">
                    <div class="wfocu-progressbar  wfocu-clearfix">
                        <span class="wfocu-pstep wfocu-completed step1"><?php echo $step_1_text; ?></span>
                        <span class="wfocu-pstep wfocu-active step2"><?php echo $step_2_text; ?></span>
                        <span class="wfocu-pstep step3"><?php echo $step_3_text; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
