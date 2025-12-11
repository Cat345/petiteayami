<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wfacp_product_list_wrap wfacp_bg_table">
    <div class="product_list">
        <table class="product_section_table wfacp_product_">
            <thead class="listing_table_head">
            <tr>
                <th class="product_th" colspan="2"><?php _e( 'Name', 'woocommerce' ); ?></th>
                <th><?php _e( 'Type', 'woocommerce' ); ?></th>
                <th><?php _e( 'Discount', 'woocommerce' ); ?></th>
                <th><?php _e( 'Quantity', 'woocommerce' ); ?></th>
                <th><?php _e( 'Action', 'woocommerce' ); ?></th>

            </tr>
            </thead>
            <tbody class="tb_body wfacp_product_table_sortable">
            <tr v-for="(product, index) in products" v-bind:id="index" v-bind:data-proid="index" class="wfacp-product-row">

                <td class="wfacp_product_drag_handle">
                    <span class="wfacp_drag_handle dashicons dashicons-menu"></span>
                </td>
                <td>

                    <div class="wfacp_overlay_active wfacp_overlay_none_here"></div>
                    <input type="hidden" v-bind:name="'wfacp_product['+index+'][id]'" v-bind:value="product.id" v-model='product.id' style="width: 51px;">
                    <div class="product_image">
                        <img v-bind:src="product.image" style="max-width: 100%">
                    </div>
                    <div class="product_details">

                        <div class="product_name">
                            <span class="wfacp_product_title">{{product.title}}</span>
                        </div>
                        <div class="product_options">
                            <div v-if="product.type=='variable' || wfacp.tools.hp(product,'variable')">
								<?php _e( 'Price', 'woofunnels-aero-checkout' ); ?>:
                                <p v-html="product.price"></p>
                            </div>
                            <div v-if="product.type!=='variable'">
                                <div v-if="typeof product.regular_price!='undefined' && product.regular_price!=''">
									<?php _e( ' Regular Price', 'woofunnels-aero-checkout' ); ?>:
                                    <p v-html="product.regular_price"></p>
                                </div>
                                <div v-if="typeof product.sale_price!='undefined' && product.sale_price!=''">
									<?php _e( 'Sale Price', 'woofunnels-aero-checkout' ); ?>:
                                    <p v-html="product.sale_price"></p>
                                </div>
                            </div>
                            <div v-if="typeof product.stock!=='undefined' && product.stock==false" style="color:red">
								<?php _e( 'Status', 'woofunnels-order-bump' ); ?>:
                                <p v-if="product.stock==false"><?php _e( 'Out of Stock', 'woofunnels-order-bump' ); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="clear"></div>
                </td>
                <td>
                    <div class="wfacp_overlay_active wfacp_overlay_none_here"></div>
                    <div class="product_type">
                        <p>{{product.type}}</p>
                    </div>
                </td>
                <td>
                    <div class="wfacp_overlay_active wfacp_overlay_none_here"></div>
                    <input type="number" step="0.01" min="0" v-bind:name="'wfacp_product['+index+'][discount_amount]'" v-bind:value="!product.discount_amount?0:product.discount_amount" v-model='product.discount_amount' class="discount_number" @input="set_product_data(index,'discount_amount',$event)" oninput="setTimeout(function(){this.value = Math.abs(this.value)},2000)" style="width: 60px;">
                    <select v-model="product.discount_type" v-bind:name="'wfacp_product['+index+'][discount_type]'" v-on:change="set_product_data(index,'discount_type',$event)">
                        <option value="percent_discount_sale"><?php _e( 'Percentage % on Sale Price', 'woofunnels-aero-checkout' ); ?></option>
                        <option value="fixed_discount_sale"><?php _e( 'Fixed Amount on Sale Price', 'woofunnels-aero-checkout' ); ?></php></option>
                        <option value="percent_discount_reg"><?php _e( 'Percentage % on Regular Price', 'woofunnels-aero-checkout' ); ?></option>
                        <option value="fixed_discount_reg"><?php _e( 'Fixed Amount on Regular Price', 'woofunnels-aero-checkout' ); ?></option>
                    </select>


                </td>
                <td class="wfacp_product_input_type">
                    <div class="wfacp_overlay_active wfacp_overlay_none_here"></div>
                    <div v-if="product.is_sold_individually==true">
						<?php _e( 'Sold individually', 'woocommerce' ); ?>
                    </div>
                    <div v-else>
                        <input type="number" v-model="product.quantity" min="1" v-bind:value="!product.quantity?1:product.quantity" v-bind:name="'wfacp_product['+index+'][discount_quantity]'" v-model='product.discount_quantity' @input="set_product_data(index,'discount_quantity',$event)" oninput="this.value = Math.abs(this.value)">
                    </div>
                </td>
                <td>
                    <div class="wfacp_overlay_active wfacp_overlay_none_here"></div>
                    <button type="button" class="wfacp_form_remove_product" v-on:click="removeProduct(index)">
                        <?php

                        include plugin_dir_path( WFACP_PLUGIN_FILE ) . 'admin/assets/img/icons/delete.svg';
                        ?>
                    </button>

                </td>

            </tr>
            </tbody>
        </table>
    </div>
    <div class="product_add_new" v-if="!isEmpty()">
        <div class="wfacp_steps">
            <div class="wfacp_step wfacp_modal_open wfacp_btn wfacp_btn_primary" data-izimodal-open="#modal-add-product">
                <?php _e( 'Add Product', 'woofunnels-aero-checkout' ); ?>
            </div>
        </div>
    </div>
    <div style="clear: both;"></div>
</div>
