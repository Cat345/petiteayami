<div class="wfocu_fsetting_table_head">
    <div class="wfocu_fsetting_table_head_in wfocu_clearfix">
        <div class="wfocu_fsetting_table_title">
            <div class="offer_state wfocu_toggle_btn" v-if="Object.keys(products).length>0">
                <input v-model="offer_state" name="offer_state" class="wfocu-tgl wfocu-tgl-ios" v-bind:id="'state'+current_offer_id" type="checkbox" v-on:change="update_offer_state($event)">
                <label class="wfocu-tgl-btn wfocu-tgl-btn-small" v-bind:for="'state'+current_offer_id"></label>
            </div>

			{{current_offer}}
            <a href="javacript:void()" data-izimodal-open="#modal-update-offer" data-iziModal-title="<?php _e( 'Update Offer', 'woofunnels-upstroke-one-click-upsell' ); ?>" data-izimodal-transitionin="fadeInUp">
                <?php echo file_get_contents(  plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'admin/assets/img/icons/edit.svg'  ) ?>
            </a>
            <span class="wfocu_offer_type">{{offer_type}}</span>
            
        </div>
        <div class="wfocu_offer_action">
            <a href="javascript:void(0)" v-on:click="delete_offer" class="wfocu_del_offer_style wfocu_alert_btn wfocu_left"><?php _e( 'Remove', 'woofunnels-upstroke-one-click-upsell' ) ?></a>
            <div class="offer_save_buttons wfocu_form_submit" v-if="Object.keys(products).length>0">
                <input type="submit" value="Save Changes" name="submit" class="wfocu_save_btn_style" v-bind:data-offer_id="current_offer_id" id="wfocu_offer_saving_btn"/>
            </div>
        </div>
    </div>
</div>