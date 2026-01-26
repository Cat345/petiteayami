<?php

use BWFAN\Importers\Importer;

class BWFAN_API_Cancel_Import extends BWFAN_API_Base {
	public static $ins;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function __construct() {
		parent::__construct();
		$this->method = WP_REST_Server::READABLE;
		$this->route  = '/contacts/import/(?P<id>\d+)/cancel';
	}

	public function process_api_call() {
		$import_id = absint( $this->get_sanitized_arg( 'id', 'text_field' ) );

		$action = $this->get_sanitized_arg( 'action', 'text_field' );

		// Check if the action is to dismiss the current import
		if ( $action === 'dismiss' ) {
			BWFAN_Importer::update_import_option( '' );
			return $this->success_response( [], __( 'Successfully Dismissed Importer.', 'wp-marketing-automations' ) );
		}

		// Check if the import ID is provided
		if ( empty( $import_id ) ) {
			return $this->error_response( __( 'Import ID not found.', 'wp-marketing-automations' ), null, 400 );
		}

		$import = BWFAN_Model_Export_Import::get( $import_id );
		// Check if the import exists
		if ( ! $import ) {
			return $this->error_response( __( 'Invalid Import ID.', 'wp-marketing-automations' ), null, 400 );
		}

		// If import is scheduled, cancel it
		if ( bwf_has_action_scheduled( BWFAN_Importer::$IMPORTER_ACTION_HOOK, array( 'import_id' => $import_id ), 'bwfan' ) ) {
			bwf_unschedule_actions( BWFAN_Importer::$IMPORTER_ACTION_HOOK, array( 'import_id' => $import_id ), 'bwfan' );
		}
		BWFAN_Importer::update_import_option( '' );

		// Changed status to canceled
		$status = BWFAN_Model_Export_Import::update( [ 'status' => BWFAN_Importer::$IMPORT_CANCELLED ], [ 'id' => $import_id ] );

		return $this->success_response( [ 'status' => $status], __( 'Successfully Updated Import Status.', 'wp-marketing-automations' ) );
	}

}

BWFAN_API_Loader::register( 'BWFAN_API_Cancel_Import' );