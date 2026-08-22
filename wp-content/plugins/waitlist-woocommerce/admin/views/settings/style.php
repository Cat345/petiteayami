<?php



$settings = array(


	/** Button **/

	array(
		'callback' 		=> 'checkbox',
		'title' 		=> 'New Button Layout',
		'id' 			=> 'btn-newlayout',
		'section_id' 	=> 'sy_button',
		'args' 			=> array(
			'toggleSettings' => array(
				'xoo-wl-style-options[btn-bgcolor]' 		=> array( 'yes' ),
				'xoo-wl-style-options[btn-txtcolor]' 		=> array( 'yes' ),
				'xoo-wl-style-options[btn-form-width]' 		=> array( 'yes' ),
				'xoo-wl-style-options[btn-open-width]' 		=> array( 'yes' ),
				'xoo-wl-style-options[btn-padding]' 		=> array( 'yes' ),
				'xoo-wl-style-options[btntheme-action]' 	=> array('unchecked'),
				'xoo-wl-style-options[btntheme-shop]' 		=> array('unchecked'),
				'xoo-wl-style-options[btntheme-product]' 	=> array('unchecked'),
				'xoo-wl-style-options[btntheme-form]' 		=> array('unchecked'),
			)
		),
		'default' => 'yes'
		
	),

	array(	
		'callback' 		=> 'color',
		'section_id' 	=> 'sy_button',
		'id'			=> 'btn-bgcolor',
		'title' 		=> 'Background Color',
		'default' 		=> '#333'
	),

	array(
		'callback' 		=> 'color',
		'section_id' 	=> 'sy_button',
		'id'			=> 'btn-txtcolor',
		'title' 		=> 'Text Color',
		'default' 		=> '#fff'
	),


	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'sy_button',
		'id'			=> 'btn-form-width',
		'title' 		=> 'Submit Button Width',
		'default' 		=> '300',
		'desc'			=> 'Width in px'
	),


	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'sy_button',
		'id'			=> 'btn-open-width',
		'title' 		=> 'Open Button Width',
		'default' 		=> '300',
		'desc'			=> 'Width in px'
	),


	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'sy_button',
		'id'			=> 'btn-padding',
		'title' 		=> 'Padding',
		'default' 		=> '10',
		'desc'			=> 'Padding in px'
	),

	array(
		'callback' 		=> 'button_theme_creator',
		'title' 		=> '',
		'id' 			=> 'btnthemes',
		'section_id' 	=> 'sy_button_theme_creator',
		'default' 		=> array(
			'form' => xoo_wl_helper()->get_button_values( array(
				'theme_id' 	=> 'form',
				'title' 	=> 'Form Submit Button Theme',
				'width'    	=> 100,
			), 'light' ),
			'product' => xoo_wl_helper()->get_button_values( array(
				'theme_id'	=> 'product',
				'title' 	=> 'Product Button Theme',
				'position' 	=> is_rtl() ? 'right' : 'left',
				'size_type' => 'auto',
				'margin_h' 	=> 0
			) ),
			'shop' => xoo_wl_helper()->get_button_values( array(
				'theme_id'	=> 'shop',
				'title' 	=> 'Shop Button Theme',
				'size_type' => 'auto',
				
			) ),
			'default_theme1' => xoo_wl_helper()->get_button_values( array(
				'theme_id'	=> 'default_theme1',
				'title' 	=> 'Default Theme #1',
				'size_type' => 'auto',
				'position' 	=> 'left'
			) ),
		)
	),

	array(
		'callback' 		=> 'button_theme_selector',
		'title' 		=> 'Form submit button',
		'id' 			=> 'btntheme-form',
		'section_id' 	=> 'sy_button_theme_creator',
		'default' 		=> 'form'
	),

	array(
		'callback' 		=> 'button_theme_selector',
		'title' 		=> 'Shop page button',
		'id' 			=> 'btntheme-shop',
		'section_id' 	=> 'sy_button_theme_creator',
		'default' 		=> 'shop'
	),

	array(
		'callback' 		=> 'button_theme_selector',
		'title' 		=> 'Product page button',
		'id' 			=> 'btntheme-product',
		'section_id' 	=> 'sy_button_theme_creator',
		'default' 		=> 'product'
	),

	array(
		'callback' 		=> 'button_theme_selector',
		'title' 		=> 'Shortcode/Other button',
		'id' 			=> 'btntheme-action',
		'section_id' 	=> 'sy_button_theme_creator',
		'default' 		=> 'default_theme1'
	),


	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'sy_popup',
		'id'			=> 'popup-pos',
		'title' 		=> 'Position',
		'default' 		=> 'middle',
		'args'			=> array(
			'options' => array(
				'top'  => 'Top',
				'middle' => 'Middle',
			)	
		)
	),


	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'sy_popup',
		'id'			=> 'popup-width',
		'title' 		=> 'Popup Width',
		'default' 		=> 700,
		'desc'			=> 'Size in px'
	),


	array(
		'callback' 		=> 'select',
		'title' 		=> 'Popup Height',
		'id' 			=> 'popup-height-type',
		'section_id' 	=> 'sy_popup',
		'args'			=> array(
			'options' => array(
				'custom' 	=> 'Custom',
				'auto' 		=> 'Auto Adjust'
			)
		),
		'default' 		=> 'auto',
	),

	array(
		'callback' 		=> 'number',
		'title' 		=> 'Custom Popup Height',
		'id' 			=> 'popup-height',
		'section_id' 	=> 'sy_popup',
		'default' 		=> 450,
		'desc' 			=> 'size in px'
	),



	array(
		'callback' 		=> 'upload',
		'section_id' 	=> 'sy_popup',
		'id'			=> 'popup-sidebar-img',
		'title' 		=> 'Sidebar Image',
		'default' 		=> XOO_WL_URL.'/assets/images/waitlist-sidebar.jpg',
		'desc'			=> 'Supported format: JPEG,PNG',
		'args'			=> array(
			'upload_type' => 'image'
		)
	),

	array(
		'callback' 		=> 'select',
		'section_id' 	=> 'sy_popup',
		'id'			=> 'popup-sidebar-pos',
		'title' 		=> 'Sidebar Position',
		'default' 		=> 'left',
		'args'			=> array(
			'options' => array('left','right')	
		)
	),

	array(
		'callback' 		=> 'number',
		'section_id' 	=> 'sy_popup',
		'id'			=> 'popup-sidebar-width',
		'title' 		=> 'Sidebar Width',
		'default' 		=> '40',
		'desc'			=> 'Width in percentage'
	),



);

return apply_filters( 'xoo_wl_admin_settings', $settings, 'style' );

?>