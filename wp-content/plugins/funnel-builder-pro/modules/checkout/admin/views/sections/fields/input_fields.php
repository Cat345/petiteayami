<?php
defined( 'ABSPATH' ) || exit;
?>
<div v-for="(fields,section) in available_fields" class="wfacp_input_fields" v-if="wfacp.tools.ol(fields)>0">
    <div class="wfacp_input_fields_list_wrap">
        <div class="wfacp_input_fields_title" v-if="section=='billing'"><b><?php _e( 'Basic ', 'woofunnels-aero-checkout' ); ?></b></div>
        <div class="wfacp_input_fields_title" v-if="section!='billing'"><b>{{section}} </b></div>
        <hr/>
        <div class="wfacp_input_fields_list" v-bind:id="'input_field_'+section+'_container'">
            <div class="wfacp_input_field_btn_holder" v-for="(data,index) in fields">
                <div v-if="true==wfacp.tools.hp(input_fields[section],index)" v-bind:id="index" class="wfacp_save_btn_style wfacp_item_drag" v-bind:data-input-section="section" draggable="true" v-on:dragstart="dragStart($event)" v-on:dragend="dragEnd($event)">
                    <span class="dashicons dashicons-no-alt" v-on:click="deleteCustomField(section,index,data.label)" v-if="data.is_wfacp_field"></span>
                    <span>{{''!==data.label?data.label:data.data_label}}</span>
                </div>
                <div v-if="false==wfacp.tools.hp(input_fields[section],index)" class="wfacp_save_btn_style wfacp_input_field_place_holder">
                    <span>{{''!==data.label?data.label:data.data_label}}</span>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="wfacp_input_fields_btn">
    <button class="button" v-on:click="addField()"><?php _e( 'Add New Field', 'woofunnels-aero-checkout' ); ?></button>
</div>