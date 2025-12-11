<?php
/**
 * @var $wc_product WC_Product;
 * @var $product_key
 */
?>
<div class="wfob_feature_image" v-if="model.product_<?php echo $product_key ?>_featured_image_options.type=='product'">
	<?php
	echo $wc_product->get_image();
	?>
</div>
<div class="wfob_feature_image" v-if="model.product_<?php echo $product_key ?>_featured_image_options.type=='custom'">

    <div v-if="model.product_<?php echo $product_key ?>_featured_image_options.custom_url!=''">
        <img v-bind:src="model.product_<?php echo $product_key ?>_featured_image_options.custom_url" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail">
    </div>
    <div v-if="model.product_<?php echo $product_key ?>_featured_image_options.custom_url==''">
		<?php include WFOB_PLUGIN_DIR . '/assets/img/no-image.php'; ?>
    </div>
</div>
