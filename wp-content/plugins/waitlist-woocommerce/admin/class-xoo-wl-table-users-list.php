<?php

if ( ! class_exists( 'Xoo_WL_Table_Users_List_Parent' ) ) {
	require_once XOO_WL_PATH . '/admin/class-xoo-wl-table-users-list-parent.php';
}


class Xoo_WL_Table_Users_List extends Xoo_WL_Table_Users_List_Parent {

	public $xoo_pageurl;

	protected $product_cache = array();

	public function __construct() {

		$this->xoo_pageurl = esc_url( add_query_arg( 'list', 'users', admin_url( 'admin.php?page=xoo-wl-view-waitlist' ) ) );

		parent::__construct();
	}


	protected function get_product( $product_id ) {

	    if ( ! isset( $this->product_cache[ $product_id ] ) ) {
	        $this->product_cache[ $product_id ] = wc_get_product( $product_id );
	    }

	    return $this->product_cache[ $product_id ];
	}



	/* Columns */
	public function get_columns() {

		$columns = parent::get_columns();

		$new = [];

		foreach ( $columns as $key => $label ) {
	        $new[ $key ] = $label;

	        if ( $key === 'join_date' ) {
	            $new['product_id'] = 'Product';
	        }
	    }


		return $new;
	}


	protected function column_product_id( $item ) {

		$product_id = (int) $item['product_id'];

		if ( !$product_id || !$this->get_product( $product_id ) ) {
			return xoo_wl_admin_settings()->waitlist_product_not_exist( $item['product_name'] );
		}

		return xoo_wl_admin_settings()->waitlist_table_product_column( $this->get_product( $product_id ) );
		
	}

	

	

	public function prepare_items() {

		parent::prepare_items();

		$this->preload_products();

		
	}



	protected function preload_products() {

		if ( empty( $this->items ) ) {
			return;
		}

		$product_ids = array();

		foreach ( $this->items as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$product_ids[] = (int) $item['product_id'];
			}
		}

		$product_ids = array_unique( $product_ids );

		if ( empty( $product_ids ) ) {
			return;
		}


		$products = wc_get_products(
			array(
				'include' => $product_ids,
				'limit'   => -1,
				'type'  => array_merge(
					array_keys( wc_get_product_types() ),
					array( 'variation' )
				)
			)
		);


		foreach ( $products as $product ) {
			$this->product_cache[ $product->get_id() ] = $product;
		}
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

			<?php if ( ! empty( $_GET ) && count( $_GET ) > 1 ) : ?>
				<a href="<?php echo esc_url(
				add_query_arg(
					array( 'page' => sanitize_key( $_GET['page'] ?? '' ) ),
					admin_url( 'admin.php' )
				)
			); ?>" class="button">
					Clear filters
				</a>
			<?php endif; ?>

		</div>
		<?php
	}


}
