<?php xoo_wl_admin_settings()->cron_not_working_html(); ?>

<?php


$show_qty = $fieldsData['xoo_wl_required_qty']['settings']['active'] === "yes";

$customFieldsData = $fieldsData;
unset( $customFieldsData['xoo_wl_required_qty'] );
unset( $customFieldsData['xoo_wl_user_email'] );

$extraFields = array();
$index 		 = 0;

foreach ( $customFieldsData as $field_id => $field_data ) {
	$settings = $field_data['settings'];
	if( $settings['active'] !== "yes" ) continue;
	$extraFields[ $index ]['heading'] = xoo_wl()->aff->fields->get_field_label( $field_data );
	$extraFields[ $index ]['id'] = $field_id;
	$index++;
}

if( $product = wc_get_product( $product_id ) ){
	?>

	<div class="xoo-wl-ut-header">
		<div class="xoo-wl-uth-cont">
			<?php echo wp_kses_post( $product->get_image() ); ?>
			<div class="xoo-wl-uth-right">

				<span><a href="<?php echo esc_url( $product->is_type('variation') ? get_edit_post_link( $product->get_parent_id() ) : get_edit_post_link( $product_id ) ); ?>" target="_blank"><?php echo $product->get_formatted_name(); ?></a></span>

				<span class="xoo-wl-ut-ucount"><?php printf( '<b>No of users:</b> <span>%s</span>', $count['rowsCount'] ); ?></span>

				<span class="xoo-wl-ut-qcount"><?php printf( '<b>Total quantity requested:</b> <span>%s</span>', $count['totalQuantity'] ); ?></span>

				<span><b>Stock Status: </b><span><?php echo esc_html( $product->get_stock_status() ); ?></span></span>

			</div>
		</div>
	</div>

	<?php
}

?>

<div class="xoo-wl-exim-cont <?php echo !function_exists('xoo_wl_exim') ? 'xoo-wl-exim-no' : '' ?>">

	<?php

	$args = array(
		'fields' 		=> $export_fields,
		'table_type' 	=> 'users_table',
		'product_id' 	=> $product_id
	);
	xoo_wl_helper()->get_template( "xoo-wl-export-form.php", $args, XOO_WL_PATH.'/admin/templates/' );

	if( !function_exists('xoo_wl_exim') ){
		?>
		<div class="xoo-wl-exim-no-notice">
			Export and Import waitlist is a separate add-on. <a href="https://xootix.com/waitlist-for-woocommerce#sp-addons" target="__blank">BUY</a>
		</div>
		<?php
	}

	?>

</div>



<div class="xoo-wl-table-container">
	<div class="xoo-wl-notices"></div>
	<table id="xoo-wl-users-table" class="display xoo-wl-table" data-product_id="<?php echo (int) $product_id; ?>">
		<thead>

			<tr>
				<th class="no-sort"><span class="dashicons dashicons-no-alt"></span></th>
				<th>Joined on</th>
				<th>Email</th>

				<?php if( $show_qty ): ?>
					<th>Quantity</th>
				<?php endif; ?>

				<?php foreach ( $extraFields as $extraFieldData ): ?>
					<th><?php echo esc_html( $extraFieldData['heading'] ); ?></th>
				<?php endforeach; ?>

				<th class="no-sort">Back in Stock Email</th>

			</tr>

			<tbody>
			<?php
				foreach ( $rows as $userRow ) {
				$timestamp 	= strtotime( $userRow->join_date);
				$row_id 	= (int) $userRow->xoo_wl_id;
				$meta_data 	= xoo_wl_db()->get_waitlist_meta( $row_id  );
				$sent_count = isset( $meta_data['_sent_count'] ) ? (int) $meta_data['_sent_count'] : '';
			?>
				<tr data-row_id="<?php echo (int) $row_id; ?>">

					<td><span class="dashicons dashicons-no-alt xoo-wl-remove-row"></span></td>
					<td data-sort="<?php echo esc_attr( $timestamp ) ?>" ><?php echo date( "d M y", $timestamp ); ?></td>
					<td class="xoo-wl-ut-email"><?php echo esc_html( $userRow->email ); ?><?php echo (int) $userRow->user_id ? '<span class="dashicons dashicons-yes-alt"></span>' : ''; ?></td>

					<?php if( $show_qty ): ?>

					<td><?php echo esc_html( $userRow->quantity ); ?></td>

					<?php endif; ?>

					<?php foreach ( $extraFields as $extraFieldData ):

						$field_id 	 = $extraFieldData['id'];
						$field_value = isset( $meta_data[ $field_id ] ) ? $meta_data[ $field_id ] : '';
						if( $field_value ){
							$field_value = xoo_wl()->aff->fields->get_field_value_label( $field_id, $field_value );
						}
						
					?>

					<td><?php echo esc_html( $field_value ); ?></td>
	
					<?php endforeach; ?>

					<td><span class="xoo-wl-bis-btn xoo-wl-table-btn">Send <span class="xoo-wl-sent-count"><?php echo (int) $sent_count ? '( '.$sent_count.' )': ''; ?></span></span></td>

				</tr>

			<?php }; ?>

			</tbody>
		</thead>
	</table>
</div>