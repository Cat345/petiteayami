<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Readiness model
 */
?>
<div id="bwfabt-readiness-vue" class="experiment_readiness-wrap" style="padding: 0px;">
    <!-- Step-1 Checking -->
    <div v-if="`1`==readiness_state" id="experiment_readiness">

        <div class="bwfabt_pop_body">
            <div class="bwfabt_row bwfabt_text_center">
                <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
            </div>
            <div class="bwfabt_row bwfabt_text_center">
                <div class="wfabt_h3"><?php esc_html_e( 'Please wait for a few moments! ', 'woofunnels-ab-tests' ); ?></div>
                <div class="wfabt_p" v-if="``==starting_text"><?php esc_html_e( 'Checking your experiment readiness.....', 'woofunnels-ab-tests' ); ?></div>
                <div class="wfabt_p" v-if="``!==starting_text">{{decodeHtml(starting_text)}}</div>
            </div>
        </div>
    </div>

    <!-- Step-2 Error step -->
    <div v-if="`2`==readiness_state" v-bind:class="(`2`==readiness_state)?` bwfabt_disp_show`:``" id="oh_snap" class="wfabt_announce bwfabt_disp_none bwfabt-error-state">
        <div id="modal-ajax5" style="padding: 0px;">
            <div class="bwfabt_readines_popup bwfabt_pop_body">

                <div class="bwfabt_row">
                    <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                        <circle class="path circle" fill="none" stroke="#ffb7bf" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
                        <line class="path line" fill="none" stroke="#e64155" stroke-width="8" stroke-linecap="round" stroke-miterlimit="10" x1="34.4" y1="37.9" x2="95.8" y2="92.3"/>
                        <line class="path line" fill="none" stroke="#e64155" stroke-width="8" stroke-linecap="round" stroke-miterlimit="10" x1="95.8" y1="38" x2="34.4" y2="92.2"/>
                    </svg>
                </div>

                <div class="bwfabt_row bwfabt_text_center">
                    <div class="wfabt_black_color wfabt_red_status"><?php esc_html_e( 'Oh Snap, found few issues with this experiment.', 'woofunnels-ab-tests' ); ?></div>
                </div>
                <div class="bwfabt_clear_20"></div>
                <div class="bwfabt_readines_popup wfabt_bg_red_alrt">   <!-- wfabt_bg_green_success -->
                    <ul>
                        <li>{{message}}</li>
                        <ul class="inactive-variants" v-if="true==inactive_variant">
                            <li style="list-style-type:disc;" v-for="(variant_title, variant_id) in inactive_variants">{{decodeHtml(variant_title)}}(#{{decodeHtml(variant_id)}})</li>
                        </ul>
                    </ul>
                </div>
                <div class="bwfabt_clear_20"></div>
                <div class="bwfabt_row bwfabt_text_center">
                    <a v-on:click="closeReadiness()" class="wfabt_giude_user wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                        <span class="animate_btn"></span>
						<?php esc_html_e( 'Fix and try again', 'woofunnels-ab-tests' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Step-4 After starting -->
    <div v-if="`3`==readiness_state" v-bind:class="(`3`==readiness_state)?` bwfabt_disp_show`:``" id="experiment_start" class="bwfabt_disp_none">
        <div id="modal-ajax5" style="padding: 0px;">

            <div class="bwfabt_pop_body bwfabt_readines_popup">

                <div class="bwfabt_row">
                    <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                        <circle class="path circle" fill="none" stroke="#b7e6bb" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                        <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                    </svg>
                </div>

                <div class="bwfabt_row wfabt_txt_center">
                    <div class="wfabt_black_color wfabt_red_status"><?php esc_html_e( 'Awesome! Your experiment is about to start!', 'woofunnels-ab-tests' ); ?></div>
                </div>

                <div class="wfabt_bg_red_alrt wfabt_bg_green_success">   <!-- wfabt_bg_green_success -->
                    <p><?php esc_html_e( 'After you\'ve started your experiment, you can', 'woofunnels-ab-tests' ); ?></p>
                    <ul>
                        <li><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/right.png"><?php esc_html_e( 'Pause & restart it anytime', 'woofunnels-ab-tests' ); ?></li>
                        <li><img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/right.png"><?php esc_html_e( 'Adjust traffic while it is running', 'woofunnels-ab-tests' ); ?></li>
                        <li>
                            <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/right.png"><?php esc_html_e( 'Remove the variant you don’t want to test anymore', 'woofunnels-ab-tests' ); ?>
                        </li>
                    </ul>
                </div>

                <div class="wfabt_bg_red_alrt ">   <!-- wfabt_bg_green_success -->
                    <p class="bwfabt-note">
						<?php esc_html_e( 'Note: You cannot create, delete or duplicate a variant while your experiment is running.', 'woofunnels-ab-tests' ); ?>
                    </p>
                </div>

                <div class="bwfabt_row wfabt_txt_center">

                    <a v-on:click="goLive()" class="wfabt_giude_user wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                        <span class="animate_btn"></span>
						<?php esc_html_e( 'Start now', 'woofunnels-ab-tests' ); ?>
                    </a>

                    <a v-on:click="closeReadiness()" class="wfabt_giude_user wfabt_btn wfabt_btn_grey" href="javascript:void(0);">
                        <span class="animate_btn"></span>
						<?php esc_html_e( 'Start later', 'woofunnels-ab-tests' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Step-4 Live now -->
    <div v-if="`4`==readiness_state" v-bind:class="(`4`==readiness_state)?` bwfabt_disp_show`:``" class="wfabt_check_readiness wfabt_announce bwfabt_disp_none">
        <div id="modal-ajax5" style="padding: 0px;">

            <div class="bwfabt_clear_20"></div>

            <div class="bwfabt_row">
                <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                    <circle class="path circle" fill="none" stroke="#b7e6bb" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                    <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                </svg>
            </div>

            <div class="bwfabt_row wfabt_txt_center">
                <div class="wfabt_h3"><?php esc_html_e( 'Yay! Your experiment has just started!', 'woofunnels-ab-tests' ); ?></div>
                <div class="wfabt_p"><?php esc_html_e( 'Sit back and wait for the results now…', 'woofunnels-ab-tests' ); ?></div>
            </div>

            <div class="bwfabt_clear_20"></div>
        </div>
    </div>
</div>
