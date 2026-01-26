<?php

use BWFAN\Importers\Importer;

class BWFAN_API_Create_Import extends BWFAN_API_Base {
	public static $ins;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function __construct() {
		parent::__construct();
		$this->method = WP_REST_Server::CREATABLE;
		$this->route  = '/contacts/import';
	}

	public function process_api_call() {
		$importer_type = $this->get_sanitized_arg( 'importer_type', 'text_field' );
		$fields        = isset( $this->args['fields'] ) ? $this->args['fields'] : [];

		if ( empty( $importer_type ) ) {
			return $this->error_response( __( 'Importer type is required.', 'wp-marketing-automations' ), null, 400 );
		}

		// Create a new importer instance
		$importer = BWFAN_Core()->importer->get_importer( $importer_type );

		if ( ! $importer ) {
			return $this->error_response( __( 'Invalid importer type.', 'wp-marketing-automations' ), null, 400 );
		}


		/**
		 * Get the second step fields from the importer
		 *
		 * @return array.
		 * @var Importer $importer The importer object.
		 */
		$second_step_fields = method_exists( $importer, 'get_second_step_fields' ) ? $importer->get_second_step_fields( $fields ) : array();

		// Handle the additional fields needed for the configuration
		if ( isset( $second_step_fields['updated_schema'] ) && $second_step_fields['updated_schema'] && ! empty( $second_step_fields['fields'] ) ) {
			return $this->success_response( $second_step_fields, __( 'Additional fields fetched', 'wp-marketing-automations' ) );
		}

		if ( is_array( $second_step_fields ) && isset( $second_step_fields['status'] ) && 3 === $second_step_fields['status'] ) {
			return $this->error_response( $second_step_fields['message'], null, 400 );
		}

		$importer->create_import( $fields );
		$import_id = $importer->get_import_id();

		if ( $import_id === 0 ) {
			return $this->error_response( __( 'Error while creating a new import', 'wp-marketing-automations' ), null, 500 );
		}

		// Prepare the response data
		$response_data = array(
			'import_id' => $import_id,
			'data'      => [
				'second_step_schema' => $second_step_fields,
				'profile_fields'     => $importer->contact_profile_fields(),
				'stepValidation'     => method_exists( $importer, 'validate_second_step' ) ? $importer->validate_second_step() : true,
			]
		);

		return $this->success_response( $response_data, __( 'Import created successfully', 'wp-marketing-automations' ) );
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Create_Import' );