<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


?>

<div class="wfob_bump_r_outer_wrap wfob_layout_5" v-if="selected_layout=='layout_5'">
    <div v-bind:class="get_wrapper_class('<?php echo $product_key ?>','wfob_wrapper wfob_bump wfob_clear')">
        <div class="wfob_outer">
            <div class="wfob_Box wfob_skin_wrap">
                <div class="wfob_contentBox wfob_clear">


                    <div class="wfob_pro_img_wrap" v-if="true==enable_featured_image('<?php echo $product_key ?>') && 'left'==get_featured_image_position('<?php echo $product_key ?>')" v-bind:style="get_image_width('<?php echo $product_key ?>')">
						<?php include __DIR__ . '/skin-product-image.php' ?>
                    </div>
                    <div class="wfob_pro_txt_wrap">
                        <div class="wfob_l3_l_desc" v-html="<?php echo 'model.product_' . $product_key . '_description'; ?>"></div>

                        <div class="wfob_bgBox_tablecell wfob_price_container" v-if="model.enable_price=='1'">
                            <div class="wfob_price">
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
                        </div>

                        <div class="wfob_bgBox_table">
                            <div class="wfob_bgBox_tablecell wfob_check_container">

                                <div v-bind:class="header_enable_pointing_arrow('wfob_check_wrap')">
                                        <span v-if="model.header_enable_pointing_arrow" class="wfob_checkbox_blick_image_container wfob_bk_blink_wrap">
									<span v-if="model.point_animation=='1'" class="wfob_checkbox_blick_image"><img src="<?php echo WFOB_PLUGIN_URL; ?>/assets/img/arrow-blink.gif"></span>
									<span v-if="model.point_animation=='0'" class="wfob_checkbox_blick_image"><img src="<?php echo WFOB_PLUGIN_URL; ?>/assets/img/arrow-no-blink.gif"></span>
								</span>
                                       <input type="checkbox" id="<?php echo $product_key; ?>" data-value="" class="wfob_checkbox wfob_bump_product">

                                   </div>
                                <div class="wfob_content_sec">
                                    <label for="<?php echo $product_key; ?>" class="wfob_title" v-html="<?php echo 'model.product_' . $product_key . '_title'; ?>"></label>
                                </div>


                            </div>


                        </div>
						<?php
						if ( isset( $data['variable'] ) ) {
							printf( "<a href='#' class='wfob_qv-button var_product' qv-id='%d' qv-var-id='%d'>%s</a>", 0, 0, __( 'Choose an option', 'woocommerce' ) );
						}
						?>

                    </div>

                    <div class="wfob_pro_img_wrap" v-if="true==enable_featured_image('<?php echo $product_key ?>') && 'right'==get_featured_image_position('<?php echo $product_key ?>')" v-bind:style="get_image_width('<?php echo $product_key ?>')">

						<?php include __DIR__ . '/skin-product-image.php' ?>

                    </div>


                </div>
            </div>
        </div>
    </div>
</div>
