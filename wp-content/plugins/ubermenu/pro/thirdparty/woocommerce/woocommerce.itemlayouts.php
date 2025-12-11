<?php


/**
 * LAYOUTS
 */

/* Add custom layout as an available menu item setting */
add_filter( 'ubermenu_item_layout_ops' , 'ubermenu_woocommerce_layout_ops' );
function ubermenu_woocommerce_layout_ops( $ops ){

  if( !ubermenu_woocommerce_is_active() ) return $ops;

  $admin_img_assets = UBERMENU_URL . 'admin/assets/images/';

  $ops['woocommerce'] = array(
  	'group_title' => __( 'WooCommerce Layouts', 'ubermenu' ),

  	'woocommerce_image_above_price' => array(
  		'name' => __( 'Product - Image Above, Price', 'ubermenu' ),
      'img'	=> $admin_img_assets.'ItemLayout_WCImageAbove.jpg',
  	),

    'woocommerce_image_left_price' => array(
  		'name' => __( 'Product - Image Left, Price', 'ubermenu' ),
      'img'	=> $admin_img_assets.'ItemLayout_WCImageLeft.jpg',
      'desc' => __( 'Don\'t forget to set a glocal Image Width or Custom Image Width', 'ubermenu' )
  	),
  );

  return $ops;
}

/* Define the layout components */
add_filter( 'ubermenu_item_layouts' , 'ubermenu_woocomerce_item_layouts' );
function ubermenu_woocomerce_item_layouts( $layouts ){

  if( !ubermenu_woocommerce_is_active() ) return $layouts;

  //Product - Image Above, Price
  $layouts['woocommerce_image_above_price'] = array(
    'order'  => array(
      'image',
      'title',
      'woo_price',
      'description',
      'woo_sale_badge',
    ),
  );

  //Product - Image Left, Price
  $layouts['woocommerce_image_left_price'] = array(
    'order'  => array(
      'image',
      'title',
      'woo_price',
      'description',
      'woo_sale_badge',
    ),
  );


  return $layouts;
}


/* Add custom content */
add_filter( 'ubermenu_custom_item_layout_data' , 'ubermenu_woocommerce_item_layout_filter' , 10 , 4 );
/**
* Filter the array of content parameters to inject any custom content you desire
* @param  array  $custom_pieces  An array indexed with layout elements, which you should add to
* @param  string $layout         The ID of the layout currently in use
* @param  int    $item_id        The ID of the menu item being processed
* @param  int    $object_id      The ID of the associated content (such as Post ID or Term ID)
* @return array                  You must return the filtered $custom_pieces array
*/
function ubermenu_woocommerce_item_layout_filter( $custom_pieces , $layout , $item_id , $object_id ){

  if( !ubermenu_woocommerce_is_active() ) return $custom_pieces;

  //Custom Data for Product - Price Layout
  if( $layout === 'woocommerce_image_above_price' ||
      $layout === 'woocommerce_image_left_price' ){

    if( function_exists( 'wc_get_product' ) ){
      $product = wc_get_product( $object_id );

      if( $product ){
        $custom_pieces['woo_price'] = '<span class="ubermenu-target-text ubermenu-target-woo-price">'.ubermenu_woocommerce_product_price($product).'</span>';
        $custom_pieces['woo_sale_badge'] = ubermenu_woocommerce_sale_badge( $product );
      }
    }
  }

  return $custom_pieces;
}
