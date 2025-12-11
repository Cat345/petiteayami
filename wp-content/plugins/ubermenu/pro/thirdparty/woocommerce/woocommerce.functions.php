<?php

// Allow cart widget to display on cart and checkout pages
add_filter( 'wp', 'ubermenu_woocommerce_allow_cart_widget' );
function ubermenu_woocommerce_allow_cart_widget(){
  if( ubermenu_op( 'wc_allow_cart_widget', 'general' ) === 'off' ) return;
  add_filter( 'woocommerce_widget_cart_is_hidden', '__return_false', 40, 0 );
}

function ubermenu_woocommerce_is_active(){
  if( !function_exists( 'WC' ) ) return false;
  return true;
}

function ubermenu_woocommerce_product_price( $product ){

  //wc_get_price_to_display?

  $display = ubermenu_op( 'wc_price_display', 'general' );
  switch( $display ){

    case 'current':
      $price = wc_price( $product->get_price() ); // Just the current price
      break;

    case 'html':
      $price = $product->get_price_html(); // Price showing main and sale
      break;

    default:
      $price = $product->get_price_html(); // Price showing main and sale

  }

  return apply_filters( 'ubermenu_woocommerce_product_price', $price );

}

function ubermenu_woocommerce_sale_badge( $product ){
  if( ubermenu_op( 'wc_show_sale_badge', 'general' ) === 'off' ) return;
  if( !$product->is_on_sale() ) return '';
  $content = apply_filters( 'ubermenu_woocommerce_sale_badge_content', __( 'Sale!', 'ubermenu' ) );
  return '<span class="ubermenu-target-woo-sale-badge">'.$content.'</span>';
}

function ubermenu_get_woocommerce_product_category_image_id( $product_cat_id ){

  if( ubermenu_op( 'wc_category_images', 'general' ) === 'off' ) return false;

  return get_term_meta( $product_cat_id, 'thumbnail_id', true );
}
