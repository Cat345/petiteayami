<?php

#[\AllowDynamicProperties]
class BWFAN_API_Update_Option extends BWFAN_API_Base {
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
		$this->method = WP_REST_Server::EDITABLE;
		$this->route  = '/update-option-data/';
	}

	public function default_args_values() {
		return array(
			'optionkey' => '',
			'optionval' => '',
		);
	}

	/**
	 * Allow-list of option keys this endpoint may write, with expected value type.
	 *
	 * Why: update_option() with an unrestricted key allows site takeover via
	 * writes to options like `active_plugins`, `users_can_register`, `default_role`.
	 */
	protected function get_allowed_options() {
		return apply_filters( 'bwfan_allowed_options_save', array(
			'_bwfan_onboarding_completed'     => 'bool',
			'bwfan_smtp_recommend'            => 'int',
			'bwf_global_block_editor_setting' => 'array',
		));
	}

	public function process_api_call() {
		$this->response_code = 404;
		if ( empty( $this->args['optionkey'] ) || ! isset( $this->args['optionval'] ) ) {
			return $this->error_response( __( "Some data missing", 'wp-marketing-automations' ) );
		}

		$option_key = $this->args['optionkey'];
		$option_val = $this->args['optionval'];

		$allowed = $this->get_allowed_options();
		if ( ! isset( $allowed[ $option_key ] ) ) {
			$this->response_code = 403;

			return $this->error_response( __( "Unsupported option key.", 'wp-marketing-automations' ) );
		}

		switch ( $allowed[ $option_key ] ) {
			case 'bool':
				$option_val = (bool) $option_val;
				break;
			case 'int':
				$option_val = absint( $option_val );
				break;
			case 'array':
				if ( ! is_array( $option_val ) ) {
					$this->response_code = 400;

					return $this->error_response( __( "Invalid option value.", 'wp-marketing-automations' ) );
				}
				break;
		}

		$result = update_option( $option_key, $option_val, true );

		if ( $result ) {
			$this->response_code = 200;

			return $this->success_response( __( "Preference updated", 'wp-marketing-automations' ) );
		}

		return $this->error_response( __( "Some error occurred, unable to update the preference", 'wp-marketing-automations' ) );
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Update_Option' );
