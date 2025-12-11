<?php
/**
 * Single product short description
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/short-description.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  Automattic
 * @package WooCommerce/Templates
 * @version 3.3.0
 */

if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
/**
 * @var $wfacp_product WC_Product_Variation;
 */

global $post;

global $wfacp_product, $wfacp_post;
if ( is_null( $wfacp_product ) ) {
	$short_description = apply_filters( 'woocommerce_short_description', $post->post_excerpt );

} else {
	$short_description = $wfacp_product->get_description();
	if ( '' == $short_description ) {
		$short_description = apply_filters( 'woocommerce_short_description', $post->post_excerpt );
	}
}

if ( ! $short_description ) {
	return;
}

$desc=__('Description','woocommerce');

?>



<div class="woocommerce-product-details__short-description">

	<?php
    if($short_description!=''){echo "<label class='description_label_head'>$desc</label>";}
    echo $short_description; // WPCS: XSS ok.
     ?>
</div>
