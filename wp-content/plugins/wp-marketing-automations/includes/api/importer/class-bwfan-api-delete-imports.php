<?php

class BWFAN_API_Delete_Imports extends BWFAN_API_Base {
	public static $ins;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function __construct() {
		parent::__construct();
		$this->method = WP_REST_Server::DELETABLE;
		$this->route  = '/contacts/import/delete';
	}

	public function process_api_call() {
		$ids            = isset( $this->args['ids'] ) ? (array) $this->args['ids'] : [];
		$delete_pending = false;
		if ( isset( $this->args['delete_pending'] ) && intval( $this->args['delete_pending'] ) === 1 ) {
			$delete_pending = true;
		}

		if ( empty( $ids ) && ! $delete_pending ) {
			return $this->error_response( __( 'No import IDs provided', 'wp-marketing-automations' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'bwfan_import_export';

		if ( ! $delete_pending ) {
			$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// Perform the delete operation
			$query = $wpdb->prepare( "DELETE FROM $table_name WHERE ID IN ($id_placeholders)", $ids );
		} else {
			$query = $wpdb->prepare( "DELETE FROM $table_name WHERE type = %d AND status = %d", 1, 0 );
		}

		$result = $wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL

		if ( $result === false ) {
			return $this->error_response( __( 'Failed to delete imports', 'wp-marketing-automations' ) );
		}

		$response = array(
			'deleted_count'   => $result,
			'total_requested' => count( $ids ),
		);

		return $this->success_response( $response, $delete_pending ? __( 'Successfully Deleted Pending Import', 'wp-marketing-automations' ) : __( 'Import deleted successfully', 'wp-marketing-automations' ) );
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Delete_Imports' );