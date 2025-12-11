<?php

// Add Woo Images panel to Dynamic Terms
add_filter( 'ubermenu_custom_menu_item_types' , 'ubermenu_woocommerce_item_types_filter' , 20, 1 );
function ubermenu_woocommerce_item_types_filter( $items ){
  if( !ubermenu_woocommerce_is_active() ) return $items;

  $dynamic_terms_panels = $items['dynamic_terms']['panels'];
  $dynamic_terms_panels[] = 'woocommerce_dt_image';

  $items['dynamic_terms']['panels'] = $dynamic_terms_panels;

  return $items;
}


// Create Woo Images Panel
add_filter( 'ubermenu_menu_item_settings_panels' , 'ubermenu_woocommerce_category_image_settings_panels' );
function ubermenu_woocommerce_category_image_settings_panels( $panels ){

  if( !ubermenu_woocommerce_is_active() ) return $panels;

  $panels['woocommerce_dt_image'] = array(
		'title'	=> __( 'WC Product Category Image', 'ubermenu' ),
		'icon'	=> 'image',
		'info'	=> __( 'Specifically for WooCommerce Product Categories images.  Will not apply to Terms that are not Product Categories' , 'ubermenu' ),
		'order'	=> 121
	);

  return $panels;

}


// Create Woo Images Settings
add_filter( 'ubermenu_menu_item_settings' , 'ubermenu_woocommerce_category_image_item_settings' );
function ubermenu_woocommerce_category_image_item_settings( $settings ){

  if( !ubermenu_woocommerce_is_active() ) return $settings;

  // $settings['woocommerce_dt_image'][10] = array(
	// 	'id'		=> 'item_image',
	// 	'title'		=> __( 'Image' , 'ubermenu' ),
	// 	'desc'		=> __( 'Click "Select" to upload or choose a new image.  Click "Remove" to remove the image.  Click "Edit" to edit the currently selected image. For Dynamic Posts, this image is the optional fallback for when a Post does not have a featured image.' , 'ubermenu' ),
	// 	'type'		=> 'media',
	// 	'default'	=> '',
  //
	// );

	$settings['woocommerce_dt_image'][15] = array(
		'id'		=> 'inherit_featured_image',
		'title'		=> __( 'Inherit Product Category Image' , 'ubermenu' ),
		'desc'		=> __( 'For WooCommerce Product Categories, dynamically inherit featured images.' , 'ubermenu' ),
		'type'		=> 'radio',
		'type_class'=> 'ubermenu-radio-blocks',
		'ops'		=> array(
			'group'	=> array(
				'off'	=> array(
					'name'	=> __( 'Disabled' , 'ubermenu' ),
					'desc'	=> __( 'Do not apply product category image' , 'ubermenu' )
				),
				'on'	=> array(
					'name'	=> __( 'Enabled' , 'ubermenu' ),
					'desc'	=> __( 'Dynamically assign Product Category Image' , 'ubermenu' )
				),
			)
		),
		'default'	=> 'off',
		// 'scenario'	=> __( 'WooCommerce Product Category' , 'ubermenu' ),
		// 'on_save'	=> 'inherit_featured_image',

	);

	$settings['woocommerce_dt_image'][20] = array(
		'id'		=> 'image_size',
		'title'		=> __( 'Image Size' , 'ubermenu' ),
		'type'		=> 'radio',
		'type_class'=> 'ubermenu-radio-blocks',
		'default'	=> 'inherit',
		'ops'		=> 'ubermenu_get_image_size_ops',
		'desc'		=> __( 'This is the size of the actual file that will be served.  You can choose from any registered image size in your setup.  You can set a default to be inherited globally in the Control Panel.' , 'ubermenu' ),
	);


	$settings['woocommerce_dt_image'][30] = array(
		'id'		=> 'image_dimensions',
		'title'		=> __( 'Image Dimensions' , 'ubermenu' ),
		'type'		=> 'radio',
		'type_class'=> 'ubermenu-radio-blocks',
		'default'	=> 'inherit',
		'ops'		=> array(
			'group'	=> array(
				'inherit'	=> array(
					'name'	=> __( 'Inherit' , 'ubermenu' ),
					'desc'	=> __( 'Inherit settings from the menu instance settings' , 'ubermenu' )
				),
				'natural'	=> array(
					'name'	=> __( 'Natural' , 'ubermenu' ),
					'desc'	=> __( 'Display image at natural dimensions' , 'ubermenu' )
				),
				'custom'	=> array(
					'name'	=> __( 'Custom' , 'ubermenu' ),
					'desc'	=> __( 'Use a custom size, defined below' )
				),
			)
		),
		'on_save'	=> 'image_dimensions',
	);

	$settings['woocommerce_dt_image'][40] = array(
		'id'		=> 'image_width_custom',
		'title'		=> __( 'Custom Image Width' , 'ubermenu' ),
		'desc'		=> __( 'Image width attribute (px).  Do not include units.  Only valid if "Image Dimensions" is set to "Custom" above.' , 'ubermenu' ),
		'type'		=> 'text',
		'default'	=> '',
		'on_save'	=> 'image_width_custom',
	);

	$settings['woocommerce_dt_image'][50] = array(
		'id'		=> 'image_height_custom',
		'title'		=> __( 'Custom Image Height' , 'ubermenu' ),
		'desc'		=> __( 'Image height attribute (px).  Do not include units.  Only valid if "Image Dimensions" is set to "Custom" above.  Leave blank to maintain aspect ratio.' , 'ubermenu' ),
		'type'		=> 'text',
		'default'	=> '',
	);

	$settings['woocommerce_dt_image'][55] = array(
		'id'		=> 'image_text_top_padding',
		'title'		=> __( 'Image Text Top Padding' , 'ubermenu' ),
		'desc'		=> __( 'The top padding for the accompanying text when Image Left or Image Right layouts are displayed.  This allows control over the vertical alignment of the text relative to the image.', 'ubermenu' ),
		'type'		=> 'text',
		'default'	=> '',
		'on_save'	=> 'image_text_top_padding',
	);

	$settings['woocommerce_dt_image'][60] = array(
		'id'		=> 'disable_padding',
		'title'		=> __( 'Disable Item Padding' , 'ubermenu' ),
		'desc'		=> __( 'Disable the padding on this item.  Useful for image-only menu items where the image should extend to the extents of the item.' , 'ubermenu' ),
		'type'		=> 'checkbox',
		'default'	=> 'off',
	);


  return $settings;
}
