<?php

include XOO_WL_PATH.'/admin/class-xoo-wl-table-product-users-list.php';

$product_id = (int) $_GET['product'];

$list_table = new Xoo_WL_Table_Product_Users_List();


?>

<div class="wrap xoo-wl-table" id="xoo-wl-table-users">
	

	<form  method="get">

		<?php $list_table->prepare_items(); ?>

		<div class="xoo-wl-product-info">

		
			<?php if( !$list_table->product ): ?>

				<?php echo xoo_wl_admin_settings()->waitlist_product_not_exist( $list_table->get_product_name() ); ?>

			<?php else: ?>

				<?php ob_start(); ?>

				<div class="xoo-wl-product-count">
					<div>
						<label>Users: </label><span><?php echo $list_table->count['rowsCount'] ?></span>
					</div>
					<div>
						<label>Quantity Requested: </label><span><?php echo $list_table->count['totalQuantity'] ?></span>
					</div>
				</div>

				<?php $info_html = ob_get_clean(); ?>

				<?php echo xoo_wl_admin_settings()->waitlist_table_product_column( $list_table->product, array(
					'info_html' => $info_html,
			 	) ) 
				?>

			<?php endif; ?>
			
		</div>

		<input type="hidden" name="product" value="<?php echo esc_attr( $_GET['product'] ); ?>">

		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
		

		<div class="xoo-wl-topbar">	

			
			<a href="<?php echo esc_url( add_query_arg( 'list', 'products', admin_url( 'admin.php?page=xoo-wl-view-waitlist' ) ) ); ?>">Back to Products</a>

			<?php $list_table->search_box( 'Search users', 'xoo-wl-search' ); ?>
			
		</div>
		
		<?php $list_table->display(); ?>
	</form>
</div>