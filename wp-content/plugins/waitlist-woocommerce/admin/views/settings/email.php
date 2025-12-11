<?php


$option_name = 'xoo-wl-email-options';

$email_content = 'You requested to be notified when [product_link] was back in stock and available for order.We are extremely pleased to announce that the product is now available for purchase. Please act fast, as the item may only be available in limited quantities.';

$footer_content = '[i]Thank you for choosing '.esc_html( get_option( 'blogname' ) ).'. If you have any questions, feel free to contact us at support@'.esc_html( get_option( 'blogname' ) ).'.[/i]';

$settings = array(

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


	array(
		'callback' 		=> 'upload',
		'section_id' 	=> 'em_general',
		'id'			=> 'gl-logo',
		'title' 		=> 'Header Logo',
		'default' 		=> XOO_WL_URL.'/assets/images/email-logo.png'
	),



	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'em_general',
		'id'			=> 'gl-ft-content',
		'title' 		=> 'Footer Content',
		'default' 		=> $footer_content,
		'desc' 			=> '<a href="#xoo-wl-placeholder-nfo">List of Placeholders</a>',
		'args' 			=> array(
			'rows' 	=> 8,
			'cols' 	=> 70
		),
	),


	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'em_bis',
		'id' 			=> 'bis-show-pimg',
		'title' 		=> 'Show Product Image',
		'default' 		=> 'yes',
	),

	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'em_bis',
		'id' 			=> 'bis-send-once',
		'title' 		=> 'Send email once',
		'default' 		=> 'no',
		'desc' 			=> "This will check if an email has been already sent to a user. If you mistakenly clicks twice on send button, it won't send another email.",
	),



	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'em_bis',
		'id' 			=> 'bis-check-stock',
		'title' 		=> 'Force check product stock status',
		'default' 		=> 'no',
		'desc' 			=> 'Before sending back in stock email, this will check if the product is actually in stock or not.'
	),


	array(
		'callback' 		=> 'checkbox',
		'section_id' 	=> 'em_bis',
		'id' 			=> 'bis-keep-wl',
		'title' 		=> 'Keep waitlist after sending email.',
		'default' 		=> 'yes',
	),



	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'em_bis',
		'id'			=> 'bis-subject',
		'title' 		=> 'Subject',
		'default' 		=> 'The product you wanted is back in stock',
		'desc' 			=> '<a href="#xoo-wl-placeholder-nfo">List of Placeholders</a>',
		'args' 			=> array(
			'rows' 	=> 3,
			'cols' 	=> 70
		)
	),


	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'em_bis',
		'id'			=> 'bis-heading',
		'title' 		=> 'Heading',
		'default' 		=> 'Your Product is Now In Stock.',
		'desc' 			=> '<a href="#xoo-wl-placeholder-nfo">List of Placeholders</a>',
		'args' 			=> array(
			'rows' 	=> 2,
			'cols' 	=> 70
		)
	),

	array(
		'callback' 		=> 'textarea',
		'section_id' 	=> 'em_bis',
		'id'			=> 'bis-content',
		'title' 		=> 'Content',
		'default' 		=> $email_content,
		'desc' 			=> '<a href="#xoo-wl-placeholder-nfo">List of Placeholders</a>',
		'args' 			=> array(
			'rows' 	=> 8,
			'cols' 	=> 70
		)
	),


	array(
		'callback' 		=> 'text',
		'section_id' 	=> 'em_bis',
		'id'			=> 'bis-buy-btn-txt',
		'title' 		=> 'Buy Now Button Text',
		'default' 		=> 'Buy Now',
	),
);

return apply_filters( 'xoo_wl_admin_settings', $settings, 'email' );

?>