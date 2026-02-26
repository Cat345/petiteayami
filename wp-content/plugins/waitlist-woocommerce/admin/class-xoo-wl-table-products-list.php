<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Xoo_WL_Table_Products_List extends WP_List_Table {

	public $count;

	public $xoo_fields;

	protected $product_cache = array();

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


	protected function get_product( $product_id ) {

	    if ( ! isset( $this->product_cache[ $product_id ] ) ) {
	        $this->product_cache[ $product_id ] = wc_get_product( $product_id );
	    }

	    return $this->product_cache[ $product_id ];
	}

	/* Columns */
	public function get_columns() {

		$cols = array(
			'cb'        	=> '<input type="checkbox" />',
			'product_id' 	=> 'Product',
			'quantity' 		=> 'Quantity',
			'entries' 		=> 'Users',
			'actions' 		=> 'Actions'
		);

		if( $this->xoo_fields['xoo_wl_required_qty']['settings']['active'] !== "yes" ){
			unset( $cols['quantity'] );
		}

		return $cols;
	}

	/* Sortable columns */
	protected function get_sortable_columns() {
		return array(
			'entries' 	=> array( 'entries', true ),
			'quantity'  => array( 'quantity', false ),
		);
	}

	/* Checkbox column */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="ids[]" value="%d" />',
			(int) $item['product_id']
		);
	}

	protected function column_product_id( $item ) {

		$product_id = (int) $item['product_id'];

		$product = $this->get_product( $product_id );

		if( !$product ){
			return xoo_wl_admin_settings()->waitlist_product_not_exist( $item['product_name'] );
		}
		else{
			return xoo_wl_admin_settings()->waitlist_table_product_column( $product );
		}

		
	}

	

	/* Actions column output */
	protected function column_actions( $item ) {
		ob_start();
		$product_id = (int) $item['product_id'];
		$product = $this->get_product( $product_id );
		?>
		<div class="xoo-wl-actions">
			<?php if( $product ): ?>
				<div class="xoo-wl-bis-btn xoo-wl-button" data-product_id="<?php echo $product_id ?>"><?php include XOO_WL_PATH.'/admin/assets/icons/email.svg' ?>Email all users</div>
			<?php endif; ?>
			<a href="<?php echo admin_url( 'admin.php?page=xoo-wl-view-waitlist&product='.$product_id ) ?>" class="xoo-wl-button xoo-wl-btnoutline" data-product_id="<?php echo $product_id ?>">View Users</a>
		</div>
		<?php
		return ob_get_clean();
	}



	public function column_default( $item, $column_name ) {

		// 🔥 Dynamic custom fields
		if ( isset( $item[ $column_name ] ) ) {
			return esc_html( $item[ $column_name ] );
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

		$search = sanitize_text_field( $_REQUEST['s'] ?? '' );

		$stock_status = sanitize_text_field( $_GET['stock_status'] ?? '' );

		$product_ids = null;

		/**
		 * Build a single product query if needed
		 */
		if ( $search !== '' || $stock_status !== '' ) {

			$args = array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			);

			/* Search */
			if ( $search !== '' ) {
				if ( is_numeric( $search ) ) {

					// Direct product ID search → skip WP_Query
					$product_ids = array( absint( $search ) );

				} else {
					$args['s'] = $search;
				}
			}

			/* Stock status */
			if ( $stock_status !== '' ) {
				$args['meta_query'][] = array(
					'key'   => '_stock_status',
					'value' => $stock_status,
				);
			}

			/* Run query only if needed */
			if ( $product_ids === null ) {
				$product_ids = get_posts( $args );
			}

			$where_args[] = array(
				'key'     => 'product_id',
				'compare' => 'IN',
				'value'   => $product_ids ?: array( 0 ),
			);
		}




		/* 🔹 Count query (must include filters) */
		$this->count = $count_data  = xoo_wl_db()->get_waitlisted_count( array(
			'where' => $where_args
		) );

		
		$total_items = isset( $count_data['rowsCount'] ) ? (int) $count_data['rowsCount'] : 0;

		/* 🔹 Whitelist sortable columns */
		$sortable = array(
			'entries' 	=> 'entries',
			'quantity'  => 'quantity',
		);

		$orderby = isset( $_GET['orderby'], $sortable[ $_GET['orderby'] ] ) ? $sortable[ $_GET['orderby'] ] : 'entries';
		$order   = isset( $_GET['order'] ) && 'ASC' === strtoupper( $_GET['order'] ) ? 'ASC' : 'DESC';

		
		/* 🔹 Main data query */
		$this->items = xoo_wl_db()->get_products_waitlist(
			array(
				'limit'     => $per_page,
				'offset'    => $offset,
				'orderby'   => $orderby,
				'order'     => $order,
				'where' 	=> $where_args
			),
			ARRAY_A
		);


		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
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

		xoo_wl_db()->delete_waitlist_by_products( $ids );

		wp_safe_redirect(
			remove_query_arg( array( 'action', 'action2', 'ids', '_wpnonce' ) )
		);
		exit;
	}


	protected function extra_tablenav( $which ) {

		if ( $which !== 'top' ) {
			return;
		}

		$current = sanitize_text_field( $_GET['stock_status'] ?? '' );
		$statuses = wc_get_product_stock_status_options();
		?>
		<div class="alignleft actions">
			<select name="stock_status">
				<option value=""><?php esc_html_e( 'All stock statuses', 'woocommerce' ); ?></option>

				<?php foreach ( $statuses as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $current, $status ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}



}
