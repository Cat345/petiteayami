<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

return array(
	'basic' => array(
		'basic',
		'General',
		array(
			'priority' => 10
		),
	),
	'display' => array(
		'display',
		'Display',
		array(
			'priority' => 20
		),
	),
	'validation' => array(
		'validation',
		'Validation',
		array(
			'priority' => 30
		),
	),
	'advanced' => array(
 		'advanced',
 		'Advanced Settings',
 		array(
			'priority' => 40
		)
	)
);