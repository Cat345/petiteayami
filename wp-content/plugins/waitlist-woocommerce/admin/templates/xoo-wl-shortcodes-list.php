<?php

$placeholders = array(
	'[user_email]' 			=> 'User email',
	'[quantity]' 			=> 'Quantity requested',
	'[join_date]' 			=> 'Waitlisted Date',
	'[product_id]' 			=> 'Product ID',
	'[product_name]' 		=> 'Product name',
	'[product_image]' 		=> 'Product image',
	'[product_image_link]' 	=> 'Product image link',
	'[product_link]' 		=> 'Product link',
	'[product_link_raw]' 	=> 'Raw Product link',
	'[product_price]' 		=> 'Product price'
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

ob_start();

foreach ( $placeholders as $key => $desc ) {

	?>


	<div class="xoo-placeholder" data-placeholder="<?php echo $key ?>">
		<span class="xoo-placeholder__token"><?php echo $key ?></span>
		<span class="xoo-placeholder__description"><?php echo $desc ?></span>
		<span class="xoo-placeholder__copy">
			<i class="xoo-icon-copy"></i>
		</span>
	</div>
	
	<?php

	}

	$placeholders_text = ob_get_clean();

?>

<div class="xoo-ass-section">
	<div class="xoo-asc-head">
		<span class="xoo-asch-title ">Placeholders</span>
	</div>

	<div id="xoo-wl-placeholder-nfo"><?php echo wp_kses_post( $placeholders_text ); ?></div>
</div>