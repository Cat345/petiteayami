<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
if ( ! class_exists( 'BWFABT_Experiment_Table' ) ) {
	#[AllowDynamicProperties]
	class BWFABT_Experiment_Table extends WP_List_Table {

		public $per_page = 4;
		public $data;
		public $meta_data;
		public $date_format;

		/**
		 * Constructor.
		 * @since  1.0.0
		 */
		public function __construct( $args = array() ) {
			parent::__construct( array(
				'singular' => 'Experiment',
				'plural'   => 'Experiments',
				'ajax'     => false,
			) );

			$this->data        = array();
			$this->date_format = BWFABT_Core()->admin->get_date_format();
			$this->per_page    = BWFABT_Core()->admin->posts_per_page();
			// Make sure this file is loaded, so we have access to plugins_api(), etc.
			require_once( ABSPATH . '/wp-admin/includes/plugin-install.php' );

			parent::__construct( $args );

		}


		/**
		 * Text to display if no experiments are present.
		 * @return  void
		 * @since  1.0.0
		 */
		public function no_items() {
			echo esc_html__( 'No experiment available.', 'woofunnels-ab-tests' );
		}

		public function column_name( $item ) {
			$edit_link     = $item['row_actions']['edit']['link'];
			$column_string = '<div><strong>';

			$column_string .= '<a href="' . $edit_link . '" class="row-title">' . $item['title'] . '</a>';
			$column_string .= '</strong>';

			$column_string .= "<div style='clear:both'></div></div>";

			$column_string .= '<div class=\'row-actions\'>';

			$item_last     = array_keys( $item['row_actions'] );
			$item_last_key = end( $item_last );
			foreach ( $item['row_actions'] as $k => $action ) {
				$column_string .= '<span class="' . $action['action'] . '"><a href="' . $action['link'] . '" ' . $action['attrs'] . ' >' . $action['text'] . '</a>';

				if ( $k !== $item_last_key ) {
					$column_string .= ' | ';
				}
				$column_string .= '</span>';
			}
			$column_string .= '</div>';

			return ( $column_string );
		}

		public function column_description( $item ) {
			return $item['desc'];
		}

		public function column_status( $item ) {
			$statuses = BWFABT_Core()->admin->get_experiment_statuses();

			return isset( $statuses[ $item['status'] ] ) ? $statuses[ $item['status'] ] : '';
		}

		public function column_type( $item ) {
			return $item['type'];
		}

		public function column_date_added( $item ) {
			return $item['date_added'];
		}

		public function column_quick_links( $item ) {

			$id = absint( $item['id'] );

			$links = apply_filters( 'bwfabt_experiments_quick_links', array(
				array(
					'text' => __( 'Variants', 'woofunnels-ab-tests' ),
					'link' => add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'variants',
						'edit'    => $id,
					), admin_url( 'admin.php' ) ),

				),
				array(
					'text' => __( 'Analytics', 'woofunnels-ab-tests' ),
					'link' => add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'analytics',
						'edit'    => $id,
					), admin_url( 'admin.php' ) ),
				),
				array(
					'text' => __( 'Settings', 'woofunnels-ab-tests' ),
					'link' => add_query_arg( array(
						'page'    => 'bwf_ab_tests',
						'section' => 'settings',
						'edit'    => $id,
					), admin_url( 'admin.php' ) ),

				),
			) );

			$html = array();
			foreach ( $links as $link ) {
				$html[] = '<span><a href="' . $link['link'] . '">' . $link['text'] . '</a></span>';
			}

			return ( count( $html ) > 0 ) ? implode( ' | ', $html ) : false;
		}

		/**
		 * Prepare an array of items to be listed.
		 * @return array Prepared items.
		 * @since  1.0.0
		 */
		public function prepare_items() {
			$columns               = $this->get_columns();
			$hidden                = array();
			$sortable              = $this->get_sortable_columns();
			$this->_column_headers = array( $columns, $hidden, $sortable );

			$total_items = $this->data['found_posts'];

			$this->set_pagination_args( array(
				'total_items' => $total_items, //WE have to calculate the total number of items
				'per_page'    => $this->per_page, //WE have to determine how many items to show on a page
			) );
			$this->items = $this->data['items'];
		}

		/**
		 * Retrieve an array of columns for the list table.
		 * @return array Key => Value pairs.
		 * @since  1.0.0
		 */
		public function get_columns() {
			$columns = array(
				'name'        => __( 'Experiment', 'woofunnels-ab-tests' ),
				'description' => __( 'Description', 'woofunnels-ab-tests' ),
				'type'        => __( 'Type', 'woofunnels-ab-tests' ),
				'status'      => __( 'Status', 'woofunnels-ab-tests' ),
				'date_added'  => __( 'Date Added', 'woofunnels-ab-tests' ),
				'quick_links' => __( 'Quick Links', 'woofunnels-ab-tests' ),
			);

			return $columns;
		}


		public function get_table_classes() {
			$get_default_classes = parent::get_table_classes();
			array_push( $get_default_classes, 'bwfabt-instance-table' );

			return $get_default_classes;
		}

		public function single_row( $item ) {
			$tr_class = 'bwfabt_experiments';
			echo '<tr class="' . esc_attr( $tr_class ) . '">';
			$this->single_row_columns( $item );
			echo '</tr>';
		}

		/**
		 * Displays the search box.
		 *
		 * @param string $text The 'submit' button label.
		 * @param string $input_id ID attribute value for the search input field.
		 *
		 * @since 3.1.0
		 *
		 */
		public function search_box( $text = '', $input_id = 'bwfabt' ) {
			$input_id = $input_id . '-search-input'; ?>
            <p class="search-box">
                <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
                <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>"/>
				<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
            </p>
			<?php
		}

		public static function render_trigger_nav() {
			$experiment_status = array( '' => __( 'All', 'woofunnels-ab-tests' ) ) + BWFABT_Core()->admin->get_experiment_statuses();
			$html              = '<ul class="subsubsub subsubsub_bwfabt">';
			$html_inside       = array();
			$current_status    = '';
			if ( isset( $_GET['status'] ) && '' !== $_GET['status'] ) {  // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
				$current_status = ( '' === $_GET['status'] ) ? bwfabt_clean( $_GET['status'] ) : intval( bwfabt_clean( $_GET['status'] ) );  // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
			}

			foreach ( $experiment_status as $slug => $status ) {
				$need_class = '';
				if ( $slug === $current_status ) {
					$need_class = 'current';
				}
				$url = add_query_arg( array( 'status' => $slug ), admin_url( 'admin.php?page=bwf_ab_tests' ) );
				if ( empty( $slug ) ) {
					$url = admin_url( 'admin.php?page=bwf_ab_tests' );
				}

				$html_inside[] = sprintf( '<li><a href="%s" class="%s">%s</a> </li>', $url, $need_class, $status );
			}

			if ( is_array( $html_inside ) && count( $html_inside ) > 0 ) {
				$html .= implode( '', $html_inside );
			}
			$html .= '</ul>';

			echo wp_kses_post( $html );
		}

		/**
		 * Generate the table navigation above or below the table
		 *
		 * @param string $which
		 *
		 * @since 3.1.0
		 *
		 */
		protected function display_tablenav( $which ) {

			?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>">

				<?php if ( $this->has_items() ) : ?>
                    <div class="alignleft actions bulkactions">
						<?php $this->bulk_actions( $which ); ?>
                    </div>
				<?php
				endif;
				$this->extra_tablenav( $which );
				$this->pagination( $which );
				?>

                <br class="clear"/>
            </div>
			<?php
		}
	}
}