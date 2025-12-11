<?php
/**
 * Created by PhpStorm.
 * User: sandeep
 * Date: 2/28/19
 * Time: 5:04 PM
 */ ?>
<div id="wfacp_wysiwyg">
    <fieldset>
        <!---->
        <div class="form-group required field-input">
            <label for="label">{{wfacp_localization.fields.label_field_label}}</label>
            <div class="field-wrap">
                <div class="wrapper">
                    <input id="label" type="text" v-model="model.label" required="required" class="form-control">
                    <small><strong><i><?php _e( 'This label is for admin purpose only, won\'t appear on the checkout form.', 'woofunnels-aero-checkout' ) ?></i></strong></small>
                </div>
            </div>
        </div>
        <!---->
        <!---->
        <div class="form-group field-input">
            <label for="default"><?php __( 'Content', 'woofunnels-aero-checkout' ) ?></label>
            <div class="field-wrap">
                <div class="wrapper">
                    <textarea id="wfacp_wysiwyg_editor" type="text" class="form-control" v-model="model.default"></textarea>
                </div>
            </div>
        </div>
    </fieldset>
</div>