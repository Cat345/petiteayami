<?php

class BWFAN_API_Download_Importer_Log_File extends BWFAN_API_Base {
	public static $ins;

	public function __construct() {
		parent::__construct();
		$this->method = WP_REST_Server::READABLE;
		$this->route  = '/importer-log/download/(?P<importer_id>[\\d]+)';
	}

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public function default_args_values() {
		return array(
			'importer_id' => 0,
		);
	}

	public function process_api_call() {
		$importer_id = absint( $this->get_sanitized_arg( 'importer_id', 'text_field' ) );
		if ( empty( $importer_id ) ) {
			$this->response_code = 404;

			return $this->error_response( __( 'Invalid Importer ID', 'wp-marketing-automations' ) );
		}

		$import_row  = BWFAN_Model_Export_Import::get( $importer_id );
		$import_meta = ! empty( $import_row['meta'] ) ? json_decode( $import_row['meta'], true ) : array();

		if ( ! isset( $import_meta['log_file'] ) ) {
			$this->response_code = 404;

			return $this->error_response( __( 'Log file url is missing', 'wp-marketing-automations' ) );
		}

		// Extract filename from path and use basename to prevent path traversal
		$file_url = explode( '/', $import_meta['log_file'] );
		$file_name = basename( end( $file_url ) );

		// Sanitize filename to remove any dangerous characters
		$file_name = sanitize_file_name( $file_name );

		// Build full path using constant
		$allowed_dir = defined( 'BWFAN_IMPORT_DIR' ) ? BWFAN_IMPORT_DIR : wp_upload_dir()['basedir'] . '/funnelkit/fka-import';
		$filename    = $allowed_dir . '/' . $file_name;

		// Validate path using realpath to prevent directory traversal
		$real_path    = realpath( $filename );
		$real_allowed = realpath( $allowed_dir );

		if ( false === $real_path || false === $real_allowed || strpos( $real_path, $real_allowed ) !== 0 ) {
			$this->response_code = 403;

			return $this->error_response( __( 'Invalid file path', 'wp-marketing-automations' ) );
		}

		// Validate file exists and is readable
		if ( ! file_exists( $real_path ) || ! is_readable( $real_path ) ) {
			$this->response_code = 404;

			return $this->error_response( __( 'File not found or not readable', 'wp-marketing-automations' ) );
		}

		// Additional validation: ensure it's a CSV file
		$ext = strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			$this->response_code = 403;

			return $this->error_response( __( 'Invalid file type', 'wp-marketing-automations' ) );
		}

			// Set a time limit for script execution
			set_time_limit( 60 );

			// Define header information
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( basename( $real_path ) ) . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $real_path ) );

			// Clear system output buffer
			flush();

		// Read and output the file
		readfile( $real_path );
			exit;
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Download_Importer_Log_File' );