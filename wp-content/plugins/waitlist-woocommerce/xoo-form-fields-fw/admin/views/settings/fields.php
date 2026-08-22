<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


$option_key = $this->aff->field_option_key;

$settings = array(

	array(
		'callback' 		=> 'links',
		'title' 		=> 'Form Fields',
		'id' 			=> 'fake',
		'section_id' 	=> 'main',
		'args' 			=> array(
			'options' 	=> array(
				admin_url('admin.php?page='.$this->aff->admin_page_slug) => 'Fields Page Link'
			)
		)
	),


);

/**
 * Google Places & Geolocation (Conditional)
 */
if ( $this->aff->en_autocompadr ) {

	$settings[]	= array(
		'callback' 		=> 'text',
		'title' 		=> 'API Key',
		'id' 			=> 'aca-apikey',
		'section_id' 	=> 'main',
	);

	$settings[]	=	array(
		'callback' 		=> 'text',
		'title' 		=> 'Restrict results to specific countries',
		'id' 			=> 'aca-countries',
		'section_id' 	=> 'main',
		'desc' 			=> 'Add the ISO codes, separated by comma. For eg: US,IN,UK',
	);

}


$input_icon = array(

	array(
		'callback' 		=> 'checkbox',
		'title' 		=> 'Show Icons',
		'id' 			=> 's-show-icons',
		'section_id' 	=> 'input-icon',
		'default' 		=> 'yes',
	),

	array(
		'callback' 		=> 'select',
		'id'			=> 's-icon-align',
		'title' 		=> 'Icon Position',
		'section_id' 	=> 'input-icon',
		'default' 		=> is_rtl() ? 'right' : 'left',
		'args' 			=> array(
			'options' => array(
				'left' => 'Left',
				'right' => 'Right'
			)
		)
	),

	array(
		'callback' 		=> 'number',
		'title' 		=> 'Icon Size',
		'id' 			=> 's-icon-size',
		'section_id' 	=> 'input-icon',
		'default' 		=> '14',
		'desc' 			=> 'in px',
	),

	array(
		'callback' 		=> 'number',
		'title' 		=> 'Icon Container Width',
		'id' 			=> 's-icon-width',
		'section_id' 	=> 'input-icon',
		'default' 		=> '40',
		'desc' 			=> 'in px',
	),

	array(
		'callback' 		=> 'color',
		'title' 		=> 'Icon Background Color',
		'id' 			=> 's-icon-bgcolor',
		'section_id' 	=> 'input-icon',
		'default' 		=> '#f2f5fd',
	),

	array(
		'callback' 		=> 'color',
		'title' 		=> 'Icon Color',
		'id' 			=> 's-icon-color',
		'section_id' 	=> 'input-icon',
		'default' 		=> '#1b3162',
	),


	array(
		'callback' 		=> 'color',
		'title' 		=> 'Icon Color on Focus',
		'id' 			=> 's-icon-color-focus',
		'section_id' 	=> 'input-icon',
		'default' 		=> '#526d82',
	),

	array(
		'callback' 		=> 'color',
		'title' 		=> 'Icon Background Color on Focus',
		'id' 			=> 's-icon-bgcolor-focus',
		'section_id' 	=> 'input-icon',
		'default' 		=> '#f2f5fd',
	),

	array(
		'callback' 		=> 'color',
		'title' 		=> 'Icon Border Color',
		'id' 			=> 's-icon-borcolor',
		'section_id' 	=> 'input-icon',
		'default' 		=> '#cccccc',
	),

	array(
		'callback' 		=> 'number',
		'title' 		=> 'Icon Border Width',
		'id' 			=> 's-icon-borwidth',
		'section_id' 	=> 'input-icon',
		'default' 		=> '1',
	)

);

$settings = array_merge( $settings, $input_icon );


if( get_option( 'xoo_aff_'.$this->aff->plugin_slug.'_allow_old_layout' ) === "yes" ){
	$settings[] = array(
		'callback' 		=> 'checkbox',
		'title' 		=> 'New Layout',
		'id' 			=> 's-new-layout',
		'section_id' 	=> 'input',
		'default' 		=> 'yes',
		'args' 			=> array(
			'toggleSettings' => array( //hide elements if this field value is
				"{$option_key}[s-icon-borcolor]" 		=> array( 'yes' ),
				"{$option_key}[s-icon-borwidth]" 		=> array( 'yes' ),
				"{$option_key}[s-input-borcolor]" 		=> array( 'yes' ),
				"{$option_key}[s-input-borwidth]" 		=> array( 'yes' ),
				"{$option_key}[s-input-fsize]" 			=> array( 'unchecked' ),
				"{$option_key}[s-input-border-focus]" 	=> array( 'unchecked' ),
				"{$option_key}[s-icon-color-focus]" 	=> array( 'unchecked' ),
				"{$option_key}[s-icon-bgcolor-focus]" 	=> array( 'unchecked' ),
				"{$option_key}[s-label-fsize]" 			=> array( 'unchecked' ),
				"{$option_key}[s-icon-align]" 			=> array( 'unchecked' ),
				"{$option_key}[s-input-border]" 		=> array( 'unchecked' ),
			),
		)
	);
}

$other_settings = array(

	array(
		'callback' 		=> 'checkbox',
		'title' 		=> 'Show Required(*) symbol',
		'id' 			=> 's-show-reqicon',
		'section_id' 	=> 'input',
		'default' 		=> 'no',
	),



	array(
		'callback' 		=> 'number',
		'title' 		=> 'Field Height',
		'id' 			=> 's-input-height',
		'section_id' 	=> 'input',
		'default' 		=> '50',
	),


	array(
		'callback' 		=> 'number',
		'title' 		=> 'Font Size',
		'id' 			=> 's-input-fsize',
		'section_id' 	=> 'input',
		'default' 		=> '14',
	),

	array(
		'callback' 		=> 'number',
		'title' 		=> 'Label Font Size',
		'id' 			=> 's-label-fsize',
		'section_id' 	=> 'input',
		'default' 		=> '15',
	),


	array(
		'callback' 		=> 'color',
		'title' 		=> 'Input Background Color',
		'id' 			=> 's-input-bgcolor',
		'section_id' 	=> 'input',
		'default' 		=> '#fff',
	),

	array(
		'callback' 		=> 'color',
		'title' 		=> 'Input Text Color',
		'id' 			=> 's-input-txtcolor',
		'section_id' 	=> 'input',
		'default' 		=> '#27374d',
	),


	array(
		'callback' 		=> 'number',
		'title' 		=> 'Input Border Width',
		'id' 			=> 's-input-borwidth',
		'section_id' 	=> 'input',
		'default' 		=> '1',
	),

	array(
		'callback' 		=> 'color',
		'title' 		=> 'Input Border Color',
		'id' 			=> 's-input-borcolor',
		'section_id' 	=> 'input',
		'default' 		=> '#cccccc',
	),

	array(
		'callback' 		=> 'number',
		'title' 		=> 'Input Border Width',
		'id' 			=> 's-input-borwidth',
		'section_id' 	=> 'input',
		'default' 		=> '1',
	),

	array(
		'callback' 		=> 'color',
		'title' 		=> 'Input Background color on focus',
		'id' 			=> 's-input-focusbgcolor',
		'section_id' 	=> 'input',
		'default' 		=> '#f2f5fd',
	),

	array(
		'callback' 		=> 'color',
		'title' 		=> 'Input on Focus text color',
		'id' 			=> 's-input-focustxtcolor',
		'section_id' 	=> 'input',
		'default' 		=> '#27374d',
	),

	array(
		'callback' 		=> 'border',
		'title' 		=> 'Border',
		'id' 			=> 's-input-border',
		'section_id' 	=> 'input',
		'default' 		=> array(
			'size' 			=> 1,
			'color' 		=> '#9DB2BF',
			'style' 		=> 'solid',
			'radius' 		=> 5,
		),
	),

	array(
		'callback' 		=> 'border',
		'title' 		=> 'Border on Focus',
		'id' 			=> 's-input-border-focus',
		'section_id' 	=> 'input',
		'default' 		=> array(
			'size' 			=> 1,
			'color' 		=> '#9DB2BF',
			'style' 		=> 'solid',
			'radius' 		=> 5,
		),
	),

	array(
		'callback' 		=> 'number',
		'title' 		=> 'Field Gap',
		'id' 			=> 's-field-bmargin',
		'section_id' 	=> 'input',
		'default' 		=> '30',
		'desc' 			=> 'gap between two field rows ( in px )',
	),

);


$settings = array_merge( $settings, $other_settings );

if( get_option( 'xoo_aff_'.$this->aff->plugin_slug.'_allow_old_layout' ) !== "yes" ){
	foreach  ($settings as $index => $setting ) {
		if( in_array( $setting['id'], array( 's-icon-borcolor', 's-icon-borwidth', 's-input-borcolor', 's-input-borwidth' ) ) ){
			unset( $settings[$index] );
		}
	}
}

return apply_filters( 'xoo_'.$this->aff->plugin_slug.'_admin_settings', $settings, 'fields' );