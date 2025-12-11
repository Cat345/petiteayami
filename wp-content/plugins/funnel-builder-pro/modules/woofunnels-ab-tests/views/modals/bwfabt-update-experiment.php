<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
/**
 * Update experiment model
 */
?>
<div id="part_update_experiment_vue" class="bwfabt-pop bwfabt-pop-common">

    <div v-if="`update`===update_status" class="wfabt_popup_header">
        <div class="wfabt_pop_title">
			<?php esc_attr_e( 'Update Experiment', 'woofunnels-ab-tests' ); ?>
        </div>
        <button class="wfabt_pop_close_btn" data-izimodal-close="" href="javascript:void(0);"><span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>


    <div class="bwfabt-loading" v-bind:class="`1`===is_initialized?'bwfabt-hide':''">
        <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
    </div>

    <form v-if="`update`===update_status"  class="bwfabt_update_experiment" data-bwfabtaction="update_experiment">
        <div class="bwfabt_pop_body">

            <div class="bwfabt_row">
                <div class="bwfabt_vue_forms" id="part-update-experiment">
                    <vue-form-generator ref="update_experiment_ref" :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
                </div>
            </div>

            <div class="bwfabt_row wfabt_txt_center bwfabt_disp_show">
                <a v-on:click="updateExperiment()" class="updt wfabt_btn wfabt_btn_success" href="javascript:void(0);">
                    <span class="animate_btn"></span>
					<?php esc_attr_e( "Update", 'woofunnels-ab-tests' ); ?>
                </a>
            </div>
        </div>
    </form>


    <!-- Step-2 updating -->
    <div v-if="`updating`===update_status" v-bind:class="(`updating`===update_status)?` bwfabt_disp_show`:``" class="bwfabt_disp_none">
        <div class="bwfabt_pop_body">
            <div class="bwfabt-updating-experiment bwfabt_row wfabt_txt_center">
                <img src="<?php echo esc_url( BWFABT_PLUGIN_URL ) ?>/assets/img/readiness-loader.gif">
            </div>
        </div>
    </div>

    <!-- Step-3 updated -->
    <div id="modal-ajax5" v-if="`updated`===update_status" v-bind:class="(`updated`===update_status)?` bwfabt_disp_show`:``" class="bwfabt-success bwfabt_disp_none">
        <div class="bwfabt_pop_body">
            <div class="bwfabt_row wfabt_txt_center">
                <svg class="wfabt_loader" version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                    <circle class="path circle" fill="none" stroke="#39c359" stroke-width="5" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"></circle>
                    <polyline class="path check" fill="none" stroke="#39c359" stroke-width="9" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5 "></polyline>
                </svg>
                <div class="bwfabt_clear_40"></div>
                <div class="wfabt_h3"><?php esc_html_e( 'Experiment updated successfully!', 'woofunnels-ab-tests' ); ?> </div>
            </div>
        </div>
    </div>
</div>
