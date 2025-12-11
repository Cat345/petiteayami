<?php
$step_1_text = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step1t' );
$step_2_text = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step2t' );
$step_3_text = WFOCU_Common::get_option( WFOCU_SLUG . '_header_progress_bar_step3t' );
?>
<div class="wfocu-landing-section wfocu-progressbar-section wfocu-progressbar-style1">
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
