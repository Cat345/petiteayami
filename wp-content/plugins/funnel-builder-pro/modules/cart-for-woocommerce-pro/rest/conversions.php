<?php

namespace FKCart\Pro\Rest;


use WFFN_Common;

if ( class_exists( 'WFFN_REST_Controller' ) && ! class_exists( 'FKCart\Pro\Rest\Conversions' ) ) {
	#[\AllowDynamicProperties]
	class Conversions extends \WFFN_REST_Controller {
		private static $ins = null;
		protected $namespace = 'funnelkit-app';
		private $args = [];

		/**
		 * Check if the onumber column exists in the fk_cart table
		 *
		 * @return bool
		 */
		private function column_exists( $column_name = 'onumber' ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'fk_cart';
			$column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", $column_name ) );
			return ! empty( $column );
		}

		private function __construct() {
			add_action( 'rest_api_init', [ $this, 'register_contact_data_endpoint' ], 11 );
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function register_contact_data_endpoint() {
			$this->order_end_points();
		}

		private function order_end_points() {
			register_rest_route( $this->namespace, '/fkcart-conversions/', array(
				'args'                => [],
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cart_upsell_data' ),
				'permission_callback' => array( $this, 'get_read_api_permission_check' ),
			) );
			register_rest_route( $this->namespace, '/fkcart-product-search/', array(
				'args'                => [],
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'custom_product_search_api' ),
				'permission_callback' => array( $this, 'get_read_api_permission_check' ),
			) );
			register_rest_route( $this->namespace, '/fkcart-coupon-search/', array(
				'args'                => [],
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'discount_search_api' ),
				'permission_callback' => array( $this, 'get_read_api_permission_check' ),
			) );
			register_rest_route( $this->namespace, '/fkcart-reward-chart/', array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'generate_pie_chart_data' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					'args'                => [],
				),
			) );
			register_rest_route( $this->namespace, '/fkcart-popular-upsells/', array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_most_popular_upsells' ),
				'permission_callback' => array( $this, 'get_read_api_permission_check' ),
			) );
			register_rest_route( $this->namespace, '/fkcart-overview/', array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cart_upsell_overview' ),
				'permission_callback' => array( $this, 'get_read_api_permission_check' ),
			) );
			register_rest_route( $this->namespace, '/fkcart-upsell-performance/', array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cart_upsell_performance' ),
				'permission_callback' => array( $this, 'get_read_api_permission_check' ),
			) );
			register_rest_route( $this->namespace, '/fkcart-migrate-data/', array(
				'args'                => [],
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'migrator_run' ),
				'permission_callback' => array( $this, 'get_write_api_permission_check' ),
			) );
			register_rest_route( $this->namespace, '/cart-conversions/export/add', array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_cart_conversions_export' ),
					'permission_callback' => array( $this, 'get_write_api_permission_check' )
				),
			) );
		}

		public function get_read_api_permission_check() {
			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				if ( current_user_can( 'administrator' ) ) {
					return true;
				}

				return false;
			}

			return wffn_rest_api_helpers()->get_api_permission_check( 'analytics', 'read' );
		}

		public function get_write_api_permission_check() {
			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				if ( current_user_can( 'administrator' ) ) {
					return true;
				}

				return false;
			}

			return wffn_rest_api_helpers()->get_api_permission_check( 'analytics', 'write' );
		}

		public function migrator_run() {
			if ( ! class_exists( 'FKCART_DB_Migrator' ) ) {
				return rest_ensure_response( [ 'success' => false ] );
			}
			fkcart_db_migrator()->push_to_queue( 'fkcart_run_db_migrator' );
			fkcart_db_migrator()->set_upgrade_state( 2 );
			fkcart_db_migrator()->dispatch();
			fkcart_db_migrator()->save();

			return rest_ensure_response( [ 'success' => true ] );
		}

		public function prepare_filters( $filters ) {
			if ( ! is_array( $filters ) ) {
				$filters = json_decode( $filters, true );
			}

			$single_data = [];

			if ( ! is_array( $filters ) || count( $filters ) === 0 ) {
				return $single_data;
			}

			foreach ( $filters as $filter ) {
				$single_data[ $filter['filter'] ] = $filter;
				if ( is_array( $filter['data'] ) ) {
					$ids = array_column( $filter['data'], 'id' );
					if ( ! empty( $ids ) ) {
						$single_data[ $filter['filter'] ]['data'] = implode( ',', $ids );
					}
				}
			}

			return $single_data;
		}

		public function filters_list( $args, $optin = false ) {
			$filters = array(
				array(
					'type'  => 'sticky',
					'rules' => array(
						array(
							'slug'          => 'period',
							'title'         => __( 'Date Created', 'funnel-builder' ),
							'type'          => 'date-range',
							'op_label'      => __( 'Time Period', 'funnel-builder' ),
							'required'      => array( 'rule', 'data' ),
							'readable_text' => '{{value /}}',
						),
						array(
							'slug'          => 'cart_upsell',
							'title'         => __( 'Cart Upsell', 'funnel-builder' ),
							'type'          => 'search',
							'operators'     => array(
								'accepted' => __( 'Yes', 'funnel-builder' ),
								'rejected' => __( 'No', 'funnel-builder' ),
							),
							'api'           => '/fkcart-product-search?s={{search}}',
							'op_label'      => __( 'Has Purchased', 'cart-for-woocommerce' ),
							'val_label'     => __( 'Select Purchased', 'cart-for-woocommerce' ),
							'required'      => array( 'rule', 'data' ),
							'readable_text' => '{{rule /}} - {{value /}}',
							'multiple'      => true,
						),
						array(
							'slug'          => 'special_addon',
							'title'         => __( 'Special Addon', 'funnel-builder' ),
							'type'          => 'search',
							'operators'     => array(
								'accepted' => __( 'Yes', 'funnel-builder' ),
								'rejected' => __( 'No', 'funnel-builder' ),
							),
							'api'           => '/fkcart-product-search?s={{search}}',
							'op_label'      => __( 'Has Purchased', 'cart-for-woocommerce' ),
							'val_label'     => __( 'Products (Optional)', 'funnel-builder' ),
							'required'      => array( 'rule', 'data' ),
							'readable_text' => '{{rule /}} - {{value /}}',
							'multiple'      => true,
						),
						array(
							'slug'          => 'rewards',
							'title'         => __( 'Rewards', 'funnel-builder' ),
							'type'          => 'select',
							'operators'     => array(
								'free_shipping' => __( 'Free Shipping', 'funnel-builder' ),
								'discount'      => __( 'Discount', 'funnel-builder' ),
								'free_gift'     => __( 'Free Gift', 'funnel-builder' ),
							),
							'options'       => array(
								'yes' => __( 'Yes', 'funnel-builder' ),
								'no'  => __( 'No', 'funnel-builder' ),
							),
							'val_label'     => __( 'Has Earned', 'funnel-builder' ),
							'op_label'      => __( 'Type', 'funnel-builder' ),
							'required'      => array( 'rule', 'data' ),
							'readable_text' => '{{rule /}} - {{value /}}',
							'data_2'        => array(
								'toggler'         => array( 'rule', 'discount' ),
								'type'            => 'search',
								'api'             => '/fkcart-coupon-search?s={{search}}',
								'val_label'       => __( 'Coupons (Optional)', 'funnel-builder' ),
								'default_options' => array(),
							),
						)
					),
				),
			);

			return $filters;
		}

		public function get_product_names( $ids ) {
			if ( empty( $ids ) ) {
				return [];
			}

			$product_ids = explode( ', ', $ids );
			if ( empty( $product_ids ) ) {
				return [];
			}
			$map_products = [];
			$product_ids  = array_unique( $product_ids );
			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}
				
				// If this is a variation, use the parent product ID as key but keep variation name
				if ( $product instanceof \WC_Product_Variation ) {
					$parent_id = $product->get_parent_id();
					$map_products[ $parent_id ] = $product->get_name();
				} else {
					$map_products[ $product_id ] = $product->get_name();
				}
			}

			return $map_products;
		}

		public function get_cart_upsell_data( $request, $return_data = false ) {
			global $wpdb;
			$filters = array(
				'search'      => isset( $request['s'] ) ? sanitize_text_field( $request['s'] ) : '',
				'limit'       => isset( $request['limit'] ) ? intval( $request['limit'] ) : get_option( 'posts_per_page' ),
				'total_count' => isset( $request['total_count'] ) ? $request['total_count'] : '',
				'offset'      => isset( $request['offset'] ) ? $request['offset'] : 0,
				'page_no'     => isset( $request['page_no'] ) ? intval( $request['page_no'] ) : 1,
				'delete_ids'  => isset( $request['delete_ids'] ) ? $request['delete_ids'] : false,
			);

			// Ensure limit is at least 1 and offset is non-negative
			$limit   = $filters['limit'];
			$page_no = $filters['page_no'];
			$offset  = ! empty( $filters['offset'] ) ? $filters['offset'] : ( intval( $limit ) * intval( $page_no - 1 ) );

			$this->args = $filters;

			// Parse the filter format
			$filter_data = is_array( $request['filters'] ) ? $request['filters'] : json_decode( $request['filters'] ?? '[]', true );

			// Initialize filter variables
			$date_after             = null;
			$date_before            = null;
			$rewards_dis_filter     = null;
			$rewards_ship_filter    = null;
			$rewards_gift_filter    = null;
			$specific_discounts     = [];
			$cart_upsell_products   = [];
			$special_addon_products = [];
			$cart_reject_rule       = false;
			$is_filter              = false;

			// Process filters
			if ( is_array( $filter_data ) ) {
				foreach ( $filter_data as $filter ) {
					switch ( $filter['filter'] ) {
						case 'period':
							$date_after  = sanitize_text_field( $filter['data']['after'] );
							$date_before = sanitize_text_field( $filter['data']['before'] );
							$is_filter   = true;
							break;
						case 'rewards':
							if ( $filter['rule'] === 'discount' ) {
								$rewards_dis_filter = $filter['data'] === 'yes' ? 1 : 0;
								if ( ! empty( $filter['data_2'] ) ) {
									foreach ( $filter['data_2'] as $discount ) {
										$specific_discounts[] = $discount['id'];
									}
								}
							}
							if ( $filter['rule'] === 'free_shipping' ) {
								$rewards_ship_filter = $filter['data'] === 'yes' ? 1 : 0;
							}
							if ( $filter['rule'] === 'free_gift' ) {
								$rewards_gift_filter = $filter['data'] === 'yes' ? 1 : 0;
							}
							$is_filter = true;
							break;
						case 'cart_upsell':
							if ( $filter['rule'] === 'accepted' && ! empty( $filter['data'] ) ) {
								foreach ( $filter['data'] as $product ) {
									$cart_upsell_products[] = intval( $product['id'] );
								}
							} else if ( $filter['rule'] === 'rejected' && ! empty( $filter['data'] ) ) {
								$cart_reject_rule = true;
								foreach ( $filter['data'] as $product ) {
									$cart_upsell_products[] = intval( $product['id'] );
								}
							}
							$is_filter = true;
							break;
						case 'special_addon':
							if ( $filter['rule'] === 'accepted' && ! empty( $filter['data'] ) ) {
								foreach ( $filter['data'] as $product ) {
									$special_addon_products[] = intval( $product['id'] );
								}
							} else if ( $filter['rule'] === 'rejected' && ! empty( $filter['data'] ) ) {
								$cart_reject_rule = true;
								foreach ( $filter['data'] as $product ) {
									$special_addon_products[] = intval( $product['id'] );
								}
							}
							$is_filter = true;
							break;
					}
				}
			}
			$delete_ids = $filters['delete_ids'];
			if ( ! empty( $delete_ids ) ) {
				$this->delete_orders_by_oid( $delete_ids );
				$filter['total_count'] = 'yes';
			}

			// Construct the WHERE clause
			$where_clause = "WHERE 1=1";
			$where_args   = [];
			$post_join    = '';

			// Add search condition
			if ( ! empty( $filters['search'] ) ) {
				$search_term  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
				$onumber_condition = $this->column_exists( 'onumber' ) ? " OR c.onumber LIKE %s" : "";
				$where_clause .= " AND (c.oid LIKE %s OR p.post_title LIKE %s" . $onumber_condition . ")";
				$where_args[] = $search_term;
				$where_args[] = $search_term;
				if ( $this->column_exists( 'onumber' ) ) {
					$where_args[] = $search_term;
				}
			}

			// Add date range to WHERE clause
			if ( $date_after && $date_before ) {
				$where_clause .= " AND c.date_created BETWEEN %s AND %s";
				$where_args[] = $date_after;
				$where_args[] = $date_before;
			}

			// Add rewards filter
			if ( $rewards_dis_filter !== null ) {

				/*
				 * First case Handle case when has Earned NO and set coupons
				 * second case
				 *  1. first condition get data when has Earned Yes
				 *  1. second condition get data when has Earned No and set coupons, return all data after exclude coupon
				 */
				if ( ! $rewards_dis_filter && ! empty( $specific_discounts ) ) {
					$where_clause .= '';
				} else {
					$where_clause .= $rewards_dis_filter ? " AND (c.discount != '' AND c.discount IS NOT NULL)" : "  AND ( c.discount = '' OR c.discount IS NULL) ";
				}
				if ( ! empty( $specific_discounts ) ) {
					$where_clause .= " AND (";
					foreach ( $specific_discounts as $discount ) {
						$where_clause .= $rewards_dis_filter ? " c.discount LIKE %s OR" : "c.discount NOT LIKE %s OR";
						$where_args[] = '%' . $wpdb->esc_like( $discount ) . '%';
					}
					$where_clause = rtrim( $where_clause, ' OR' ) . ")";
				}
			}

			// Add rewards ship filter
			if ( $rewards_ship_filter !== null ) {
				$where_clause .= $rewards_ship_filter ? " AND (c.free_shipping = 1)" : " AND (c.free_shipping != 1)";
			}

			// Add rewards ship filter
			if ( $rewards_gift_filter !== null ) {
				if ( $rewards_gift_filter ) {
					$where_clause .= " AND EXISTS ( SELECT 1 FROM {$wpdb->prefix}fk_cart_products cp_upsell WHERE cp_upsell.oid = c.oid AND cp_upsell.type = 2 )";
				} else {
					$where_clause .= " AND cp.type != 2 ";
				}
			}

			// Add cart upsell filter
			if ( ! empty( $cart_upsell_products ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $cart_upsell_products ), '%d' ) );
				$cart_in      = $cart_reject_rule ? ' NOT IN (' . $placeholders . ') ' : ' IN (' . $placeholders . ')';
				$where_clause .= " AND EXISTS ( SELECT 1 FROM {$wpdb->prefix}fk_cart_products cp_upsell WHERE cp_upsell.oid = c.oid AND cp_upsell.type = 1 AND cp_upsell.product_id " . $cart_in . " )";
				$where_args   = array_merge( $where_args, $cart_upsell_products );
			}

			// Add cart addon filter
			if ( ! empty( $special_addon_products ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $special_addon_products ), '%d' ) );
				$cart_in      = $cart_reject_rule ? ' NOT IN (' . $placeholders . ') ' : ' IN (' . $placeholders . ')';
				$where_clause .= " AND EXISTS ( SELECT 1 FROM {$wpdb->prefix}fk_cart_products cp_upsell WHERE cp_upsell.oid = c.oid AND cp_upsell.type = 3 AND cp_upsell.product_id " . $cart_in . " )";
				$where_args   = array_merge( $where_args, $special_addon_products );
			}

			if ( ! empty( $filters['search'] ) ) {
				$post_join = " LEFT JOIN {$wpdb->posts} p ON cp.product_id = p.ID ";
			}

			// Get total count
			$total_count       = 0;
			$start_total_query = "";
			$last_total_query  = " ";
			$filter_count      = 0;
			if ( ! empty( $filters['total_count'] ) && ( ( true === $is_filter ) || ! empty( $filters['search'] ) ) ) {
				$onumber_group = $this->column_exists( 'onumber' ) ? ", c.onumber" : "";
				$count_query = $wpdb->prepare( "
	            SELECT c.id
	            FROM 
	                {$wpdb->prefix}fk_cart c
	            LEFT JOIN 
	                {$wpdb->prefix}fk_cart_products cp ON c.oid = cp.oid
	                " . $post_join . "
	                $where_clause
	            GROUP BY 
	                c.oid, c.free_shipping, c.discount, c.date_created" . $onumber_group . "
	            ", $where_args );

				$filter_col = $wpdb->get_col( $count_query );

				if ( is_array( $filter_col ) && count( $filter_col ) > 0 ) {
					$filter_count = count( $filter_col );
				}

			} else if ( ! empty( $filters['total_count'] ) ) {
				$start_total_query = "SELECT main.*, ( SELECT COUNT(*) FROM {$wpdb->prefix}fk_cart c ) AS total_count FROM ( ";
				$last_total_query  = " ) AS main";

			}
			// Construct the main query
			$onumber_select = $this->column_exists( 'onumber' ) ? "c.onumber AS order_number," : "c.oid AS order_number,";
			$onumber_group = $this->column_exists( 'onumber' ) ? ", c.onumber" : "";
			$query = $wpdb->prepare( $start_total_query . "
    		SELECT c.oid AS order_id,
    		    " . $onumber_select . "
	            GROUP_CONCAT(DISTINCT CASE WHEN cp.type = 1 THEN cp.product_id END SEPARATOR ', ') AS cart_upsell_ids,
	            SUM(CASE WHEN cp.type = 1 THEN cp.price ELSE 0 END) AS upsell_revenue,
	            GROUP_CONCAT(DISTINCT CASE WHEN cp.type = 3 THEN cp.product_id END SEPARATOR ', ') AS special_addon_ids,
	            SUM(CASE WHEN cp.type = 3 THEN cp.price ELSE 0 END) AS special_addon_revenue,
	            GROUP_CONCAT(DISTINCT CASE WHEN cp.type = 2 THEN cp.product_id END SEPARATOR ', ') AS free_gift_ids,
	            c.free_shipping AS free_shipping_orders,
	            COUNT(DISTINCT CASE WHEN cp.type = 2 THEN cp.id END) AS free_gift_orders,
	            c.discount AS discount,
	            c.date_created AS date
        	FROM 
            	{$wpdb->prefix}fk_cart c
        	LEFT JOIN 
            	{$wpdb->prefix}fk_cart_products cp ON c.oid = cp.oid
        		" . $post_join . "
                $where_clause
            GROUP BY 
                c.oid, c.free_shipping, c.discount, c.date_created" . $onumber_group . " 
            ORDER BY 
                c.date_created DESC, c.oid DESC
            LIMIT %d, %d " . $last_total_query, array_merge( $where_args, [ $offset, $limit ] ) ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$results = $wpdb->get_results( $query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared


			if ( is_array( $results ) && count( $results ) > 0 ) {
				// Process results
				foreach ( $results as &$row ) {
					$row['order_url']             = class_exists('WFFN_Common') && method_exists('WFFN_Common', 'add_order_urls') ? WFFN_Common::add_order_urls( $row['order_id'] ) : '';
					$row['cart_upsell']           = $this->get_product_names( $row['cart_upsell_ids'] );
					$row['special_addon']         = $this->get_product_names( $row['special_addon_ids'] );
					$row['free_gift']             = $this->get_product_names( $row['free_gift_ids'] );
					$row['upsell_name']           = empty( $row['cart_upsell'] ) ? '-' : $row['cart_upsell'];
					$row['special_addon']         = empty( $row['special_addon'] ) ? '-' : $row['special_addon'];
					$row['free_gift']             = empty( $row['free_gift'] ) ? '-' : $row['free_gift'];
					$row['special_addon_revenue'] = empty( $row['special_addon_revenue'] ) ? '-' : $row['special_addon_revenue'];
					$row['free_shipping_orders']  = '1' === $row['free_shipping_orders'] ? __( 'Yes', 'funnel-builder' ) : '-';
					$discount                     = json_decode( $row['discount'], true );
					$row['discount']              = ! empty( $discount ) ? implode( ',', $discount ) : '-';
					$total_count                  = ! empty( $row['total_count'] ) ? $row['total_count'] : $total_count;
					// Remove the ID fields
					unset( $row['cart_upsell_ids'], $row['special_addon_ids'], $row['free_gift_ids'] );
				}
			}

			$response = [
				'status'                      => true,
				'records'                     => is_array( $results ) ? $results : [],
				'filters_list'                => $this->filters_list( $this->args ),
				'conversion_migration_status' => function_exists( 'fkcart_db_migrator' ) ? fkcart_db_migrator()->get_upgrade_state() : 0

			];

			if ( ! empty( $filters['total_count'] ) ) {
				$response['total_count'] = ( $is_filter || ! empty( $filters['search'] ) ) ? absint( $filter_count ) : absint( $total_count );
			}

			return $return_data ? $response : rest_ensure_response( $response );
		}

		public function custom_product_search_api( $request ) {
			global $wpdb;
			$term           = $request->get_param( 's' );
			$show_variation = $request->get_param( 'variations' ) !== '0' ? 1 : 0;
			$like_term      = '%' . $wpdb->esc_like( $term ) . '%';
			$post_statuses  = current_user_can( 'edit_private_products' ) ? array( 'private', 'publish' ) : array( 'publish' );

			$p_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} AS posts 
        LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup AS product_meta_lookup ON posts.ID = product_meta_lookup.product_id 
        WHERE (posts.post_title LIKE %s OR product_meta_lookup.sku LIKE %s OR posts.ID LIKE %s) 
        AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') 
        AND posts.post_type = 'product' 
        ORDER BY posts.post_parent ASC, posts.post_title ASC 
        LIMIT 10", $like_term, $like_term, $like_term ) );

			$allowed_types = apply_filters( 'fkcart_allow_product_types', array(
				'simple',
				'variable',
				'variation',
				'variable-subscription',
				'subscription',
			) );

			$products = [];
			foreach ( $p_ids as $pid ) {
				$prod_obj = wc_get_product( $pid );
				if ( ! $prod_obj instanceof \WC_Product ) {
					continue;
				}

				$type = $prod_obj->get_type();
				if ( ! wc_products_array_filter_editable( $prod_obj ) || ! in_array( $type, $allowed_types, true ) || 'publish' !== $prod_obj->get_status() ) {
					continue;
				}

				$products[] = array(
					'id'   => $prod_obj->get_id(),
					'name' => $prod_obj->get_name()
				);

				if ( in_array( $type, [ 'variable', 'variable-subscription' ], true ) && $show_variation ) {
					$variations = $prod_obj->get_available_variations();
					foreach ( $variations as $variation ) {
						$variation_obj = wc_get_product( $variation['variation_id'] );
						$products[]    = array(
							'id'   => $variation_obj->get_id(),
							'name' => $prod_obj->get_name() . ' - ' . implode( ', ', $variation_obj->get_variation_attributes() )
						);
					}
				}
			}

			return rest_ensure_response( $products );
		}

		public function discount_search_api( $request ) {
			global $wpdb;
			$search_term = $request->get_param( 's' );
			$limit       = 10; // You can make this a parameter if you want to allow variable limits

			$where        = [ "1=1", "discount != ''" ];
			$prepare_args = [];

			if ( $search_term ) {
				$where[]        = "discount LIKE %s";
				$prepare_args[] = '%' . $wpdb->esc_like( $search_term ) . '%';
			}
			$where_query = implode( ' AND ', $where );
			$query       = $wpdb->prepare( "SELECT discount 
        FROM {$wpdb->prefix}fk_cart 
        WHERE $where_query 
        ORDER BY id DESC 
        LIMIT %d", array_merge( $prepare_args, [ $limit * 2 ] ) // Fetch more to ensure we have enough after processing
			);

			$results          = $wpdb->get_col( $query );
			$discount_coupons = [];
			foreach ( $results as $result ) {
				$decoded = json_decode( $result, true );
				if ( is_array( $decoded ) ) {
					$discount_coupons = array_merge( $discount_coupons, $decoded );
				} elseif ( ! empty( $result ) ) {
					$discount_coupons[] = $result;
				}
			}

			$unique_discounts = [];
			foreach ( $discount_coupons as $coupon ) {
				$lower_code                      = strtolower( $coupon );
				$unique_discounts[ $lower_code ] = [
					'name' => $coupon,
					'id'   => $lower_code,
				];
			}

			return rest_ensure_response( array_values( $unique_discounts ) );
		}

		public function generate_pie_chart_data( $request ) {
			$data = $this->get_cart_upsell_overview( $request, true );

			$free_shipping_count = ! empty( $data['free_shipping_orders'] ) ? $data['free_shipping_orders'] : 0;
			$free_gift_count     = ! empty( $data['free_gift_orders'] ) ? $data['free_gift_orders'] : 0;
			$discount_count      = ! empty( $data['discount'] ) ? $data['discount'] : 0;;
			$total_carts = ( $free_shipping_count + $free_gift_count + $discount_count );
			$data        = [
				[
					'key'        => 'free_shipping',
					'title'      => __( 'Free Shipping', 'funnel-builder' ),
					'percentage' => $total_carts > 0 ? round( ( $free_shipping_count / $total_carts ) * 100, 2 ) : 0,
					'count'      => $free_shipping_count,
				],
				[
					'key'        => 'free_gift',
					'title'      => __( 'Free Gift', 'funnel-builder' ),
					'percentage' => $total_carts > 0 ? round( ( $free_gift_count / $total_carts ) * 100, 2 ) : 0,
					'count'      => $free_gift_count,
				],
				[
					'key'        => 'discount',
					'title'      => __( 'Discount', 'funnel-builder' ),
					'percentage' => $total_carts > 0 ? round( ( $discount_count / $total_carts ) * 100, 2 ) : 0,
					'count'      => $discount_count,
				],
			];

			return rest_ensure_response( [ 'data' => $data, 'status' => true ] );
		}

		public function get_most_popular_upsells( $request ) {
			global $wpdb;

			$params  = $request->get_params();
			$filters = array(
				'limit'  => isset( $params['limit'] ) ? intval( $params['limit'] ) : get_option( 'posts_per_page' ),
				'offset' => isset( $params['offset'] ) ? intval( $params['offset'] ) : 0,
				'search' => isset( $params['s'] ) ? sanitize_text_field( $params['s'] ) : ''
			);

			// Ensure limit is at least 1 and offset is non-negative
			$filters['limit']  = max( 1, $filters['limit'] );
			$filters['offset'] = max( 0, $filters['offset'] );
			// Parse the filter format
			$filter_data = is_array( $request['filters'] ) ? $request['filters'] : json_decode( $request['filters'] ?? '[]', true );

			// Initialize filter variables
			$date_after  = false;
			$date_before = false;

			// Process filters
			if ( is_array( $filter_data ) ) {
				foreach ( $filter_data as $filter ) {
					switch ( $filter['filter'] ) {
						case 'period':
							$date_after  = sanitize_text_field( $filter['data']['after'] );
							$date_before = sanitize_text_field( $filter['data']['before'] );
							break;
					}
				}
			}

			$where_clause = "WHERE cp.type = 1"; // Assuming type 1 is for upsells
			$where_args   = [];
			// Add date range to WHERE clause
			if ( $date_after && $date_before ) {
				$where_clause .= " AND c.date_created BETWEEN %s AND %s";
				$where_args[] = $date_after;
				$where_args[] = $date_before;
			}


			$query = $wpdb->prepare( "
        SELECT 
            cp.product_id as pid,
            p.post_title as product_name,           
            SUM(cp.price) as revenue,
            COUNT(DISTINCT cp.oid) as conversions,
            
            -- Only calculate times_offered (remove unused popularity_percentage)
            (SELECT COUNT(DISTINCT c2.oid) 
             FROM {$wpdb->prefix}fk_cart c2 
             WHERE c2.upsells_viewed IS NOT NULL 
             AND c2.upsells_viewed != '' 
             AND c2.upsells_viewed NOT LIKE '[]'
             AND c2.upsells_viewed LIKE CONCAT('%\"', cp.product_id, '\"%')
             " . ($date_after && $date_before ? "AND c2.date_created BETWEEN '{$date_after}' AND '{$date_before}'" : "") . "
            ) as times_offered
        FROM 
            {$wpdb->prefix}fk_cart_products cp
        JOIN 
            {$wpdb->prefix}fk_cart c ON cp.oid = c.oid
        JOIN
            {$wpdb->posts} p ON cp.product_id = p.ID
        {$where_clause}
        GROUP BY 
            cp.product_id, p.post_title
        ORDER BY 
            revenue DESC
        LIMIT %d OFFSET %d
    ", array_merge( $where_args, [$filters['limit'], $filters['offset']] ));
			$results           = $wpdb->get_results( $query );
			$formatted_results = array_filter( array_map( function ( $row ) use ( $filters ) {
				$product = wc_get_product( $row->pid );
				if ( ! $product ) {
					return null;
				} // Skip if product doesn't exist

				$name = $product->get_name();
				// Apply search filter here
				if ( ! empty( $filters['search'] ) && stripos( $name, $filters['search'] ) === false ) {
					return null;
				}
				$conversion_rate = $row->times_offered > 0 ?
					round(($row->conversions / $row->times_offered) * 100, 2) : 0;

				return [
					'pid'             => (string) $row->pid,
					'name'            => $name,
					'revenue'         => number_format( $row->revenue, 2, '.', '' ),
					'conversion_rate' => round( $conversion_rate, 2 ),
				];
			}, $results ) );

			$count_where_args = array_filter($where_args, function($arg) use ($filters) {
				return $arg !== $filters['limit'] && $arg !== $filters['offset'];
			});
			$count_query = $wpdb->prepare( "
		        SELECT COUNT(DISTINCT cp.product_id) as total 
		        FROM {$wpdb->prefix}fk_cart_products cp
		        JOIN {$wpdb->prefix}fk_cart c ON cp.oid = c.oid
		        JOIN {$wpdb->posts} p ON cp.product_id = p.ID
		        {$where_clause}
		    ", $count_where_args );

			$total_upsells = $wpdb->get_var( $count_query );

			return rest_ensure_response( [ 'data' => array_values( $formatted_results ), 'status' => true, 'total_count' => $total_upsells ] );

		}

		public function get_cart_upsell_overview( $request, $is_data = false, $is_interval = false ) {
			global $wpdb;

			$params  = $request->get_params();
			$filters = json_decode( $params['filters'] ?? '[]', true );

			$where_clause = "WHERE 1=1";
			$where_args   = [];

			// Process filters
			foreach ( $filters as $filter ) {
				switch ( $filter['filter'] ) {
					case 'period':
						$start_date   = sanitize_text_field( $filter['data']['after'] );
						$end_date     = sanitize_text_field( $filter['data']['before'] );
						$where_clause .= " AND c.date_created BETWEEN %s AND %s";
						$where_args[] = $start_date;
						$where_args[] = $end_date;
						break;
					case 'cart_upsell':
						if ( $filter['rule'] === 'yes' && ! empty( $filter['data'] ) ) {
							$cart_upsell_products = array_map( 'intval', array_column( $filter['data'], 'id' ) );
							$placeholders         = implode( ',', array_fill( 0, count( $cart_upsell_products ), '%d' ) );
							$where_clause         .= " AND EXISTS (
                        SELECT 1 FROM {$wpdb->prefix}fk_cart_products cp_upsell 
                        WHERE cp_upsell.oid = c.oid AND cp_upsell.type = 1 AND cp_upsell.product_id IN ($placeholders)
                    )";
							$where_args           = array_merge( $where_args, $cart_upsell_products );
						}
						break;
				}
			}
			$date_col       = "c.date_created";
			$interval_query = '';
			$interval_type  = '';
			$group          = ' NULL ';

			if ( $is_interval ) {
				$int_request    = ( isset( $request['interval'] ) && '' !== $request['interval'] ) ? $request['interval'] : 'week';
				$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
				$interval_query = $get_interval['interval_query'];
				// Escape % to %%
				$interval_query = str_replace( '%', '%%', $interval_query );
				$interval_group = $get_interval['interval_group'];
				$group          = $interval_group;
				$interval_type  = $this->get_two_date_interval( $start_date, $end_date );
			}
			$query = $wpdb->prepare( "SELECT 
            COUNT(DISTINCT c.oid) as total_orders,
            SUM(cp.price) as total_revenue,
            COUNT( DISTINCT CASE WHEN c.upsells_viewed IS NOT NULL AND c.upsells_viewed != '' AND c.upsells_viewed NOT LIKE '[]' THEN c.oid END) AS upsell_views,
            COUNT( DISTINCT CASE WHEN c.addon_viewed IS NOT NULL AND c.addon_viewed != '' AND c.addon_viewed NOT LIKE '[]' THEN c.oid END) AS addon_views,

            COUNT(DISTINCT CASE WHEN cp.type = 1 THEN c.oid END) as upsell_orders,
            SUM( CASE WHEN cp.type = 1 THEN cp.price END) as upsell_total_revenue,
            COUNT(DISTINCT CASE WHEN c.free_shipping = 1 THEN c.oid END) as free_shipping_orders,
            COUNT(CASE WHEN cp.type = 2 THEN cp.id END) as free_gift_orders,
            COUNT( DISTINCT CASE WHEN c.discount IS NOT NULL AND c.discount != '' AND c.discount NOT LIKE '[]' THEN c.oid END) AS discount_count,
            COUNT(DISTINCT CASE WHEN cp.type = 3 THEN c.oid END) as special_addon_orders,
            SUM(CASE WHEN cp.type = 3 THEN cp.price ELSE 0 END) as special_addon_revenue 
			{$interval_query}
        	FROM 
            	{$wpdb->prefix}fk_cart c
        	LEFT JOIN 
            	{$wpdb->prefix}fk_cart_products cp ON c.oid = cp.oid
        		$where_clause
        	GROUP BY {$group}
    		", $where_args );

			if ( $is_interval ) {
				$results                        = $wpdb->get_results( $query, ARRAY_A );//phpcs:ignore
				$interval_data                  = [];
				$intervals                      = array();
				$interval_data['intervals']     = [];
				$interval_data['interval_type'] = isset( $interval_type ) ? $interval_type : '';
				$overall                        = isset( $item['overall'] ) ? true : false;
				$intervals_all                  = $this->intervals_between( $start_date, $end_date, $int_request, $overall );
				foreach ( $intervals_all as $all_interval ) {
					$interval   = $all_interval['time_interval'];
					$start_date = $all_interval['start_date'];
					$end_date   = $all_interval['end_date'];

					$get_data = is_array( $results ) ? $this->maybe_interval_exists( $results, 'time_interval', $interval ) : 0;

					if ( ! empty( $get_data ) ) {
						$upsell_conversion_rate = ( absint( $get_data[0]['upsell_views'] ) > 0 && absint( $get_data[0]['upsell_orders'] ) > 0 ) ? ( $get_data[0]['upsell_orders'] / $get_data[0]['upsell_views'] ) * 100 : 0;
						$addon_conversion_rate  = ( absint( $get_data[0]['special_addon_orders'] ) > 0 && absint( $get_data[0]['addon_views'] ) > 0 ) ? ( $get_data[0]['special_addon_orders'] / $get_data[0]['addon_views'] ) * 100 : 0;

					} else {
						$upsell_conversion_rate = 0;
						$addon_conversion_rate  = 0;
					}
					// Calculate percentages and format data
					$intervals['interval']       = $interval;
					$intervals['start_date']     = $start_date;
					$intervals['date_start_gmt'] = $this->convert_local_datetime_to_gmt( $start_date )->format( self::$sql_datetime_format );
					$intervals['end_date']       = $end_date;
					$intervals['date_end_gmt']   = $this->convert_local_datetime_to_gmt( $end_date )->format( self::$sql_datetime_format );

					$intervals['subtotals'] = array(
						'total_orders'                  => ! empty( $get_data[0]['upsell_orders'] ) ? intval( $get_data[0]['upsell_orders'] ) : 0,
						'total_revenue'                 => ! empty( $get_data[0]['upsell_total_revenue'] ) ? number_format( $get_data[0]['upsell_total_revenue'], 2, '.', '' ) : 0,
						'conversion_rate'               => ! empty( $upsell_conversion_rate ) ? number_format( $upsell_conversion_rate, 2 ) : 0,
						'free_shipping_orders'          => ! empty( $get_data[0]['free_shipping_orders'] ) ? intval( $get_data[0]['free_shipping_orders'] ) : 0,
						'free_gift_orders'              => ! empty( $get_data[0]['free_gift_orders'] ) ? intval( $get_data[0]['free_gift_orders'] ) : 0,
						'discount'                      => ! empty( $get_data[0]['discount_count'] ) ? intval( $get_data[0]['discount_count'] ) : 0,
						'special_addon'                 => ! empty( $get_data[0]['special_addon_orders'] ) ? intval( $get_data[0]['special_addon_orders'] ) : 0,
						'special_addon_revenue'         => ! empty( $get_data[0]['special_addon_revenue'] ) ? number_format( $get_data[0]['special_addon_revenue'], 2, '.', '' ) : 0,
						'special_addon_conversion_rate' => ! empty( $addon_conversion_rate ) ? number_format( $addon_conversion_rate, 2 ) : 0,

					);

					$interval_data['intervals'][] = $intervals;

				}


				return $interval_data;
			} else {


				$result = $wpdb->get_row( $query, ARRAY_A );//phpcs:ignore

				if ( empty( $result ) ) {
					$result = [
						'upsell_views'          => 0,
						'upsell_orders'         => 0,
						'upsell_total_revenue'  => 0,
						'free_shipping_orders'  => 0,
						'free_gift_orders'      => 0,
						'discount_count'        => 0,
						'special_addon_orders'  => 0,
						'special_addon_revenue' => 0,
					];
				}

				// Calculate percentages and format data
				$upsell_conversion_rate = ( absint( $result['upsell_views'] ) > 0 && absint( $result['upsell_orders'] ) > 0 ) ? ( $result['upsell_orders'] / $result['upsell_views'] ) * 100 : 0;
				$addon_conversion_rate  = ( absint( $result['special_addon_orders'] ) > 0 && absint( $result['addon_views'] ) > 0 ) ? ( $result['special_addon_orders'] / $result['addon_views'] ) * 100 : 0;

				$overview_data = [
					'total_orders'                  => intval( $result['upsell_orders'] ),
					'total_revenue'                 => ! empty( $result['upsell_total_revenue'] ) ? number_format( $result['upsell_total_revenue'], 2, '.', '' ) : 0,
					'conversion_rate'               => ! empty( $upsell_conversion_rate ) ? number_format( $upsell_conversion_rate, 2 ) : 0,
					'free_shipping_orders'          => ! empty( $result['free_shipping_orders'] ) ? intval( $result['free_shipping_orders'] ) : 0,
					'free_gift_orders'              => ! empty( $result['free_gift_orders'] ) ? intval( $result['free_gift_orders'] ) : 0,
					'discount'                      => ! empty( $result['discount_count'] ) ? intval( $result['discount_count'] ) : 0,
					'special_addon'                 => ! empty( $result['special_addon_orders'] ) ? intval( $result['special_addon_orders'] ) : 0,
					'special_addon_revenue'         => ! empty( $result['special_addon_revenue'] ) ? number_format( $result['special_addon_revenue'], 2, '.', '' ) : 0,
					'special_addon_conversion_rate' => ! empty( $addon_conversion_rate ) ? number_format( $addon_conversion_rate, 2 ) : 0,
				];

				if ( true === $is_data ) {
					return $overview_data;
				}
			}

			return rest_ensure_response( [
				'data'                        => $overview_data,
				'status'                      => true,
				'conversion_migration_status' => function_exists( 'fkcart_db_migrator' ) ? fkcart_db_migrator()->get_upgrade_state() : 0
			] );
		}


		public function get_cart_upsell_performance( $request ) {
			$interval_data = $this->get_cart_upsell_overview( $request, true, true );

			return rest_ensure_response( [ 'data' => $interval_data, 'status' => true ] );
		}

		/**
		 * @param $request
		 *
		 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
		 */
		public function add_cart_conversions_export( $request ) {
			$resp = array(
				'status'  => false,
				'message' => __( 'Error in exporting cart conversions', 'funnel-builder-powerpack' )
			);

			if ( ! class_exists( 'WFFN_Abstract_Exporter' ) ) {
				return rest_ensure_response( $resp );
			}
			$export_contact = WFFN_Pro_Core()->exporter->get_integration_object( \FKCART_Export_Cart_Conversion::get_instance()->get_slug() );
			if ( ! $export_contact instanceof \WFFN_Abstract_Exporter ) {
				return rest_ensure_response( $resp );
			}

			$data                     = [];
			$data['fields']           = $export_contact->get_columns();
			$data['filters']          = isset( $request['filters'] ) ? $request['filters'] : [];
			$data['csv_header']       = [ 'header' => $data['fields'] ];
			$data['is_global_export'] = 'yes';
			$data['title']            = __( 'Cart Conversion Export' );
			$response                 = $export_contact->handle_export( $data );
			if ( ! $response['status'] ) {
				return rest_ensure_response( $response );
			}
			$resp['status']   = true;
			$resp['message']  = __( 'Export Added to Queue', 'funnel-builder-powerpack' );
			$resp['response'] = $response;

			return rest_ensure_response( $resp );
		}

		/**
		 * Delete conversions from using order ids
		 *
		 * @param $order_ids_csv
		 *
		 * @return bool
		 */
		function delete_orders_by_oid( $order_ids_csv ) {
			global $wpdb;
			$wpdb->query( 'START TRANSACTION' );
			// Sanitize and prepare IDs
			$order_ids = array_filter( array_map( 'intval', explode( ',', $order_ids_csv ) ) );
			if ( empty( $order_ids ) ) {
				return false;
			}
			// Prepare placeholders for SQL IN clause
			$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
			// Table names with dynamic prefix
			$cart_table          = $wpdb->prefix . 'fk_cart';
			$cart_products_table = $wpdb->prefix . 'fk_cart_products';
			// Delete from fk_cart_products
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$cart_products_table} WHERE oid IN ($placeholders)", ...$order_ids ) );
			// Delete from fk_cart
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$cart_table} WHERE oid IN ($placeholders)", ...$order_ids ) );
			$wpdb->query( 'COMMIT' );

			return true;
		}

	}

	Conversions::get_instance();
}