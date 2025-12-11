<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
if ( ! class_exists( 'WC_Report_wfob_bumps' ) ) {
	/**
	 * Upstroke Admin Report - Upsells by date
	 *
	 * Find the upsells accepted between given dates
	 *
	 * @package        WooCommerce Upstroke
	 * @subpackage    WC_Report_Upsells_By_Date
	 * @category    Class
	 */
	class WC_Report_wfob_bumps extends WP_List_Table {

		public $per_page = 20;
		private static $ins = null;
		private $data = [];

		public function __construct( $args = array() ) {
			parent::__construct( array(
				'singular' => 'Bump',
				'plural'   => 'Bumps',
				'ajax'     => false,
			) );
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;

		}

		public static function get_report() {

			$table = self::get_instance();
			$table->prepare_items();
			$table->display();
		}

		private function get_item_name( $items ) {
			if ( count( $items ) > 0 ) {

				global $wpdb;
				$item_string = implode( ',', $items );

				$sql     = "SELECT concat(item.order_item_name ,' (',count(item.order_item_name),')') as name,meta.meta_value as id FROM `{$wpdb->prefix}woocommerce_order_items` as item JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` as meta ON item.order_item_id=meta.order_item_id where item.order_item_id in($item_string) and meta.meta_key='_product_id' GROUP by order_item_name;";
				$results = $wpdb->get_results( $sql, ARRAY_A );
				if ( count( $results ) > 0 ) {

					$output = [];
					foreach ( $results as $result ) {
						$url      = add_query_arg( [ 'post' => $result['id'], 'action' => 'edit' ], admin_url( 'post.php' ) );
						$output[] = sprintf( "<a href='%s' target='_blank'>%s</a>", $url, $result['name'] );;
					}


					return $output;
				}

			}

			return [];
		}

		private function map_data( $data ) {

			$output = [ 'views' => 0, 'accepted' => 0, 'rejected' => 0, 'revenue' => 0, 'no_items' => 0, 'item_lists' => '' ];
			if ( count( $data ) > 0 ) {
				$cart_items = [];
				foreach ( $data as $d ) {
					if ( '1' == $d['converted'] ) {
						$output['accepted'] += 1;
						$items              = json_decode( $d['iid'], true );
						if ( is_array( $items ) ) {
							$cart_items = array_merge( $cart_items, $items );

							$output['no_items'] += count( $items );
						}
					} else {
						$output['rejected'] += 1;
						$output['no_items'] += 0;
					}
					$output['revenue'] += $d['total'];
				}

				$output['views']           = count( $data );
				$output['conversion_rate'] = ( $output['accepted'] / count( $data ) ) * 100;
				if ( count( $cart_items ) > 0 ) {

					$item_list = $this->get_item_name( $cart_items );
					if ( count( $item_list ) > 0 ) {
						$output['item_lists'] = implode( '<br/>', $item_list );
					}

				}

			}

			return $output;

		}


		private function get_data() {

			global $wpdb;

			if ( count( $this->data['items'] ) > 0 ) {
				$bumps = $this->data['items'];
				foreach ( $bumps as $index => $bump ) {
					$id = $bump['id'];

					$sql  = "select * from {$wpdb->wfob_stats} where bid={$id}";
					$data = $wpdb->get_results( $sql, ARRAY_A );

					unset( $bumps[ $index ]['row_actions'] );
					if ( ! empty( $data ) ) {
						$bumps[ $index ]['data'] = $this->map_data( $data );
					} else {
						$bumps[ $index ]['data'] = $this->map_data( [] );
					}
				}

			}


			return $bumps;
		}

		/**
		 * Text to display if no items are present.
		 * @return  void
		 * @since  1.0.0
		 */
		public function no_items() {
			echo wpautop( __( 'No Transaction available.', 'woofunnels-order-bump' ) );
		}


		public function column_name( $item ) {
			$bump_id = $item['id'];

			$post = get_post( $bump_id );

			if ( ! is_null( $post ) ) {
				$url = add_query_arg( [ 'page' => 'wfob', 'section' => 'rules', 'wfob_id' => $bump_id ], admin_url( 'admin.php' ) );

				return sprintf( "<a href='%s' target='_blank'>%s</a>", $url, $post->post_title );
			}

			return __( 'Order Bump Deleted', 'woofunnels-order-bump' );
		}


		public function column_revenue( $item ) {

			return wc_price( $item['data']['revenue'] );
		}

		public function column_accepted( $item ) {
			return $item['data']['accepted'];
		}

		public function column_rejected( $item ) {
			return $item['data']['rejected'];
		}


		public function column_no_of_bump_item( $item ) {

			return $item['data']['item_lists'];

		}

		public function column_conversion( $item ) {
			if ( isset( $item['data']['conversion_rate'] ) && $item['data']['conversion_rate'] > 0 ) {
				return number_format( $item['data']['conversion_rate'], 2 ) . '%';
			}

			return '0%';

		}

		public function column_trigger_count( $item ) {

			return $item['data']['views'];

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

			$this->data = WFOB_Common::get_post_table_data();
			$this->set_pagination_args( array(
				'total_items' => $this->data['found_posts'], //WE have to calculate the total number of items
				'per_page'    => WFOB_Common::posts_per_page(), //WE have to determine how many items to show on a page
			) );
			$this->items = $this->get_data();;
		}


		/**
		 * Retrieve an array of columns for the list table.
		 * @return array Key => Value pairs.
		 * @since  1.0.0
		 */
		public function get_columns() {
			$columns = array(
				'name'          => __( 'Title', 'woofunnels-order-bump' ),
//			'no_of_bump_item' => __( 'Items', 'woofunnels-order-bump' ),
				'trigger_count' => __( 'Orders', 'woofunnels-order-bump' ),
				'accepted'      => __( 'Accepted', 'woofunnels-order-bump' ),
				'rejected'      => __( 'Rejected', 'woofunnels-order-bump' ),
				'Revenue'       => __( 'Revenue', 'woofunnels-order-bump' ),
				'conversion'    => __( 'Conversion Rate', 'woofunnels-order-bump' ),


			);

			return $columns;
		}


		public function single_row( $item ) {
			$tr_class = 'wfob_report_bump';
			echo '<tr class="' . $tr_class . '">';
			$this->single_row_columns( $item );
			echo '</tr>';
		}

	}
}