<?php
/** Registering Settings in top bar */
if ( class_exists( 'BWF_Admin_Breadcrumbs' ) ) {
	BWF_Admin_Breadcrumbs::register_node( [ 'text' => __( 'Settings', 'woofunnels-upstroke-one-click-upsell' ) ] );
}
BWF_Admin_Breadcrumbs::render_sticky_bar();
?>
<div class="wrap wfocu_global wfocu_global_settings">
    <div class="wfocu_clear_10"></div>
    <h1 class="wp-heading-inline"><?php _e( 'Settings', 'woofunnels-upstroke-one-click-upsell' ); ?></h1>
    <hr class="wp-header-end">
    <div class="wfocu_clear_10"></div>
    <div class=" wfocu_global_settings_wrap wfocu_page_col2_wrap">
        <div class="wfocu_page_left_wrap" id="wfocu_global_setting_vue">
			<?php
			$admin_settings = BWF_Admin_Settings::get_instance();
			$admin_settings->render_tab_html( 'upstroke' );
			?>
            <div class="wfocu-product-tabs-view-vertical wfocu-product-widget-tabs">
                <div class="wfocu-product-tabs-wrapper wfocu-tab-center">
                    <div class="wfocu-tab-title wfocu-tab-desktop-title additional_information_tab wfocu-active" id="tab-title-additional_information" data-tab="1" role="tab" aria-controls="wfocu-tab-content-additional_information">
						<?php _e( 'Gateways', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    </div>
                    <div class="wfocu-tab-title wfocu-tab-desktop-title description_tab " id="tab-title-description" data-tab="2" role="tab" aria-controls="wfocu-tab-content-description">
						<?php _e( 'Order Statuses', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    </div>
                    <div class="wfocu-tab-title wfocu-tab-desktop-title additional_information_tab" id="tab-title-additional_information" data-tab="3" role="tab" aria-controls="wfocu-tab-content-additional_information">
						<?php _e( 'Confirmation Email', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    </div>
                    <div class="wfocu-tab-title wfocu-tab-desktop-title additional_information_tab" id="tab-title-additional_information" data-tab="4" role="tab" aria-controls="wfocu-tab-content-additional_information">
						<?php _e( 'External Scripts', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    </div>
                    <div class="wfocu-tab-title wfocu-tab-desktop-title additional_information_tab" id="tab-title-additional_information" data-tab="5" role="tab" aria-controls="wfocu-tab-content-additional_information">
						<?php _e( 'Offer Confirmation', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    </div>
                    <div class="wfocu-tab-title wfocu-tab-desktop-title additional_information_tab" id="tab-title-additional_information" data-tab="6" role="tab" aria-controls="wfocu-tab-content-additional_information">
						<?php _e( 'Advance', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    </div>

                </div>

                <div class="wfocu-product-widget-container">
                    <div class="wfocu-product-tabs wfocu-tabs-style-line" role="tablist">

                        <div class="wfocu-product-tabs-content-wrapper">

                            <div class="wfocu_global_setting_inner" id="wfocu_global_setting">


                                <form class="wfocu_forms_wrap wfocu_forms_global_settings ">
                                    <fieldset>
                                        <vue-form-generator :schema="schema" :model="model" :options="formOptions">

                                        </vue-form-generator>
                                    </fieldset>

                                </form>


                                <div style="display: none" id="modal-global-settings_success" data-iziModal-icon="icon-home">


                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="wfocu_form_button">
                        <span class="wfocu_loader_global_save spinner" style="float: left;"></span>
                        <button v-on:click.self="onSubmit" style="float: left;" class="wfocu_save_btn_style"><?php _e( 'Save Changes', 'woofunnels-upstroke-one-click-upsell' ); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="wfocu_page_right_wrap">
			<?php do_action( 'wfocu_page_right_content' ); ?>
        </div>
        <div class="wfocu_clear"></div>
    </div>
    <div style="display: none" class="wfocu-global-settings-help-ofc" data-iziModal-title="<?php echo __( 'Offer Confirmation Help', 'woofunnels-upstroke-one-click-upsell' ) ?>" data-iziModal-icon="icon-home">
        <div class="sections wfocu_img_preview" style="height: 254px;">
            <img src="<?php echo WFOCU_PLUGIN_URL . '/assets/img/global-settings-help-guide-offer.jpg' ?>"/>
        </div>
    </div>
</div>




