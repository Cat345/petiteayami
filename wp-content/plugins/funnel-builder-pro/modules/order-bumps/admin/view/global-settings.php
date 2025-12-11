<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/** Registering Settings in top bar */
if ( class_exists( 'BWF_Admin_Breadcrumbs' ) ) {
	BWF_Admin_Breadcrumbs::register_node( [ 'text' => __( 'Settings', 'woofunnels-order-bump' ) ] );
}
BWF_Admin_Breadcrumbs::render_sticky_bar();
?>
    <div class="inside">
        <div class="wrap wfob_global wfob_global_settings">
            <div class="wfob_clear_30"></div>
            <div class="wfob_head_bar">
                <div class="wfob_bar_head"><?php _e( 'Settings', 'woofunnels-order-bump' ); ?></div>
            </div>
			<?php
			$bwf_settings = BWF_Admin_Settings::get_instance();
			$bwf_settings->render_tab_html( 'wfob' );
			?>
            <div class=" wfob_global_settings_wrap wfob_page_col2_wrap">
                <div class="wfob-product-tabs-view-vertical wfob-product-widget-tabs" id="wfob_global_setting_vue">
                    <div class="wfob-product-tabs-wrapper wfob-tab-center">
                        <div class="wfob-tab-title wfob-tab-desktop-title global_custom_css wfob-active" id="tab-title-global_custom_css" data-tab="1" role="tab" aria-controls="wfob-tab-content-global_custom_css">
                            <?php echo __( 'Custom CSS', 'woofunnels-order-bump' ); ?>
                        </div>
                        <div class="wfob-tab-title wfob-tab-desktop-title additional_information_tab wfob-active" id="tab-title-additional_information" data-tab="2" role="tab" aria-controls="wfob-tab-content-additional_information">
                            <?php echo __( 'Advance', 'woofunnels-order-bump' ); ?>
                        </div>
                    </div>
                    <div class="wfob-product-widget-container">
                        <div class="wfob-product-tabs wfob-tabs-style-line" role="tablist">
                            <div class="wfob-product-tabs-content-wrapper">
                                <div class="wfob_global_setting_inner" id="wfob_global_setting">
                                    <form class="wfob_forms_wrap wfob_forms_global_settings " v-on:submit.prevent="save">
                                        <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
                                        <fieldset>
                                            <div class="wfob_form_submit" style="display: inline-block">
                                                <span class="wfob_spinner spinner" style="float: left"></span>
                                                <input type="submit" class="wfob_btn wfob_btn_primary" value="<?php _e( 'Save Changes', 'woofunnels-aero-checkout' ); ?>"/>
                                            </div>
                                        </fieldset>
                                    </form>
                                    <div class="wfob_success_modal" style="display: none" id="modal-saved-data-success" data-iziModal-icon="icon-home"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wfob_clear"></div>
            </div>
        </div>
    </div>
<?php
do_action( 'wfob_admin_footer' );
