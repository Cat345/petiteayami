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
		'callback' 		=> 'checkbox_list',
		'title' 		=> 'Show Waitlist Button For',
		'id' 			=> 'm-btn-show',
		'section_id' 	=> 'gl_main',
		'args' 			=> array(
			'options' 	=> array(
				'outofstock' 	=> 'Out-of-stock items',
				'instock' 	 	=> 'In-stock items',
				'backorder'  	=> 'Backorder',
				'backorder_out' => 'Backorder but out of stock',
			)
		),
		'default' 	=> array(
			'outofstock', 'backorder_out'
		),
		'desc' 	=> 'You can also manage each product individually from the "edit product -> inventory" section'
	),

	array(
		'callback' 		=> 'checkbox',
		'title' 		=> 'Show on Archive/Shop Page',
		'id' 			=> 'm-en-shop',
		'section_id' 	=> 'gl_main',
		'default' 		=> 'yes'
	),




	array(
		'callback' 		=> 'select',
		'title' 		=> 'Waitlist Form UI',
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
		'title' 		=> 'Allow Guest users',
		'id' 			=> 'm-en-guest',
		'section_id' 	=> 'gl_main',
		'default' 		=> 'yes',
		'desc' 			=> 'Allow guest users to join the waitlist'
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