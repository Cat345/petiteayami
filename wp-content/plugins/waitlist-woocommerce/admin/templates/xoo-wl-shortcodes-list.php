<?php

$placeholders = array(
	'[user_email]' 		=> 'User email',
	'[quantity]' 		=> 'Quantity requested',
	'[join_date]' 		=> 'Waitlisted Date',
	'[product_id]' 		=> 'Product ID',
	'[product_name]' 	=> 'Product name',
	'[product_link]' 	=> 'Product link',
	'[product_price]' 	=> 'Product price'
);


$customFields = xoo_wl()->aff->fields->get_fields_data();



$predefined_fields = array(
	'xoo_wl_user_email',
	'xoo_wl_required_qty'
);

foreach ( $customFields as $field_id => $field_data ) {
	if( in_array( $field_id , $predefined_fields ) ) continue;
	$settings = $field_data['settings'];
	$label = $settings['label'] ? $settings['label'] : ( $settings['placeholder'] ? $settings['placeholder'] : $field_id.' value' );
	$placeholders['['.$field_id.']'] = $label;
}

$placeholders = apply_filters( 'xoo_wl_settings_placeholders', $placeholders );


$placeholders_text = '';

foreach ( $placeholders as $key => $desc ) {
	$placeholders_text .= '<span>'.$key .' - '.$desc.'</span>';
}

?>
<h4>Placeholders</h4>

<div id="xoo-wl-placeholder-nfo"><?php echo wp_kses_post( $placeholders_text ); ?></div>