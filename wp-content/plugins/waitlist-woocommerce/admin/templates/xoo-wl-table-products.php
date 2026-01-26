<?php

include XOO_WL_PATH.'/admin/class-xoo-wl-table-products-list.php';

$list_table = new Xoo_WL_Table_Products_List();


?>

<div class="wrap xoo-wl-table" id="xoo-wl-table-products">
	
	

	<form  method="get">

		<?php $list_table->prepare_items(); ?>


		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
		

		<div class="xoo-wl-topbar">	

			<?php xoo_wl_admin_settings()->waitlist_list_view_html( $list_table->count ) ?>


			<?php $list_table->search_box( 'Search Products', 'xoo-wl-search' ); ?>
			
		</div>
		<?php $list_table->display(); ?>
	</form>
</div>