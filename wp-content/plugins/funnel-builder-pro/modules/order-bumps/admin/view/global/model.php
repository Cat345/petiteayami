<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wfob_success_modal" style="display: none" id="modal-saved-data-success" data-iziModal-icon="icon-home"></div>
<div class="wfob_izimodal_default" style="display: none" id="modal-add-bump">
    <div class="sections">
        <form class="wfob_add_funnel" data-wfoaction="add_new_bump" id="add-new-form" v-on:submit.prevent="onSubmit">
            <div class="wfob_vue_forms" id="part-add-bump">
                <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
            </div>
            <fieldset>
                <div class="wfob_form_submit wfob_swl_btn">
					<button data-iziModal-close type="button" class="wf_cancel_btn wfob_btn" value="cancel"><?php esc_html_e( 'Cancel', 'woofunnels-order-bump' ); ?></button>
					<button type="submit" class="wfob_btn wfob_btn_primary" value="add_new"><?php esc_html_e( 'Add', 'woofunnels-order-bump' ); ?></button>
                </div>
                <div class="wfob_form_response"></div>
            </fieldset>
        </form>
        <div class="wfob-funnel-create-success-wrap">
            <div class="wfob-funnel-create-success-logo">
                <!--                <i class="dashicons dashicons-yes"></i>-->
                <div class="swal2-icon swal2-success swal2-animate-success-icon" style="display: flex;">
                    <div class="swal2-success-circular-line-left" style="background-color: rgb(255, 255, 255);"></div>
                    <span class="swal2-success-line-tip"></span> <span class="swal2-success-line-long"></span>
                    <div class="swal2-success-ring"></div>
                    <div class="swal2-success-fix" style="background-color: rgb(255, 255, 255);"></div>
                    <div class="swal2-success-circular-line-right" style="background-color: rgb(255, 255, 255);"></div>
                </div>
            </div>
            <div class="wfob-funnel-create-message"><?php _e( 'Order Bump Created Successfully. Launching Order Bump Editor...', 'woofunnels-order-bump' ); ?></div>
        </div>
    </div>
</div>
