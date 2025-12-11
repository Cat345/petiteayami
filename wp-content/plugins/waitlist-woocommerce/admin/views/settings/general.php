<?php


$settings = array(

	/** MAIN **/
	array(
		'callback' 		=> 'links',
		'title' 		=> 'Manage',
		'id' 			=> 'fake',
		'section_id' 	=> 'gl_main',
		'args' 			=> array(
			'options' 	=> array(
				admin_url('admin.php?page=xoo-wl-fields') 			=> 'Form Fields',
				admin_url('admin.php?page=xoo-wl-view-waitlist') 	=> 'Waiting List',
				admin_url('admin.php?page=xoo-wl-email-history') 	=> 'Email Log',
			)
		)
	),


	array(
		'callback' 		=> 'select',
		'title' 		=> 'Waitlist Form Type',
		'id' 			=> 'm-form-type',
		'section_id' 	=> 'gl_main',
		'args'			=> array(
			'options' => array(
				'popup' 		=> 'Popup',
				'inline'  		=> 'Inline',
				'inline_toggle' => 'Inline Toggle'
			)
		),
		'default' 		=> 'popup'
	),

	array(
		'callback' 		=> 'checkbox',
		'title' 		=> 'Enable Guest',
		'id' 			=> 'm-en-guest',
		'section_id' 	=> 'gl_main',
		'default' 		=> 'yes',
		'desc' 			=> 'Allow guest users to join the waitlist'
	),


	array(
		'callback' 		=> 'checkbox',
		'title' 		=> 'Show on Archive/Shop',
		'id' 			=> 'm-en-shop',
		'section_id' 	=> 'gl_main',
		'default' 		=> 'yes'
	),

	array(
		'callback' 		=> 'checkbox',
		'title' 		=> 'Show on Backorders',
		'id' 			=> 'm-en-bod',
		'section_id' 	=> 'gl_main',
		'default' 		=> 'no',
		'desc' 			=> 'If you are also managing the stock quantity, then the quantity should be set to 0 for the button to appear.'
	),

	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'gl_texts',
		'id'			=> 'txt-btn',
		'title' 		=> 'Button Text',
		'default' 		=> 'Email me when available',
	),


	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'gl_texts',
		'id'			=> 'txt-head',
		'title' 		=> 'Form Heading',
		'default' 		=> 'Join Waitlist',
	),


	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'gl_texts',
		'id'			=> 'txt-subhead',
		'title' 		=> 'Form Sub-Head',
		'default' 		=> 'We will inform you when the product arrives in stock. Please leave your valid email address below.',
	),


	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'gl_texts',
		'id'			=> 'txt-success-notice',
		'title' 		=> 'Success Notice',
		'default' 		=> 'You are now in waitlist. We will inform you as soon as we are back in stock.',
	)


);

return apply_filters( 'xoo_wl_admin_settings', $settings, 'general' );

?>