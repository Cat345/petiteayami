<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Stop  Experiment model
 */
?>
<div id="part_stop_experiment_vue" class="bwfabt-pop-common">
    <div class="wfabt_after_remove">
        <div class="wfabt_stop_experiment bwfabt_pop_body">
            <div id="modal-ajax5" style="padding: 0px;">

                <!-- Step-2 stopping -->
                <div v-if="`stopping`===stop_status" class=" bwfabt_stopping_experiment">
                    <div class="bwfabt-stopping-experiment ">
                        <div class="bwfabt_row wfabt_txt_center">
                            <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
                        </div>
                    </div>
                </div>

                <!-- Step-3 paused -->
                <div v-if="`paused`===stop_status" v-bind:class="(`paused`===stop_status)?` bwfabt_disp_show`:``" class="bwfabt_stopping_experiment bwfabt-success main wfabt_main_2 wfabt_main_3 bwfabt_disp_none">

                    <div class="bwfabt_row wfabt_txt_center">
                        <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                            <circle class="path circle" fill="none" stroke="#b7e6bb" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                            <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                        </svg>
                    </div>
                    <div class="bwfabt_row wfabt_txt_center">
                        <div class="wfabt_h3"><?php esc_html_e( 'Experiment paused successfully!', 'woofunnels-ab-tests' ); ?> </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
