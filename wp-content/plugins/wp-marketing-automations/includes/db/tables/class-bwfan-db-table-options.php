<?php

#[\AllowDynamicProperties]
class BWFAN_DB_Table_Options extends BWFAN_DB_Tables_Base {
	public $table_name = 'bwf_options';

	/** bwf_options_update() treats ID 0 as a missing option, and no other row points to this ID */
	public $renumber_zero_pk = true;

	/**
	 * Get table's columns
	 *
	 * @return string[]
	 */
	public function get_columns() {
		return [
			"id",
			"key",
			"value",
		];
	}

	/**
	 * Get primary key and indexes definition
	 *
	 * @return array
	 */
	public function get_indexes() {
		return [
			'primary_key' => 'id',
			'indexes'    => [
				'id'  => [ 'id' ],
				'key' => [ 'key' ],
			],
			'unique_keys' => [],
		];
	}

	/**
	 * Create the table only when it is missing.
	 *
	 * dbDelta() treats any schema line whose first word is `key` as an index, so on an existing table it
	 * misreads the `key` column and runs "ALTER TABLE ... ADD `` (``)", a SQL syntax error. The schema has
	 * never changed, and the table validation tool repairs its primary key and indexes, so skip dbDelta here.
	 *
	 * @return void
	 */
	public function create_table() {
		if ( $this->is_exists() ) {
			return;
		}

		parent::create_table();
	}

	/**
	 * Get query for create table
	 *
	 * @return string
	 */
	public function get_create_table_query() {
		global $wpdb;
		$collate = $this->get_collation();

		return "CREATE TABLE {$wpdb->prefix}$this->table_name (
 		  	`id` bigint(10) unsigned NOT NULL auto_increment,
			`key` varchar(150) default NULL,
			`value` longtext,
			PRIMARY KEY (`id`),
			KEY `id` (`id`),
			KEY `key` (`key`)
		) $collate;";
	}
}
