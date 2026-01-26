<?php

use BWFAN\Importers\Importer;

class BWFAN_Api_Get_Importers extends BWFAN_API_Base {

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
		$this->route  = '/contacts/importers';
	}

	/**
	 * @throws ReflectionException
	 */
	public function process_api_call() {
		// Get all available importers
		$importer_classes = BWFAN_Core()->importer->get_importers();

		if ( empty( $importer_classes ) ) {
			return $this->error_response( __( "No importers found", 'wp-marketing-automations' ) );
		}

		// Prepare the importers data
		$importers_data = array();
		foreach ( $importer_classes as $slug => $importer_class ) {
			$importer = new $importer_class();

			if ( $importer instanceof Importer ) {
				$importers_data[ 0 ][] = array(
					'slug'          => $slug,
					'name'          => $importer->name,
					'description'   => $importer->description,
					'logo_url'      => $importer->logo_url,
					'has_fields'    => $importer->has_fields,
					'field_schema'  => $importer->get_field_schema(),
					'defaultValues' => $importer->get_default_values(),
					'priority'      => $importer->priority,
					'submit_text'  => ! empty( $importer->submit_text ) ? $importer->submit_text : __( 'Import', 'wp-marketing-automations' ),
				);
			}
		}

		if ( empty( $importers_data ) ) {
			return $this->error_response( __( "No valid importers found", 'wp-marketing-automations' ) );
		}

		// Sort the importers by group and priority
		foreach ( $importers_data as $group => $importers ) {
			usort( $importers, function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			} );
			$importers_data[ $group ] = $importers;
		}

		$this->response_code = 200;

		return $this->success_response( $importers_data );
	}

}

BWFAN_API_Loader::register( 'BWFAN_Api_Get_Importers' );
