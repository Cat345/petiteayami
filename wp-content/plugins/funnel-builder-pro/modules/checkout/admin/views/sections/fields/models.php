<?php
defined( 'ABSPATH' ) || exit;
?>
<!-- add Field modal start-->
<div class="wfacp_izimodal_default" id="modal-add-field">
    <div class="sections">
        <form id="add-field-form" data-bwf-action="add_field" v-on:submit.prevent="onSubmit">
            <div class="wfacp_vue_forms">
                <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
                <fieldset>
                    <div class="bwf_form_submit wfacp_swl_btn">
                        <button type="button" data-izimodal-close="" value="cancel" class="wf_cancel_btn wfacp_btn">Cancel</button>
                        <input type="submit" class="wfacp_btn wfacp_btn_primary" value="<?php _e( 'Add Field', 'woofunnels-aero-checkout' ); ?>"/>
                    </div>
                </fieldset>
            </div>
        </form>
    </div>
</div>

<div class="wfacp_izimodal_default iziModal " id="modal-edit-field" aria-hidden="false" aria-labelledby="modal-edit-field" role="dialog" style="background: rgb(239, 239, 239); z-index: 999; border-radius: 8px; overflow: hidden; max-width: 640px;min-height:350px;">
    <div id="edit-field-form" class="wfacp_product_swicther_field_wrap">
        <div class="iziModal-header iziModal-noSubtitle" style="background: rgb(109, 190, 69); padding-right: 40px;">
            <h2 class="iziModal-header-title"><?php _e( 'Edit field', 'woofunnels-aero-checkout' ); ?></h2>
            <div class="iziModal-header-buttons">
                <a href="javascript:void(0)" class="iziModal-button iziModal-button-close has-svg" data-izimodal-close="">
                    <svg fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16">
                        <path d="M9.46702 7.99987L15.6972 1.76948C16.1027 1.36422 16.1027 0.708964 15.6972 0.303702C15.292 -0.10156 14.6367 -0.10156 14.2315 0.303702L8.00106 6.5341L1.77084 0.303702C1.36539 -0.10156 0.710327 -0.10156 0.305065 0.303702C-0.100386 0.708964 -0.100386 1.36422 0.305065 1.76948L6.53528 7.99987L0.305065 14.2303C-0.100386 14.6355 -0.100386 15.2908 0.305065 15.696C0.507032 15.8982 0.772588 15.9998 1.03795 15.9998C1.30332 15.9998 1.56869 15.8982 1.77084 15.696L8.00106 9.46565L14.2315 15.696C14.4336 15.8982 14.699 15.9998 14.9643 15.9998C15.2297 15.9998 15.4951 15.8982 15.6972 15.696C16.1027 15.2908 16.1027 14.6355 15.6972 14.2303L9.46702 7.99987Z" fill="#353030"></path>
                    </svg>
                </a>
            </div>
        </div>
        <div class="iziModal-wrap" style="min-height: 390px;">
            <div class="iziModal-content" style="padding: 0px;">
                <div class="sections">
                    <form data-bwf-action="add_field" data-bwf-action="add_field" v-on:submit.prevent="onSubmit">
                        <div class="wfacp_vue_forms">
                            <div class="wfacp_without_form_generator " v-if="current_field_id=='product_switching'">
								<?php include __DIR__ . '/product_switcher.php'; ?>
                            </div>
                            <div class="wfacp_without_form_generator " v-else-if="current_field_id=='address'">
								<?php
								$this->get_address_field_html( 'billing' );
								?>
                            </div>
                            <div class="wfacp_without_form_generator " v-else-if="current_field_id=='shipping-address'">
								<?php $this->get_address_field_html( 'shipping' ); ?>
                            </div>
                            <div class="wfacp_without_form_generator " v-else-if="model.field_type=='wfacp_wysiwyg'">
								<?php include __DIR__ . '/html_field.php'; ?>
                            </div>
							<?php do_action( 'wfacp_edit_field_model', $this ); ?>
                            <div class="" v-else="">
                                <div class="wfacp_edit_field_wrap">
                                    <p class="subtitle_wrap" v-if="''!==model_sub_title">{{edit_model_field_label}}<span>{{model_sub_title}}</span></p>
                                </div>
                                <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
                            </div>
                            <fieldset>
                                <div class="bwf_form_submit wfacp_swl_btn" v-if="!(current_field_id=='product_switching'&& wfacp.tools.ol(wfacp_data.products)==0)">
                                    <input data-izimodal-close="" type="button" class="iziModal-button-close wf_cancel_btn wfacp_btn" value="<?php esc_html_e( 'Cancel', 'woofunnels-aero-checkout' ); ?>"/>
                                    <input type="submit" class="wfacp_btn wfacp_btn_primary wfacp_update_field_btn" value="<?php _e( 'Update', 'woofunnels-aero-checkout' ); ?>"/>
                                </div>
                            </fieldset>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="wfacp_overlay"></div>

<!-- edit Field modal end-->

<!-- add product modal start-->
<div class="wfacp_izimodal_default" id="modal-add-section">
    <div class="sections">
        <form id="add-section-form" data-bwf-action="add_section" v-on:submit.prevent="onSubmit" v-on:keyup.enter="onSubmit">
            <div class="wfacp_vue_forms">
                <vue-form-generator :schema="schema" :model="model" :options="formOptions"></vue-form-generator>
            </div>

            <fieldset>
                <div class="bwf_form_submit wfacp_swl_btn">
                    <button type="button" data-iziModal-close class="wf_cancel_btn wfacp_btn" value="cancel"><?php esc_html_e( 'Cancel', 'woofunnels-aero-checkout' ); ?></button>
                    <button type="submit" class="wfacp_btn wfacp_btn_primary">{{btn_name}}</button>
                </div>
            </fieldset>

        </form>
    </div>

</div>
<!-- add product modal end-->
