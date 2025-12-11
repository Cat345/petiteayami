<?php

$shortcodes = array(
	'xoo_wl_form' => array(
		'shortcode' => '[xoo_wl_form]',
		'desc' 		=> 'Creates a link/button to open popup',
		'example' 	=> '[xoo_wl_form id="10" type="inline_toggle" text="Join Waitlist"]',
		'atts' 		=> array(
			array(
				'type',
				'popup, inline_toggle, inline',
				'popup',
				'Waitlist form display type'
			),
			array(
				'id',
				"(int) Product ID (Optional)",
				'',
				"Product ID | Not required if you're using the shortcode on a product page or in a loop such as shop page"
			),
			array(
				'text',
				'Custom Text',
				'Join Waitlist',
				'Button Text'
			)
		)
	)
);

return apply_filters( 'xoo_el_shortcode_info_tab', $shortcodes );

?>