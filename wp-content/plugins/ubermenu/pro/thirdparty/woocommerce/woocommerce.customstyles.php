<?php

/*
 * WOOCOMMERCE PRICE COLOR
 */
function ubermenu_get_menu_style_wc_price_color( $field , $menu_id , &$menu_styles ){

	$val = ubermenu_op( $field['name'] , $menu_id );
  if( $val ){
		$selector = ".ubermenu-$menu_id .ubermenu-target .ubermenu-target-woo-price,".
		            ".ubermenu-$menu_id .ubermenu-target .ubermenu-target-woo-price .woocommerce-Price-amount";
		$menu_styles[$selector]['color'] = $val;
	}
}


function ubermenu_get_menu_style_wc_sale_badge_color( $field , $menu_id , &$menu_styles ){

	$val = ubermenu_op( $field['name'] , $menu_id );
  if( $val ){
		$selector = ".ubermenu-$menu_id .ubermenu-item .ubermenu-target-woo-sale-badge";
		$menu_styles[$selector]['color'] = $val;
	}
}

function ubermenu_get_menu_style_wc_sale_badge_background_color( $field , $menu_id , &$menu_styles ){

	$val = ubermenu_op( $field['name'] , $menu_id );
  if( $val ){
		$selector = ".ubermenu-$menu_id .ubermenu-item .ubermenu-target-woo-sale-badge";
		$menu_styles[$selector]['background'] = $val;
	}
}

function ubermenu_get_menu_style_wc_sale_badge_text_transform( $field , $menu_id , &$menu_styles ){

	$val = ubermenu_op( $field['name'] , $menu_id );
  if( $val ){
		$selector = ".ubermenu-$menu_id .ubermenu-item .ubermenu-target-woo-sale-badge";
		$menu_styles[$selector]['text-transform'] = $val;
	}
}

function ubermenu_get_menu_style_wc_sale_badge_font_weight( $field , $menu_id , &$menu_styles ){

	$val = ubermenu_op( $field['name'] , $menu_id );
  if( $val ){
		$selector = ".ubermenu-$menu_id .ubermenu-item .ubermenu-target-woo-sale-badge";
		$menu_styles[$selector]['font-weight'] = $val;
	}
}
