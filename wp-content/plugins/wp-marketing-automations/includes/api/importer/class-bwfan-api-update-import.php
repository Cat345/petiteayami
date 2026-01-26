<?php

use BWFAN\Importers\Importer;

class BWFAN_API_Update_Import extends BWFAN_API_Base {
	public static $ins;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function __construct() {
		parent::__construct();
		$this->method = WP_REST_Server::EDITABLE;
		$this->route  = '/contacts/import/(?P<id>\d+)';
	}

	public function process_api_call() {
		$importer_type = $this->get_sanitized_arg( 'type', 'text_field' );
		$data          = $this->args['data'] ?? [];
		$import_id     = $this->get_sanitized_arg( 'id', 'text_field' );

		if ( empty( $import_id ) ) {
			return $this->error_response( __( 'Import ID not found', 'wp-marketing-automations' ), null, 400 );
		}

		$importer = BWFAN_Core()->importer->get_importer( $importer_type, array( 'import_id' => $import_id ) );

		if ( ! $importer ) {
			return $this->error_response( __( 'Importer not found', 'wp-marketing-automations' ), null, 404 );
		}

		// Validate array sizes to prevent DoS attacks
		$tags = ! empty( $data['tags'] ) && is_array( $data['tags'] ) ? $data['tags'] : array();
		$lists = ! empty( $data['lists'] ) && is_array( $data['lists'] ) ? $data['lists'] : array();

		$update_data = [
			'tags'                    => $tags,
			'lists'                   => $lists,
			'update_existing'         => ! empty( $data['update_existing'] ),
			'disable_events'          => ! empty( $data['disable_events'] ),
			'dont_update_blank'       => ! empty( $data['dont_update_blank'] ),
			'trigger_events'          => ! empty( $data['trigger_events'] ),
			'marketing_status'        => ! empty( $data['marketing_status'] ) ? sanitize_text_field( $data['marketing_status'] ) : '',
			'imported_contact_status' => isset( $data['imported_contact_status'] ) ? absint( $data['imported_contact_status'] ) : 0,
			'update_existing_fields'  => ! empty( $data['update_existing_fields'] )
		];

		/**
		 * Check if importer has specific validation/data handling
		 *
		 * @var Importer $importer The importer object.
		 *
		 */
		if ( method_exists( $importer, 'handle_update' ) ) {
			$result = $importer->handle_update( $data );
			if ( is_wp_error( $result ) ) {
				return $this->error_response( $result->get_error_message(), null, 500 );
			}
			if ( ! empty( $result ) ) {
				$update_data = array_merge( $update_data, $result );
			}
		}

		// Update meta
		$importer->update_import_meta( $update_data );

		// Start import
		$importer->start_import();

		$importer_label = $importer->name;
		/* translators: 1: Importer label */
		return $this->success_response( $importer->get_status(), sprintf( __( '%s import updated successfully', 'wp-marketing-automations' ), $importer_label ) );
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Update_Import' );