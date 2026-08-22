<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


$sections = array(



	array(
		'title' => 'Main',
		'id' 	=> 'main',
		'tab' 	=> 'fields',
		'args' 	=> array(
			'icon' 	=> 'xoo-icon-home'
		)
		
	),

	array(
		'title' => 'Input Field Icon',
		'id' 	=> 'input-icon',
		'tab' 	=> 'fields',
		'args' 	=> array(
			'icon' 	=> 'xoo-icon-tools'
		)
	),

	array(
		'title' => 'Input Field',
		'id' 	=> 'input',
		'tab' 	=> 'fields',
		'args' 	=> array(
			'icon' 	=> 'xoo-icon-page'
		)
	),

);

return $sections;