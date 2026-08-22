<?php

$sections = array(

	/* General TAB Sections */
	array(
		'title' => 'Main',
		'id' 	=> 'gl_main',
		'tab' 	=> 'general',
		'icon' 	=> 'xoo-icon-home'
	),


	array(
		'title' => 'Texts',
		'id' 	=> 'gl_texts',
		'tab' 	=> 'general',
		'desc' 	=> 'Leave text empty to remove element',
		'icon' 	=> 'xoo-icon-page'
	),


	/* Email TAB Sections */
	

	array(
		'title' => 'General',
		'id' 	=> 'em_general',
		'tab' 	=> 'email',
		'icon' 	=> 'xoo-icon-home'
	),

	array(
		'title' => 'Sender Options',
		'id' 	=> 'em_sender',
		'tab' 	=> 'email',
		'icon' 	=> 'xoo-icon-sendmail',
		'desc' 	=> 'Available when <b>Template Style</b> is set to <b>Custom</b>'
	),



	array(
		'title' => 'Back In Stock Email',
		'id' 	=> 'em_bis',
		'tab' 	=> 'email',
		'icon' 	=> 'xoo-icon-mail'
	),



	array(
		'title' => 'Admin Notification Email',
		'id' 	=> 'em_an',
		'tab' 	=> 'email',
		'icon' 	=> 'xoo-icon-mail',
		'pro' 	=> 'yes',
	),


	array(
		'title' => 'Confirmation Email to user',
		'id' 	=> 'em_un',
		'tab' 	=> 'email',
		'icon' 	=> 'xoo-icon-mail',
		'pro' 	=> 'yes',
	),


	/* Email Style TAB Sections */
	array(
		'title' => 'Container',
		'id' 	=> 'emsy_container',
		'tab' 	=> 'email-style',
		'icon' 	=> 'xoo-icon-page',
	),


	array(
		'title' => 'Button',
		'id' 	=> 'emsy_button',
		'tab' 	=> 'email-style',
		'icon' 	=> 'xoo-icon-tune',
	),


	array(
		'title' => 'Footer Container',
		'id' 	=> 'emsy_footer',
		'tab' 	=> 'email-style',
		'icon' 	=> 'xoo-icon-footer',
	),


	array(
		'title' => 'Back In Stock Email',
		'id' 	=> 'emsy_bis',
		'tab' 	=> 'email-style',
		'icon' 	=> 'xoo-icon-mail',
	),


	/* Style Sections*/

	array(
		'title' => 'Button',
		'id' 	=> 'sy_button',
		'tab' 	=> 'style',
		'icon' 	=> 'xoo-icon-tune',
	),

	array(
		'title' => 'Button Themes',
		'id' 	=> 'sy_button_theme_creator',
		'tab' 	=> 'style',
		'icon' 	=> 'xoo-icon-tune',
		'desc' 	=> 'Create and manage reusable button styles for side cart.'
	),

	array(
		'title' => 'Popup',
		'id' 	=> 'sy_popup',
		'tab' 	=> 'style',
		'icon' 	=> 'xoo-icon-popup',
	),


);

return apply_filters( 'xoo_wl_admin_settings_sections', $sections );