<?php
defined( 'ABSPATH' ) || exit;
?>
<!-- add product modal start-->
<div class="wfob_izimodal_default" id="modal-add-product">
    <div class="sections">
        <form id="modal-add-product-form" data-wfoaction="add_product" v-on:submit.prevent="onSubmit">
            <div class="wfob_vue_forms">
                <fieldset>
                    <div class="form-group ">
                        <div id="product_search">
                            <div class="wfob_pro_label_wrap wfob_clearfix">
                                <div class="wfob_select_pro_wrap"><label><?php _e( 'Select Product', 'woofunnels-order-bump' ); ?></label></div>

                            </div>
                            <multiselect v-model="selectedProducts" id="ajax" label="product" track-by="product" placeholder="Type to search" open-direction="bottom" :options="products" :multiple="<?php echo( 'true' ); ?>" :searchable="true" :loading="isLoading" :internal-search="true" :clear-on-select="false" :close-on-select="true" :options-limit="300" :limit="3" :max-height="600" :show-no-results="true" :hide-selected="true" @search-change="asyncFind">
                                <template slot="clear" slot-scope="props">
                                </template>
                                <span slot="noResult"><?php echo __( 'Oops! No elements found. Consider changing the search query.', 'woofunnels-order-bump' ); ?></span>
                            </multiselect>
                        </div>
                    </div>
                </fieldset>
                <fieldset>
                    <div class="wfob_form_submit wfob_swl_btn">
						<button data-iziModal-close type="button" class="wf_cancel_btn wfob_btn" value="cancel"><?php esc_html_e( 'Cancel', 'woofunnels-order-bump' ); ?></button>
						<input type="submit" class="wfob_btn wfob_btn_primary" value="<?php esc_html_e( 'Add Product', 'woofunnels-order-bump' ); ?>"/>
                    </div>
                </fieldset>
            </div>
        </form>
    </div>
</div>
<!-- add product modal end-->
