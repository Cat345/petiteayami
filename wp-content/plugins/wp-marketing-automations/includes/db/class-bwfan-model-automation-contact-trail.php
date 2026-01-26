<?php

class BWFAN_Model_Automation_Contact_Trail extends BWFAN_Model {
	static $primary_key = 'ID';

	/**
	 * @param $step_ids
	 * @param $status
	 * @param $after_time
	 *
	 * @return array|false|object|stdClass[]|null
	 */
	public static function get_bulk_step_count( $step_ids = [], $status = 1, $after_time = 0 ) {
		global $wpdb;
		$table_name = self::_table();
		if ( empty( $step_ids ) ) {
			return false;
		}

		$string_placeholder = array_fill( 0, count( $step_ids ), '%d' );
		$placeholder        = implode( ', ', $string_placeholder );

		$args             = array_merge( $step_ids, [ $status ] );
		$after_time_query = '';
		if ( ! empty( $after_time ) ) {
			$after_time_query .= " AND `c_time` > %d ";
			$args[]           = $after_time;
		}

		$query = "SELECT `sid`, COUNT(DISTINCT `tid`) AS `count` FROM {$table_name} WHERE `sid` IN ($placeholder) AND `status` = %d $after_time_query GROUP BY `sid`";

		return $wpdb->get_results( $wpdb->prepare( $query, $args ), ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return table name
	 *
	 * @return string
	 */
	protected static function _table() {
		global $wpdb;

		return $wpdb->prefix . 'bwfan_automation_contact_trail';
	}

	/**
	 * Update all steps trails status to success
	 *
	 * @param $trail_id
	 *
	 * @return false|void
	 */
	public static function update_all_step_trail_status_complete( $trail_id ) {
		if ( empty( $trail_id ) ) {
			return false;
		}

		global $wpdb;
		$table_name = self::_table();
		$query      = $wpdb->prepare( "UPDATE {$table_name} SET `status`= 1 WHERE `tid` = %s AND `status` = 2", $trail_id );

		$wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get step trail data
	 *
	 * @param $trail_id
	 * @param $step_id
	 *
	 * @return array|false|object|void|null
	 */
	public static function get_step_trail( $trail_id, $step_id ) {
		global $wpdb;
		$table_name = self::_table();

		if ( empty( $trail_id ) || empty( $step_id ) ) {
			return false;
		}

		$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE `tid` = %s AND `sid` = %d ORDER BY ID DESC LIMIT 0,1", $trail_id, $step_id );

		return $wpdb->get_row( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get steps 'completed contacts' data
	 *
	 * @param $aid
	 * @param $step_id
	 * @param $queued
	 * @param $offset
	 * @param $limit
	 *
	 * @return array
	 */
	public static function get_step_completed_contacts( $aid, $step_id, $type = '', $offset = 0, $limit = 25, $path = 0 ) {
		global $wpdb;
		$table_name = self::_table();
		switch ( $type ) {
			case 'queued':
				$status = 2;
				break;
			case 'skipped':
				$status = 4;
				break;
			case 'failed':
				$status = 3;
				break;
			default:
				$status = 1;
				break;
		}

		if ( ! empty( $path ) ) {
			$path_pattern = '%"path":"' . $wpdb->esc_like( $path ) . '"%';
			$sub_query    = $wpdb->prepare( "JOIN ( SELECT tid, MAX(c_time) AS latest_time FROM {$table_name} WHERE aid = %d AND sid = %d AND status = %d AND data LIKE %s GROUP BY tid LIMIT %d OFFSET %d ) AS latest ON ct.tid = latest.tid AND ct.c_time = latest.latest_time ", $aid, $step_id, $status, $path_pattern, $limit, $offset );
		} else {
			$sub_query = $wpdb->prepare( "JOIN ( SELECT tid, MAX(c_time) AS latest_time FROM {$table_name} WHERE aid = %d AND sid = %d AND status = %d GROUP BY tid LIMIT %d OFFSET %d ) AS latest ON ct.tid = latest.tid AND ct.c_time = latest.latest_time ", $aid, $step_id, $status, $limit, $offset );
		}
		$query    = $wpdb->prepare( "SELECT ct.ID, ct.cid, ct.aid, ct.data, ct.tid, ct.c_time, c.email, c.f_name, c.l_name, c.contact_no FROM {$table_name} as ct {$sub_query} JOIN {$wpdb->prefix}bwf_contact AS c ON ct.cid = c.ID WHERE ct.sid = %d ORDER BY ct.ID DESC ", $step_id );
		$contacts = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return [
			'contacts' => $contacts,
			'total'    => self::get_step_count( $step_id, $status, $path )
		];
	}

	/**
	 * Get step count, complete or wait both
	 *
	 * @param $step_id
	 * @param $completed
	 *
	 * @return string|null
	 */
	public static function get_step_count( $step_id, $status = true, $path = '' ) {
		global $wpdb;
		$table_name = self::_table();
		$status     = ( true === $status ) ? 1 : $status;

		$query = $wpdb->prepare( " SELECT COUNT(*) AS `count` FROM {$table_name} as ct JOIN ( SELECT tid,MAX(c_time) AS latest_time FROM {$table_name} WHERE sid = %d AND status = %d  GROUP BY tid ) AS latest ON ct.tid = latest.tid AND ct.c_time = latest.latest_time  WHERE ct.sid = %d", $step_id, $status, $step_id );
		if ( ! empty( $path ) ) {
			$path_pattern = '%"path":"' . $wpdb->esc_like( $path ) . '"%';
			$query        .= $wpdb->prepare( " AND `data` LIKE %s ", $path_pattern );
		}

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * Get step trails for automation contact
	 *
	 * @param $tid
	 *
	 * @return array|object|null
	 */
	public static function get_trail( $tid ) {
		global $wpdb;
		$table_name = self::_table();

		$query = $wpdb->prepare( "SELECT tr.*, st.type, st.data AS step_data, st.action, st.status AS step_status FROM {$table_name} AS tr LEFT JOIN {$wpdb->prefix}bwfan_automation_step AS st ON tr.sid = st.ID  WHERE tid = %s ", $tid );

		return $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function delete_automation_trail_by_id( $aid ) {
		global $wpdb;
		$table_name = self::_table();

		$where = "aid = %d";
		if ( is_array( $aid ) ) {
			$where = "aid IN ('" . implode( "','", array_map( 'esc_sql', $aid ) ) . "')";
			$aid   = [];
		}

		$query = $wpdb->prepare( "DELETE FROM $table_name WHERE $where", $aid );

		return $wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function delete_row_by_trail_by( $trail_id ) {
		global $wpdb;
		$table_name = self::_table();

		$query = $wpdb->prepare( "DELETE FROM $table_name WHERE tid = %s", $trail_id );

		return $wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function update_multiple_trail_status( $tids, $sid, $status = 1 ) {
		if ( empty( $tids ) || ! is_array( $tids ) ) {
			return false;
		}

		$tids = implode( "', '", $tids );

		global $wpdb;
		$table_name = self::_table();
		$args       = [ $tids ];
		$query      = "UPDATE {$table_name} SET `status`= $status WHERE `tid` IN (%s) ";

		if ( absint( $sid ) > 0 ) {
			$query  .= " AND `sid` = %d ";
			$args[] = $sid;
		}

		$query = $wpdb->prepare( $query, $args );

		return $wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Delete trail if already exist for specific step id
	 *
	 * @param $arr
	 *
	 * @return void
	 */
	public static function delete_if_trail_exists( $arr ) {
		if ( ! is_array( $arr ) || ! isset( $arr['tid'] ) || ! isset( $arr['aid'] ) || ! isset( $arr['cid'] ) || ! isset( $arr['sid'] ) ) {
			return;
		}

		global $wpdb;
		$table_name = self::_table();

		$query = $wpdb->prepare( "SELECT COUNT(*) AS `count` FROM {$table_name} WHERE `tid` = %s AND `cid` = %d AND `aid` = %d AND `sid` = %d", $arr['tid'], $arr['cid'], $arr['aid'], $arr['sid'] ); //phpcs:ignore WordPress.DB.PreparedSQL
		$count = BWFAN_Model_Automation_Contact_Trail::get_var( $query );
		if ( intval( $count ) > 0 ) {
			/** Delete existing */
			$query = $wpdb->prepare( "DELETE FROM {$table_name} WHERE `tid` = %s AND `cid` = %d AND `aid` = %d AND `sid` = %d", $arr['tid'], $arr['cid'], $arr['aid'], $arr['sid'] ); //phpcs:ignore WordPress.DB.PreparedSQL
			BWFAN_Model_Automation_Contact_Trail::query( $query );
		}
	}

	/**
	 * Get completed email step count
	 *
	 * @param $sids
	 *
	 * @return int
	 */
	public static function get_completed_email_steps_count( $sids ) {
		global $wpdb;
		$table_name = self::_table();

		$string_placeholder = array_fill( 0, count( $sids ), '%d' );
		$placeholder        = implode( ', ', $string_placeholder );
		$args               = array_merge( $sids, [ 1 ] );
		$query              = $wpdb->prepare( "SELECT COUNT(ID) FROM {$table_name} WHERE `sid` IN ($placeholder) AND `status` = %d ", $args ); //phpcs:ignore WordPress.DB.PreparedSQL

		return intval( $wpdb->get_var( $query ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get path's contact count
	 *
	 * @param $split_id
	 * @param $path
	 *
	 * @return string|null
	 */
	public static function get_path_contact_count( $split_id, $path ) {
		global $wpdb;
		$table_name = self::_table();
		$path       = '%"path":"' . $wpdb->esc_like( $path ) . '"%';

		$query = $wpdb->prepare( "SELECT count(`sid`) as `count` FROM {$table_name} WHERE `sid`= %d AND `data` LIKE %s GROUP BY `sid`", intval( $split_id ), $path );

		return $wpdb->get_var( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}


}
