<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}

// Note: `wc_get_gallery_image_html` was added in WC 3.3.2 and did not exist prior. This check protects against theme overrides being used on older versions of WC.
if ( ! function_exists( 'wc_get_gallery_image_html' ) ) {
	return;
}

global $product, $wfacp_product;

$columns           = apply_filters( 'woocommerce_product_thumbnails_columns', 4 );
$post_thumbnail_id = $product->get_image_id();
if ( ! is_null( $wfacp_product ) ) {
	$post_thumbnail_id = $wfacp_product->get_image_id();
	$post_thumbnail_id = absint( $post_thumbnail_id );
}


$wrapper_classes = apply_filters( 'woocommerce_single_product_image_gallery_classes', array(
	'woocommerce-product-gallery',
	'woocommerce-product-gallery--' . ( absint( $post_thumbnail_id ) > 0 ? 'with-images' : 'without-images' ),
	'woocommerce-product-gallery--columns-' . absint( $columns ),
	'images',
) );
include_once __DIR__ . '/sale-flash.php';
?>
<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>" data-columns="<?php echo esc_attr( $columns ); ?>" style="opacity: 0; transition: opacity .25s ease-in-out;">
    <figure class="woocommerce-product-gallery__wrapper">
		<?php

		if ( absint( $post_thumbnail_id ) > 0 ) {
			$html = wc_get_gallery_image_html( $post_thumbnail_id, true );
		} else {
			$html = '<div class="woocommerce-product-gallery__image--placeholder">';
			$html .= sprintf( '<img src="%s" alt="%s" class="wp-post-image" />', esc_url( wc_placeholder_img_src() ), esc_html__( 'Awaiting product image', 'woocommerce' ) );
			$html .= '</div>';
		}

		echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', $html, $post_thumbnail_id );

		include_once __DIR__ . '/product-thumbnails.php';
		?>
    </figure>
</div>

