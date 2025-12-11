<div class="wfocu_funnel_setting" id="wfocu_funnel_setting_vue">
    <div class="wfocu_funnel_setting_inner">
<!--        <div class="wfocu_fsetting_table_head">
            <div class="wfocu_fsetting_table_head_in wfocu_clearfix">
                <div class="wfocu_fsetting_table_title "><?php echo __( 'Settings', 'woofunnels-upstroke-one-click-upsell' ); ?>
                </div>
                <div class="setting_save_buttons wfocu_form_submit">
                    <span class="wfocu_save_funnel_setting_ajax_loader spinner"></span>
                    <button v-on:click.self="onSubmit" class="wfocu_save_btn_style"><?php _e( 'Save Changes', 'woofunnels-upstroke-one-click-upsell' ) ?></button>
                </div>
            </div>
        </div>-->
        <div class="wfocu-tabs-view-vertical wfocu-widget-tabs">
            <div class="wfocu-tabs-wrapper wfocu-tabs-style-line wfocu-funnel-setting-tabs" role="tablist">
				<div class="wfocu-tab-title wfocu-tab-behav-title basic_tab wfocu-active" data-tab="1" role="tab" aria-controls="wfocu-tab-content-basic">
		            <?php _e( 'Order', 'woofunnels-upstroke-one-click-upsell' ); ?>
				</div>

	            <div class="wfocu-tab-title wfocu-tab-prices-title basic_tab" data-tab="2" role="tab" aria-controls="wfocu-tab-content-basic">
		            <?php _e( 'Prices', 'woofunnels-upstroke-one-click-upsell' ); ?>
	            </div>
				<div class="wfocu-tab-title wfocu-tab-priority-title basic_tab" data-tab="3" role="tab" aria-controls="wfocu-tab-content-basic">
		            <?php _e( 'Priority', 'woofunnels-upstroke-one-click-upsell' ); ?>
				</div>
	            <div class="wfocu-tab-title wfocu-tab-messages-title basic_tab" data-tab="4" role="tab" aria-controls="wfocu-tab-content-basic">
		            <?php _e( 'Confirmation Messages', 'woofunnels-upstroke-one-click-upsell' ); ?>
	            </div>
	            <div class="wfocu-tab-title wfocu-tab-external-title basic_tab" data-tab="5" role="tab" aria-controls="wfocu-tab-content-basic">
		            <?php _e( 'External Tracking Code', 'woofunnels-upstroke-one-click-upsell' ); ?>
	            </div>
            </div>

            <!--	ADD CLASS "wfocu_hr_gap" to class form-group to add separator-->
            <div class="wfocu-tabs-content-wrapper wfocu-funnel-setting-tabs-content">
	                <div class="wfocu_forms_fields_settings">
		                <div class="wfocu_forms_conatiner">
							<form class="wfocu_forms_wrap wfocu_forms_global_settings">
								<fieldset class="fieldsets">
									<vue-form-generator :schema="schema" :model="model" :options="formOptions">
									</vue-form-generator>
								</fieldset>
							</form>
			                <div class="wfocu-tabs-content-btn wfocu_form_submit wfocu_btm_grey_area wfocu_clearfix">
				                <div class="wfocu_btm_save_wrap wfocu_clearfix" style="display:none">
					                <button v-on:click.self="onSubmit" class="wfocu_save_btn_style"><?php _e( 'Save Changes', 'woofunnels-upstroke-one-click-upsell' ) ?></button>
					                <span class="wfocu_save_funnel_setting_ajax_loader spinner"></span>
				                </div>
			                </div>
		                </div>


	                </div>
            </div>

            <div class="wfocu_success_modal" style="display: none" id="modal-settings_success" data-iziModal-icon="icon-home">
            </div>

        </div>
        <div style="display: none" class="wfocu-funnel-settings-help-messages" data-iziModal-title="<?php echo __( 'Offer Success/Failure Messages Help', 'woofunnels-upstroke-one-click-upsell' ) ?>" data-iziModal-icon="icon-home">
            <div class="sections wfocu_img_preview" style="height: 254px;">
                <img src="<?php echo WFOCU_PLUGIN_URL . '/assets/img/funnel-settings-prop.jpg' ?>"/>
            </div>
        </div>
    </div>
