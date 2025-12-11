<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
if ( ! class_exists( 'WFACP_Post_Table' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Post_Table extends WP_List_Table {

		private static $global_settings = [];
		public $per_page = 20;
		public $data;
		public $meta_data;
		public $date_format;
		public $sitepress_column = null;
		public $screen = null;

		/**
		 * Constructor.
		 * @since  1.0.0
		 */
		public function __construct( $args = array() ) {
			global $status, $page;
			parent::__construct( array(
				'singular' => 'Checkout',
				'plural'   => 'Checkouts',
				'ajax'     => false,
			) );

			$status                = 'all';
			$page                  = $this->get_pagenum();
			$this->data            = array();
			$this->date_format     = WFACP_Common::get_date_format();
			$this->per_page        = WFACP_Common::posts_per_page();
			self::$global_settings = WFACP_Common::global_settings( true );
			$this->screen          = get_current_screen();
			add_filter( "manage_{$this->screen->id}_columns", [ $this, 'remove_columns_from_screen_option' ], 10, 1 );

			// Make sure this file is loaded, so we have access to plugins_api(), etc.
			if ( defined( 'ICL_SITEPRESS_VERSION' ) && class_exists( 'WPML_Custom_Columns' ) ) {
				global $sitepress;
				$this->sitepress_column = new WPML_Custom_Columns( $sitepress );

			}
			require_once( ABSPATH . '/wp-admin/includes/plugin-install.php' );


			$this->enable_screen_options();

			parent::__construct( $args );

		}

		public static function render_trigger_nav() {
			$get_campaign_statuses = apply_filters( 'wfacp_admin_trigger_nav', array(
				'all'      => __( 'All', 'woofunnels-aero-checkout' ),
				'active'   => __( 'Active', 'woofunnels-aero-checkout' ),
				'inactive' => __( 'Inactive', 'woofunnels-aero-checkout' ),
			) );
			$html                  = '<ul class="subsubsub subsubsub_wfacp">';
			$html_inside           = array();
			$current_status        = 'all';
			if ( isset( $_GET['status'] ) && '' !== $_GET['status'] ) {
				$current_status = $_GET['status'];
			}
			$ct = count( $get_campaign_statuses );
			$i  = 0;

			foreach ( $get_campaign_statuses as $slug => $status ) {
				$need_class = '';
				if ( $slug === $current_status ) {
					$need_class = 'current';
				}
				$i ++;
				$url           = add_query_arg( array(
					'status' => $slug,
				), admin_url( 'admin.php?page=wfacp' ) );
				$html_inside[] = sprintf( '<li><a href="%s" class="%s">%s</a>%s</li>', $url, $need_class, $status, ( ( $ct !== $i ) ? '|' : '' ) );
			}

			if ( is_array( $html_inside ) && count( $html_inside ) > 0 ) {
				$html .= implode( '', $html_inside );
			}
			$html .= '</ul>';

			echo $html;
		}

		/**
		 * Text to display if no items are present.
		 * @return  void
		 * @since  1.0.0
		 */
		public function no_items() {
			echo wpautop( __( 'No Checkout page available.', 'woofunnels-aero-checkout' ) );
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
				case 'post_status':
					return $item[ $column_name ];
					break;
			}
		}

		public function column_cb( $item ) {

			$funnel_status = '';
			if ( 'publish' === $item['post_status'] ) {
				$funnel_status = "checked='checked'";
			}

			?>
            <div class='wfacp_fsetting_table_title'>
                <div class='offer_state wfacp_toggle_btn'>
                    <input name='wfacp_checkout_page_state' id="state<?php echo $item['ID']; ?>" data-id="<?php echo $item['ID']; ?>" type='checkbox' class='wfacp-tgl wfacp-tgl-ios wfacp_checkout_page_status' <?php echo $funnel_status; ?> >
                    <label for='state<?php echo $item['ID']; ?>' class='wfacp-tgl-btn wfacp-tgl-btn-small'></label>
                </div>
            </div>
			<?php
		}

		public function column_name( $item ) {

			$edit_link = add_query_arg( [
				'page'     => 'wfacp',
				'wfacp_id' => $item['ID'],
			] );

			$is_global_checkout = '';
			if ( isset( self::$global_settings['override_checkout_page_id'] ) && self::$global_settings['override_checkout_page_id'] == $item['ID'] ) {
				$is_global_checkout = "<span style='color: #555;'> —" . __( 'Checkout Page', 'woofunnels-aero-checkout' ) . '</span>';
			}
			$description   = $this->column_description( $item );
			$column_string = '<div><strong>';
			$column_string .= '<a href="' . $edit_link . '" class="row-title">' . _draft_or_post_title( $item['ID'] ) . $is_global_checkout . '</a>';
			$column_string .= '</strong>';
			$column_string .= '<p>' . $description . '</p>';
			$column_string .= "<div style='clear:both'></div></div>";
			$column_string .= '<div class=\'row-actions\'>';
			if ( isset( $item['row_actions'] ) && count( $item['row_actions'] ) > 0 ) {
				$item_last     = array_keys( $item['row_actions'] );
				$item_last_key = end( $item_last );
				foreach ( $item['row_actions'] as $k => $action ) {
					if ( $k == 'edit' ) {
						continue;
					}
					$column_string .= '<span class="' . $action['action'] . '"><a class="' . $action['class'] . '" href="' . $action['link'] . '" ' . $action['attrs'] . '>' . $action['text'] . '</a>';
					if ( $k != $item_last_key ) {
						$column_string .= ' | ';
					}
					$column_string .= '</span>';
				}
				$column_string .= '</div>';
			}


			return ( $column_string );
		}

		public function column_preview( $item ) {

			$column_string = sprintf( '<a href="javascript:void(0);" class="wfacp-preview" data-id="%d" title="Preview">%s </a>', $item['ID'], __( '', 'woofunnels-aero-checkout' ) );

			return $column_string;
		}

		public function column_last_update( $item ) {

			return get_the_modified_date( $this->date_format, $item['ID'] );
		}

		public function column_description( $item ) {
			return get_post_meta( $item['ID'], '_post_description', true );
		}

		public function column_quick_links( $item ) {

			$steps = WFACP_Common::get_admin_menu();

			foreach ( $steps as $key => $menu ) {
				$link                       = BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( [
					'wfacp_id' => $item['ID'],
					'section'  => $menu['slug'],
				], admin_url( 'admin.php?page=wfacp' ) ) );
				$items_arr[ $menu['name'] ] = '<span><a href="' . $link . '">' . $menu['name'] . '</a></span>';
			}

			$output = implode( ' | ', $items_arr );

			return $output;
		}

		public function column_icl_translations( $item ) {

			if ( defined( 'ICL_SITEPRESS_VERSION' ) && $this->sitepress_column instanceof WPML_Custom_Columns ) {
				global $post;
				$post = get_post( $item['ID'] );
				WFACP_Common::remove_actions( 'wpml_icon_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_icon' );
				WFACP_Common::remove_actions( 'wpml_link_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_link' );
				WFACP_Common::remove_actions( 'wpml_text_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_text' );
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
			$hidden                = $this->get_hidden_columns();
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
				'cb'      => '',
				'name'    => __( 'Name', 'woofunnels-aero-checkout' ),
				'preview' => __( '&nbsp;', 'woofunnels-aero-checkout' ),

				'last_update' => __( 'Last Update', 'woofunnels-aero-checkout' ),
				'quick_links' => __( 'Quick Links', 'woofunnels-aero-checkout' ),
			);
			if ( defined( 'ICL_SITEPRESS_VERSION' ) && $this->sitepress_column instanceof WPML_Custom_Columns ) {
				$columns = $this->sitepress_column->add_posts_management_column( $columns );
			}
			if ( ! class_exists( 'Polylang' ) ) {
				return $columns;
			}
			global $polylang;
			if ( ! empty( $polylang ) ) {
				$polylang_flags = '';
				foreach ( $polylang->model->get_languages_list() as $language ) {
					$polylang_flags .= $this->get_flag_html( $language ) . '<span class="screen-reader-text">' . esc_html( $language->name ) . '</span>';
				}
				if ( ! empty( $polylang_flags ) ) {
					$columns['poly_lang'] = $polylang_flags;
				}
			}

			return $columns;
		}

		protected function get_sortable_columns() {
			return array(
				'last_update' => [ 'modified', 1 ],
				'priority'    => [ 'menu_order', 1 ],
			);
		}

		public function get_table_classes() {
			$get_default_classes = parent::get_table_classes();
			array_push( $get_default_classes, 'wfacp-instance-table' );

			return $get_default_classes;
		}

		public function single_row( $item ) {

			$tr_class = 'wfacp_funnels';
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
		public function search_box( $text = '', $input_id = 'wfacp' ) {
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
                <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>"/>
				<?php
				submit_button( $text, '', '', false, array(
					'ID' => 'search-submit',
				) );
				?>
            </p>
			<?php
		}

		public function order_preview_template() {
			?>
            <script type="text/template" id="tmpl-wfacp-page-popup">
                <div class="wc-backbone-modal wc-order-preview">
                    <div class="wc-backbone-modal-content">
                        <section class="wc-backbone-modal-main" role="main">
                            <header class="wc-backbone-modal-header">
                                <h1>{{data.post_name}}</h1>
                                <mark class="wfacp-os order-status status-{{ data.post_status.toLowerCase() }}">
                                    <# if(data.post_status != 'publish') { #>
                                    <span><?php _e( 'Inactive', 'woocommerce' ); ?></span>
                                    <# } else {#>
                                    <span><?php _e( 'Active', 'woocommerce' ); ?></span>
                                    <# } #>
                                </mark>

                                <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                    <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'woofunnels-aero-checkout' ); ?></span>
                                </button>
                            </header>
                            <article>
                                <# print(data.html) #>
                            </article>
                            <footer class="wfacp_launch_btn_outer">
                                <div class="inner">
                                    <a target="_blank" href="{{data.launch_url}}" class="button button-primary wfacp-funnel-pop-launch-btn "><?php _e( 'Launch', 'woofunnels-aero-checkout' ); ?></a>
                                </div>
                            </footer>
                        </section>
                    </div>
                </div>
                <div class="wc-backbone-modal-backdrop modal-close"></div>
            </script>
			<?php
		}

		public function get_hidden_columns() {
			$columns = ! empty( get_user_option( "manage{$this->screen->id}columnshidden" ) ) ? get_user_option( "manage{$this->screen->id}columnshidden" ) : array();

			return $columns;
		}

		public function remove_columns_from_screen_option( $culumns ) {
			unset( $culumns['name'] );
			unset( $culumns['preview'] );

			return $culumns;
		}

		public function column_poly_lang( $item ) {
			if ( ! class_exists( 'Polylang' ) ) {
				return;
			}
			global $polylang;
			$post_id = $item['ID'];

			foreach ( $polylang->model->get_languages_list() as $language ) {
				$flag = '';
				// Link to edit post ( or a translation )
				if ( $id = $polylang->model->post->get( $post_id, $language ) ) {

					$href = 'javascript:void(0)';
					if ( $id === $post_id ) {
						$flag  = $this->get_flag_html( $language );
						$class = 'pll_column_flag';
						/* translators: accessibility text, %s is a native language name */
						$s = sprintf( __( 'Edit this item in %s', 'polylang' ), $language->name );
					} else {
						$class = esc_attr( 'pll_icon_edit translation_' . $id );
						/* translators: accessibility text, %s is a native language name */
						$href = add_query_arg( [ 'wfacp_id' => $id ], admin_url( 'admin.php?page=wfacp' ) );
						$s    = sprintf( __( 'Edit the translation in %s', 'polylang' ), $language->name );
					}
					$post = get_post( $id );

					if ( ! empty( $post ) ) {
						printf( '<a class="%1$s" title="%2$s" href="' . $href . '" ><span class="screen-reader-text">%3$s</span>%4$s</a>', esc_attr( $class ), esc_attr( $post->post_title ), esc_html( $s ), $flag // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
						);
					}
				} else {
					?>
                    <span title="Add a translation in <?php echo $language->name; ?>" class="pll_icon_add wfacp-add-polylang" data-wfacpid="<?php echo $post_id; ?>" data-lang="<?php echo $language->slug; ?>"><span class="screen-reader-text"><?php _e( 'Add a translation in', 'woofunnels-aero-checkout' ) ?><?php echo $language->name; ?> </span></span> <?php

				}
			}
		}


		private function enable_screen_options() {
			$user_id  = get_current_user_id();
			$option   = $this->screen->get_option( 'per_page', 'option' );
			$per_page = get_user_meta( $user_id, $option, true );

			if ( absint( $per_page ) > 0 ) {
				$this->per_page = $per_page;
			}
		}

		protected function get_flag_html( $language ) {
			return $language->flag ? $language->flag : sprintf( '<abbr>%s</abbr>', esc_html( $language->slug ) );
		}
	}
}
