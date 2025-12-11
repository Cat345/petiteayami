<?php

/** CONFIGURATION SETTINGS **/

add_action( 'ubermenu_customizer_register_subsections' , 'ubermenu_woocommerce_customizer_subsections', 20, 2 );
function ubermenu_woocommerce_customizer_subsections( $wp_customize , $panel_id ){

  if( !ubermenu_woocommerce_is_active() ) return;

  $wp_customize->add_section( $panel_id.'_woocommerce', array(
    'title'		=> __( 'WooCommerce', 'ubermenu' ),
    'priority'	=> 200,
    'panel'		=> $panel_id,
  ) );
}

add_filter( 'ubermenu_settings_subsections' , 'ubermenu_settings_subsection_woocommerce' , 50 , 1 );
function ubermenu_settings_subsection_woocommerce( $subsections ){

  if( !ubermenu_woocommerce_is_active() ) return $subsections;

	$subsections['woocommerce'] = array(
		'title'	=> __( 'WooCommerce', 'ubermenu' ),
	);
	return $subsections;
}


add_filter( 'ubermenu_instance_settings' , 'ubermenu_woocommerce_config_settings_fields' , 50 , 2 );
function ubermenu_woocommerce_config_settings_fields( $fields , $config_id ){

  if( !ubermenu_woocommerce_is_active() ) return $fields;

  // uberp( $fields );

  $fields[3000] = array(
    'name'	=> 'woocommerce_header',
		'label'	=> __( 'WooCommerce' , 'ubermenu' ),
		'type'	=> 'header',
		'group'	=> 'woocommerce',
    'customizer' => true,
  );

  $fields[3010] = array(
    'name'	=> 'wc_price_color',
	'label'	=> __( 'WooCommerce Product Item Price Text Color' , 'ubermenu' ),
	'type'	=> 'color',
	'group'	=> 'woocommerce',
    'custom_style' => 'wc_price_color',
    'customizer' => true,
    'customizer_section' => 'woocommerce',
  );

  $fields[3020] = array(
    'name'	=> 'wc_sale_badge_color',
		'label'	=> __( 'WooCommerce Product Item Sale Badge Text Color' , 'ubermenu' ),
		'type'	=> 'color',
		'group'	=> 'woocommerce',
    'custom_style' => 'wc_sale_badge_color',
    'customizer' => true,
    'customizer_section' => 'woocommerce',
  );

  $fields[3030] = array(
    'name'	=> 'wc_sale_badge_background_color',
		'label'	=> __( 'WooCommerce Product Item Sale Badge Background Color' , 'ubermenu' ),
		'type'	=> 'color',
		'group'	=> 'woocommerce',
    'custom_style' => 'wc_sale_badge_background_color',
    'customizer' => true,
    'customizer_section' => 'woocommerce',
  );

  $fields[3040] = array(
    'name'	=> 'wc_sale_badge_text_transform',
		'label'	=> __( 'WooCommerce Product Item Sale Badge Text Transform' , 'ubermenu' ),
    'type'	=> 'select',
		'desc'	=> __( 'Text transform for the sale badge', 'ubermenu' ),
		'options'	=> array(
			''			=> '&mdash;',
			'none'		=> __( 'None', 'ubermenu' ),
			'uppercase'	=> __( 'Uppercase', 'ubermenu' ),
			'capitalize'=> __( 'Capitalize', 'ubermenu' ),
		),
		'default'	=> '',
		'group'	=> 'woocommerce',
    'custom_style' => 'wc_sale_badge_text_transform',
    'customizer' => true,
    'customizer_section' => 'woocommerce',
  );

  $fields[3050] = array(
    'name'	=> 'wc_sale_badge_font_weight',
		'label'	=> __( 'WooCommerce Product Item Sale Badge Font Weight' , 'ubermenu' ),
    'type'	=> 'select',
		'desc'	=> __( 'Font weight for the sale badge', 'ubermenu' ),
		'options'	=> array(
			''			=> '&mdash;',
			'normal'	=> __( 'Normal', 'ubermenu' ),
			'bold'		=> __( 'Bold', 'ubermenu' ),
			'100'			=> '100',
			'200'			=> '200',
			'300'			=> '300',
			'400'			=> '400',
			'500'			=> '500',
			'600'			=> '600',
			'700'			=> '700',
			'800'			=> '800',
			'900'			=> '900',
		),
		'default'	=> '',
		'group'	=> 'woocommerce',
    'custom_style' => 'wc_sale_badge_font_weight',
    'customizer' => true,
    'customizer_section' => 'woocommerce',
  );

  return $fields;

}





/** GENERAL SETTINGS **/

/**
 * Add WooCommerce Sub Sections to General Panel
 */
add_filter( 'ubermenu_general_settings_sections' , 'ubermenu_general_settings_sections_woocommerce', 20, 1 );
function ubermenu_general_settings_sections_woocommerce( $section ){

  if( !ubermenu_woocommerce_is_active() ) return $section;

	$section['sub_sections']['woocommerce'] = array(
    'title'	=> __( 'WooCommerce' , 'ubermenu' ),
  );

	return $section;
}

add_filter( 'ubermenu_settings_panel_fields' , 'ubermenu_settings_panel_fields_woocommerce' , 30, 1 );
function ubermenu_settings_panel_fields_woocommerce( $all_fields = array() ){

  if( !ubermenu_woocommerce_is_active() ) return $all_fields;

  $general = $all_fields[UBERMENU_PREFIX.'general'];

  $general[1000] = array(
		'name'	=> 'header_woocommerce',
		'label'	=> __( 'WooCommerce' , 'ubermenu' ),
		'type'	=> 'header',
		'group'	=> 'woocommerce',
	);

  // Layouts
  $general[1100] = array(
		'name'	=> 'header_woocommerce_items',
		'label'	=> __( 'WooCommerce Menu Items' , 'ubermenu' ),
		'type'	=> 'header',
		'group'	=> 'woocommerce',
	);

  $general[1110] = array(
		'name' 		=> 'wc_show_sale_badge',
		'label' 	=> __( 'Show Sale Badge', 'ubermenu' ),
		'desc' 		=> __( 'In WooCommerce Product item layouts, show the sale badge when an item is on sale', 'ubermenu' ),
		'type' 		=> 'checkbox',
		'default' 	=> 'on',
		'group'	=> 'woocommerce',
	);

  $general[1120] = array(
		'name'		=> 'wc_price_display',
		'label'		=> __( 'Product Price Display' , 'ubermenu' ),
		'desc'		=> sprintf( __( 'How should prices be displayed for Product items in the menu?  You can override with the %s filter' , 'ubermenu' ), '<code>ubermenu_woocommerce_product_price</code>' ),
		'type'		=> 'radio',
		'options' 	=> [
      		'current' 	=> [
				'title' => __( 'Current Price', 'ubermenu' ),
				'desc' => __('<code>get_price()</code>','ubermenu'),
			],
			'html'		=> [
				'title' => __( 'HTML price', 'ubermenu' ),
				'desc' => __('<code>get_price_html()</code>','ubermenu'),
			],
		],
		'default' 	=> 'html',
		'group'	=> 'woocommerce',

	);

  $general[1130] = array(
		'name' 		=> 'wc_category_images',
		'label' 	=> __( 'Use Category Images as Featured Images', 'ubermenu' ),
		'desc' 		=> __( 'For WooCommerce Product Category items, use the WooCommerce Product Category Image when inheriting featured images', 'ubermenu' ),
		'type' 		=> 'checkbox',
		'default' 	=> 'on',
		'group'	=> 'woocommerce',
	);


  // Cart Widget
  $general[1200] = array(
		'name'	=> 'header_woocommerce_cart',
		'label'	=> __( 'WooCommerce Cart' , 'ubermenu' ),
		'type'	=> 'header',
		'group'	=> 'woocommerce',
	);

  $general[1210] = array(
		'name' 		=> 'wc_allow_cart_widget',
		'label' 	=> __( 'Allow Cart Widget on Cart and Checkout pages', 'ubermenu' ),
		'desc' 		=> __( 'By default, WooCommerce will disable the cart widget on cart and checkout pages.  To display it, enable this setting.', 'ubermenu' ),
		'type' 		=> 'checkbox',
		'default' 	=> 'on',
		'group'	=> 'woocommerce',
	);


  $all_fields[UBERMENU_PREFIX.'general'] = $general;

  return $all_fields;
}
