<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$countries = (array) include $this->aff->dir.'/countries/countries.php';

$field_settings = array(

	'active' 	=> array(
		'type' 		=> 'checkbox',
		'id'		=> 'active',
		'section' 	=> 'basic',	
		'title' 	=> 'Active',
		'width'		=> 'half',
		'value'		=> 'yes',
	),

	'required' 	=> array(
		'type' 		=> 'checkbox',
		'id'		=> 'required',
		'section' 	=> 'basic',
		'title' 	=> 'Required (*)',
		'width'		=> 'half',
		'value'		=> 'no',
	),


	'label' => array(
		'type' 		=> 'text',
		'id'		=> 'label',
		'section' 	=> 'display',
		'title' 	=> 'Label',
		'width'		=> 'half',
		'value'		=> '',
		'translate' => 'yes',
		'priority' 	=> 10
	),

	'placeholder' => array(
		'type' 			=> 'text',
		'id'			=> 'placeholder',
		'section' 		=> 'display',
		'title' 		=> 'Placeholder',
		'width'			=> 'half',
		'value'			=> '',
		'translate' 	=> 'yes',
		'priority' 		=> 20
	),


	'icon' => array(
		'type' 			=> 'iconpicker',
		'id'			=> 'icon',
		'section' 		=> 'display',
		'title' 		=> 'Input Icon',
		'width'			=> 'half',
		'placeholder' 	=> 'Click here',
		'info' 			=> 'Icons can be disabled/enabled from the settings page',
		'priority' 		=> 30
	),


	'cols' => array(
		'type' 		=> 'select',
		'id'		=> 'cols',
		'section' 	=> 'display',
		'title' 	=> 'Field Width',
		'options' 	=> array(
			'one' 			=> '100%',
			'onehalf' 		=> '50%',
			'onethird'  	=> '33%',
			'onefourth' 	=> '25%',
			'twothird' 		=> '66%',
			'threefourth'	=> '75%',
		),
		'width'		=> 'half',
		'value'		=> 'one',
		'priority' 	=> 40
	),


	'phone_code_display_type' => array(
		'type' 		=> 'select',
		'id'		=> 'phone_code_display_type',
		'section' 	=> 'display',
		'title' 	=> 'Display Type',
		'width'		=> 'half',
		'value'		=> 'select',
		'options' 	=> array(
			'input' 	=> 'Input',
			'select' 	=> 'Select' 
		),
		'priority' 		=> 50
	),


	'date_format' => array(
		'type' 		=> 'select',
		'id'		=> 'date_format',
		'section' 	=> 'display',
		'title' 	=> 'Date Format',
		'options' 	=> array(
			'dd/mm/yy' 	=> 'dd/mm/yy',
			'mm/dd/yy' 		=> 'mm/dd/yy',
			'yy-mm-dd' 		=> 'yy-mm-dd',
			'd M, y'   		=> 'd M, y',
			'd MM, y'  		=> 'd MM, y',
			'DD, d MM, yy' 	=> 'DD, d MM, yy',
			"'day' d 'of' MM 'in the year' yy" => "'day' d 'of' MM 'in the year' yy"
		),
		'width'		=> 'half',
		'value'		=> 'dd/mm/yy',
		'priority' 		=> 50
	),


	

	'password_visibility' => array(
		'type' 		=> 'checkbox',
		'id'		=> 'password_visibility',
		'section' 	=> 'display',	
		'title' 	=> 'Password Visibility Toggle',
		'width'		=> 'half',
		'value'		=> 'yes',
		'priority' 	=> 50
	),


	'use_select2' 	=> array(
		'type' 		=> 'checkbox',
		'id'		=> 'use_select2',
		'section' 	=> 'display',	
		'title' 	=> 'Select2 UI',
		'width'		=> 'half',
		'value'		=> 'no',
		'info' 		=> 'UI for searching select options',
		'priority' 	=> 50
	),

	'upload_layout' => array(
		'type' 		=> 'select',
		'id'		=> 'upload_layout',
		'section' 	=> 'display',
		'title' 	=> 'Layout',
		'options' 	=> array(
			'profile' 	=> 'Profile',
			'file' 		=> 'Default File Upload',
		),
		'width'		=> 'half',
		'value'		=> 'profile',
		'priority' 	=> 50
	),


	'profile_icon_size' => array(
		'type' 		=> 'number',
		'id'		=> 'profile_icon_size',
		'section' 	=> 'display',
		'title' 	=> 'Profile Icon Size',
		'width'		=> 'half',
		'value'		=> 80,
		'priority' 	=> 50
	),


	'one_line' 	=> array(
		'type' 		=> 'checkbox',
		'id'		=> 'one_line',
		'section' 	=> 'display',	
		'title' 	=> 'Show Items in One Line',
		'width'		=> 'half',
		'value'		=> 'no',
		'priority' 	=> 50
	),




	'default'  => array(
		'type' 		=> 'text',
		'id'		=> 'default',
		'section' 	=> 'basic',
		'title' 	=> 'Default',
		'width'		=> 'half',
		'value'		=> '',
	),

	


	'minlength'	=> array(
		'type' 		=> 'number',
		'id'		=> 'minlength',
		'section' 	=> 'validation',
		'title' 	=> 'Minimum Characters',
		'width'		=> 'half',
		'value'		=> '',
	),

	'maxlength'	=> array(
		'type' 		=> 'number',
		'id'		=> 'maxlength',
		'section' 	=> 'validation',
		'title' 	=> 'Maximum Characters',
		'width'		=> 'half',
		'value'		=> '',
	),


	'min' => array(
		'type' 		=> 'number',
		'id'		=> 'min',
		'section' 	=> 'validation',
		'title' 	=> 'Minimum Value',
		'width'		=> 'half',
		'value'		=> '1',
	),


	'max' => array(
		'type' 		=> 'number',
		'id'		=> 'max',
		'section' 	=> 'validation',
		'title' 	=> 'Maximum Value',
		'width'		=> 'half',
		'value'		=> '',
	),


	'step' => array(
		'type' 		=> 'text',
		'id'		=> 'step',
		'section' 	=> 'validation',
		'title' 	=> 'Step',
		'width'		=> 'half',
		'value'		=> 'any',
		'info' 		=> 'Value by which number should increase. Type "any" for any value'
	),


	'date' 		=> array(
		'type' 		=> 'text',
		'id'		=> 'date',
		'section' 	=> 'basic',
		'title' 	=> 'Date',
		'width'		=> 'half',
		'value'		=> '',
	),




	'checkbox_single' => array(
		'type' 		=> 'checkbox_single',
		'id'		=> 'checkbox_single',
		'section' 	=> 'basic',
		'title' 	=> 'Checkbox',
		'width'		=> 'full',
		'value' 	=> array(
			'first' => array(
				'value' 	=> 'first',
				'label' 	=> 'First Checkbox Title',
				'checked' 	=> 'checked'
			)
		),
		'translate' => 'yes'
	),

	'checkbox_list' => array(
		'type' 		=> 'checkbox_list',
		'id'		=> 'checkbox_list',
		'section' 	=> 'basic',
		'title' 	=> 'Checkboxes',
		'width'		=> 'full',
		'value' 	=> array(
			'first' => array(
				'value' 	=> 'first',
				'label' 	=> 'First Checkbox Title',
				'checked' 	=> 'checked'
			),
			'second' => array(
				'value' 	=> 'second',
				'label' 	=> 'Second Checkbox Title',
				'checked' 	=> ''
			)
		),
		'sort' 		=> 'yes',
		'translate' => 'yes'
	),

	'radio' => array(
		'type' 		=> 'radio',
		'id'		=> 'radio',
		'section' 	=> 'basic',
		'title' 	=> 'Radio List',
		'width'		=> 'full',
		'value' 	=> array(
			'first' => array(
				'value' 	=> 'first',
				'label' 	=> 'First Radio Title',
				'checked' 	=> 'checked'
			),
			'second' => array(
				'value' 	=> 'second',
				'label' 	=> 'Second Radio Title',
				'checked' 	=> ''
			)
		),
		'sort' 		=> 'yes',
		'translate' => 'yes'
	),


	'select_list' => array(
		'type' 		=> 'select_list',
		'id'		=> 'select_list',
		'section' 	=> 'basic',
		'title' 	=> 'Select',
		'width'		=> 'full',
		'value' 	=> array(
			'first' => array(
				'value' 	=> 'first',
				'label' 	=> 'First Select Title',
				'checked' 	=> 'checked'
			),
			'second' => array(
				'value' 	=> 'second',
				'label' 	=> 'Second Select Title',
				'checked' 	=> ''
			)
		),
		'sort' 		=> 'yes',
		'translate' => 'yes'
	),
	
	'country_list' => array(
		'type' 		=> 'select',
		'id'		=> 'country_list',
		'section' 	=> 'basic',
		'title' 	=> 'Countries',
		'options' 	=> array(
			'all' 		=> 'All countries',
			'all_but' 	=> 'All countries except..',
			'only'  	=> 'Specific countries',
		),
		'width'		=> 'half',
		'value'		=> 'all',
		'new_row' 	=> "yes"
	),

	'country_choose' => array(
		'type' 			=> 'select_multiple',
		'id'			=> 'country_choose',
		'section' 		=> 'basic',
		'title' 		=> 'Choose Countries',
		'placeholder'	=> 'Start typing..',
		'options' 		=> $countries,
		'width'			=> 'half',
		'value'			=> '',
		'translate' 	=> 'yes'
	),


	'for_country_id' => array(
		'type' 		=> 'text',
		'id'		=> 'for_country_id',
		'section' 	=> 'basic',
		'title' 	=> 'Country Field ID',
		'width'		=> 'half',
		'value'		=> '',
		'info'		=> 'If you have a country field & wants to auto fill states based on the country selected by user on frontend. Place the country field ID here',
		'required' 	=> 'yes'
	),

	'linked_to' => array(
		'type' 		=> 'text',
		'id'		=> 'linked_to',
		'section' 	=> 'basic',
		'title' 	=> 'Linked Field',
		'width'		=> 'half',
		'value'		=> '',
		'info'		=> 'Put linked Field ID here',
		'required' 	=> 'yes'
	),


	'strength_meter' => array(
		'type' 		=> 'checkbox',
		'id'		=> 'strength_meter',
		'section' 	=> 'basic',	
		'title' 	=> 'Strength Meter',
		'width'		=> 'half',
		'value'		=> 'yes',
	),


	'strength_meter_pass' => array(
		'type' 		=> 'select',
		'id'		=> 'strength_meter_pass',
		'section' 	=> 'basic',
		'title' 	=> 'Password acceptance strength',
		'width'		=> 'half',
		'value'		=> '3',
		'options' 	=> array(
			'0' 	=> 'Short(0)',
			'1' 	=> 'Bad(1)',
			'2'		=> 'Bad(2)',
			'3' 	=> 'Good(3)',
			'4' 	=> 'Strong(4)'
		),
		'info'		=> 'Accept password if strength is equals to or above this status',
	),


	

	'ta_rows'	=> array(
		'type' 		=> 'number',
		'id'		=> 'rows',
		'section' 	=> 'basic',
		'title' 	=> 'Rows',
		'width'		=> 'half',
		'value'		=> '2',
	),

	'ta_cols'	=> array(
		'type' 		=> 'number',
		'id'		=> 'ta_cols',
		'section' 	=> 'basic',
		'title' 	=> 'Columns',
		'width'		=> 'half',
		'value'		=> '40',
	),

	

	'max_filesize'	=> array(
		'type' 		=> 'number',
		'id'		=> 'max_filesize',
		'section' 	=> 'validation',
		'title' 	=> 'Maximum File Size',
		'width'		=> 'half',
		'value'		=> '2',
		'info'		=> 'File Size in MB',
	),

	'file_multiple' 	=> array(
		'type' 		=> 'checkbox',
		'id'		=> 'file_multiple',
		'section' 	=> 'validation',	
		'title' 	=> 'Multiple Files',
		'width'		=> 'half',
		'value'		=> 'yes',
	),

	'file_multiple_max' 	=> array(
		'type' 		=> 'number',
		'id'		=> 'file_multiple_max',
		'section' 	=> 'validation',	
		'title' 	=> 'Maximum number of files',
		'width'		=> 'half',
		'value'		=> 3,
		'info' 		=> 'When Multiple files are allowed'
	),

	'file_type'	=> array(
		'type' 		=> 'text',
		'id'		=> 'file_type',
		'section' 	=> 'validation',
		'title' 	=> 'File Type',
		'width'		=> 'half',
		'value'		=> '.png, .jpg, .pdf',
		'info'		=> 'Use comma separated values. For eg: .png, .jpg, .pdf. Leave empty for any file type',
	),


	


	'autocomplete_auto_fetch' => array(
		'type' 		=> 'checkbox',
		'id'		=> 'autocomplete_auto_fetch',
		'section' 	=> 'basic',
		'title' 	=> 'Auto fetch location',
		'width'		=> 'half',
		'value'		=> 'yes',
		'info'		=> 'Auto fetch location via browser.',
	),



	'autocomplete_field_id' => array(
		'type' 		=> 'text',
		'id'		=> 'autocomplete_field_id',
		'section' 	=> 'advanced',
		'title' 	=> 'Autocomplete Address Field ID',
		'width'		=> 'half',
		'value'		=> '',
		'info'		=> 'If you have an autocomplete address field and want to extract a specific address part to fill this field.',
	),


	'autocomplete_field_type' => array(
		'type' 		=> 'select',
		'id'		=> 'autocomplete_field_type',
		'section' 	=> 'advanced',
		'title' 	=> 'Autocomplete Address Part',
		'width'		=> 'half',
		'options' 	=> array(
			'address' 		=> 'Address',
			'postal_code' 	=> 'Postal Code',
			'city' 			=> 'City',
			'states' 		=> 'States'
		),
		'value'		=> 'Which address part do you want to auto-fill in this field?',
	),


	

	/**
	  * Advanced section
	**/

	'unique_id' => array(
		'type' 		=> 'text',
		'id'		=> 'unique_id',
		'section' 	=> 'advanced',
		'title' 	=> 'Unique ID/Name',
		'width'		=> 'half',
		'value'		=> '',
		'info'		=> 'Leave it default, if you don\'t know what you are using it for. Keep it very unique. Start it with xoo_aff_',
	),

	'class'		=> array(
		'type' 		=> 'text',
		'id'		=> 'class',
		'section' 	=> 'advanced',
		'title' 	=> 'Extra CSS Class',
		'width'		=> 'half',
		'value'		=> '',
	),

	

);

return apply_filters( 'xoo_aff_'.$this->plugin_slug.'_field_setting_options', $field_settings );