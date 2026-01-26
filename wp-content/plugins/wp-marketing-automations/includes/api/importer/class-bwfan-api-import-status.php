<?php

use BWFAN\Importers\Importer;

class BWFAN_API_Import_Status extends BWFAN_API_Base {
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
		$this->route  = '/contacts/import/(?P<id>\d+)/status';
	}

	public function process_api_call() {
		$import_id = intval( $this->get_sanitized_arg( 'id', 'text_field' ) );

		if ( empty( $import_id ) ) {
			return $this->error_response( __( 'Import ID is required.', 'wp-marketing-automations' ), null, 400 );
		}

		$import = BWFAN_Model_Export_Import::get( $import_id );
		if ( ! $import ) {
			return $this->error_response( __( 'Invalid Import ID.', 'wp-marketing-automations' ), null, 404 );
		}


		$meta = $import['meta'] ?? '';
		$meta = ! empty( $meta ) ? json_decode( $meta, true ) : [];
		if ( BWFAN_Importer::$IMPORT_IN_PROGRESS === intval( $import['status'] ) && empty( $meta['is_running'] ) ) {
			if ( bwf_has_action_scheduled( BWFAN_Importer::$IMPORTER_ACTION_HOOK, array( 'import_id' => $import_id ), 'bwfan' ) ) {
				bwf_unschedule_actions( BWFAN_Importer::$IMPORTER_ACTION_HOOK, array( 'import_id' => absint( $import_id ) ), 'bwfan' );
			}
			bwf_schedule_recurring_action( time() + 60, 60, BWFAN_Importer::$IMPORTER_ACTION_HOOK, array( 'import_id' => $import_id ), 'bwfan' );

			$importer_type = $meta['import_type'] ?? '';

			try{
				/** @var Importer $importer_obj */
				$importer_obj = BWFAN_Core()->importer->get_importer( $importer_type );
				$importer_obj->set_import_id( $import_id );
				
				$importer_obj->read_import();
				$importer_obj->import( 5 );
				
				/** If still in progress, return the status */
				$importer_obj->reset_import_data();

				/** Get percent completed */
				$percent = $importer_obj->get_percent_completed();
				$import  = $importer_obj->get_import_db_row();

				/** End import if completed */
				if ( $percent >= 100 ) {
					$importer_obj->end_import();
				}
			} catch ( Exception $e ) {
				return $this->error_response( __( 'An error occurred while processing the import.', 'wp-marketing-automations' ), $e->getMessage(), 404 );
			}
		}

		$status = $this->get_import_status( $import );

		return $this->success_response( $status, __( 'Import status retrieved successfully.', 'wp-marketing-automations' ) );
	}

	/**
	 * Get status data for Importer process.
	 *
	 * @return array
	 */
	private function get_import_status( $import ) {
		$import_meta = json_decode( $import['meta'], true ) ?? [];
		$import_log  = $import_meta['log'] ?? [];
		$percentage  = $this->calculate_percentage( $import );

		$response = [
			'status'     => BWFAN_Importer::get_status_text( $import['status'] ),
			'count'      => $import['count'],
			'skipped'    => $import_log['skipped'] ?? 0,
			'processed'  => ( $import_log['imported'] ?? 0 ) + ( $import_log['updated'] ?? 0 ),
			'failed'     => $import_log['failed'] ?? 0,
			'percentage' => $percentage
		];

		if ( intval( $import['status'] ) === BWFAN_Importer::$IMPORT_FAILED && ! empty( $import_meta['status_msg'] ) ) {
			$response['msg'] = [ $import_meta['status_msg'] ];
		}

		if ( $percentage === 100 && ! empty( $import_meta['log_file'] ) && file_exists( $import_meta['log_file'] ) ) {
			$response['log_file'] = $import_meta['log_file'];
		}

		return $response;
	}

	/**
	 * Calculates the percentage of completion for the import process.
	 *
	 * @return int|float The percentage of completion (between 0 and 100).
	 */
	private function calculate_percentage( $import ) {
		$count     = $import['count'];
		$processed = $import['processed'];

		if ( $count <= 0 || $processed <= 0 ) {
			return 0;
		}

		$percentage = min( max( 0, ( $processed / $count ) * 100 ), 100 );

		return intval( $percentage );
	}

}

BWFAN_API_Loader::register( 'BWFAN_API_Import_Status' );