<?php
defined( 'ABSPATH' ) || exit;
?>
<!-- add product modal start-->
<div class="wfob_izimodal_default izimodal" id="modal_edit_skin">
    <div class="sections">
        <div class="wfob_skin_selection">
            <div class="wfob_skin_wrap" data-layout="layout_1">
                <div class="wfob_skin_preview">
                    <img src="<?php echo WFOB_PLUGIN_URL; ?>/assets/img/skin-1.jpg">
                </div>
                <label>
                    <input type="checkbox" class="wfob_select_skin" value="layout_1">
                    <span><?php esc_html_e( 'Use this skin' ) ?></span>
                </label>
            </div>

            <div class="wfob_skin_wrap" data-layout="layout_6">
                <div class="wfob_skin_preview">
                    <img src="<?php echo WFOB_PLUGIN_URL; ?>/assets/img/skin-6.jpg">
                </div>
                <label>
                    <input type="checkbox" class="wfob_select_skin" value="layout_6">
                    <span><?php esc_html_e( 'Use this skin' ) ?></span>
                </label>
            </div>


            <div class="wfob_skin_wrap" data-layout="layout_5">
                <div class="wfob_skin_preview">
                    <img src="<?php echo WFOB_PLUGIN_URL; ?>/assets/img/skin-5.jpg">
                </div>
                <label>
                    <input type="checkbox" class="wfob_select_skin" value="layout_5">
                    <span><?php esc_html_e( 'Use this skin' ) ?></span>
                </label>
            </div>

            <div class="wfob_skin_wrap" data-layout="layout_3">
                <div class="wfob_skin_preview">
                    <img src="<?php echo WFOB_PLUGIN_URL; ?>/assets/img/skin-3.jpg">
                </div>
                <label>
                    <input type="checkbox" class="wfob_select_skin" value="layout_3">
                    <span><?php esc_html_e( 'Use this skin' ) ?></span>
                </label>
            </div>
            <div class="wfob_skin_wrap" data-layout="layout_2">
                <div class="wfob_skin_preview">
                    <img src="<?php echo WFOB_PLUGIN_URL; ?>/assets/img/skin-2.jpg">
                </div>
                <label>
                    <input type="checkbox" class="wfob_select_skin" value="layout_2">
                    <span><?php esc_html_e( 'Use this skin' ) ?></span>
                </label>
            </div>

            <div class="wfob_skin_wrap" data-layout="layout_4">
                <div class="wfob_skin_preview">
                    <img src="<?php echo WFOB_PLUGIN_URL; ?>/assets/img/skin-4.jpg">
                </div>
                <label>
                    <input type="checkbox" class="wfob_select_skin" value="layout_4">
                    <span><?php esc_html_e( 'Use this skin' ) ?></span>
                </label>
            </div>

        </div>
        <div class="wfob_form_submit wfob_swl_btn">
            <span class="wfob_spinner spinner" style="margin:0 -8px 0 0"></span>
            <button data-iziModal-close type="button" class="wf_cancel_btn wfob_btn" value="cancel"><?php esc_html_e( 'Cancel', 'woofunnels-order-bump' ); ?></button>
            <input type="submit" id="wfob_select_skin" class="wfob_btn wfob_btn_primary" value="<?php esc_html_e( 'Import', 'woofunnels-order-bump' ); ?>"/>
        </div>
    </div>
</div>
<!-- add product modal end-->
