<?php
/**
 * @var $featured_image ;
 * @var $wc_product WC_Product
 * @var $design_data
 * @var $product_key
 * @var $parent_product WC_Product_Variable
 */


if ( isset( $design_data[ "product_" . $product_key . "_featured_image_options" ] ) ) {
	$image_options = $design_data[ "product_" . $product_key . "_featured_image_options" ];
	if ( 'custom' == $image_options['type'] ) {
		if ( ! empty( $image_options['custom_url'] ) ) {
			echo "<img src='{$image_options['custom_url']}' class='attachment-woocommerce_thumbnail size-woocommerce_thumbnail'>";
		} else {
			include WFOB_PLUGIN_DIR . '/assets/img/no-image.php';
		}

		return;
	}
}
if ( isset( $data['variable'] ) && 'yes' == $data['variable'] && empty( $cart_item_key ) ) {
	$image_url = WFOB_Common::get_product_image( $parent_product, $data );
	echo "<img src='{$image_url}' class='attachment-woocommerce_thumbnail size-woocommerce_thumbnail'>";
} else {
	$img_src = $wc_product->get_image();
	echo $img_src;
}




