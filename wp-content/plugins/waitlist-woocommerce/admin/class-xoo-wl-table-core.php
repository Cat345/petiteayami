<?php

class Xoo_Wl_Table_Core{

	protected static $_instance = null;

	public static function get_instance(){
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	
	public function __construct(){
		$this->hooks();
	}


	public function hooks(){

		add_action( 'wp_ajax_xoo_wl_table_remove_row', array( $this, 'remove_row' ) );
		add_action( 'wp_ajax_xoo_wl_table_send_email', array( $this, 'send_email' ) );
		
	}


	public function send_email(){

		if ( !wp_verify_nonce( $_POST['xoo_wl_nonce'], 'xoo-wl-nonce' ) ){
			die('cheating');
		}

		try {

			if( !isset( $_POST['productID'] ) && !isset( $_POST['rowID'] ) ){
				throw new Xoo_Exception( 'Product/Row ID not found' );
			}

			$email_sent = false;

			$product_id = isset( $_POST['productID'] ) ? (int) $_POST['productID'] : false;

			$row_id = isset( $_POST['rowID'] ) ? (int) $_POST['rowID'] : false;

			if( $row_id ){
				$email_sent = xoo_wl_core()->send_back_in_stock_email( $row_id );
				$message 	= 'Email Sent';
			}

			if( $product_id ){

				$email_sent  = xoo_wl_core()->trigger_back_in_stock_email_for_product( $product_id );
				$message 	 = sprintf( 'Sending emails....This may take few seconds to minutes depending on your list. You can check the status <a target="#blank" href="%s">here</a>', xoo_wl_urls( 'email_history' ) );
				
			}

			if( is_wp_error( $email_sent ) ){
				throw new Xoo_Exception( $email_sent );
			}


			if( $email_sent ){
				wp_send_json(array(
					'error' 		=> 0,
					'notice' 		=> xoo_wl_add_notice( $message, 'success'  ),
					'delete_row' 	=> xoo_wl_helper()->get_email_option( 'bis-keep-wl' ) !== "yes",
					'sent_count' 	=> $row_id ? xoo_wl_db()->get_waitlist_meta( $row_id , '_sent_count' ) : false
				));
			}
			else{
				throw new Xoo_Exception( 'Unable to send email. Please check troubleshoot section under info tab ( settings ).' );
			}
			
		} catch (Xoo_Exception $e) {
			
			wp_send_json(array(
				'error' 	=> 1,
				'notice' 	=> xoo_wl_add_notice( $e->getMessage(), 'error'  )
			));
		}
	}



	public function remove_row(){

		if ( !wp_verify_nonce( $_POST['xoo_wl_nonce'], 'xoo-wl-nonce' ) ){
			die('cheating');
		}

		
		try {

			if( !isset( $_POST['productID'] ) ){
				throw new Xoo_Exception( 'Product ID not found' );
			}

			if( $_POST['remove'] === 'user' && !isset( $_POST['rowID'] ) ){
				throw new Xoo_Exception( 'Row ID not found' );
			}

			$row_id 	= (int) $_POST['rowID'];
			$product_id = (int) $_POST['productID'];

			if( $_POST['remove'] === 'user' ){
				$delete = xoo_wl_db()->delete_waitlist_row_by_id( $row_id );
			}
			else{
				$delete = xoo_wl_db()->delete_waitlist_by_product( $product_id );
			}

			if( is_wp_error( $delete ) ){
				throw new Xoo_Exception( $delete->get_error_message() );
			}

			wp_send_json(array(
				'error' 	=> 0,
				'notice' 	=> xoo_wl_add_notice( 'Deleted successfully', 'success'  ),
				'count' 	=> xoo_wl_db()->get_waitlisted_count( $product_id )
			));
			
		} catch (Xoo_Exception $e) {
			wp_send_json(array(
				'error' 	=> 1,
				'notice' 	=> xoo_wl_add_notice( $e->getMessage(), 'error' )
			));
		}

		wp_die();

	}


}

function xoo_wl_table_core(){
	return Xoo_Wl_Table_Core::get_instance();
}
xoo_wl_table_core();