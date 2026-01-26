<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Xoo_WL_WPList_Table extends WP_List_Table {

	public function __construct(){
		$this->hooks();
	}

	public function hooks(){
		
	}

	

}