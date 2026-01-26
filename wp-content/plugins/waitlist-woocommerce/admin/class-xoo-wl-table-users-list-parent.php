<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Xoo_WL_Table_Users_List_Parent extends WP_List_Table {

	public $xoo_fields;

	public $count;

	public $send_email_button = true;

	public function __construct() {

		$this->xoo_fields = xoo_wl()->aff->fields->get_fields_data();

		parent::__construct(
			array(
				'singular' => 'waitlist',
				'plural'   => 'waitlists',
				'ajax'     => false,
			)
		);
	}


	/* Columns */
	public function get_columns() {

		$customFieldsData = $this->xoo_fields;

		unset( $customFieldsData['xoo_wl_required_qty'] );
		unset( $customFieldsData['xoo_wl_user_email'] );

		$extraFields = array();

		foreach ( $customFieldsData as $field_id => $field_data ) {
			$settings = $field_data['settings'];
			if( $settings['active'] !== "yes" ) continue;
			$extraFields[ $field_id ] = xoo_wl()->aff->fields->get_field_label( $field_data );
		}

		$cols = array(
			'cb'        	=> '<input type="checkbox" />',
			'join_date' 	=> 'Joined On',
			'email'     	=> 'Email',
			'quantity'  	=> 'Quantity',
		);

		$cols = array_merge( $cols, $extraFields );

		$cols['actions'] = 'Actions';

		if( $this->xoo_fields['xoo_wl_required_qty']['settings']['active'] !== "yes" ){
			unset( $cols['quantity'] );
		}

		return $cols;
	}

	/* Sortable columns */
	protected function get_sortable_columns() {
		return array(
			'join_date' => array( 'join_date', true ),
			'quantity'  => array( 'quantity', false ),
		);
	}

	/* Checkbox column */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="ids[]" value="%d" />',
			(int) $item['xoo_wl_id']
		);
	}

	

	/* Actions column output */
	protected function column_actions( $item ) {
		$meta_data 	= $item['meta'];
		$sent_count = isset( $meta_data['_sent_count'] ) ? (int) $meta_data['_sent_count'] : '';
		ob_start();
		?>
		<div class="xoo-wl-actions">
			<?php if( $this->send_email_button ): ?>
				<div class="xoo-wl-bis-btn xoo-wl-button" data-row_id="<?php echo (int) $item['xoo_wl_id'] ?>"><?php include XOO_WL_PATH.'/admin/assets/icons/email.svg' ?>Send Email <span class="xoo-wl-sent-count"><?php echo (int) $sent_count ? '('.$sent_count.')': ''; ?></span></span></div>
			<?php endif; ?>
			<button class="button xoo-wl-remove-row" data-row_id="<?php echo (int) $item['xoo_wl_id'] ?>">Remove</button>
		</div>
		<?php
		return ob_get_clean();
	}



	public function column_default( $item, $column_name ) {

		// 🔥 Dynamic custom fields
		if ( isset( $item[ $column_name ] ) ) {
			return esc_html( $item[ $column_name ] );
		}

		// Meta column
		if ( isset( $item['meta'][ $column_name ] ) ) {
			return esc_html( $item['meta'][ $column_name ] );
		}

		return '';
	}


	/* Bulk actions */
	protected function get_bulk_actions() {
		return array(
			'delete' => 'Delete',
		);
	}

	public function prepare_items() {

		$this->process_bulk_action();

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$where_args = array();

		if( isset( $_GET['product'] ) ){
			$where_args[] = array(
				'key' 		=> 'product_id',
				'value' 	=> (int) $_GET['product'],
				'compare' 	=> '='
			);
		}

		/* 🔹 Read date filters */
		$from_date = isset( $_GET['from_date'] ) && $_GET['from_date'] ? sanitize_text_field( $_GET['from_date'] ).' 00:00:00' : null;
		$to_date   = isset( $_GET['to_date'] ) && $_GET['to_date'] ? sanitize_text_field( $_GET['to_date'] ). ' 23:59:59' : null;

		if( $from_date ){
			$where_args[] = array(
				'key' 	=> 'join_date',
				'value' => $from_date,
				'compare' => '>='
			);
		}

		if( $to_date ){
			$where_args[] = array(
				'key' 	=> 'join_date',
				'value' => $to_date,
				'compare' => '<='
			);
		}


		/* 🔹 Apply search */
		$search = isset( $_REQUEST['s'] ) && $_REQUEST['s'] ? sanitize_text_field( $_REQUEST['s'] ) : null;

		if ( $search ) {
			$where_args[] = array(
				'relation' => 'OR',
				'where'    => array(
					array(
						'key'     => 'email',
						'value'   => '%' . $search . '%',
						'compare' => 'LIKE',
					)
				),
			);
		}


		$user_type = isset( $_GET['user_type'] ) ? sanitize_key( $_GET['user_type'] ) : '';

		if ( 'guest' === $user_type ) {

			$where_args[] = array(
				'key'     => 'user_id',
				'value'   => 0,
				'compare' => '=',
			);

		} elseif ( 'registered' === $user_type ) {

			$where_args[] = array(
				'key'     => 'user_id',
				'value'   => 0,
				'compare' => '>',
			);
		}


		/* 🔹 Count query (must include filters) */
		$this->count  = $count_data  = xoo_wl_db()->get_waitlisted_count( array(
			'where' => $where_args
		) );

		
		$total_items = isset( $count_data['rowsCount'] ) ? (int) $count_data['rowsCount'] : 0;

		/* 🔹 Whitelist sortable columns */
		$sortable = array(
			'join_date' => 'join_date',
			'quantity'  => 'quantity',
		);

		$orderby = isset( $_GET['orderby'], $sortable[ $_GET['orderby'] ] ) ? $sortable[ $_GET['orderby'] ] : 'join_date';
		$order   = isset( $_GET['order'] ) && 'ASC' === strtoupper( $_GET['order'] ) ? 'ASC' : 'DESC';

		/* 🔹 Main data query */
		$this->items = xoo_wl_db()->get_waitlist_rows(
			array(
				'limit'     => $per_page,
				'offset'    => $offset,
				'orderby'   => $orderby,
				'order'     => $order,
				'where' 	=> $where_args
			),
			ARRAY_A
		);


		if ( ! empty( $this->items ) ) {

			$ids = wp_list_pluck( $this->items, 'xoo_wl_id' );

			$all_meta = xoo_wl_db()->get_waitlist_meta_bulk( $ids );

			foreach ( $this->items as &$item ) {
				$id = (int) $item['xoo_wl_id'];

				// Store meta under a single key
				$item['meta'] = $all_meta[ $id ] ?? array();
			}

			unset( $item );

		}

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}



	protected function column_join_date( $item ) {

		if ( empty( $item['join_date'] ) ) {
			return '—';
		}

		try {
			// Create DateTime using WordPress timezone
			$dt = new DateTime(
				$item['join_date'],
				wp_timezone()
			);

			// Convert to UTC timestamp (what wp_date expects)
			$timestamp = $dt->getTimestamp();

		} catch ( Exception $e ) {
			return esc_html( $item['join_date'] );
		}

		$exact = wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp
		);

		$relative = human_time_diff(
			$timestamp,
			current_time( 'timestamp', true ) // UTC-safe
		) . ' ago';

		ob_start();
		?>
		<div class="xoo-wl-date">
			<span><?php echo esc_html( $relative ); ?></span>
			<span><?php echo esc_html( $exact ); ?></span>
		</div>
		<?php

		return ob_get_clean();
	}



	protected function process_bulk_action() {

		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		if ( ! current_user_can( xoo_wl_helper()->admin->capability ) ) {
			return;
		}

		$ids = isset( $_REQUEST['ids'] ) ? (array) $_REQUEST['ids'] : array();

		xoo_wl_db()->delete_waitlist_rows( $ids );

		wp_safe_redirect(
			remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce' ) )
		);
		exit;
	}




	protected function extra_tablenav( $which ) {

		if ( $which !== 'top' ) {
			return;
		}

		$from = isset( $_GET['from_date'] ) ? esc_attr( $_GET['from_date'] ) : '';
		$to   = isset( $_GET['to_date'] ) ? esc_attr( $_GET['to_date'] ) : '';
		?>
		<div class="alignleft actions">

			<label for="from_date" class="screen-reader-text">From date</label>
			<input type="date" name="from_date" id="from_date" value="<?php echo $from; ?>" />

			<label for="to_date" class="screen-reader-text">To date</label>
			<input type="date" name="to_date" id="to_date" value="<?php echo $to; ?>" />

			<select name="user_type">
				<option value="">All Users</option>
				<option value="guest" <?php selected( $_GET['user_type'] ?? '', 'guest' ); ?>>
					Guest
				</option>
				<option value="registered" <?php selected( $_GET['user_type'] ?? '', 'registered' ); ?>>
					Registered
				</option>
			</select>


			<?php submit_button( 'Filter', '', 'filter_action', false ); ?>

			<?php if ( ! empty( $_GET ) && isset( $_GET['filter_action'] ) ) : ?>
				<a href="<?php echo esc_url( $this->xoo_pageurl ); ?>" class="button">Clear</a>
			<?php endif; ?>

		</div>
		<?php
	}


}
