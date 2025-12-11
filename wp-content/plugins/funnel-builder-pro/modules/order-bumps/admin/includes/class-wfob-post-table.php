<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
if ( ! class_exists( 'WFOB_Post_Table' ) ) {
	class WFOB_Post_Table extends WP_List_Table {

		public $per_page = 4;
		public $data;
		public $meta_data;
		public $date_format;
		public $sitepress_column = null;

		/**
		 * Constructor.
		 * @since  1.0.0
		 */
		public function __construct( $args = array() ) {
			global $status, $page;
			parent::__construct( array(
				'singular' => 'Funnel',
				'plural'   => 'Funnels',
				'ajax'     => false,
			) );
			$status            = 'all';
			$page              = $this->get_pagenum();
			$this->data        = array();
			$this->date_format = WFOB_Common::get_date_format();
			$this->per_page    = WFOB_Common::posts_per_page();
			if ( defined( 'ICL_SITEPRESS_VERSION' ) && class_exists( 'WPML_Custom_Columns' ) ) {
				global $sitepress;
				$this->sitepress_column = new WPML_Custom_Columns( $sitepress );

			}
			// Make sure this file is loaded, so we have access to plugins_api(), etc.
			require_once( ABSPATH . '/wp-admin/includes/plugin-install.php' );

			parent::__construct( $args );

		}

		/**
		 * Text to display if no items are present.
		 * @return  void
		 * @since  1.0.0
		 */
		public function no_items() {
			echo wpautop( __( 'No order bump available.', 'woofunnels-order-bump' ) );
		}


		/**
		 * The content of each column.
		 *
		 * @param array $item The current item in the list.
		 * @param string $column_name The key of the current column.
		 *
		 * @return string              Output for the current column.
		 * @since  1.0.0
		 */
		public function column_default( $item, $column_name ) {

			switch ( $column_name ) {
				case 'check-column':
					return '&nbsp;';
				case 'status':
					return $item[ $column_name ];
					break;
			}
		}

		public function get_item_data( $item_id ) {

			if ( isset( $this->meta_data[ $item_id ] ) ) {
				$data = $this->meta_data[ $item_id ];
			} else {
				$data                        = get_post_meta( $item_id );
				$this->meta_data[ $item_id ] = $data;
			}

			return $data;
		}

		public function column_cb( $item ) {
			$bump_status = '';
			if ( 'publish' === $item['status'] ) {
				$bump_status = "checked='checked'";
			}
			?>
            <div class='wfob_fsetting_table_title'>
                <div class='offer_state wfob_toggle_btn'>
                    <input name='offer_state' id='state<?php echo $item['id']; ?>' data-id="<?php echo $item['id']; ?>" type='checkbox' class='wfob-tgl wfob-tgl-ios wfob_checkout_page_status' <?php echo $bump_status; ?> data-id="<?php echo $item['id']; ?>">
                    <label for='state<?php echo $item['id']; ?>' class='wfob-tgl-btn wfob-tgl-btn-small'></label>
                </div>
            </div>
			<?php
		}

		public function column_name( $item ) {
			$edit_link     = $item['row_actions']['edit']['link'];
			$column_string = '<div><strong>';

			$column_string .= '<a href="' . $edit_link . '" class="row-title">' . _draft_or_post_title( $item['id'] ) . '</a>';
			$column_string .= '</strong>';
			$column_string .= "<div style='clear:both'></div></div>";

			$column_string .= '<div class=\'row-actions\'>';

			$item_last     = array_keys( $item['row_actions'] );
			$item_last_key = end( $item_last );
			foreach ( $item['row_actions'] as $k => $action ) {
				$attr          = isset( $action['attrs'] ) ? $action['attrs'] : '';
				$column_string .= '<span class="' . $action['action'] . '"><a href="' . $action['link'] . '" ' . $attr . '>' . $action['text'] . '</a>';
				if ( $k != $item_last_key ) {
					$column_string .= ' | ';
				}
				$column_string .= '</span>';
			}
			$column_string .= '</div>';

			return ( $column_string );
		}

		public function column_steps( $item ) {
			$data = $this->get_item_data( absint( $item['id'] ) );

			$bump_steps_data = maybe_unserialize( ( isset( $data['_bump_steps'] ) ? $data['_bump_steps'] : array() ) );
			if ( is_array( $bump_steps_data ) && count( $bump_steps_data ) > 0 ) {
				return count( $bump_steps_data );
			}

			return '0';
		}

		public function column_last_update( $item ) {

			return get_the_modified_date( $this->date_format, $item['id'] );
		}

		public function column_product( $item ) {
			$products = get_post_meta( $item['id'], '_wfob_selected_products', true );
			if ( is_array( $products ) && count( $products ) > 0 ) {
				ob_start();
				foreach ( $products as $s ) {
					echo "- {$s['title']} #{$s['id']} - Qty {$s['quantity']}<br>";
				}

				return ob_get_clean();
			}

			return '';
		}


		public function column_priority( $item ) {

			if ( isset( $item['priority'] ) ) {
				return $item['priority'];
			} else {
				$bump_post = array(
					'ID'         => absint( $item['id'] ),
					'menu_order' => 0,
				);
				wp_update_post( $bump_post );

				return 0;
			}

			return;
		}

		public function column_quick_links( $item ) {

			$id = absint( $item['id'] );

			$links = apply_filters( 'wfob_bump_quick_links', array(
				array(
					'text' => __( 'Products', 'woofunnels-order-bump' ),
					'link' => add_query_arg( array(
						'page'    => 'wfob',
						'section' => 'products',
						'wfob_id' => $id,
					), admin_url( 'admin.php' ) ),
				),
				array(
					'text' => __( 'Design', 'woofunnels-order-bump' ),
					'link' => add_query_arg( array(
						'page'    => 'wfob',
						'section' => 'design',
						'wfob_id' => $id,
					), admin_url( 'admin.php' ) ),
				),
				array(
					'text' => __( 'Rules', 'woofunnels-order-bump' ),
					'link' => add_query_arg( array(
						'page'    => 'wfob',
						'section' => 'rules',
						'wfob_id' => $id,
					), admin_url( 'admin.php' ) ),
				),
				array(
					'text' => __( 'Settings', 'woofunnels-order-bump' ),
					'link' => add_query_arg( array(
						'page'    => 'wfob',
						'section' => 'settings',
						'wfob_id' => $id,
					), admin_url( 'admin.php' ) ),
				),
			) );


			$html = array();

			foreach ( $links as $link ) {
				$html[] = '<span><a href="' . $link['link'] . '">' . $link['text'] . '</a></span>';
			}

			return ( count( $html ) > 0 ) ? implode( ' | ', $html ) : false;
		}

		public function column_icl_translations( $item ) {

			if ( defined( 'ICL_SITEPRESS_VERSION' ) && $this->sitepress_column instanceof WPML_Custom_Columns ) {
				global $post;
				$post = get_post( $item['id'] );
				$this->sitepress_column->add_content_for_posts_management_column( 'icl_translations' );
			}
			echo '';
		}

		/**
		 * Retrieve an array of possible bulk actions.
		 * @return array
		 * @since  1.0.0
		 */
		public function get_bulk_actions() {
			$actions = array();

			return $actions;
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

		protected function get_sortable_columns() {
			return array(
				'last_update' => [ 'modified', 1 ],
				'priority'    => [ 'menu_order', 1 ],
			);
		}

		/**
		 * Retrieve an array of columns for the list table.
		 * @return array Key => Value pairs.
		 * @since  1.0.0
		 */
		public function get_columns() {
			$columns = array(
				'cb'          => __( '' ),
				'name'        => __( 'Name', 'woofunnels-order-bump' ),
				'preview'     => __( '&nbsp;', 'woofunnels-order-bump' ),
				'last_update' => __( 'Last Update', 'woofunnels-order-bump' ),
				'priority'    => __( 'Priority', 'woofunnels-order-bump' ),
				'quick_links' => __( 'Quick Links', 'woofunnels-order-bump' ),
			);

			if ( defined( 'ICL_SITEPRESS_VERSION' ) && $this->sitepress_column instanceof WPML_Custom_Columns ) {
				$columns = $this->sitepress_column->add_posts_management_column( $columns );
			}

			return $columns;
		}


		public function get_table_classes() {
			$get_default_classes = parent::get_table_classes();
			array_push( $get_default_classes, 'wfob-instance-table' );

			return $get_default_classes;
		}

		public function single_row( $item ) {
			$tr_class = 'wfob_bump';
			echo '<tr class="' . $tr_class . '">';
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
		public function search_box( $text = '', $input_id = 'wfob' ) {
			$input_id = $input_id . '-search-input';

			if ( ! empty( $_REQUEST['orderby'] ) ) {
				echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
			}
			if ( ! empty( $_REQUEST['order'] ) ) {
				echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
			}
			if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
				echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
			}
			if ( ! empty( $_REQUEST['detached'] ) ) {
				echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
			}
			?>
            <p class="search-box">
                <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo $text; ?>:</label>
                <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s"
                       value="<?php _admin_search_query(); ?>"/>
				<?php
				submit_button( $text, '', '', false, array(
					'id' => 'search-submit',
				) );
				?>
            </p>
			<?php
		}

		public static function render_trigger_nav() {
			$get_campaign_statuses = apply_filters( 'wfob_admin_trigger_nav', array(
				'all'      => __( 'All', 'woofunnels-order-bump' ),
				'active'   => __( 'Active', 'woofunnels-order-bump' ),
				'inactive' => __( 'Inactive', 'woofunnels-order-bump' ),
			) );
			$html                  = '<ul class="subsubsub subsubsub_wfob">';
			$html_inside           = array();
			$current_status        = 'all';
			if ( isset( $_GET['status'] ) && '' !== $_GET['status'] ) {
				$current_status = $_GET['status'];
			}

			foreach ( $get_campaign_statuses as $slug => $status ) {
				$need_class = '';
				if ( $slug === $current_status ) {
					$need_class = 'current';
				}

				$url           = add_query_arg( array(
					'status' => $slug,
				), admin_url( 'admin.php?page=wfob' ) );
				$html_inside[] = sprintf( '<li><a href="%s" class="%s">%s</a> </li>', $url, $need_class, $status );
			}

			if ( is_array( $html_inside ) && count( $html_inside ) > 0 ) {
				$html .= implode( '', $html_inside );
			}
			$html .= '</ul>';

			echo $html;
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

		public function column_preview( $item ) {
			$column_string = sprintf( '<a href="javascript:void(0);" class="wfob-preview" data-id="%d" title="Preview">%s </a>', $item['id'], __( '', 'woofunnels-order-bump' ) );

			return $column_string;
		}

		public function order_preview_template() {
			?>
            <script type="text/template" id="tmpl-wfob-page-popup">
                <div class="wc-backbone-modal wc-order-preview">
                    <div class="wc-backbone-modal-content">
                        <section class="wc-backbone-modal-main" role="main">
                            <header class="wc-backbone-modal-header">
                                <h1>{{data.post_name}}</h1>
                                <mark class="wfacp-os order-status status-{{ data.post_status.toLowerCase() }}">
                                    <# if(data.post_status != 'publish') { #>
                                    <span><?php _e( 'InActive', 'woocommerce' ) ?></span>
                                    <# } else {#>
                                    <span><?php _e( 'Active', 'woocommerce' ) ?></span>
                                    <# } #>
                                </mark>

                                <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                    <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'woofunnels-order-bump' ); ?></span>
                                </button>
                            </header>
                            <article>
                                <# print(data.html) #>
                            </article>
                            <footer>
                                <div class="inner">
                                    <a target="_blank" href="{{data.launch_url}}" class="wfob_btn wfob_btn_primary wfob-funnel-pop-launch-btn "><?php _e( 'Edit', 'woofunnels-order-bump' ); ?></a>
                                </div>
                            </footer>
                        </section>
                    </div>
                </div>
                <div class="wc-backbone-modal-backdrop modal-close"></div>
            </script>
			<?php
		}

	}
}