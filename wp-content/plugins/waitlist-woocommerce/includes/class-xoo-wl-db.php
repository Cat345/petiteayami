<?php

 class Xoo_Wl_DB{

 	protected static $_instance = null;
	public $waitlist_table, $waitlist_meta_table, $waitlist_crons_table;

	public static function get_instance(){
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct(){

		global $wpdb;

		$this->waitlist_table		= $wpdb->prefix . 'xoo_wl_list';
		$this->waitlist_meta_table 	= $wpdb->prefix . 'xoo_wl_list_meta';
		$this->waitlist_crons_table = $wpdb->prefix . 'xoo_wl_crons';

		$this->create_table();
		$this->hooks();

	}


	public function hooks(){
		add_action( 'plugins_loaded', array( $this, 'register_meta_table' ), 20 );
	}

	/* Inserts new Row if does not exist
	   Updates if row duplication is not allowed
	*/
	public function update_waitlist_row( $data = array() ){

		global $wpdb;

		$defaults = array(
			'product_id' 	=> 0,
			'join_date' 	=> current_time( 'mysql' ),
			'email'			=> null,
			'quantity' 		=> 1,
			'user_id'		=> get_current_user_id(),
			'product_name' 	=> null
		);

		$data = wp_parse_args( $data, $defaults );


		if( !$data['product_id'] || !$data['email'] ){
			return new WP_Error( "Product ID/ Email missing" );
		}

		$meta_data = array();

		$data = wp_unslash( apply_filters( 'xoo_wl_before_inserting_waitlist_row', $data ) );

		if( isset( $data['meta'] ) ){
			$meta_data = $data[ 'meta' ];
			unset( $data['meta'] );
		}

		$allowed_columns = apply_filters(
			'xoo_wl_allowed_waitlist_columns',
			array_keys( $defaults )
		);


		//Remove other keys
		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, $allowed_columns, true ) ){
				unset( $data[ $key ] );
			}
		}

		$allow_duplicate_email = apply_filters( 'xoo_wl_allow_duplicate_emails', false );
		$user_row_id = false;

		//Search if email id already exists
		if( !$allow_duplicate_email ){
			$user_exists =  $this->get_waitlist_rows_by_product( $data['product_id'], $data['email'] );
			if( !empty( $user_exists ) ){
				$user_row_id = $user_exists[0]->xoo_wl_id;
			}
		}

		$data = apply_filters(
			'xoo_wl_waitlist_row_data',
			$data,
			$user_row_id
		);


		//If user already exists & duplication is not allowed, update the row
		if( !$allow_duplicate_email && $user_row_id ){
			$action = $wpdb->update( $this->waitlist_table, $data, array(
				'xoo_wl_id' => $user_row_id
			) );
		}
		else{
			$action = $wpdb->insert( $this->waitlist_table, $data );
			$user_row_id = $wpdb->insert_id;
		}

		if( false === $action ){
			return new WP_Error( $wpdb->last_error );
		}

		foreach ( $meta_data as $meta_key => $meta_value ) {
			$this->update_waitlist_meta( $user_row_id, $meta_key, $meta_value );
		}

		
		return $user_row_id;

	}

	public function update_waitlist_meta( $xoo_wl_id, $meta_key, $meta_value ){

		update_metadata( 'xoo_wl', $xoo_wl_id, $meta_key, $meta_value );

	}

	public function get_waitlist_meta( $xoo_wl_id, $meta_key = '', $single = true ){
		$meta_value = get_metadata( 'xoo_wl', $xoo_wl_id, $meta_key, $single  );
		if( !$meta_key && is_array( $meta_value ) ){
			foreach ( $meta_value as $key => $value ) {
				$meta_value[ $key ] = maybe_unserialize( $value[0] );
			}
		}
		return $meta_value;
	}


	public function get_waitlist_meta_bulk( array $xoo_wl_ids ) {

		$xoo_wl_ids = array_values(
			array_unique(
				array_map( 'absint', $xoo_wl_ids )
			)
		);

		if ( empty( $xoo_wl_ids ) ) {
			return array();
		}

		// Prime meta cache (ONE query)
		update_meta_cache( 'xoo_wl', $xoo_wl_ids );

		$meta = array();

		foreach ( $xoo_wl_ids as $id ) {
			$meta[ $id ] = $this->get_waitlist_meta( $id );
		}

		return $meta;
	}





	private function get_placeholder( $input ){

		$type = gettype( $input );

		if( $type === "integer" ){
			return '%d';
		}elseif( $type === "float" ){
			return '%f';
		}
		elseif ( $type === 'array' ) {
			$placeholders = array();
		 	foreach ( $input as $inputVal ) {
		 		$placeholders[] = $this->get_placeholder($inputVal);
		 	}
		 	return '('.implode(',', $placeholders).')' ;
		}
		else{
			return '%s';
		}

	}


	public function get_products_waitlist( $args = array(), $output = OBJECT ) {

		global $wpdb;

		$defaults = array(
			'where'   => array(),
			'limit'   => -1,
			'offset'  => 0,
			'orderby' => '',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );
		extract( $args );

		/* Allowed ORDER BY columns */
		$allowed_orderby = array(
			'product_id',
			'quantity',
			'entries',
		);

		/* Build WHERE */
		$where_data = $this->build_where( $where );

		$query = "
			SELECT
				product_id,
				SUBSTRING_INDEX(
					GROUP_CONCAT(product_name ORDER BY join_date DESC SEPARATOR '|||'),
					'|||',
					1
				) AS product_name,
				SUM(quantity) AS quantity,
				COUNT(*) AS entries
			FROM {$this->waitlist_table}
			WHERE {$where_data['query']}
			GROUP BY product_id
		";


		$values = $where_data['values'];

		/* ✅ ORDER BY */
		if ( ! empty( $orderby ) && in_array( $orderby, $allowed_orderby, true ) ) {

			$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

			$query .= " ORDER BY {$orderby} {$order}";
		}

		/* Pagination */
		if ( $limit !== -1 ) {

			$query .= " LIMIT {$this->get_placeholder( $limit )}";
			$values[] = $limit;

			if ( $offset ) {
				$query .= " OFFSET {$this->get_placeholder( $offset )}";
				$values[] = $offset;
			}
		}

		return $wpdb->get_results(
			$wpdb->prepare( $query, $values ),
			$output
		);
	}





	public function get_product_waitlist_count( $product_id ){
		return $this->get_waitlisted_count( array(
				'where' => array(
					array(
						'key' 		=> 'product_id',
						'value' 	=> $product_id,
						'compare' 	=> '='
					)
				)
			)
		);
	}


	public function get_waitlisted_count( $args = array() ){

		global $wpdb;

		$defaults = array(
			'where'      => array(),
			'relation'   => 'AND'
		);

		$args = wp_parse_args( $args, $defaults );

		extract($args);

		$query = "
		SELECT COUNT(DISTINCT {$this->waitlist_table}.product_id) AS productsCount, COUNT(*) AS rowsCount, SUM(quantity) AS totalQuantity FROM {$this->waitlist_table}
		";

		$values = array();

		$query .= " WHERE ";


		$where_build = $this->build_where( $where, $relation );

		$query  .= $where_build['query'];
		$values  = array_merge( $values, $where_build['values'] );

	
		$results = $wpdb->get_row(
			$wpdb->prepare( 
				$query,
				$values
			),
			ARRAY_A
		);

		return array(
			'rowsCount' 	=> $results['rowsCount'],
			'productsCount' => $results['productsCount'],
			'totalQuantity' => $results['totalQuantity']
		);

	}


	public function get_waitlist_rows_by_product( $product_id, $user_email = false, $args = array() ){
		
		$defaults = array(
			'limit' 	=> -1,
			'offset' 	=> 0
		);

		$args = wp_parse_args( $args, $defaults );

		$args['where'][] = array(
			'key' 		=> 'product_id',
			'value' 	=> (int) $product_id,
			'compare' 	=> '='
		);


		if( $user_email ){
			$args['where'][] = array(
				'key' 		=> 'email',
				'value' 	=> $user_email,
				'compare' 	=> '='
			);
		}

		$rows = $this->get_waitlist_rows( $args );
		
		return $rows;
	}


	public function get_waitlist_row( $row_id, $output = OBJECT ){

		$args['where'][] = array(
			'key' 		=> 'xoo_wl_id',
			'value' 	=> $row_id,
			'compare' 	=> '='
		);

		$rows = $this->get_waitlist_rows( $args, $output );

		if( !empty( $rows ) ){
			return $rows[0];
		}

		return false;
	}

	public function get_waitlist_rows( $args = array(), $output = OBJECT ){
		return $this->get_rows( $this->waitlist_table, $args, $output );
	}

	public function get_cron_rows( $args = array(), $output = OBJECT ){
		return $this->get_rows( $this->waitlist_crons_table, $args );
	}


	public function build_where( $where, $relation = 'AND' ) {

		$query  = array();
		$values = array();

		if ( empty( $where ) ) {
			return array(
				'query'  => '1 = %d',
				'values' => array( 1 ),
			);
		}

		foreach ( $where as $index => $whereData ) {

			/* 🔁 Nested group */
			if ( isset( $whereData['where'] ) && is_array( $whereData['where'] ) ) {

				$group_relation = strtoupper( $whereData['relation'] ?? 'AND' );
				$nested = $this->build_where( $whereData['where'], $group_relation );

				$query[]  = '( ' . $nested['query'] . ' )';
				$values   = array_merge( $values, $nested['values'] );
				continue;
			}

			$key     = $whereData['key'];
			$compare = $whereData['compare'] ?? '=';
			$value   = $whereData['value'];

			$placeholder = $this->get_placeholder( $value );

			$query[] = "{$key} {$compare} {$placeholder}";

			if ( is_array( $value ) ) {
				$values = array_merge( $values, $value );
			} else {
				$values[] = $value;
			}
		}

		return array(
			'query'  => implode( " {$relation} ", $query ),
			'values' => $values,
		);
	}


	public function get_rows( $table, $args = array(), $output = OBJECT ) {

		global $wpdb;

		$defaults = array(
			'limit'      => -1,
			'offset'     => 0,
			'orderby'    => '',
			'order'      => 'DESC',
			'where'      => array(),
			'meta_query' => array(),
			'relation'   => 'AND'
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args );

		$allowed_orderby = array(
			'join_date',
			'quantity',
			'xoo_wl_id',
		);

		$values = array();
		$query  = "SELECT * FROM {$table} WHERE ";

		$where_build = $this->build_where( $where, $relation );

		$query  .= $where_build['query'];
		$values  = array_merge( $values, $where_build['values'] );

		/* ✅ ORDER BY */
		if ( ! empty( $orderby ) && in_array( $orderby, $allowed_orderby, true ) ) {

			$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

			$query .= " ORDER BY {$orderby} {$order}";
		}

		/* LIMIT & OFFSET */
		if ( $limit !== -1 ) {

			$query .= " LIMIT {$this->get_placeholder( $limit )}";
			$values[] = $limit;

			if ( $offset ) {
				$query .= " OFFSET {$this->get_placeholder( $offset )}";
				$values[] = $offset;
			}
		}

		return $wpdb->get_results(
			$wpdb->prepare( $query, $values ),
			$output
		);
	}


	/* $where contains key value pair of column and value */
	public function delete_waitlist_row( $where = array() ){

		global $wpdb;

		if( empty( $where ) ) return;

		$where = wp_unslash( $where );

		return $wpdb->delete(
			$this->waitlist_table,
			$where
		);
	}


	/**
	 * Delete waitlist rows and related meta
	 *
	 * @param array $ids Waitlist IDs
	 *
	 * @return bool
	 */
	public function delete_waitlist_rows( array $ids ) {

		global $wpdb;

		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// Start transaction (if supported)
		$wpdb->query( 'START TRANSACTION' );

		try {

			// Delete meta first
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$this->waitlist_meta_table}
					 WHERE xoo_wl_id IN ($placeholders)",
					$ids
				)
			);

			// Delete main rows
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$this->waitlist_table}
					 WHERE xoo_wl_id IN ($placeholders)",
					$ids
				)
			);

			$wpdb->query( 'COMMIT' );

			return true;

		} catch ( Exception $e ) {

			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}



	/**
	 * Delete waitlist rows and related meta for multiple product IDs
	 *
	 * @param array $product_ids
	 * @return bool|WP_Error
	 */
	public function delete_waitlist_by_products( array $product_ids ) {

		global $wpdb;

		$product_ids = array_map( 'absint', $product_ids );
		$product_ids = array_filter( $product_ids );

		if ( empty( $product_ids ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

		$wpdb->query( 'START TRANSACTION' );

		try {

			/**
			 * 1. Delete meta using JOIN (fastest, no PHP loops)
			 */
			$meta_sql = "
				DELETE m
				FROM {$this->waitlist_meta_table} m
				INNER JOIN {$this->waitlist_table} w
					ON w.xoo_wl_id = m.xoo_wl_id
				WHERE w.product_id IN ($placeholders)
			";

			$wpdb->query(
				$wpdb->prepare( $meta_sql, $product_ids )
			);

			/**
			 * 2. Delete waitlist rows
			 */
			$row_sql = "
				DELETE FROM {$this->waitlist_table}
				WHERE product_id IN ($placeholders)
			";

			$wpdb->query(
				$wpdb->prepare( $row_sql, $product_ids )
			);

			$wpdb->query( 'COMMIT' );
			return true;

		} catch ( Exception $e ) {

			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'delete_failed', $e->getMessage() );
		}
	}




	public function delete_waitlist_row_by_id( $row_id ){

		global $wpdb;

		$delete_meta = $this->delete_waitlist_meta_by_row_id( array(
			$row_id
		) );

		if( false === $delete_meta ){
			return new WP_Error( $wpdb->last_error );
		}

		$delete_row = $this->delete_waitlist_row(
			array(
				'xoo_wl_id' => $row_id
			)
		);

		if( false === $delete_row ){
			return new WP_Error( $wpdb->last_error );
		}


	}

	public function delete_waitlist_meta_by_row_id( $row_ids ){

		global $wpdb;

		if( empty( $row_ids ) ) return;

		$row_ids = wp_unslash( $row_ids );

		$placeholder =  implode( ",", array_fill( 0, count($row_ids), '%d' ) );

		$query = "DELETE FROM {$this->waitlist_meta_table} WHERE xoo_wl_id IN ({$placeholder})";

		return $wpdb->query(
			$wpdb->prepare(
				$query,
				$row_ids
			)
		);
	}


	public function insert_cron_row( $data = array() ){

		global $wpdb;

		$defaults = array(
			'product_id' 	=> 0,
			'status' 		=> 'inqueue',
			'created' 		=> current_time( 'mysql' ),
			'emails_count'	=> 0,
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert( $this->waitlist_crons_table, $data );

		return $wpdb->insert_id;

	}


	public function update_cron_row( $data, $where ){

		global $wpdb;

		return $wpdb->update( $this->waitlist_crons_table, $data, $where );

	}

	public function get_cron_row_by_id( $cron_id, $args = array() ){
		
		$args['where'][] = array(
			'key' 		=> 'cron_id',
			'value' 	=> $cron_id,
			'compare' 	=> '='
		);

		$rows = $this->get_cron_rows( $args );

		if( !empty( $rows ) ){
			return $rows[0]; // can only be one row
		}
		
	}


	public function get_cron_rows_by_product_id( $product_id, $status = '', $args = array() ){
		
		$args['where'][] = array(
			'key' 		=> 'product_id',
			'value' 	=> $product_id,
			'compare' 	=> '='
		);
		
		if( $status ){
			$args['where'][] = array(
				'key' 		=> 'status',
				'value' 	=> $status,
				'compare' 	=> is_array( $status ) ? 'IN': '='
			);
		}

		$rows = $this->get_cron_rows( $args );
		
		return $rows;
	}


	public function get_cron_rows_by_status( $status, $args = array() ){

		
		$args['where'][] = array(
			'key' 		=> 'status',
			'value' 	=> $status,
			'compare' 	=> is_array( $status ) ? 'IN': '='
		);
		

		$rows = $this->get_cron_rows( $args );
		
		return $rows;

	}



	//Clear completed cron jobs
	public function clear_completed_crons(){

		global $wpdb;

		return $wpdb->query( "TRUNCATE TABLE $this->waitlist_crons_table" );
	}


	public function cleanup_crons(){

		global $wpdb;

		$lastChecked = get_option( 'xoo-wl-cron-cleanup-last-check' );

		//Check once in 30 days
		if( !$lastChecked || strtotime( $lastChecked ) < strtotime('-31 days') ){

			// Query to delete rows older than 30 days based on the 'created' column
			$query = $wpdb->prepare(
			    "DELETE FROM $this->waitlist_crons_table WHERE created < DATE_SUB(NOW(), INTERVAL 30 DAY)"
			);

			// Execute the query
			$wpdb->query($query);

			update_option( 'xoo-wl-cron-cleanup-last-check', date('Y-m-d') );
		}

	}



	public function create_table() {

		global $wpdb;

		$version_option = 'xoo-wl-db-version';
		$db_version     = get_option( $version_option );

		// Only run if upgrade is needed
		if ( version_compare( $db_version, '1.2', '>=' ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$this->waitlist_table} (
			xoo_wl_id BIGINT(20) UNSIGNED AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			product_name VARCHAR(255) NOT NULL DEFAULT '',
			email VARCHAR(100) NOT NULL,
			quantity FLOAT(20) UNSIGNED NOT NULL,
			join_date DATETIME NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (xoo_wl_id),
			KEY product_id (product_id)
		) $charset_collate;";

		$sql .= "CREATE TABLE {$this->waitlist_meta_table} (
			meta_id BIGINT(20) UNSIGNED AUTO_INCREMENT,
			xoo_wl_id BIGINT(20) UNSIGNED NOT NULL,
			meta_key VARCHAR(255),
			meta_value LONGTEXT,
			PRIMARY KEY  (meta_id),
			KEY meta_key (meta_key),
			KEY xoo_wl_id (xoo_wl_id)
		) $charset_collate;";

		$sql .= "CREATE TABLE {$this->waitlist_crons_table} (
			cron_id BIGINT(20) UNSIGNED AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			status VARCHAR(100) NOT NULL,
			created DATETIME NOT NULL,
			emails_count BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (cron_id),
			KEY product_id (product_id),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $sql );

		update_option( $version_option, '1.2' );
	}




	public function register_meta_table(){
		global $wpdb;
		$wpdb->xoo_wlmeta = $this->waitlist_meta_table;
	}

}


function xoo_wl_db(){
	return Xoo_Wl_DB::get_instance();
}
xoo_wl_db();