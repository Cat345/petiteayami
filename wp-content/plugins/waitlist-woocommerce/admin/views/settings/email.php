<?php


$option_name = 'xoo-wl-email-options';

$email_content = '<p style="margin: 0 0 16px 0;">You requested to be notified when [product_link] was back in stock and available for order.We are extremely pleased to announce that the product is now available for purchase. Please act fast, as the item may only be available in limited quantities.</p>';

$footer_content = '<p style="margin: 0 0 0 0;"><i>Thank you for choosing '.esc_html( get_option( 'blogname' ) ).'. If you have any questions, feel free to contact us at support@'.esc_html( get_option( 'blogname' ) ).'.</i></p>';

$placeholders = array();

$settings = array(

	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'em_bis',
		'id' 			=> 'bis-send-once',
		'title' 		=> 'Prevent duplicate emails',
		'default' 		=> 'no',
		'desc' 			=> "Ensures each user receives this email only once, even if the send action is triggered multiple times.",
	),



	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'em_bis',
		'id' 			=> 'bis-check-stock',
		'title' 		=> 'Check Stock status',
		'default' 		=> 'no',
		'desc' 			=> 'The email will be sent only when the product is confirmed to be in stock.'
	),


	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'em_bis',
		'id' 			=> 'bis-keep-wl',
		'title' 		=> 'Keep users on waitlist after email',
		'desc' 			=> 'Users will remain on the waitlist even after the email is sent, allowing future notifications.<br><b>Note: If disabled, users will be delted permanently from waitlist.</b>',
		'default' 		=> 'yes',
	),



	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'em_bis',
		'id'			=> 'bis-subject',
		'title' 		=> 'Email Subject',
		'default' 		=> 'The product you wanted is back in stock',
		'desc' 			=> '<a href="#xoo-wl-placeholder-nfo">List of Placeholders</a>',
		'args' 			=> array(
			'rows' 	=> 3,
			'cols' 	=> 70
		)
	),

	array(
		'callback' 		=> 'wp_editor',
		'section_id' 	=> 'em_bis',
		'id'			=> 'bis-heading',
		'title' 		=> 'Email Heading',
		'args' 			=> array(
			'group' 	=> 'email_content',
			'editor_settings' => array(
				'editor_height' => 100,	
			)
		),
		'default' 		=> '<p style="margin: 0 0 20px 0;"><u>Your Product is Now In Stock</u></p>',
		'desc' 			=> '<a href="#xoo-wl-placeholder-nfo">List of Placeholders</a>',
	),

	array(
		'callback' 		=> 'wp_editor',
		'title' 		=> 'Email Content',
		'id' 			=> 'bis-content',
		'section_id' 	=> 'em_bis',
		'args' 			=> array(
			'group' 	=> 'email_content',
			'editor_settings' => array(
				'editor_height' => 200,	
			)
		),
		'default' 		=> $email_content,
		'desc' 			=> '<a href="#xoo-wl-placeholder-nfo">List of Placeholders</a>',
	),

	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'em_bis',
		'id' 			=> 'bis-show-pimg',
		'title' 		=> 'Show Product Image',
		'default' 		=> 'yes',
	),



	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'em_bis',
		'id'			=> 'bis-buy-btn-txt',
		'title' 		=> 'Buy Now Button Text',
		'default' 		=> 'Buy Now',
	),

	array(
		'callback' 		=> 'wp_editor',
		'section_id' 	=> 'em_general',
		'id'			=> 'gl-ft-content',
		'title' 		=> 'Footer Content',
		'args' 			=> array(
			'group' 	=> 'email_content',
			'editor_settings' => array(
				'editor_height' => 200,	
			)
		),
		'default' 		=> $footer_content,
		'desc' 			=> '<a href="#xoo-wl-placeholder-nfo">List of Placeholders</a>',
	),


	array(
		'callback' 		=> 'upload',
		'section_id' 	=> 'em_general',
		'id'			=> 'gl-logo',
		'title' 		=> 'Header Logo',
		'default' 		=> XOO_WL_URL.'/assets/images/email-logo.png'
	),


	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'em_sender',
		'id'			=> 's-email',
		'title' 		=> '"From" Email',
		'default' 		=> esc_attr( get_option( 'admin_email' ) ),
		'desc' 			=> 'How the sender email appears in outgoing emails.'
	),


	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'em_sender',
		'id'			=> 's-name',
		'title' 		=> '"From" Name',
		'default' 		=> esc_attr( get_option( 'blogname' ) ),
		'desc' 			=> 'How the sender name appears in outgoing emails.'
	),


	

);

return apply_filters( 'xoo_wl_admin_settings', $settings, 'email' );

?>