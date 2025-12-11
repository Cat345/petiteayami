<div class="xoo-wl-table-container">
	<div class="xoo-wl-notices"></div>
	<table id="xoo-wl-history-table" class="display xoo-wl-table">
		<thead>

			<tr>
				<th>Date</th>
				<th>Status</th>
				<th>Product</th>
				<th>Emails</th>
			</tr>

			<tbody>
			<?php foreach ( $crons as $_cron ) {
				$product_id 	= (int) $_cron->product_id;
				$product 		= wc_get_product( $product_id );

				if( !$product || !is_object( $product ) ) continue;

				$timestamp 		= $_cron->created;
				$status 		= $_cron->status;
				$count 			= (int) $_cron->emails_count;
				
				$edit_link 		= $product->is_type('variation') ? get_edit_post_link( $product->get_parent_id() ) : get_edit_post_link( $product_id );

			?>
				<tr data-product_id="<?php echo esc_attr( $product_id ); ?>">

					<td data-sort="<?php echo esc_attr( $timestamp ) ?>"><?php echo get_date_from_gmt( $timestamp, 'd M y <\b\r> h:i a' ); ?></td>

					<td class="xoo-wlht-status xoo-wlht-status-<?php echo esc_attr( $status ); ?>" ><?php echo esc_html( $status ); ?></td>

					<td class="xoo-wltd-pname">
						<div class="xoo-wl-pimg">
							<?php echo wp_kses_post( $product->get_image() ); ?>
							<a href="<?php echo esc_url( $edit_link ); ?>" target="_blank"><span><?php echo esc_html( $product->get_name() ); ?></span></a>
						</div>
					</td>

					<td><?php echo (int) $count; ?></td>

				</tr>

			<?php }; ?>

			</tbody>
		</thead>
	</table>
</div>

<a class="button" href="<?php echo wp_nonce_url( add_query_arg( 'clearLog', 'yes' ) ) ?>" style="background-color: #b32d2e; color: #fff">Clear Log & in queue events</a>
<i>Stores last 30 days entries</i>

<?php if( xoo_wl_core()->history_count < 10 ): ?>
	<style type="text/css">
		div#xoo-wl-history-table_length {
		    display: none;
		}
	</style>
<?php endif; ?>