<?php

add_shortcode( 'ubermenu_image_portal' , 'ubermenu_image_portal_shortcode' );
function ubermenu_image_portal_shortcode( $atts, $content ){
	extract( shortcode_atts(
		[
			'class' => '',
			'select' => '',
		], $atts , 'ubermenu_image_portal' )
	);

	return ubermenu_image_portal( $select, $class, $content );
}
function ubermenu_image_portal( $select, $class = '', $default_content = '' ){
	if( !$select ){
		return '<!-- UberMenu Image Portal: No items selects -->';
	}

	$atts = ubermenu_html_atts([
		'class' => 'ubermenu-image-portal '.$class,
		'data-ubermenu-portal-select' => $select,
	]);

	if( $default_content ){
		$default_content = '<div class="ubermenu-image-portal__default">'.do_shortcode($default_content).'</div>';
	}

	return "<!-- UberMenu Image Portal --><div $atts>{$default_content}</div><!-- End UberMenu Image Portal -->";
}