<?php

class BWFAN_API_Get_Automations extends BWFAN_API_Base {
	public static $ins;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	public $total_count = 0;
	public $count_data = 0;

	public function __construct() {
		parent::__construct();
		$this->method             = WP_REST_Server::READABLE;
		$this->route              = '/automations';
		$this->pagination->offset = 0;
		$this->pagination->limit  = 25;
		$this->request_args       = array(
			'search'   => array(
				'description' => __( 'Autonami Search', 'wp-marketing-automations' ),
				'type'        => 'string',
			),
			'status'   => array(
				'description' => __( 'Autonami Status', 'wp-marketing-automations' ),
				'type'        => 'string',
			),
			'offset'   => array(
				'description' => __( 'Autonami list Offset', 'wp-marketing-automations' ),
				'type'        => 'integer',
			),
			'limit'    => array(
				'description' => __( 'Per page limit', 'wp-marketing-automations' ),
				'type'        => 'integer',
			),
			'category' => array(
				'description' => __( 'Autonami list Category', 'wp-marketing-automations' ),
				'type'        => 'string',
			)
		);
	}

	public function default_args_values() {
		$args = [
			'search'   => '',
			'status'   => 'all',
			'offset'   => 0,
			'limit'    => 25,
			'category' => ''
		];

		return $args;
	}

	public function process_api_call() {
		$status          = $this->get_sanitized_arg( 'status', 'text_field' );
		$search          = $this->get_sanitized_arg( 'search', 'text_field' );
		$offset_arg   = $this->get_sanitized_arg( 'offset', 'text_field' );
		$offset       = ! empty( $offset_arg ) ? absint( $offset_arg ) : 0;
		$limit_arg    = $this->get_sanitized_arg( 'limit', 'text_field' );
		$limit        = ! empty( $limit_arg ) ? absint( $limit_arg ) : 25;
		$version      = isset( $this->args['version'] ) ? $this->args['version'] : 1;
		$category_arg = $this->get_sanitized_arg( 'category', 'text_field' );
		$category     = ! empty( $category_arg ) ? $category_arg : '';
		$get_automations = BWFAN_Common::get_all_automations( $search, $status, $offset, $limit, false, $version, $category );

		if ( ! is_array( $get_automations ) || ! isset( $get_automations['automations'] ) || ! is_array( $get_automations['automations'] ) ) {
			return $this->error_response( __( 'Unable to fetch automations', 'wp-marketing-automations' ), null, 500 );
		}

		/** Check if worker call is late */
		$last_run = bwf_options_get( 'fk_core_worker_let' );
		if ( '' !== $last_run && ( ( time() - $last_run ) > BWFAN_Common::get_worker_delay_timestamp() ) ) {
			/** Worker is running late */
			$get_automations['worker_delayed'] = time() - $last_run;
		}

		/** Check basic worker last run time and status code check */
		$resp = BWFAN_Common::validate_core_worker();
		if ( isset( $resp['response_code'] ) ) {
			$get_automations['response_code'] = $resp['response_code'];
		}

		$this->total_count = isset( $get_automations['total_records'] ) ? absint( $get_automations['total_records'] ) : 0;
		$this->count_data  = BWFAN_Common::get_automation_data_count( $version );

		return $this->success_response( $get_automations, __( 'Automations found', 'wp-marketing-automations' ) );
	}

	public function get_result_total_count() {
		return $this->total_count;
	}

	public function get_result_count_data() {
		return $this->count_data;
	}
}

BWFAN_API_Loader::register( 'BWFAN_API_Get_Automations' );
