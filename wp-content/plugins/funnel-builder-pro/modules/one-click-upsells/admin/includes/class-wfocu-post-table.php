<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
if ( ! class_exists( 'WFOCU_Post_Table' ) ) {
	class WFOCU_Post_Table extends WP_List_Table {

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
			$this->date_format = WFOCU_Common::get_date_format();
			$this->per_page    = WFOCU_Common::posts_per_page();

			// Make sure this file is loaded, so we have access to plugins_api(), etc.
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
			echo wpautop( __( 'No Funnel available.', 'woofunnels-upstroke-one-click-upsell' ) );
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

			return null;
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
			$funnel_status = '';
			if ( 'publish' === $item['status'] ) {
				$funnel_status = "checked='checked'";
			}
			?>
            <div class='wfocu_fsetting_table_title'>
                <div class='offer_state wfocu_toggle_btn'>
                    <input name='offer_state' id='state<?php echo esc_attr( $item['id'] ); ?>' data-id="<?php echo esc_attr( $item['id'] ); ?>" type='checkbox' class='wfocu-tgl wfocu-tgl-ios' <?php echo esc_attr( $funnel_status ); ?> >
                    <label for='state<?php echo esc_attr( $item['id'] ); ?>' class='wfocu-tgl-btn wfocu-tgl-btn-small'></label>
                </div>
            </div>
			<?php
		}

		public function column_name( $item ) {

			$edit_link     = $item['row_actions']['edit']['link'];
			$column_string = '<div><strong>';

			$column_string .= '<a href="' . $edit_link . '" class="row-title">' . _draft_or_post_title( $item['id'] ) . ' (#' . $item['id'] . ')</a>' . _post_states( get_post( $item['id'] ) );
			$column_string .= '</strong>';

			$column_string .= '<p>' . esc_html( $item['post_content'] ) . '</p>';

			$column_string .= "<div style='clear:both'></div></div>";
			$get_steps     = WFOCU_Core()->funnels->get_funnel_steps( $item['id'] );

			if ( is_array( $get_steps ) && count( $get_steps ) > 0 ) {

				$get_status = wp_list_pluck( $get_steps, 'state' );
				$get_status = array_filter( $get_status, function ( $val ) {
					return wc_string_to_bool( $val );
				} );

				if ( empty( $get_status ) ) {
					$column_string .= sprintf( '<div class="wfocu_row_notice">%s</div>', __( 'Activate at least one offer to show the funnel.', 'woofunnels-upstroke-one-click-upsell' ) );
				}
			}

			$column_string .= '<div class=\'row-actions\'>';

			$item_last     = array_keys( $item['row_actions'] );
			$item_last_key = end( $item_last );
			foreach ( $item['row_actions'] as $k => $action ) {

				if ( $k === 'delete' ) {

					$column_string .= '<span class="' . $action['action'] . '">';
					$column_string .= '<a href="' . $action['link'] . '" ' . $action['attrs'] . ' onclick="return confirm(\'Are you sure you want to permanently delete this funnel? This action cannot be undone.\')">';
					$column_string .= $action['text'];
					$column_string .= '</a>';

				} else {
					$column_string .= '<span class="' . $action['action'] . '">';
					$column_string .= '<a href="' . $action['link'] . '" ' . $action['attrs'] . ' >';
					$column_string .= $action['text'];
					$column_string .= '</a>';

				}
				if ( $k !== $item_last_key ) {
					$column_string .= ' | ';
				}
				$column_string .= '</span>';
			}
			$column_string .= '</div>';

			return ( $column_string );
		}

		public function column_steps( $item ) {
			$data = $this->get_item_data( absint( $item['id'] ) );

			$funnel_steps_data = maybe_unserialize( ( isset( $data['_funnel_steps'] ) ? $data['_funnel_steps'] : '' ) );
			if ( is_array( $funnel_steps_data ) && count( $funnel_steps_data ) > 0 ) {
				return count( $funnel_steps_data );
			}

			return '0';
		}

		public function column_preview( $item ) {
			return sprintf( '<a href="javascript:void(0);" class="wfocu-preview" data-funnel-id="%d" title="Preview"></a>', $item['id'] );
		}

		public function column_icl_translations( $item ) {

			if ( defined( 'ICL_SITEPRESS_VERSION' ) && $this->sitepress_column instanceof WPML_Custom_Columns ) {
				global $post;
				$post = get_post( $item['id'] );
				//WFACP_Common::remove_actions( 'wpml_icon_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_icon' );
				//WFACP_Common::remove_actions( 'wpml_link_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_link' );
				//WFACP_Common::remove_actions( 'wpml_text_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_text' );

				remove_action( 'wpml_icon_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_icon' );
				remove_action( 'wpml_link_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_link' );
				remove_action( 'wpml_text_to_translation', 'WPML_TM_Translation_Status_Display', 'filter_status_text' );
				$this->sitepress_column->add_content_for_posts_management_column( 'icl_translations' );
			}
			echo '';
		}


		public function column_last_update( $item ) {

			return get_the_modified_date( $this->date_format, $item['id'] );
		}

		public function column_description( $item ) {

			return $item['post_content'];
		}

		public function column_priority( $item ) {

			if ( isset( $item['priority'] ) ) {
				return $item['priority'];
			} else {
				$funnel_post = array(
					'ID'         => absint( $item['id'] ),
					'menu_order' => 0,
				);
				wp_update_post( $funnel_post );

				return 0;
			}

			return;
		}

		public function column_quick_links( $item ) {

			$wfocu_is_rules_saved = get_post_meta( $item['id'], '_wfocu_is_rules_saved', true );

			$id = absint( $item['id'] );

			$links = apply_filters( 'wfocu_funnel_quick_links', array(
				array(
					'text' => __( 'Offers', 'woofunnels-upstroke-one-click-upsell' ),
					'link' => add_query_arg( array(
						'page'    => 'upstroke',
						'section' => 'offers',
						'edit'    => $id,
					), admin_url( 'admin.php' ) ),

				),
				array(
					'text' => __( 'Design', 'woofunnels-upstroke-one-click-upsell' ),
					'link' => add_query_arg( array(
						'page'    => 'upstroke',
						'section' => 'design',
						'edit'    => $id,
					), admin_url( 'admin.php' ) ),

				),
				array(
					'text' => __( 'Rules', 'woofunnels-upstroke-one-click-upsell' ),
					'link' => add_query_arg( array(
						'page'    => 'upstroke',
						'section' => 'rules',
						'edit'    => $id,
					), admin_url( 'admin.php' ) ),

				),
				array(
					'text' => __( 'Settings', 'woofunnels-upstroke-one-click-upsell' ),
					'link' => add_query_arg( array(
						'page'    => 'upstroke',
						'section' => 'settings',
						'edit'    => $id,
					), admin_url( 'admin.php' ) ),

				),
			) );
			if ( 'yes' !== $wfocu_is_rules_saved ) {
				$rules = $links[0];
				unset( $links );
				$links    = array();
				$links[0] = $rules;
			}

			$html = array();
			foreach ( $links as $link ) {
				$html[] = '<span><a href="' . $link['link'] . '">' . $link['text'] . '</a></span>';
			}

			return ( count( $html ) > 0 ) ? implode( ' | ', $html ) : false;
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
				'cb'          => '',
				'name'        => __( 'Name', 'woofunnels-upstroke-one-click-upsell' ),
				'preview'     => '&nbsp',
				'last_update' => __( 'Last Update', 'woofunnels-upstroke-one-click-upsell' ),
				'priority'    => __( 'Priority', 'woofunnels-upstroke-one-click-upsell' ),
				'quick_links' => __( 'Quick Links', 'woofunnels-upstroke-one-click-upsell' ),
			);

			if ( defined( 'ICL_SITEPRESS_VERSION' ) && $this->sitepress_column instanceof WPML_Custom_Columns ) {
				$columns = $this->sitepress_column->add_posts_management_column( $columns );
			}

			return $columns;
		}


		public function get_table_classes() {
			$get_default_classes = parent::get_table_classes();
			array_push( $get_default_classes, 'wfocu-instance-table' );

			return $get_default_classes;
		}

		public function single_row( $item ) {
			$tr_class = 'wfocu_funnels';
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
		public function search_box( $text = '', $input_id = 'wfocu' ) {
			$input_id = $input_id . '-search-input';

			if ( ! empty( $_REQUEST['orderby'] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Missing
				echo '<input type="hidden" name="orderby" value="' . esc_attr( wc_clean( $_REQUEST['orderby'] ) ) . '" />'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if ( ! empty( $_REQUEST['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				echo '<input type="hidden" name="order" value="' . esc_attr( wc_clean( $_REQUEST['order'] ) ) . '" />'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if ( ! empty( $_REQUEST['post_mime_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( wc_clean( $_REQUEST['post_mime_type'] ) ) . '" />'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			if ( ! empty( $_REQUEST['detached'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				echo '<input type="hidden" name="detached" value="' . esc_attr( wc_clean( $_REQUEST['detached'] ) ) . '" />'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			?>
            <p class="search-box">
                <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
                <input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>"/>
				<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
            </p>
			<?php
		}

		public static function render_trigger_nav() {
			$get_campaign_statuses = apply_filters( 'wfocu_admin_trigger_nav', array(
				'all'      => __( 'All', 'woofunnels-upstroke-one-click-upsell' ),
				'active'   => __( 'Active', 'woofunnels-upstroke-one-click-upsell' ),
				'inactive' => __( 'Inactive', 'woofunnels-upstroke-one-click-upsell' ),
			) );
			$html                  = '<ul class="subsubsub subsubsub_wfocu">';
			$html_inside           = array();
			$current_status        = 'all';
			if ( isset( $_GET['status'] ) && '' !== $_GET['status'] ) {  // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$current_status = wc_clean( $_GET['status'] );  // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			foreach ( $get_campaign_statuses as $slug => $status ) {
				$need_class = '';
				if ( $slug === $current_status ) {
					$need_class = 'current';
				}

				$url           = add_query_arg( array( 'status' => $slug ), admin_url( 'admin.php?page=upstroke' ) );
				$html_inside[] = sprintf( '<li><a href="%s" class="%s">%s</a> </li>', $url, $need_class, $status );
			}

			if ( is_array( $html_inside ) && count( $html_inside ) > 0 ) {
				$html .= implode( '', $html_inside );
			}
			$html .= '</ul>';

			echo wp_kses_post( $html );
		}

		public function order_preview_template() {
			?>
            <script type="text/template" id="tmpl-wfocu-funnel-popup">
                <div class="wc-backbone-modal wc-order-preview">
                    <div class="wc-backbone-modal-content">
                        <section class="wc-backbone-modal-main" role="main">
                            <header class="wc-backbone-modal-header">
                                <h1>{{data.funnel_name}}</h1>
                                <mark class="wfocu-os order-status status-{{ data.status.toLowerCase() }}">
                                    <# if(data.status == 'Deactivated') { #>
                                    <span v-if="">Inactive</span>
                                    <# } else {#>
                                    <span v-else>Active</span>
                                    <# } #>
                                </mark>
                                <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                                    <span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'woocommerce' ); ?></span>
                                </button>
                            </header>
                            <article>
                                <div class="wfocu-fp-wrap">
                                    <# if(data.offers.length > 0) { #>
                                    <# _(data.offers).each(function(it) { #>
                                    <div class="wfocu-fp-cont">
                                        <div class="wfocu-fp-name wfocu-fp-state-{{it.offer_state}}">{{it.offer_name}}</div>
                                        <div class="wfocu-fp-offer-type">Type: {{it.offer_type}}</div>
                                        <div class="wfocu-fp-offer-products">Product(s):
                                            <# if(it.offer_products == '') { #>
											<?php esc_attr_e( 'None', 'woofunnels-upstroke-one-click-upsell' ); ?>
                                            <# } else {#>
                                            <# print(it.offer_products) #>
                                            <# } #>
                                        </div>
                                    </div>
                                    <# }) #>
                                    <# } else {#>
                                    <div class="wfocu-funnel-pop-no-offer"> <?php esc_attr_e( 'No Offers in this funnel', 'woofunnels-upstroke-one-click-upsell' ); ?></div>
                                    <# } #>
                                </div>
                            </article>
                            <footer>
                                <div class="inner">
                                    <a href="{{data.launch_url}}" class="button button-primary wfocu-funnel-pop-launch-btn "><?php esc_attr_e( 'Launch', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                                </div>
                            </footer>
                        </section>
                    </div>
                </div>
                <div class="wc-backbone-modal-backdrop modal-close"></div>
            </script>
			<?php
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