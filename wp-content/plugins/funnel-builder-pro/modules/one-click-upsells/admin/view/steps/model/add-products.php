<?php $add_on_exist = WFOCU_Common::is_add_on_exist( 'MultiProduct' );

?>
<div class="wfocu_izimodal_default" id="modal-add-product">
    <div class="sections" >
        <form id="modal-add-product-form" data-wfoaction="add_product" v-on:submit.prevent="onSubmit">
            <input type="hidden" v-model="is_single" value="single">
 <div class="wfocu_vue_forms">
	    <fieldset>
                <div class="form-group ">
		    <div  id="product_search">
			<div class="wfocu_pro_label_wrap wfocu_clearfix">
			  		   <div class="wfocu_select_pro_wrap"> <label><?php _e( 'Select a Product', 'woofunnels-upstroke-one-click-upsell' ) ?></label></div>
			    		    <div class="wfocu_inc_var_wrap">
							<label><input id="wfocu_include_variations" type="checkbox" name="include_variations" class="form-control" v-model="include_variations"><?php echo __( 'Search Variations', 'woofunnels-upstroke-one-click-upsell' ); ?>
				   			</label>
						    </div>
			    		</div>
			<multiselect v-model="selectedProducts" id="ajax" label="product" track-by="product" placeholder="Type to search" open-direction="bottom" :options="products" :multiple="<?php echo ( $add_on_exist ) ? 'true' : 'false'; ?>" :searchable="true" :loading="isLoading" :internal-search="true" :clear-on-select="false" :close-on-select="true" :options-limit="300" :limit="3" :max-height="600" :show-no-results="true" :hide-selected="true" @search-change="asyncFind">
			    <template slot="clear" slot-scope="props">
			    </template>
			    <span slot="noResult"><?php echo __( 'Oops! No elements found. Consider changing the search query.', 'woofunnels-upstroke-one-click-upsell' ); ?></span>
			</multiselect>
			<input type="hidden" name="funnel_id" v-bind:value="funnel_id">
		    </div>
		</div>
	    </fieldset>
         
            <fieldset>
                <div class="wfocu_form_submit wfocu_swl_btn">
                    <input type="hidden" value="<?php echo wp_create_nonce('wfocu_add_product'); ?> " name="_nonce"/>
					<input data-iziModal-close type="button" class="wfocu_btn wf_cancel_btn" value="<?php _e( 'Cancel', 'woofunnels-upstroke-one-click-upsell' ) ?>"/>
					<input type="submit" class="wfocu_btn_primary wfocu_btn" value="<?php _e( 'Add', 'woofunnels-upstroke-one-click-upsell' ) ?>"/>
                </div>
            </fieldset>
     </div>
        </form>
    </div>
</div>