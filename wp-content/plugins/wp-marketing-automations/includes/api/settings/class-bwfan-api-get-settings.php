<?php

class BWFAN_API_Get_Settings extends BWFAN_API_Base {
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
		$this->route  = '/settings';
	}

	public function default_args_values() {
		return array();
	}

	public function process_api_call() {
		$settings_values = BWFAN_Common::get_global_settings();
		$setting_schema  = BWFAN_Common::get_setting_schema();

		$this->response_code = 200;

		return $this->success_response(
			array(
				'schema' => $setting_schema,
				'values' => $settings_values,
			),
			__( 'Settings fetched successfully', 'wp-marketing-automations' )
		);
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Get_Settings' );
