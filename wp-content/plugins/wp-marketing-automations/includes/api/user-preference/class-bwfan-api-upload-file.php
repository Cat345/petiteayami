<?php

class BWFAN_API_Upload_File extends BWFAN_API_Base {
	public static $ins;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public $contact;

	public function __construct() {
		parent::__construct();
		$this->method       = WP_REST_Server::CREATABLE;
		$this->route        = '/upload-file/';
	}

	public function process_api_call() {
		$files = $this->args['files'];
		$path  = $this->args['path'];

		// Validate file exists and is uploaded
		if ( empty( $files ) || ! is_array( $files['file'] ) || ! is_uploaded_file( $files['file']['tmp_name'] ) ) {
			$this->response_code = 400;

			return $this->error_response( __( 'Invalid file upload', 'wp-marketing-automations' ) );
		}

		$file = $files['file'];

		// Validate path - ensure it's within allowed import directory
		if ( empty( $path ) ) {
			$this->response_code = 400;

			return $this->error_response( __( 'Upload path is required', 'wp-marketing-automations' ) );
		}

		// Use constant for allowed path instead of user input
		$allowed_path = defined( 'BWFAN_IMPORT_DIR' ) ? BWFAN_IMPORT_DIR : wp_upload_dir()['basedir'] . '/funnelkit/fka-import';

		// Ensure allowed directory exists before validation
		if ( ! file_exists( $allowed_path ) ) {
			wp_mkdir_p( $allowed_path );
		}

		// Validate path is within allowed directory using realpath
		$real_path     = realpath( $path );
		$real_allowed  = realpath( $allowed_path );

		if ( false === $real_path || false === $real_allowed || strpos( $real_path, $real_allowed ) !== 0 ) {
			$this->response_code = 403;

			return $this->error_response( __( 'Invalid upload path', 'wp-marketing-automations' ) );
		}

		// Validate file type using wp_check_filetype_and_ext
		$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$allowed_types = array(
			'csv' => 'text/csv',
			'txt' => 'text/plain',
		);

		// Check extension
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			$this->response_code = 400;

			return $this->error_response( __( 'File must have .csv extension', 'wp-marketing-automations' ) );
		}

		// Validate MIME type
		if ( empty( $file_info['type'] ) || ! in_array( $file_info['type'], $allowed_types, true ) ) {
			$this->response_code = 400;

			return $this->error_response( __( 'Invalid file type. Only CSV files allowed.', 'wp-marketing-automations' ) );
		}

		// Ensure directory exists
		if ( ! file_exists( $real_path ) ) {
			wp_mkdir_p( $real_path );
		}

		// Generate secure filename
		$new_file_name = wp_generate_password( 32, false ) . '.csv';
		$new_file      = $real_path . '/' . $new_file_name;

		// Move uploaded file
		$move_new_file = move_uploaded_file( $file['tmp_name'], $new_file );

		if ( false === $move_new_file ) {
			$this->response_code = 500;

			return $this->error_response( __( 'Unable to upload file', 'wp-marketing-automations' ) );
		}

		$file_data['file'] = $new_file;

		return $this->success_response( $file_data, __( 'File uploaded successfully', 'wp-marketing-automations' ) );
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Upload_File' );
