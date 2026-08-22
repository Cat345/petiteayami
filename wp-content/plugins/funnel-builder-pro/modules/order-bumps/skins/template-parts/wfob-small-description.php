<div class="wfob_l3_c_sub_desc  show-read-more"><?php
$wfob_small_desc_allowed = array_merge(
	wp_kses_allowed_html( 'post' ),
	array(
		'input' => array(
			'type'  => true,
			'class' => true,
			'min'   => true,
			'max'   => true,
			'value' => true,
			'step'  => true,
			'name'  => true,
			'id'    => true,
		),
	)
);
echo wp_kses( $small_description, $wfob_small_desc_allowed );
?></div>
