<?php

$tabs = array(
	'general' => array(
		'title'			=> 'General',
		'id' 			=> 'general',
		'option_key' 	=> 'xoo-wl-general-options',
		'icon' 			=> 'xoo-icon-setting',
		'args' 			=> array(
			'priority' => 10
		),
	),

	'email' => array(
		'title'			=> 'Email',
		'id' 			=> 'email',
		'option_key' 	=> 'xoo-wl-email-options',
		'icon' 			=> 'xoo-icon-mail',
		'args' 			=> array(
			'priority' => 20
		),
	),

	'email-style' => array(
		'title'			=> 'Email Style',
		'id' 			=> 'email-style',
		'option_key' 	=> 'xoo-wl-emStyle-options',
		'icon' 			=> 'xoo-icon-brush',
		'args' 			=> array(
			'priority' => 25
		),
	),

	'style' => array(
		'title'			=> 'Style',
		'id' 			=> 'style',
		'option_key' 	=> 'xoo-wl-style-options',
		'icon' 			=> 'xoo-icon-brush',
		'args' 			=> array(
			'priority' => 30
		),
	),

	'addon' => array(
		'title'			=> 'Add-ons',
		'id' 			=> 'addon',
		'option_key' 	=> '',
		'args' 			=> array(
			'priority' => 50
		),
		'icon' 			=> 'xoo-icon-crown',
	),
	

);

return apply_filters( 'xoo_wl_admin_settings_tabs', $tabs );