<?php

/*
 * Maps
 */
function ubermenu_google_maps( $atts ){

	//Enqueue the API
	// wp_enqueue_script( 'google-maps' );
	ubermenu_enqueue_script( 'google-maps' );

	extract(shortcode_atts(array(
		'lat'		=>	null,
		'lng'		=>	null,
		'address'	=>	null,
		'zoom' 		=> 	15,
		'title'		=>	null,
		'width'		=>	'100%',
		'height'	=>	'200px'
	), $atts));

	$html_atts = [
		'class'	=> 'ubermenu-map-canvas',
		'data-lat' => $lat,
		'data-lng' => $lng,
		'data-address' => $address,
		'data-zoom' => $zoom,
		'data-mapTitle' => $title,
		'style' => "height:{$height};width:{$width}"
	];
	return '<div '.ubermenu_html_atts($html_atts).'></div>';

}
add_shortcode( 'ubermenu-map' , 'ubermenu_google_maps' );
