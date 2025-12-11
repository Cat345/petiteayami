<?php
/**
 * @var $wc_product WC_Product;
 * @var $product_key
 */
?>
<div class="wfob_thickbox_product_image" data-model='<?php echo 'product_' . $product_key . '_featured_image_options' ?>' v-if='model.<?php echo 'product_' . $product_key . '_featured_image' ?>'>
    <div style="clear: both"></div>
    <div class="wfob_product_image_type">
        <label>
            <input type='radio' value='product' v-model='model.<?php echo 'product_' . $product_key . '_featured_image_options' ?>.type'>
            <span>Product</span>
        </label>
        <label>
            <input type='radio' value='custom' v-model='model.<?php echo 'product_' . $product_key . '_featured_image_options' ?>.type'>
            <span>Custom</span>
        </label>
    </div>
    <div style="clear: both"></div>
    <div class="wfob_thickbox_product_image_options">
        <div class="wfob_image_upload_left">

            <div v-if="model.product_<?php echo $product_key ?>_featured_image_options.type=='product'">
                <img v-bind:src="model.product_<?php echo $product_key ?>_featured_image_options.image_url" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail">
            </div>
            <div v-if="model.product_<?php echo $product_key ?>_featured_image_options.type=='custom'">

                <div v-if="''!==model.product_<?php echo $product_key ?>_featured_image_options.custom_url">
                    <div class="wfob_editor_image_container">
                        <img v-bind:src="model.product_<?php echo $product_key ?>_featured_image_options.custom_url" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail">
                    </div>
                    <div class="wfob_editor_image_container_actions">
                        <button type="button" class="wfob_image_upload_delete_image" v-on:click="model.product_<?php echo $product_key ?>_featured_image_options.custom_url=''"><?php echo file_get_contents( plugin_dir_path( WFOB_PLUGIN_FILE ) . 'admin/assets/img/icons/delete.svg' ) ?></button>
                        <button type="button" class="wfob_image_upload_add_image"><?php echo file_get_contents( plugin_dir_path( WFOB_PLUGIN_FILE ) . 'admin/assets/img/icons/edit.svg' ) ?></button>
                    </div>
                </div>
                <div v-else>
                    <div class="wfob_image_upload_add_image_container" style='padding:15px;cursor: pointer;'>
						<?php include __DIR__ . '/no-image.php' ?>
                        <button type="button" class="wfob_image_upload_add_image" style="pointer-events:none"><?php _e( 'Add Images', 'woofunnels-order-bump' ) ?></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="wfob_image_product_image_options">
            <div class="wfob_image_option_width">
                <label><?php _e( 'Width' ) ?> (px)</label>
                <input type="number" v-model='model.<?php echo 'product_' . $product_key . '_featured_image_options' ?>.width'>
            </div>
            <div class="wfob_image_option_image_position">
                <label><?php _e( 'Image Position' ) ?></label>
                <select v-model='model.<?php echo 'product_' . $product_key . '_featured_image_options' ?>.position'>
                    <option v-for="(l,i) in wfob_data.design.layout_position[model.layout]" v-bind:value="i">{{l}}</option>
                </select>
            </div>
        </div>
    </div>
</div>
