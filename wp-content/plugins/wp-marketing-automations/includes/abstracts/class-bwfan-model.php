<?php

#[AllowDynamicProperties]
abstract class BWFAN_Model {
	static $primary_key = 'id';
	static $count = 20;

	/**
	 * Table name
	 *
	 * @var string
	 */
//	protected static $table = '';

	static function set_id() {
	}

	static function get( $value ) {
		global $wpdb;

		$query = self::_fetch_sql( $value );

		return $wpdb->get_row( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL
	}

	private static function _fetch_sql( $value ) {
		global $wpdb;
		$sql = sprintf( 'SELECT * FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );

		return $wpdb->prepare( $sql, $value ); // WPCS: unprepared SQL OK
	}

	protected static function _table() {
		global $wpdb;
		if ( ! property_exists( get_called_class(), 'table' ) || static::$table === '' ) {
			$tablename = strtolower( get_called_class() );
			$tablename = str_replace( 'bwfan_model_', 'bwfan_', $tablename );
		} else {
			$tablename = static::$table;
		}

		return $wpdb->prefix . $tablename;
	}

	static function insert( $data ) {
		global $wpdb;
		$wpdb->insert( self::_table(), $data ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL
	}

	static function update( $data, $where ) {
		global $wpdb;

		return $wpdb->update( self::_table(), $data, $where ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL
	}

	static function delete( $value ) {
		global $wpdb;
		$sql = sprintf( 'DELETE FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );

		return $wpdb->query( $wpdb->prepare( $sql, $value ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	static function insert_id() {
		global $wpdb;

		return $wpdb->insert_id;
	}

	static function now() {
		return self::time_to_date( time() );
	}

	static function time_to_date( $time ) {
		return gmdate( 'Y-m-d H:i:s', $time );
	}

	static function date_to_time( $date ) {
		return strtotime( $date . ' GMT' );
	}

	static function num_rows() {
		global $wpdb;

		return $wpdb->num_rows;
	}

	static function count_rows( $dependency = null ) {
		global $wpdb;

		$sql = 'SELECT COUNT(*) FROM ' . self::_table();
		if ( ! is_null( $dependency ) ) {
			$sql = 'SELECT COUNT(*) FROM ' . self::_table() . ' INNER JOIN ' . $dependency['dependency_table'] . ' on ' . self::_table() . '.' . $dependency['dependent_col'] . '=' . $dependency['dependency_table'] . '.' . $dependency['dependency_col'] . ' WHERE ' . $dependency['dependency_table'] . '.' . $dependency['col_name'] . '=' . $dependency['col_value'];
			if ( isset( $dependency['automation_id'] ) ) {
				$sql = 'SELECT COUNT(*) FROM ' . self::_table() . ' INNER JOIN ' . $dependency['dependency_table'] . ' on ' . self::_table() . '.' . $dependency['dependent_col'] . '=' . $dependency['dependency_table'] . '.' . $dependency['dependency_col'] . ' WHERE ' . $dependency['dependency_table'] . '.' . $dependency['col_name'] . '=' . $dependency['col_value'] . ' AND ' . $dependency['automation_table'] . '.' . $dependency['automation_col'] . '=' . $dependency['automation_id'];
				if ( 'any' === $dependency['col_value'] ) {
					$sql = 'SELECT COUNT(*) FROM ' . self::_table() . ' INNER JOIN ' . $dependency['dependency_table'] . ' on ' . self::_table() . '.' . $dependency['dependent_col'] . '=' . $dependency['dependency_table'] . '.' . $dependency['dependency_col'] . ' WHERE ' . $dependency['automation_table'] . '.' . $dependency['automation_col'] . '=' . $dependency['automation_id'];
				}
			}
		}

		return $wpdb->get_var( $sql ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL
	}

	static function count( $data = array() ) {
		global $wpdb;

		$sql        = 'SELECT COUNT(*) as `count` FROM ' . self::_table() . ' WHERE 1=1';
		$sql_params = [];
		if ( is_array( $data ) && count( $data ) > 0 ) {
			foreach ( $data as $key => $val ) {
				$sql          .= " AND `{$key}` LIKE {$val['operator']}";
				$sql_params[] = $val['value'];
			}

			if ( ! empty( $sql_params ) ) {
				$sql = $wpdb->prepare( $sql, $sql_params ); // WPCS: unprepared SQL OK
			}
		}

		return $wpdb->get_var( $sql ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL
	}

	static function get_specific_rows( $where_key, $where_value, $offset = 0, $limit = 0 ) {
		global $wpdb;
		$table_name = self::_table();

		// Whitelist allowed column names to prevent column injection
		$allowed_columns = array( 'ID', 'id', 'recipient', 'slug', 'email', 'cid', 'wcid', 'trackid', 'type', 'aid', 'hash_code' );
		if ( ! in_array( $where_key, $allowed_columns, true ) ) {
			return array();
		}

		if ( ! empty( $limit ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE `{$where_key}` = %s LIMIT %d, %d", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$where_value,
				absint( $offset ),
				absint( $limit )
			);
		} else {
			$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE `{$where_key}` = %s", $where_value ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	static function get_rows( $only_query = false, $automation_ids = array() ) {
		global $wpdb;

		$table_name     = self::_table();
		$page_number    = 1;
		$count_per_page = self::$count;
		$next_offset    = ( $page_number - 1 ) * $count_per_page;
		$sql_query      = $wpdb->prepare( "SELECT * FROM $table_name ORDER BY c_date DESC LIMIT %d OFFSET %d", $count_per_page, $next_offset );

		if ( isset( $_GET['paged'] ) && $_GET['paged'] > 1 ) { // WordPress.CSRF.NonceVerification.NoNonceVerification
			$page_number = sanitize_text_field( $_GET['paged'] ); // WordPress.CSRF.NonceVerification.NoNonceVerification
			$next_offset = ( $page_number - 1 ) * $count_per_page;
			$sql_query   = $wpdb->prepare( "SELECT * FROM $table_name ORDER BY c_date DESC LIMIT %d OFFSET %d", $count_per_page, $next_offset );
		}

		if ( isset( $_GET['status'] ) && 'all' !== $_GET['status'] ) { // WordPress.CSRF.NonceVerification.NoNonceVerification
			$status    = sanitize_text_field( $_GET['status'] ); // WordPress.CSRF.NonceVerification.NoNonceVerification
			$status    = ( 'active' === $status ) ? 1 : 2;
			$sql_query = $wpdb->prepare( "SELECT * FROM $table_name WHERE status = %d ORDER BY c_date DESC LIMIT %d OFFSET %d", $status, $count_per_page, $next_offset );
		}

		if ( ( isset( $_GET['paged'] ) && $_GET['paged'] > 0 ) && ( isset( $_GET['status'] ) && '' !== $_GET['status'] ) ) { // WordPress.CSRF.NonceVerification.NoNonceVerification
			$page_number = sanitize_text_field( $_GET['paged'] ); // WordPress.CSRF.NonceVerification.NoNonceVerification
			$next_offset = ( $page_number - 1 ) * $count_per_page;
			$status      = sanitize_text_field( $_GET['status'] ); // WordPress.CSRF.NonceVerification.NoNonceVerification
			$sql_query   = $wpdb->prepare( "SELECT * FROM $table_name WHERE status = %d ORDER BY c_date DESC LIMIT %d OFFSET %d", $status, $count_per_page, $next_offset );
		}

		$result = $wpdb->get_results( $sql_query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $result;
	}

	static function get_results( $query ) {
		global $wpdb;
		$query   = str_replace( '{table_name}', self::_table(), $query );
		$results = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $results;
	}

	/**
	 * Get data by id.
	 *
	 * @param array|int $ids Array of IDs or single ID
	 *
	 * @return array Associative array keyed by ID
	 */
	static function get_data_by_id( $ids = [] ) {
		if ( empty( $ids ) ) {
			return [];
		}

		if ( ! is_array( $ids ) ) {
			$ids = [ $ids ];
		}

		// Sanitize all IDs to integers
		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids ); // Remove any invalid values

		if ( empty( $ids ) ) {
			return [];
		}

		global $wpdb;
		$table_name = self::_table();

		// Build placeholders for prepared statement
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$query        = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id IN ({$placeholders})", ...$ids ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$results = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( empty( $results ) ) {
			return [];
		}

		$data = [];
		foreach ( $results as $result ) {
			if ( isset( $result['ID'] ) ) {
				$data[ $result['ID'] ] = $result;
			}

			if ( isset( $result['id'] ) ) {
				$data[ $result['id'] ] = $result;
			}
		}

		return $data;
	}

	static function get_var( $query ) {
		global $wpdb;
		$query = str_replace( '{table_name}', self::_table(), $query );

		return $wpdb->get_var( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	static function delete_multiple( $query ) {
		self::query( $query );
	}

	static function query( $query ) {
		global $wpdb;
		$query = str_replace( '{table_name}', self::_table(), $query );
		$wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	static function update_multiple( $query ) {
		self::query( $query );
	}

	static function get_current_date_time() {
		return date( 'Y-m-d H:i:s' );
	}

	static function insert_multiple( $values, $keys, $formats = [] ) {
		if ( ( ! is_array( $keys ) || empty( $keys ) ) || ( ! is_array( $values ) || empty( $values ) ) ) {
			return false;
		}

		global $wpdb;

		$values = array_map( function ( $value ) use ( $keys, $formats ) {
			global $wpdb;
			$return = array();
			foreach ( $keys as $index => $key ) {
				$format   = is_array( $formats ) && isset( $formats[ $index ] ) ? $formats[ $index ] : false;
				$format   = ! empty( $format ) ? $format : ( is_numeric( $value[ $key ] ) ? '%d' : '%s' );
				$return[] = $wpdb->prepare( $format, $value[ $key ] );
			}

			return '(' . implode( ',', $return ) . ')';
		}, $values );
		$values = implode( ', ', $values );
		$keys   = '(' . implode( ', ', $keys ) . ')';
		$query  = 'INSERT INTO ' . self::_table() . ' ' . $keys . ' VALUES ' . $values;

		return $wpdb->query( $wpdb->prepare( "$query ", $values ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Insert query with Ignore
	 *
	 * @param $data
	 * @param $format
	 *
	 * @return bool|int|mysqli_result|null
	 */
	static function insert_ignore( $data, $format = null ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return false;
		}

		// Validate format if provided
		if ( ! is_null( $format ) && count( $format ) !== count( $data ) ) {
			$format = null; // Reset format if it doesn't match data count
		}

		$placeholders = is_null( $format ) ? array_fill( 0, count( $data ), '%s' ) : $format;
		$columns      = array_keys( $data );
		$table        = self::_table();
		global $wpdb;

		$sql = "INSERT IGNORE INTO `$table` (`" . implode( '`,`', $columns ) . "`) VALUES (" . implode( ',', $placeholders ) . ")";

		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( $wpdb->prepare( $sql, array_values( $data ) ) );
		if ( ! empty( $result ) ) {
			return $result;
		}

		/** If duplicate entry DB error come */
		if ( 0 === $result ) {
			$warnings = $wpdb->get_results( "SHOW WARNINGS", ARRAY_A );//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( ! empty( $warnings ) ) {
				foreach ( $warnings as $warning ) {
					if ( empty( $warning['Message'] ) || false === strpos( $warning['Message'], 'Duplicate entry' ) ) {
						continue;
					}
					BWFAN_Common::log_test_data( 'WP db error in ' . $table . ' : ' . $warning['Message'], 'fka-db-duplicate-error', true );
					BWFAN_Common::log_test_data( $data, 'fka-db-duplicate-error', true );
				}
			}
		}

		return false;
	}
}
