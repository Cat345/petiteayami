<?php
defined( 'ABSPATH' ) || exit;
?>
<style>
    .wfacp_track_option_dropdown select {
        -webkit-appearance: menulist;
    }
</style>
<div id="wfacp_setting_container" class="wfacp_inner_setting_wrap">
    <div class="wfacp_p20_noside wfacp_box_size clearfix">
        <div class="wfacp_wrap_inner wfacp_wrap_inner_offers" style="margin-left: 0px;">
            <div class="wfacp_wrap_r">
                <div class="wfacp-product-tabs-view-vertical wfacp-product-widget-tabs">
                    <div class="wfacp-product-tabs-wrapper wfacp-tab-center ">
                        <div id="wfacp-global-checkout" data-tab="1" role="tab" class="wfacp-tab-title wfacp-tab-desktop-title wfacp_tracking_analytics"><?php _e( 'Tracking Analytics', 'woofunnels-aero-checkout' ) ?></div>
                        <div id="wfacp-tracking-analytics" data-tab="2" role="tab" class="wfacp-tab-title wfacp-tab-desktop-title wfacp_tracking_analytics"><?php _e( 'Custom Scripts', 'woofunnels-aero-checkout' ) ?>
                        </div>
                        <div id="wfacp-permalink" data-tab="3" role="tab" class="wfacp-tab-title wfacp-tab-desktop-title wfacp_tracking_analytics"><?php _e( 'Custom CSS', 'woofunnels-aero-checkout' ) ?></div>
                   		<?php do_action('wfacp_checkout_settings_tabs'); ?>
                    </div>
                    <div class="wfacp-product-widget-container wfacp_optimise_global_setting">
                        <div class="wfacp-product-tabs wfacp-tabs-style-line" role="tablist">
                            <div class="wfacp-product-tabs-content-wrapper">
                                <div class="wfacp_global_setting_inner">
                                    <div class="wfacp_global_container">
                                        <form @change="changed()" v-on:submit.prevent="save()">
                                            <div class="wfacp_settings_sections">
                                                <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
                                            </div>
                                            <div class="bwf_ajax_save_buttons bwf_form_submit">
                                                <button type="submit" class="wfacp_save_btn_style" style=" margin-bottom: 10px;"><?php _e( 'Save Changes', 'woofunnels-aero-checkout' ); ?></button>
                                                <span class="wfacp_spinner spinner"></span>
                                            </div>
                                            <br/>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
