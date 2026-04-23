<?php

class BWFAN_API_Get_Automation_stats extends BWFAN_API_Base {
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
		$this->method = WP_REST_Server::READABLE;
		$this->route  = '/automations-stats';
	}

	public function process_api_call() {
		$automation_ids = isset( $this->args['automation_ids'] ) ? $this->args['automation_ids'] : [];
		$version        = $this->get_sanitized_arg( 'version', 'text_field' );
		$ids            = implode( ',', array_filter( $automation_ids ) );

		if ( 2 === absint( $version ) ) {
			$data       = [];
			$cached_key = 'bwfan_automation_v2_stats';
			$force      = filter_input( INPUT_GET, 'force' );
			$exp        = BWFAN_Common::get_admin_analytics_cache_lifespan();

			/** Get active, paused, completed and failed count for v2 automations */
			if ( 'false' === $force ) {
				$stats = get_transient( $cached_key );
				if ( ! empty( $stats ) ) {
					$automation_ids = array_filter( $automation_ids, function ( $id ) use ( $stats ) {
						return ! array_key_exists( $id, $stats );
					} );
					if ( count( $automation_ids ) > 0 ) {
						sort( $automation_ids );
					}
					$data = $stats;
				}
			}

			if ( is_array( $automation_ids ) && count( $automation_ids ) > 0 ) {
				/** Single query for active, failed, paused counts grouped by aid */
				$status_counts       = BWFAN_Model_Automation_Contact::get_all_status_counts_by_aids( $automation_ids );
				$complete_automation = BWFAN_Model_Automation_Complete_Contact::get_automation_complete_contact_count( $ids );
				$complete_map        = array();
				foreach ( $complete_automation as $row ) {
					$complete_map[ $row['aid'] ] = intval( $row['count'] );
				}

				foreach ( $automation_ids as $aid ) {
					$data[ $aid ] = [
						'active'   => isset( $status_counts[ $aid ]['active'] ) ? $status_counts[ $aid ]['active'] : 0,
						'complete' => isset( $complete_map[ $aid ] ) ? $complete_map[ $aid ] : 0,
						'failed'   => isset( $status_counts[ $aid ]['failed'] ) ? $status_counts[ $aid ]['failed'] : 0,
						'paused'   => isset( $status_counts[ $aid ]['paused'] ) ? $status_counts[ $aid ]['paused'] : 0,
					];
				}
				BWFAN_Common::validate_scheduled_recurring_actions();
				set_transient( $cached_key, $data, $exp );
			}

			return $this->success_response( $data, __( 'Automations found', 'wp-marketing-automations' ) );
		}

		/** For v1 automations */
		$data = $this->get_v1_automations_count( $automation_ids );

		return $this->success_response( $data, __( 'Automations found', 'wp-marketing-automations' ) );
	}

	/**
	 * Get all scheduled and paused task count
	 *
	 * @param $automation_ids
	 *
	 * @return array
	 */
	public function get_v1_automations_count( $automation_ids ) {
		global $wpdb;
		$data       = [];
		$cached_key = 'bwfan_automation_v1_stats';
		$force      = filter_input( INPUT_GET, 'force' );
		$exp        = BWFAN_Common::get_admin_analytics_cache_lifespan();

		if ( 'false' === $force ) {
			$stats = get_transient( $cached_key );
			if ( ! empty( $stats ) ) {
				$automation_ids = array_filter( $automation_ids, function ( $id ) use ( $stats ) {
					return ! array_key_exists( $id, $stats );
				} );
				if ( count( $automation_ids ) > 0 ) {
					sort( $automation_ids );
				}
				$data = $stats;
			}
		}

		if ( is_array( $automation_ids ) && count( $automation_ids ) > 0 ) {
			$ids = implode( ',', array_filter( $automation_ids ) );

			$tasks_table = "{$wpdb->prefix}bwfan_tasks";
			$tasks_query = "SELECT `automation_id`, count(`ID`) AS `total_scheduled`, `status` FROM $tasks_table WHERE `automation_id` IN ($ids) GROUP BY `automation_id`, `status`";
			$tasks       = $wpdb->get_results( $tasks_query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_tasks = array();
			foreach ( $tasks as $automation_tasks ) {
				$status = absint( $automation_tasks['status'] ) === 1 ? 'paused' : 'scheduled';
				if ( absint( $automation_tasks['automation_id'] ) ) {
					$total_tasks[ $status ][ absint( $automation_tasks['automation_id'] ) ] = $automation_tasks['total_scheduled'];
				}
			}

			/** Get completed and failed task count */
			$logs_table = "{$wpdb->prefix}bwfan_logs";
			$logs_query = "SELECT `automation_id`, count(`ID`) AS `total_logs`, `status` FROM $logs_table WHERE `automation_id` IN ($ids) AND `status` IN (0, 1) GROUP BY `automation_id`, `status`";
			$logs       = $wpdb->get_results( $logs_query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_logs = array();
			foreach ( $logs as $automation_logs ) {
				$status = absint( $automation_logs['status'] ) === 1 ? 'completed' : 'failed';
				if ( absint( $automation_logs['automation_id'] ) ) {
					$total_logs[ $status ][ absint( $automation_logs['automation_id'] ) ] = $automation_logs;
				}
			}

			foreach ( $automation_ids as $id ) {
				$data[ $id ]['scheduled'] = isset( $total_tasks['scheduled'][ $id ] ) ? $total_tasks['scheduled'][ $id ] : 0;
				$data[ $id ]['paused']    = isset( $total_tasks['paused'][ $id ] ) ? $total_tasks['paused'][ $id ] : 0;
				$data[ $id ]['completed'] = isset( $total_logs['completed'][ $id ]['total_logs'] ) ? $total_logs['completed'][ $id ]['total_logs'] : 0;
				$data[ $id ]['failed']    = isset( $total_logs['failed'][ $id ]['total_logs'] ) ? $total_logs['failed'][ $id ]['total_logs'] : 0;
			}

			set_transient( $cached_key, $data, $exp );
		}

		return $data;
	}

}

BWFAN_API_Loader::register( 'BWFAN_API_Get_Automation_stats' );
