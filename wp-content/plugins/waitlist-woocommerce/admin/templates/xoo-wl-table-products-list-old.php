<?php xoo_wl_admin_settings()->cron_not_working_html(); ?>

<div class="xoo-wl-exim-cont <?php echo !function_exists('xoo_wl_exim') ? 'xoo-wl-exim-no' : '' ?>">
	<?php

	$args = array(
		'fields' 		=> $export_fields,
		'table_type' 	=> 'products_table'
	);
	xoo_wl_helper()->get_template( "xoo-wl-export-form.php", $args, XOO_WL_PATH.'/admin/templates/' );

	xoo_wl_helper()->get_template( "xoo-wl-import-form.php", array(), XOO_WL_PATH.'/admin/templates/' );

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
	<table id="xoo-wl-products-table" class="display xoo-wl-table">
		<thead>

			<tr>
				<th>Product</th>
				<th>Stock Status</th>
				<th>Total Quantity</th>
				<th>Total Users</th>
				<th class="no-sort">Actions</th>
			</tr>

			<tbody>
			<?php foreach ( $rows as $productRow ) {
				$product_id 	= (int) $productRow->product_id;
				$product 		= wc_get_product( $product_id );

				if( !$product || !is_object( $product ) ) continue;
				
				$edit_link 		= $product->is_type('variation') ? get_edit_post_link( $product->get_parent_id() ) : get_edit_post_link( $product_id );

				$product_title  = apply_filters( 'xoo_wl_admin_products_table_product_title', $product->get_formatted_name(), $productRow );



			?>
				<tr data-product_id="<?php echo (int) $product_id; ?>">

					<td class="xoo-wltd-pname">
						<div class="xoo-wl-pimg">
							<span class="dashicons dashicons-no-alt xoo-wl-remove-row"></span>
							<?php echo wp_kses_post( $product->get_image() ); ?>
							<a href="<?php echo esc_url( $edit_link ); ?>" target="_blank"><span><?php echo $product_title; ?></span></a>
						</div>
					</td>

					<td><?php echo esc_html( $product->get_stock_status() ); ?></td>

					<td><?php echo esc_html( $productRow->quantity ); ?></td>

					<td><?php echo esc_html( $productRow->entries );?></td>

					<td><span class="xoo-wl-bis-btn xoo-wl-table-btn">Send Email</span> <a href="<?php echo esc_url( $_SERVER['REQUEST_URI'] ).'&product='.$product_id; ?>" class="xoo-wl-vu-btn xoo-wl-table-btn">View Users</span></td>

				</tr>

			<?php }; ?>

			</tbody>
		</thead>
	</table>
</div>