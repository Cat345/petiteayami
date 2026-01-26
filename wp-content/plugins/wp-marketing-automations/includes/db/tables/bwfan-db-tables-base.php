<?php
declare(strict_types=1);

abstract class BWFAN_DB_Tables_Base {
	public $table_name = '';
	public $db_errors = '';
	public $max_index_length = 191;

	public $collation = null;

	/**
	 * Checking table exists or not
	 *
	 * @return bool
	 */
	public function is_exists() {
		global $wpdb;

		$safe_table_name = esc_sql( $wpdb->prefix . $this->table_name );
		return ! empty( $wpdb->query( "SHOW TABLES LIKE '{$safe_table_name}'" ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Check missing columns and return missing ones only
	 *
	 * @return array
	 */
	public function check_missing_columns() {
		global $wpdb;

		/** Get defined columns */
		$columns = $this->get_columns();
		/** Get columns from db */
		$safe_table_name = esc_sql( $wpdb->prefix . $this->table_name );
		$db_columns = $wpdb->get_results( "DESCRIBE `{$safe_table_name}`", ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$result = array_diff( $columns, array_column( $db_columns, 'Field' ) );
		sort( $result );

		return $result;
	}

	public function get_columns() {
		return [];
	}

	/**
	 * Get primary key and indexes definition
	 *
	 * @return array Array with 'primary_key', 'indexes', and 'unique_keys' keys
	 *              Format: [
	 *                  'primary_key' => 'column_name',
	 *                  'indexes' => [
	 *                      'index_name' => ['column1', 'column2'], // single or composite index
	 *                  ],
	 *                  'unique_keys' => [
	 *                      'unique_name' => ['column1'], // single or composite unique key
	 *                  ]
	 *              ]
	 */
	public function get_indexes() {
		return [
			'primary_key' => null,
			'indexes'     => [],
			'unique_keys' => [],
		];
	}

	/**
	 * Create table
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;
		$sql = $this->get_create_table_query();
		if ( empty( $sql ) ) {
			return;
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		if ( ! empty( $wpdb->last_error ) ) {
			$this->db_errors = $this->table_name . ' create table method triggered an error - ' . $wpdb->last_error;
		}
	}

	/**
	 * Check table collation and convert to utf8mb4 if not and return errors if any
	 *
	 * @return array|bool
	 */
	public function check_table_collation() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;

		$table_data = $wpdb->get_row( $wpdb->prepare( "SHOW TABLE STATUS LIKE %s", $table_name ), ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( empty( $table_data ) ) {
			return false;
		}

		$safe_table_name = esc_sql( $table_name );

		if ( isset( $table_data['Collation'] ) && ! str_contains( $table_data['Collation'], 'utf8mb4' ) ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE `{$safe_table_name}` CONVERT TO CHARACTER SET %s COLLATE %s", $wpdb->charset, $wpdb->collate ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		$columns   = $wpdb->get_results( "SHOW FULL COLUMNS FROM `{$safe_table_name}`", ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$db_errors = [];
		foreach ( $columns as $column ) {
			if ( empty( $column['Collation'] ) || str_contains( $column['Collation'], 'utf8mb4' ) ) {
				continue;
			}
			$column_type = $column['Type'];
			$field       = $column['Field'];

			$safe_table_name  = esc_sql( $table_name );
			$safe_field       = esc_sql( $field );
			$safe_column_type = esc_sql( $column_type );
			$query            = $wpdb->prepare( "ALTER TABLE `{$safe_table_name}` MODIFY COLUMN `{$safe_field}` {$safe_column_type} CHARACTER SET %s COLLATE %s", $wpdb->charset, $wpdb->collate ); //phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( ! empty( $wpdb->last_error ) ) {
				$db_errors[] = $wpdb->last_error;
			}
		}

		return ! empty( $db_errors ) ? $db_errors : true;
	}

	/**
	 * Check primary key, auto-increment, and indexes, fix if missing, return errors if any
	 *
	 * @return array|bool
	 */
	public function check_primary_key_auto_increment() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;

		$table_data = $wpdb->get_row( $wpdb->prepare("SHOW TABLE STATUS LIKE %s", $table_name), ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( empty( $table_data ) ) {
			return false;
		}

		$db_errors = [];
		$safe_table_name = esc_sql( $table_name );

		/** Get current table structure */
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$safe_table_name}", ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( empty( $columns ) ) {
			return false;
		}

		/** Create column lookup array for O(1) access */
		$column_lookup = array();
		foreach ( $columns as $column ) {
			$column_lookup[ $column['Field'] ] = $column;
		}

		/** Get all existing keys from database */
		$existing_keys = $wpdb->get_results( "SHOW KEYS FROM {$safe_table_name}", ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_key_names = array();
		$existing_primary_key = null;
		foreach ( $existing_keys as $key ) {
			if ( 'PRIMARY' === $key['Key_name'] ) {
				$existing_primary_key = $key['Column_name'];
			} else {
				$existing_key_names[ $key['Key_name'] ] = true;
			}
		}

		/** Get expected indexes from table class */
		$expected_indexes = $this->get_indexes();
		$expected_pk_column = ! empty( $expected_indexes['primary_key'] ) ? $expected_indexes['primary_key'] : null;
		$expected_indexes_list = ! empty( $expected_indexes['indexes'] ) ? $expected_indexes['indexes'] : array();
		$expected_unique_keys = ! empty( $expected_indexes['unique_keys'] ) ? $expected_indexes['unique_keys'] : array();

		/** Check and fix primary key */
		if ( ! empty( $expected_pk_column ) ) {
			$pk_was_added = false;
			$safe_pk_column = esc_sql( $expected_pk_column );

			/** If no primary key exists, clean duplicates before adding it */
			if ( empty( $existing_primary_key ) ) {
				if ( isset( $column_lookup[ $expected_pk_column ] ) ) {
					/** Clean duplicate entries in primary key column before adding PRIMARY KEY constraint */
					$db_errors = array_merge( $db_errors, $this->clean_duplicate_primary_key_values( $safe_table_name, $safe_pk_column, $expected_pk_column ) );

					/** Add primary key after cleaning duplicates */
					$query = "ALTER TABLE `{$safe_table_name}` ADD PRIMARY KEY (`{$safe_pk_column}`)"; //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
					$wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
					if ( ! empty( $wpdb->last_error ) ) {
						$db_errors[] = "Error adding primary key to {$this->table_name}: " . $wpdb->last_error;
					} else {
						$existing_primary_key = $expected_pk_column;
						$pk_was_added = true;
					}
				} else {
					$db_errors[] = "Primary key column '{$expected_pk_column}' not found in table {$this->table_name}";
				}
			}

			/** Check if primary key column has AUTO_INCREMENT */
			/** Refresh column data if primary key was just added */
			if ( $pk_was_added ) {
				$pk_column = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM `{$safe_table_name}` WHERE Field = %s", $existing_primary_key ), ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( ! empty( $pk_column ) ) {
					$column_lookup[ $existing_primary_key ] = $pk_column;
				}
			}

			/** Check AUTO_INCREMENT for primary key */
			if ( ! empty( $existing_primary_key ) && isset( $column_lookup[ $existing_primary_key ] ) ) {
				$pk_column_data = $column_lookup[ $existing_primary_key ];
				$extra = strtolower( trim( $pk_column_data['Extra'] ) );
				$has_auto_increment = ( 'auto_increment' === $extra || str_contains( $extra, 'auto_increment' ) );

				if ( ! $has_auto_increment ) {
					/** Build ALTER TABLE query to add AUTO_INCREMENT */
					$column_type = $pk_column_data['Type'];
					$column_null = ( 'YES' === $pk_column_data['Null'] ) ? 'NOT NULL' : '';
					$column_default = '';
					if ( ! empty( $pk_column_data['Default'] ) && 'NULL' !== strtoupper( $pk_column_data['Default'] ) ) {
						$escaped_default = esc_sql( $pk_column_data['Default'] );
						$column_default = "DEFAULT '{$escaped_default}'";
					}

					/** Build query parts efficiently */
					$modify_parts = array( $column_type );
					if ( ! empty( $column_null ) ) {
						$modify_parts[] = $column_null;
					}
					if ( ! empty( $column_default ) ) {
						$modify_parts[] = $column_default;
					}
					$modify_parts[] = 'AUTO_INCREMENT';

					$query = "ALTER TABLE `{$safe_table_name}` MODIFY COLUMN `{$safe_pk_column}` " . implode( ' ', $modify_parts ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

					$wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
					if ( ! empty( $wpdb->last_error ) ) {
						$db_errors[] = "Error adding AUTO_INCREMENT to primary key column '{$existing_primary_key}' in table {$this->table_name}: " . $wpdb->last_error;
					}
				}
			}
		}

		/** Check and fix regular indexes and unique keys using shared method */
		$db_errors = array_merge( $db_errors, $this->check_and_fix_indexes( $safe_table_name, $expected_indexes_list, $existing_key_names, $column_lookup, false ) );
		$db_errors = array_merge( $db_errors, $this->check_and_fix_indexes( $safe_table_name, $expected_unique_keys, $existing_key_names, $column_lookup, true ) );

		return ! empty( $db_errors ) ? $db_errors : true;
	}

	/**
	 * Clean duplicate values in primary key column before adding PRIMARY KEY constraint
	 *
	 * @param string $safe_table_name Escaped table name
	 * @param string $safe_pk_column Escaped primary key column name
	 * @param string $pk_column_name Primary key column name (for error messages)
	 *
	 * @return array Array of errors
	 */
	private function clean_duplicate_primary_key_values( $safe_table_name, $safe_pk_column, $pk_column_name ) {
		global $wpdb;
		$db_errors = array();

		/** Check for NULL values first */
		$null_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$safe_table_name}` WHERE `{$safe_pk_column}` IS NULL" ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $null_count > 1 ) {
			/** Delete all but one NULL entry */
			$delete_count = $null_count - 1;
			$delete_null_query = $wpdb->prepare( "DELETE FROM `{$safe_table_name}` WHERE `{$safe_pk_column}` IS NULL LIMIT %d", $delete_count ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $delete_null_query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( ! empty( $wpdb->last_error ) ) {
				$db_errors[] = "Error deleting NULL entries from table {$this->table_name}: " . $wpdb->last_error;
			}
		}

		/** Check for duplicate non-NULL values and delete them */
		$duplicates_query = "SELECT `{$safe_pk_column}`, COUNT(*) as count FROM `{$safe_table_name}` WHERE `{$safe_pk_column}` IS NOT NULL GROUP BY `{$safe_pk_column}` HAVING count > 1"; //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$duplicates = $wpdb->get_results( $duplicates_query, ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! empty( $duplicates ) ) {
			/** Delete duplicate entries, keeping the first one (lowest value) */
			foreach ( $duplicates as $duplicate ) {
				$duplicate_value = $duplicate[ $pk_column_name ];
				$duplicate_count = intval( $duplicate['count'] );
				$delete_count = $duplicate_count - 1;

				/** Delete all but one duplicate entry */
				$delete_query = $wpdb->prepare( "DELETE FROM `{$safe_table_name}` WHERE `{$safe_pk_column}` = %s LIMIT %d", $duplicate_value, $delete_count ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( $delete_query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( ! empty( $wpdb->last_error ) ) {
					$db_errors[] = "Error deleting duplicate entries with {$pk_column_name} = '{$duplicate_value}' from table {$this->table_name}: " . $wpdb->last_error;
				}
			}
		}

		return $db_errors;
	}

	/**
	 * Check and fix indexes or unique keys
	 *
	 * @param string $safe_table_name Escaped table name
	 * @param array  $expected_keys Expected keys array (index_name => [columns])
	 * @param array  $existing_key_names Array of existing key names
	 * @param array  $column_lookup Column lookup array for fast access
	 * @param bool   $is_unique Whether this is a unique key
	 *
	 * @return array Array of errors
	 */
	private function check_and_fix_indexes( $safe_table_name, $expected_keys, $existing_key_names, $column_lookup, $is_unique = false ) {
		global $wpdb;
		$db_errors = array();
		$key_type = $is_unique ? 'unique key' : 'index';

		foreach ( $expected_keys as $key_name => $key_columns ) {
			if ( ! is_array( $key_columns ) ) {
				$key_columns = array( $key_columns );
			}

			if ( ! isset( $existing_key_names[ $key_name ] ) ) {
				/** Verify all columns exist before creating key */
				$missing_columns = array();
				foreach ( $key_columns as $col ) {
					if ( ! isset( $column_lookup[ $col ] ) ) {
						$missing_columns[] = $col;
					}
				}

				if ( ! empty( $missing_columns ) ) {
					$db_errors[] = sprintf(
						"%s column(s) '%s' not found in table %s for %s '%s'",
						ucfirst( $key_type ),
						implode( "', '", $missing_columns ),
						$this->table_name,
						$key_type,
						$key_name
					);
					continue;
				}

				/** Build and execute query */
				$safe_columns = array_map( function( $col ) {
					return "`" . esc_sql( $col ) . "`";
				}, $key_columns );
				$columns_str = implode( ', ', $safe_columns );
				$safe_key_name = esc_sql( $key_name );
				$key_sql = $is_unique ? 'UNIQUE KEY' : 'INDEX';
				$query = "ALTER TABLE `{$safe_table_name}` ADD {$key_sql} `{$safe_key_name}` ({$columns_str})"; //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

				$wpdb->query( $query ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				if ( ! empty( $wpdb->last_error ) ) {
					$db_errors[] = "Error adding {$key_type} '{$key_name}' to table {$this->table_name}: " . $wpdb->last_error;
				}
			}
		}

		return $db_errors;
	}

	public function get_create_table_query() {
		return '';
	}

	public function get_collation() {
		if ( ! is_null( $this->collation ) ) {
			return $this->collation;
		}

		global $wpdb;
		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$this->collation = $collate;

		return $collate;
	}
}
