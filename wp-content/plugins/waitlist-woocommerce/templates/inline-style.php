<?php

$sySettings 		= xoo_wl_helper()->get_style_option();


$new_btn_layout  	= !isset( $sySettings['btn-newlayout'] ) || $sySettings['btn-newlayout'] === "yes";

$popup_width 		=$sySettings['popup-width'];
$popup_height 		=$sySettings['popup-height'];
$sidebar_img  		=$sySettings['popup-sidebar-img'];
$sidebar_width 		=$sySettings['popup-sidebar-width'];
$sidebar_pos 		=$sySettings['popup-sidebar-pos'];
$popup_pos 			=$sySettings['popup-pos'];
$popup_heightType  	=$sySettings['popup-height-type'];



$btn_bg_color 		=$sySettings['btn-bgcolor'];
$btn_txt_color 		=$sySettings['btn-txtcolor'];
$btn_form_width 	=$sySettings['btn-form-width'];
$btn_open_width 	=$sySettings['btn-open-width'];
$btn_padding 		=$sySettings['btn-padding'];


$inline_style = "
	.xoo-wl-inmodal{
		max-width: {$popup_width}px;
		max-height: {$popup_height}px;
	}
";

if( $sidebar_img ){
	$inline_style .= "
	.xoo-wl-sidebar{
		background-image: url({$sidebar_img});
		min-width: {$sidebar_width}%;
	}";
}

if($sidebar_pos == 'right'){
	$inline_style .= "
		.xoo-wl-wrap{
			direction: rtl;
		}
		.xoo-wl-wrap > div{
			direction: ltr;
		}

	";
}



if($popup_pos  === 'middle'){
	$inline_style .= "
		.xoo-wl-modal:before {
		    content: '';
		    display: inline-block;
		    height: 100%;
		    vertical-align: middle;
		    margin-right: -0.25em;
		}
	";
}
else{
	$inline_style .= "
		.xoo-wl-inmodal{
			margin-top: 40px;
		}

	";
}

if( $popup_heightType === 'auto' ){
	$inline_style .= "
		.xoo-wl-inmodal{
			display: inline-flex;
			max-height: 90%;
			height: auto;
		}

		.xoo-wl-sidebar, .xoo-wl-wrap{
			height: auto;
		}
	";
}

?>


<?php if( $new_btn_layout  ){

	$buttonThemes = $sySettings['btnthemes'];

	$buttonThemeSelectorMap = array(
		'btntheme-form' 	=> 'form.xoo-wl-form button[type=submit].xoo-wl-submit-btn',
		'btntheme-product' 	=> '.xoo-wl-btn-container.xoo-wl-prod-btncont button.xoo-wl-action-btn.xoo-wl-open-form-btn',
		'btntheme-shop' 	=> '.xoo-wl-btn-container.xoo-wl-shop-btncont button.xoo-wl-action-btn.xoo-wl-open-form-btn',
		'btntheme-action' 	=> '.xoo-wl-btn-container:not(.xoo-wl-shop-btncont, .xoo-wl-prod-btncont) button.xoo-wl-action-btn.xoo-wl-open-form-btn',
	);

	foreach ($buttonThemeSelectorMap as $themeOption => $themeClasses ) {

		if( !isset( $sySettings[ $themeOption ] ) ) continue;

		$buttonThemeValues = $buttonThemes[ $sySettings[ $themeOption ] ];

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$inline_style .= xoo_wl_helper()->get_button_css( $themeClasses, $buttonThemeValues );

		if( $themeOption === 'btntheme-form' ){
			$width_type = $buttonThemeValues['size_type'] === 'custom' ? 'width' : 'max-width';
			$inline_style .= esc_html( $themeClasses ). '{
				'.$width_type.': calc('.esc_html( $buttonThemeValues['width'] ).esc_html( $buttonThemeValues[ 'width_unit' ] ).' - 20px );
				margin: 0 20px 0 0;
			}';
			$inline_style .= 'body.rtl '.esc_html( $themeClasses ). '{
				margin-right: 0;
				margin-left: 20px;
			}';
		}

	}

	$inline_style .= "form.xoo-wl-form {
	    display: flex;
	    flex-direction: column;
	    align-items: center;
	}";
	

}
else{
	$inline_style .= "
		button.xoo-wl-action-btn{
			background-color: {$btn_bg_color};
			color: {$btn_txt_color};
			padding: {$btn_padding}px;
		}
		button.xoo-wl-submit-btn{
			max-width: {$btn_form_width}px;
		}
		button.xoo-wl-open-form-btn{
			max-width: {$btn_open_width}px;
		}
	";
}

echo $inline_style;