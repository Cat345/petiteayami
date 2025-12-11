<?php
/**
 * @var $wc_product WC_Product;
 * @var $product_key
 */
?>
<div class="wfob_bump_r_outer_wrap wfob_layout_3" v-if="selected_layout=='layout_3' || selected_layout=='layout_4'" data-product-key="<?php echo $product_key ?>">
    <div v-bind:class="get_wrapper_class('<?php echo $product_key?>','wfob_l3_wrap')">
        <div v-bind:class="get_the_image_cls('<?php echo $product_key ?>')">

            <div class="wfob_l3_s_img wfacp_product_image" v-if="true==enable_featured_image('<?php echo $product_key ?>') && 'left'==get_featured_image_position('<?php echo $product_key ?>')" v-bind:style="get_image_flex_width('<?php echo $product_key ?>')">
		        <?php include __DIR__ . '/skin-product-image.php' ?>
            </div>

            <div class="wfob_l3_s_c" v-bind:style="get_image_flex_width_content('<?php echo $product_key ?>')">
                <div class="wfob_l3_s_data">
                    <div class="wfob_l3_c_head" v-html="get_data('title','<?php echo $product_key ?>')"></div>
                    <div class="wfob_l3_c_sub_head" v-if="<?php echo 'model.product_' . $product_key . '_sub_title'; ?>!=''" v-html="get_data('sub_title','<?php echo $product_key ?>')"></div>
                    <div class="wfob_l3_c_sub_desc show-read-more" v-if="<?php echo 'model.product_' . $product_key . '_small_description'; ?>!=''" v-html="get_data('small_description','<?php echo $product_key ?>')"></div>
                    <div class="wfob_l3_c_sub_desc_choose_option">
						<?php
						if ( isset( $data['variable'] ) ) {
							printf( "<a href='#' class='wfob_qv-button var_product' qv-id='%d' qv-var-id='%d'>%s</a>", 0, 0, __( 'Choose an option', 'woocommerce' ) );
						}
						?>
                    </div>
                </div>
                <div class="wfob_l3_s_btn">
                    <div class="wfob_price" v-if="1==model.enable_price">
						<?php

						if ( in_array( $wc_product->get_type(), WFOB_Common::get_subscription_product_type() ) ) {
							$subs_price = WFOB_Common::get_subscription_price( $wc_product, $price_data );
							echo wc_price( $subs_price );
						} else {
							if ( ( round( $price_data['price'], 2 ) !== round( $price_data['regular_org'], 2 ) ) ) {
								echo wc_format_sale_price( $price_data['regular_org'], $price_data['price'] );
							} else {
								echo wc_price( $price_data['price'] );
							}
						}

						?>
                    </div>
                    <a href="#" class="wfob_l3_f_btn wfob_btn_add" data-key="<?php echo $product_key ?>" v-html="add_button_text('<?php echo $product_key; ?>')"></a>
                    <a href="#" class="wfob_l3_f_btn wfob_btn_remove" data-key="<?php echo $product_key ?>">
                        <span class="wfob_btn_text_added" v-html="added_button_text('<?php echo $product_key; ?>')"></span>
                        <span class="wfob_btn_text_remove" v-html="remove_button_text('<?php echo $product_key; ?>')"></span>
                    </a>
                </div>
                <div class="wfob_clearfix"></div>
            </div>

            <div class="wfob_l3_s_img wfacp_product_image" v-if="true==enable_featured_image('<?php echo $product_key ?>') && 'right'==get_featured_image_position('<?php echo $product_key ?>')" v-bind:style="get_image_flex_width('<?php echo $product_key ?>')">
		        <?php include __DIR__ . '/skin-product-image.php' ?>
            </div>
            <div class="wfob_clearfix"></div>
        </div>
        <div class="wfob_l3_s_desc" v-if="<?php echo 'model.product_' . $product_key . '_description'; ?>!=''">
            <div class="wfob_l3_l_desc" v-html="get_data('description','<?php echo $product_key ?>')"></div>
        </div>
    </div>
</div>
