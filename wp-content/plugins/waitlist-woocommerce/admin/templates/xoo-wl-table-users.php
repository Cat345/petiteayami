<?php

include XOO_WL_PATH.'/admin/class-xoo-wl-table-users-list.php';

$list_table = new Xoo_WL_Table_Users_List();

?>

<div class="wrap xoo-wl-table" id="xoo-wl-table-users">
	
	

	<form  method="get">

		<?php $list_table->prepare_items(); ?>

	

		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
		

		<div class="xoo-wl-topbar">	

			<?php xoo_wl_admin_settings()->waitlist_list_view_html( $list_table->count ) ?>

			<?php $list_table->search_box( 'Search users', 'xoo-wl-search' ); ?>
			
		</div>
		
		<?php $list_table->display(); ?>
	</form>
</div>