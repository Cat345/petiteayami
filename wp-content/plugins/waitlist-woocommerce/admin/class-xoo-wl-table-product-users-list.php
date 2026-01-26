<?php

if ( ! class_exists( 'Xoo_WL_Table_Users_List_Parent' ) ) {
	require_once XOO_WL_PATH . '/admin/class-xoo-wl-table-users-list-parent.php';
}

class Xoo_WL_Table_Product_Users_List extends Xoo_WL_Table_Users_List_Parent {


	public $product_id;

	public $product;

	public $xoo_pageurl;

	public function __construct() {

		$this->product_id = (int) $_GET['product'];

		$this->product = wc_get_product( $this->product_id );

		if( !$this->product ){
			$this->send_email_button = false;
		}

		$this->xoo_pageurl = esc_url( add_query_arg( 'product', $this->product_id, admin_url( 'admin.php?page=xoo-wl-view-waitlist' ) ) );

		parent::__construct();
	}


	public function get_product_name(){
		return $this->items[0]['product_name'];
	}


}
