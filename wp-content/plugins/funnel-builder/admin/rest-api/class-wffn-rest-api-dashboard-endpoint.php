<?php
defined( 'ABSPATH' ) || exit; // Exit if accessed directly

if ( ! class_exists( 'WFFN_REST_API_Dashboard_EndPoint' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_REST_API_Dashboard_EndPoint extends WFFN_REST_Controller {

		private static $ins  = null;
		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'funnel-analytics';

		/**
		 * WFFN_REST_API_Dashboard_EndPoint constructor.
		 */
		public function __construct() {

			add_action( 'rest_api_init', array( $this, 'register_endpoint' ), 12 );
		}

		/**
		 * @return WFFN_REST_API_Dashboard_EndPoint|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function register_endpoint() {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/dashboard/stats/',
				array(
					array(
						'args'                => $this->get_stats_collection(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_graph_data' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				)
			);
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/dashboard/overview/',
				array(
					array(
						'args'                => $this->get_stats_collection(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_overview_data' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				)
			);
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/stream/timeline/',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_timeline_funnels' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				)
			);
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/dashboard/',
				array(
					array(
						'args'                => $this->get_stats_collection(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_all_stats_data' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				)
			);
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/dashboard/sources',
				array(
					array(
						'args'                => $this->get_stats_collection(),
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_all_source_data' ),
						'permission_callback' => array( $this, 'get_read_api_permission_check' ),
					),
				)
			);
		}

		public function get_read_api_permission_check() {
			return wffn_rest_api_helpers()->get_api_permission_check( 'analytics', 'read' );
		}

		public function get_overview_data( $request, $is_email_data = false ) {
			if ( isset( $request['overall'] ) ) {
				$start_date = '';
				$end_date   = '';
			} else {
				$start_date = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
				$end_date   = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : self::default_date()->format( self::$sql_datetime_format );

			}

			$funnel_id        = 0;
			$total_revenue    = null;
			$checkout_revenue = 0;
			$upsell_revenue   = 0;
			$bump_revenue     = 0;

			$get_total_revenue = $this->get_total_revenue( $funnel_id, $start_date, $end_date );
			$get_total_orders  = $this->get_total_orders( $funnel_id, $start_date, $end_date );
			$get_total_contact = $this->get_total_contacts( 0, $start_date, $end_date );

			if ( ! isset( $get_total_revenue['db_error'] ) ) {
				if ( is_array( $get_total_revenue ) ) {
					$total_revenue = $checkout_revenue = $get_total_revenue['aero'][0]['total'];
					if ( count( $get_total_revenue['aero'] ) > 0 ) {
						$checkout_revenue = $get_total_revenue['aero'][0]['sum_aero'];
					}
					if ( count( $get_total_revenue['bump'] ) > 0 ) {
						$bump_revenue = $get_total_revenue['bump'][0]['sum_bump'];
					}
					if ( count( $get_total_revenue['upsell'] ) > 0 ) {
						$upsell_revenue = $get_total_revenue['upsell'][0]['sum_upsells'];
					}
				}
			}

			$result = array(
				'revenue'          => is_null( $total_revenue ) ? 0 : $total_revenue,
				'total_orders'     => intval( $get_total_orders ),
				'checkout_revenue' => floatval( $checkout_revenue ),
				'upsell_revenue'   => floatval( $upsell_revenue ),
				'bump_revenue'     => floatval( $bump_revenue ),
			);
			if ( $is_email_data === true ) {
				$result['total_contacts'] = is_array( $get_total_contact ) ? $get_total_contact[0]['contacts'] : 0;

				$result['average_order_value'] = ( absint( $total_revenue ) !== 0 ) ? ( $total_revenue ) / $get_total_orders : 0;
			}
			$resp = array(
				'status' => true,
				'msg'    => __( 'success', 'funnel-builder' ),
				'data'   => $result,
			);

			return rest_ensure_response( $resp );
		}

		public function get_graph_data( $request ) {
			$resp = array(
				'status' => false,
				'data'   => array(),
			);

			$interval_type = '';

			if ( isset( $request['overall'] ) ) {
				global $wpdb;
				$request['after']    = $wpdb->get_var( $wpdb->prepare( "SELECT timestamp as date FROM {$wpdb->prefix}bwf_conversion_tracking WHERE funnel_id != '' AND type = 2 ORDER BY ID ASC LIMIT %d", 1 ) );
				$start_date          = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
				$end_date            = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : self::default_date()->format( self::$sql_datetime_format );
				$request['interval'] = $this->get_two_date_interval( $start_date, $end_date );
				$interval_type       = $request['interval'];
			}

			$totals    = $this->prepare_graph_for_response( $request );
			$intervals = $this->prepare_graph_for_response( $request, 'interval' );

			if ( ! is_array( $totals ) || ! is_array( $intervals ) ) {
				return rest_ensure_response( $resp );
			}

			$resp = array(
				'status' => true,
				'data'   => array(
					'totals'    => $totals,
					'intervals' => $intervals,
				),
			);

			if ( isset( $request['overall'] ) ) {
				$resp['data']['interval_type'] = $interval_type;
			}

			return rest_ensure_response( $resp );
		}

		public function prepare_graph_for_response( $request, $is_interval = '' ) {
			$start_date  = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
			$end_date    = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : self::default_date()->format( self::$sql_datetime_format );
			$int_request = ( isset( $request['interval'] ) && '' !== $request['interval'] ) ? $request['interval'] : 'week';

			$funnel_id      = 0;
			$total_revenue  = 0;
			$aero_revenue   = 0;
			$upsell_revenue = 0;
			$bump_revenue   = 0;

			$get_total_orders = $this->get_total_orders( $funnel_id, $start_date, $end_date, $is_interval, $int_request );
			if ( isset( $get_total_orders['db_error'] ) ) {
				$get_total_orders = 0;
			}
			$get_total_revenue = $this->get_total_revenue( $funnel_id, $start_date, $end_date, $is_interval, $int_request );

			$result    = array();
			$intervals = array();
			if ( ! empty( $is_interval ) ) {
				$overall       = isset( $request['overall'] ) ? true : false;
				$intervals_all = $this->intervals_between( $start_date, $end_date, $int_request, $overall );
				foreach ( $intervals_all as $all_interval ) {
					$interval   = $all_interval['time_interval'];
					$start_date = $all_interval['start_date'];
					$end_date   = $all_interval['end_date'];

					$get_total_order = is_array( $get_total_orders ) ? $this->maybe_interval_exists( $get_total_orders, 'time_interval', $interval ) : array();

					if ( ! isset( $get_total_revenue['db_error'] ) ) {
						$get_revenue        = $this->maybe_interval_exists( $get_total_revenue['aero'], 'time_interval', $interval );
						$total_revenue_aero = is_array( $get_revenue ) ? $get_revenue[0]['sum_aero'] : 0;
						$total_revenue      = is_array( $get_revenue ) ? $get_revenue[0]['total'] : 0;

						$total_revenue_bump = $this->maybe_interval_exists( $get_total_revenue['bump'], 'time_interval', $interval );
						$total_revenue_bump = is_array( $total_revenue_bump ) ? $total_revenue_bump[0]['sum_bump'] : 0;

						$total_revenue_upsells = $this->maybe_interval_exists( $get_total_revenue['upsell'], 'time_interval', $interval );
						$total_revenue_upsells = is_array( $total_revenue_upsells ) ? $total_revenue_upsells[0]['sum_upsells'] : 0;
					} else {
						$total_revenue         = 0;
						$total_revenue_aero    = 0;
						$total_revenue_bump    = 0;
						$total_revenue_upsells = 0;
					}

					$get_total_order             = is_array( $get_total_order ) ? $get_total_order[0]['total_orders'] : 0;
					$intervals['interval']       = $interval;
					$intervals['start_date']     = $start_date;
					$intervals['date_start_gmt'] = $this->convert_local_datetime_to_gmt( $start_date )->format( self::$sql_datetime_format );
					$intervals['end_date']       = $end_date;
					$intervals['date_end_gmt']   = $this->convert_local_datetime_to_gmt( $end_date )->format( self::$sql_datetime_format );
					$intervals['subtotals']      = array(
						'orders'           => $get_total_order,
						'revenue'          => $total_revenue,
						'checkout_revenue' => floatval( $total_revenue_aero ),
						'upsell_revenue'   => floatval( $total_revenue_upsells ),
						'bump_revenue'     => floatval( $total_revenue_bump ),
					);

					$result[] = $intervals;

				}
			} else {
				if ( ! isset( $get_total_revenue['db_error'] ) ) {
					if ( count( $get_total_revenue['aero'] ) > 0 ) {
						$aero_revenue   = $get_total_revenue['aero'][0]['sum_aero'];
						$total_revenue += $aero_revenue;
					}
					if ( count( $get_total_revenue['bump'] ) > 0 ) {
						$bump_revenue   = $get_total_revenue['bump'][0]['sum_bump'];
						$total_revenue += $bump_revenue;
					}
					if ( count( $get_total_revenue['upsell'] ) > 0 ) {
						$upsell_revenue = $get_total_revenue['upsell'][0]['sum_upsells'];
						$total_revenue += $upsell_revenue;
					}
				}

				$result = array(
					'orders'           => $get_total_orders,
					'revenue'          => is_null( $total_revenue ) ? 0 : $total_revenue,
					'checkout_revenue' => floatval( $aero_revenue ),
					'upsell_revenue'   => floatval( $upsell_revenue ),
					'bump_revenue'     => floatval( $bump_revenue ),
				);
			}

			return $result;
		}

		public function get_all_stats_data( $request ) {

			try {
				$response                  = array();
				$response['top_funnels']   = $this->get_top_funnels( $request );
				$top_campaigns             = array(
					'sales' => array(),
					'lead'  => array(),
				);
				$top_campaigns             = apply_filters( 'wffn_dashboard_top_campaigns', $top_campaigns, $request );
				$response['top_campaigns'] = $top_campaigns;
				$upsells_response          = $this->get_all_upsells( $request, 'dashboard' );
				$upsells_data              = is_array( $upsells_response ) ? $upsells_response : $upsells_response->get_data();
				$response['top_upsells']   = $upsells_data['result']['items'] ?? array();

				return rest_ensure_response( $response );
			} catch ( Exception | Error $e ) {
				return rest_ensure_response( $e->getMessage() );
			}
		}

		public function get_all_upsells( $request, $type = 'analytics' ) {
			global $wpdb;
			$is_fkcart_exists = class_exists( 'FKCart\Pro\Rest\Conversions' );

			try {
				$is_fkcart_exists = class_exists( 'FKCart\Pro\Rest\Conversions' );
				$limit            = isset( $request['limit'] ) && '' !== $request['limit'] ? intval( $request['limit'] ) : 5;
				$page_no          = isset( $request['page_no'] ) ? intval( $request['page_no'] ) : 1;
				$offset           = intval( $limit ) * intval( $page_no - 1 );
				$start_date       = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : '';
				$end_date         = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : '';
				if ( $type === 'dashboard' ) {
					$args = array(
						'post_type'      => array( WFOCU_Common::get_offer_post_type_slug(), WFOB_Common::get_bump_post_type_slug() ),
						'post_status'    => 'any',
						'posts_per_page' => - 1,
					);
				} else {
					$args = array(
						'post_type'      => array( WFOCU_Common::get_offer_post_type_slug(), WFOB_Common::get_bump_post_type_slug() ),
						'post_status'    => 'any',
						'posts_per_page' => - 1,
					);
				}
				$query_result = new WP_Query( $args );
				$posts        = array();

				if ( $query_result->have_posts() ) {
					$upsell_ids = array();
					$bump_ids   = array();

					foreach ( $query_result->posts as $p ) {
						if ( $p->post_type === 'wfocu_offer' ) {
							$upsell_id    = get_post_meta( $p->ID, '_funnel_id', true );
							$funnel_id    = get_post_meta( $upsell_id, '_bwf_in_funnel', true );
							$upsell_ids[] = $p->ID;
						} else {
							$funnel_id  = get_post_meta( $p->ID, '_bwf_in_funnel', true );
							$bump_ids[] = $p->ID;
						}

						$funnel_name = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$wpdb->prefix}bwf_funnels WHERE id = %d", $funnel_id ) );

						$obj                  = new stdClass();
						$obj->id              = $p->ID;
						$obj->title           = $p->post_title;
						$obj->fid             = $funnel_id;
						$obj->funnel_name     = $funnel_name;
						$obj->type            = $p->post_type;
						$obj->conversion      = 0;
						$obj->revenue         = 0;
						$obj->views           = 0;
						$obj->conversion_rate = 0;
						$posts[ $p->ID ]      = $obj;
					}

					if ( ! empty( $upsell_ids ) && class_exists( 'WFOCU_Contacts_Analytics' ) ) {
						$upsell_obj   = WFOCU_Contacts_Analytics::get_instance();
						$upsells_data = $upsell_obj->get_top_upsells( $start_date, $end_date, '' );

						if ( ! isset( $upsells_data['db_error'] ) && is_array( $upsells_data ) ) {
							foreach ( $upsells_data as $upsell ) {
								if ( isset( $posts[ $upsell->id ] ) ) {
									$posts[ $upsell->id ]->conversion      = $upsell->conversion;
									$posts[ $upsell->id ]->revenue         = $upsell->revenue;
									$posts[ $upsell->id ]->views           = $upsell->views;
									$posts[ $upsell->id ]->conversion_rate = $upsell->conversion_rate;
								}
							}
						}
					}

					if ( ! empty( $bump_ids ) && class_exists( 'WFOB_Contacts_Analytics' ) ) {
						$bump_obj   = WFOB_Contacts_Analytics::get_instance();
						$bumps_data = $bump_obj->get_top_bumps( $start_date, $end_date, '' );
						if ( ! isset( $bumps_data['db_error'] ) && is_array( $bumps_data ) ) {

							foreach ( $bumps_data as $bump ) {
								if ( isset( $posts[ $bump->id ] ) ) {
									$posts[ $bump->id ]->conversion      = $bump->conversion;
									$posts[ $bump->id ]->revenue         = $bump->revenue;
									$posts[ $bump->id ]->views           = $bump->views;
									$posts[ $bump->id ]->conversion_rate = $bump->conversion_rate;
								}
							}
						}
					}
					if ( $is_fkcart_exists ) {
						$cart_upsells_data = $this->get_cart_upsells_for_analytics( $start_date, $end_date );
						if ( ! empty( $cart_upsells_data ) ) {
							foreach ( $cart_upsells_data as $cart_upsell ) {
								$obj                  = new stdClass();
								$obj->id              = $cart_upsell['product_id'];
								$obj->title           = $cart_upsell['product_name'];
								$obj->fid             = 0;
								$obj->funnel_name     = '';
								$obj->type            = 'cart_upsells';
								$obj->conversion      = $cart_upsell['conversions'];
								$obj->revenue         = $cart_upsell['revenue'];
								$obj->views           = $cart_upsell['views'];
								$obj->conversion_rate = $cart_upsell['conversion_rate'];

								$posts[ $obj->id ] = $obj;
							}
						}
					}

					$posts = array_values( $posts );
					usort(
						$posts,
						function ( $a, $b ) {
							return $b->revenue <=> $a->revenue;
						}
					);
					$posts = array_slice( $posts, $offset, $limit );
				}

				$count_args = array(
					'post_type'      => array( WFOCU_Common::get_offer_post_type_slug(), WFOB_Common::get_bump_post_type_slug() ),
					'post_status'    => 'any',
					'posts_per_page' => - 1,
				);

				$count_query = new WP_Query( $count_args );
				$total_posts = $count_query->found_posts;
				if ( $is_fkcart_exists ) {
					$cart_upsells_count = $this->get_cart_upsells_count( $start_date, $end_date );

					$total_posts += $cart_upsells_count;
				}
				return rest_ensure_response(
					array(
						'result'  => array(
							'items'       => $posts,
							'total_count' => $total_posts,
						),
						'status'  => true,
						'message' => __( 'All Posts Fetched', 'funnel-builder' ),
					)
				);

			} catch ( Exception | Error $e ) {
				return rest_ensure_response(
					array(
						'result'  => array(
							'items'       => array(),
							'total_count' => 0,
						),
						'status'  => false,
						'message' => esc_html( $e->getMessage() ),
					)
				);
			}
		}

		/**
		 * Get cart upsells data for analytics
		 */
		private function get_cart_upsells_for_analytics( $start_date = '', $end_date = '' ) {
			global $wpdb;

			if ( $start_date && $end_date ) {
				// Date filters are bound via %s. The subquery clause is emitted earlier in the
				// SQL text than the main WHERE, so its bound values are listed first.
				// The LIKE pattern is a CONCAT over the cp.product_id column (no user input) and
				// therefore cannot be parameterized; scope the two LIKE sniffs to this query only.
				// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery, WordPress.DB.PreparedSQLPlaceholders.LikeWithoutWildcards
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT
                cp.product_id,
                p.post_title as product_name,
                SUM(cp.price) as revenue,
                COUNT(DISTINCT cp.oid) as conversions,
                (SELECT COUNT(DISTINCT c2.oid)
                 FROM {$wpdb->prefix}fk_cart c2
                 WHERE c2.upsells_viewed IS NOT NULL
                 AND c2.upsells_viewed != ''
                 AND c2.upsells_viewed NOT LIKE '[]'
                 AND c2.upsells_viewed LIKE CONCAT('%%\"', cp.product_id, '\"%%')
                 AND c2.date_created BETWEEN %s AND %s
                ) as times_offered
            FROM {$wpdb->prefix}fk_cart_products cp
            JOIN {$wpdb->prefix}fk_cart c ON cp.oid = c.oid
            LEFT JOIN {$wpdb->posts} p ON cp.product_id = p.ID
            WHERE cp.type = 1 AND c.date_created BETWEEN %s AND %s
            GROUP BY cp.product_id, p.post_title
            ORDER BY revenue DESC",
						$start_date,
						$end_date,
						$start_date,
						$end_date
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery, WordPress.DB.PreparedSQLPlaceholders.LikeWithoutWildcards
			} else {
				$results = $wpdb->get_results(
					"SELECT
                cp.product_id,
                p.post_title as product_name,
                SUM(cp.price) as revenue,
                COUNT(DISTINCT cp.oid) as conversions,
                (SELECT COUNT(DISTINCT c2.oid)
                 FROM {$wpdb->prefix}fk_cart c2
                 WHERE c2.upsells_viewed IS NOT NULL
                 AND c2.upsells_viewed != ''
                 AND c2.upsells_viewed NOT LIKE '[]'
                 AND c2.upsells_viewed LIKE CONCAT('%\"', cp.product_id, '\"%')
                ) as times_offered
            FROM {$wpdb->prefix}fk_cart_products cp
            JOIN {$wpdb->prefix}fk_cart c ON cp.oid = c.oid
            JOIN {$wpdb->posts} p ON cp.product_id = p.ID
            WHERE cp.type = 1
            GROUP BY cp.product_id, p.post_title
            ORDER BY revenue DESC"
				);
			}

			$cart_upsells = array();

			foreach ( $results as $row ) {
				$product_name = $row->product_name;

				if ( empty( $product_name ) ) {
					$product      = wc_get_product( $row->product_id );
					$product_name = $product ? $product->get_name() : 'Unknown Product';
				}

				$conversion_rate = $row->times_offered > 0 ?
					round( ( $row->conversions / $row->times_offered ) * 100, 2 ) : 0;

				$cart_upsells[] = array(
					'product_id'      => $row->product_id,
					'product_name'    => $product_name,
					'revenue'         => (float) $row->revenue,
					'conversions'     => (int) $row->conversions,
					'views'           => (int) $row->times_offered,
					'conversion_rate' => (float) $conversion_rate,
				);
			}

			return $cart_upsells;
		}
		/**
		 * Get cart upsells count
		 */
		private function get_cart_upsells_count( $start_date = '', $end_date = '' ) {
			global $wpdb;

			if ( $start_date && $end_date ) {
				$query = $wpdb->prepare(
					"
            SELECT COUNT(DISTINCT cp.product_id) as total
            FROM {$wpdb->prefix}fk_cart_products cp
            JOIN {$wpdb->prefix}fk_cart c ON cp.oid = c.oid
            JOIN {$wpdb->posts} p ON cp.product_id = p.ID  -- ADDED: Excludes deleted products
            WHERE cp.type = 1 AND c.date_created BETWEEN %s AND %s
        ",
					$start_date,
					$end_date
				);
			} else {
				$query = "
            SELECT COUNT(DISTINCT cp.product_id) as total
            FROM {$wpdb->prefix}fk_cart_products cp
            JOIN {$wpdb->prefix}fk_cart c ON cp.oid = c.oid
            JOIN {$wpdb->posts} p ON cp.product_id = p.ID  -- ADDED: Excludes deleted products
            WHERE cp.type = 1
        ";
			}

			return (int) $wpdb->get_var( $query );//phpcs:ignore
		}

		public function get_top_funnels( $request ) {

			if ( isset( $request['overall'] ) ) {
				$start_date = '';
				$end_date   = '';
			} else {
				$start_date = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
				$end_date   = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : self::default_date()->format( self::$sql_datetime_format );
			}
			$limit = isset( $request['top_funnels_limit'] ) ? $request['top_funnels_limit'] : ( isset( $request['limit'] ) ? $request['limit'] : 5 );

			global $wpdb;
			$sales_funnels = array();
			$lead_funnels  = array();
			$all_funnels   = array(
				'sales' => array(),
				'lead'  => array(),
			);

			/**
			 * get all sales funnel data
			 */

			$funnel_count = $wpdb->prepare( 'SELECT COUNT( id) AS total_count FROM ' . $wpdb->prefix . 'bwf_funnels WHERE steps LIKE %s', '%wc_%' );
			$funnel_count = $wpdb->get_var( $funnel_count );//phpcs:ignore

			if ( ! empty( $funnel_count ) && absint( $funnel_count ) > 0 ) {
				/**
				 * get all funnel conversion from conversion table order by top conversion table
				 */
				if ( '' !== $start_date && '' !== $end_date ) {
					$get_funnels = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT funnel.id as fid, funnel.title as title, SUM( COALESCE(conv.value, 0) ) as total, 0 as views, COUNT(conv.ID) as `conversion`, 0 as conversion_rate FROM ' . $wpdb->prefix . 'bwf_funnels AS funnel
				LEFT JOIN ' . $wpdb->prefix . 'bwf_conversion_tracking AS conv ON funnel.id = conv.funnel_id  AND conv.type = 2 AND conv.timestamp >= %s AND conv.timestamp < %s WHERE 1=1 AND funnel.steps LIKE %s GROUP BY funnel.id ORDER BY SUM( conv.value ) DESC LIMIT 0, %d',
							$start_date,
							$end_date,
							'%wc_%',
							$limit
						),
						ARRAY_A
					);
				} else {
					$get_funnels = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT funnel.id as fid, funnel.title as title, SUM( COALESCE(conv.value, 0) ) as total, 0 as views, COUNT(conv.ID) as `conversion`, 0 as conversion_rate FROM ' . $wpdb->prefix . 'bwf_funnels AS funnel
				LEFT JOIN ' . $wpdb->prefix . 'bwf_conversion_tracking AS conv ON funnel.id = conv.funnel_id  AND conv.type = 2 WHERE 1=1 AND funnel.steps LIKE %s GROUP BY funnel.id ORDER BY SUM( conv.value ) DESC LIMIT 0, %d',
							'%wc_%',
							$limit
						),
						ARRAY_A
					);
				}
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( false === $db_error['db_error'] ) {
						$sales_funnels = $get_funnels;
					}
				}

				/**
				 * calculate total funnels revenue
				 */
				if ( is_array( $sales_funnels ) && count( $sales_funnels ) > 0 ) {

					/**
					 *  get funnel unique views and conversion rate
					 */
					$sales_funnels = $this->get_funnel_views_data( $sales_funnels, $start_date, $end_date );

					$all_funnels['sales'] = $sales_funnels;
				}
			}

			/**
			 * get all lead funnel data
			 */
			$lead_count = $wpdb->prepare( 'SELECT COUNT( id) AS total_count FROM ' . $wpdb->prefix . 'bwf_funnels WHERE steps NOT LIKE %s', '%wc_%' );
			$lead_count = $wpdb->get_var( $lead_count );//phpcs:ignore

			if ( ! empty( $lead_count ) && absint( $lead_count ) > 0 ) {

				/**
				 * get all funnel conversion from conversion table order by top conversion table
				 */
				if ( '' !== $start_date && '' !== $end_date ) {
					$l_funnels = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT funnel.id as fid, funnel.title as title, 0 as total, 0 as views, COUNT(conv.id) as `conversion`, 0 as conversion_rate FROM ' . $wpdb->prefix . 'bwf_funnels AS funnel
				LEFT JOIN ' . $wpdb->prefix . 'bwf_conversion_tracking AS conv ON funnel.id = conv.funnel_id AND conv.type = 1 AND conv.timestamp >= %s AND conv.timestamp < %s WHERE 1=1 AND funnel.steps NOT LIKE %s GROUP BY funnel.id ORDER BY COUNT(conv.id) DESC LIMIT 0, %d',
							$start_date,
							$end_date,
							'%wc_%',
							$limit
						),
						ARRAY_A
					);
				} else {
					$l_funnels = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT funnel.id as fid, funnel.title as title, 0 as total, 0 as views, COUNT(conv.id) as `conversion`, 0 as conversion_rate FROM ' . $wpdb->prefix . 'bwf_funnels AS funnel
				LEFT JOIN ' . $wpdb->prefix . 'bwf_conversion_tracking AS conv ON funnel.id = conv.funnel_id AND conv.type = 1 WHERE 1=1 AND funnel.steps NOT LIKE %s GROUP BY funnel.id ORDER BY COUNT(conv.id) DESC LIMIT 0, %d',
							'%wc_%',
							$limit
						),
						ARRAY_A
					);
				}
				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( false === $db_error['db_error'] ) {
						$lead_funnels = $l_funnels;
					}
				}

				/**
				 * get all funnels by optin entries if deleted funnel exists
				 */
				if ( is_array( $lead_funnels ) && count( $lead_funnels ) > 0 ) {

					/**
					 *  get funnel unique views and conversion rate
					 */
					$lead_funnels = $this->get_funnel_views_data( $lead_funnels, $start_date, $end_date );

					$all_funnels['lead'] = $lead_funnels;
				}
			}

			return $all_funnels;
		}

		public function get_funnel_views_data( $funnels, $start_date, $end_date ) {
			global $wpdb;

			$ids = array_values( array_unique( array_map( 'absint', wp_list_pluck( $funnels, 'fid' ) ) ) );

			/**
			 * Empty unless something records views; the mapping below then falls
			 * to its else branch and every funnel reports zero.
			 */
			$report_data = apply_filters(
				'wffn_report_views_rows',
				array(),
				array(
					'context'    => 'dashboard_funnels',
					'object_ids' => $ids,
					'start_date' => $start_date,
					'end_date'   => $end_date,
				)
			);
			if ( is_array( $report_data ) && count( $report_data ) > 0 ) {
				/**
				 * prepare data for sales funnels and add views and conversion
				 */
				$funnels = array_map(
					function ( $item ) use ( $report_data ) {
						$search_view = array_search( intval( $item['fid'] ), array_map( 'intval', wp_list_pluck( $report_data, 'fid' ) ), true );
						if ( false !== $search_view && isset( $report_data[ $search_view ]['views'] ) && absint( $report_data[ $search_view ]['views'] ) > 0 ) {
								$item['views']           = absint( $report_data[ $search_view ]['views'] );
								$item['conversion_rate'] = $this->get_percentage( absint( $item['views'] ), $item['conversion'] );
						} else {
							$item['views']           = '0';
							$item['conversion']      = '0';
							$item['conversion_rate'] = '0';
						}

						return $item;
					},
					$funnels
				);
			}

			return $funnels;
		}

		public function get_timeline_funnels() {
			global $wpdb;
			$get_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT conv.*, coalesce(contact.f_name, '') as f_name, coalesce(contact.l_name, '') as l_name
					 FROM {$wpdb->prefix}bwf_conversion_tracking as conv
					 LEFT JOIN {$wpdb->prefix}bwf_contact AS contact ON contact.id = conv.contact_id
					 WHERE contact.id != '' AND conv.funnel_id != '' AND ( conv.type != '' AND conv.type IS NOT NULL )
					 ORDER BY conv.timestamp DESC
					 LIMIT %d",
					20
				),
				ARRAY_A
			);
			$steps       = array();
			if ( is_array( $get_results ) && count( $get_results ) > 0 ) {
				foreach ( $get_results as $result ) {
					if ( 20 === count( $steps ) ) {
						break;
					}
					$step = array(
						'fid'             => $result['funnel_id'],
						'cid'             => $result['contact_id'],
						'f_name'          => $result['f_name'],
						'l_name'          => $result['l_name'],
						'step_id'         => 0,
						'post_title'      => '',
						'order_id'        => $result['source'],
						'order_number'    => $result['order_number'] ?? '',
						'id'              => $result['step_id'],
						'tot'             => $result['value'],
						'type'            => '',
						'date'            => $result['timestamp'],
						'order_edit_link' => '',
						'edit_link'       => '',

					);
					if ( 2 === absint( $result['type'] ) ) {
						if ( empty( $result['checkout_total'] ) && ! empty( $result['offer_accepted'] ) && '[]' !== $result['offer_accepted'] ) {
							$steps = $this->maybe_add_offer( $steps, $result, $step );
							continue;
						}

						$step['type'] = 'aero';
						$step['tot']  = $result['checkout_total'];
						$steps[]      = $step;
						$steps        = $this->maybe_add_offer( $steps, $result, $step );
						$steps        = $this->maybe_add_bump( $steps, $result, $step );

					} elseif ( 1 === absint( $result['type'] ) ) {
						$step['tot']  = '';
						$step['type'] = 'optin';
						$steps[]      = $step;
					}
				}
			}
			$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
			if ( true === $db_error['db_error'] ) {
				return rest_ensure_response( $db_error );
			}

			if ( ! is_array( $steps ) || count( $steps ) === 0 ) {
				return rest_ensure_response( array() );
			}

			foreach ( $steps as &$step ) {
				if ( isset( $step['id'] ) && isset( $step['type'] ) ) {
					$step['edit_link'] = WFFN_Common::get_step_edit_link( $step['id'], $step['type'], $step['fid'], true );
				}
				$step['post_title'] = ( isset( $step['id'] ) && absint( $step['id'] ) > 0 ) ? get_the_title( $step['id'] ) : '';

				if ( isset( $step['order_id'] ) ) {
					if ( wffn_is_wc_active() ) {
						$order = wc_get_order( $step['order_id'] );
						if ( $order instanceof WC_Order ) {
							if ( absint( $step['fid'] ) === WFFN_Common::get_store_checkout_id() ) {
								$step['order_edit_link'] = WFFN_Common::get_store_checkout_edit_link( '/orders' );
							} else {
								$step['order_edit_link'] = WFFN_Common::get_funnel_edit_link( $step['fid'], '/orders' );
							}
						} else {
							$step['order_edit_link'] = '';
						}
					} else {
						$step['order_edit_link'] = '';
					}
				}
			}

			return rest_ensure_response( $steps );
		}

		public function maybe_add_offer( $steps, $result, $step ) {
			if ( ! empty( $result['offer_accepted'] ) && '[]' !== $result['offer_accepted'] ) {
				$accepted_offer = json_decode( $result['offer_accepted'], true );
				if ( is_array( $accepted_offer ) && count( $accepted_offer ) > 0 ) {
					foreach ( $accepted_offer as $offer_id ) {
						$step['tot']  = $this->get_single_offer_value( $offer_id, $result['source'] );
						$step['type'] = 'upsell';
						$step['id']   = $offer_id;
						$steps[]      = $step;
					}
				}
			}

			return $steps;
		}

		public function maybe_add_bump( $steps, $result, $step ) {
			if ( ! empty( $result['bump_accepted'] ) && '[]' !== $result['bump_accepted'] ) {
				$accepted_bump = json_decode( $result['bump_accepted'], true );
				if ( is_array( $accepted_bump ) && count( $accepted_bump ) > 0 ) {

					foreach ( $accepted_bump as $bump_id ) {

						$step['tot']  = $this->get_single_bump_value( $bump_id, $result['source'] );
						$step['type'] = 'bump';
						$step['id']   = $bump_id;
						$steps[]      = $step;
					}
				}
			}

			return $steps;
		}


		public function get_single_offer_value( $offer_id, $order_id ) {
			global $wpdb;
			if ( ! class_exists( 'WFOCU_Core' ) ) {
				return 0;
			}
			$get_revenue = $wpdb->get_var( $wpdb->prepare( "SELECT CONVERT( stats.value USING utf8) as 'value' FROM " . $wpdb->prefix . 'wfocu_session AS sess LEFT JOIN ' . $wpdb->prefix . 'wfocu_event AS stats ON stats.sess_id=sess.id where stats.object_id = %d AND stats.action_type_id = %d AND sess.order_id = %s', absint( $offer_id ), 4, $order_id ) );

			if ( ! empty( $get_revenue ) ) {
				return $get_revenue;
			}

			return 0;
		}

		public function get_single_bump_value( $bump_id, $order_id ) {
			global $wpdb;
			if ( ! class_exists( 'WFOB_Core' ) ) {
				return 0;
			}

			$get_revenue = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT CAST(bump.total AS DECIMAL(10,2)) as 'value'
				FROM " . $wpdb->prefix . 'wfob_stats AS bump
				WHERE bump.oid = %d
				AND bump.bid = %d
				AND bump.converted = 1',
					$order_id,
					$bump_id
				)
			);

			if ( ! empty( $get_revenue ) ) {
				return $get_revenue;
			}

			return 0;
		}

		public function get_total_orders( $funnel_id, $start_date, $end_date, $is_interval = '', $int_request = '' ) {
			global $wpdb;

			$funnel_id      = empty( $funnel_id ) ? 0 : (int) $funnel_id;
			$table          = $wpdb->prefix . 'bwf_conversion_tracking';
			$date_col       = 'tracking.timestamp';
			$interval_query = '';
			$group_by       = '';
			$intervals      = array();
			$funnel_id      = absint( $funnel_id );
			$funnel_query   = ( 0 === $funnel_id ) ? $wpdb->prepare( ' AND tracking.funnel_id != %d ', $funnel_id ) : $wpdb->prepare( ' AND tracking.funnel_id = %d ', $funnel_id );

			if ( 'interval' === $is_interval ) {
				$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
				$interval_query = $get_interval['interval_query'];
				$interval_group = $get_interval['interval_group'];
				$group_by       = ' GROUP BY ' . $interval_group;

			}

			$date = ( '' !== $start_date && '' !== $end_date ) ? $wpdb->prepare( ' AND tracking.timestamp >= %s AND tracking.timestamp < %s ', $start_date, $end_date ) : '';

			$total_orders = $wpdb->get_results( 'SELECT count(DISTINCT tracking.source) as total_orders ' . $interval_query . '  FROM `' . $table . '` as tracking JOIN `' . $wpdb->prefix . 'bwf_contact` as cust ON cust.id=tracking.contact_id WHERE 1=1 AND tracking.type=2 ' . $date . $funnel_query . $group_by . ' ORDER BY tracking.id ASC ', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $date and $funnel_query are $wpdb->prepare() fragments; $interval_query/$group_by are whitelisted (carry DATE_FORMAT %Y specifiers that must not pass through prepare); $table is $wpdb->prefix + literal.
			if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
				$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore

					return 0;
				}
			}

			if ( is_array( $total_orders ) && count( $total_orders ) > 0 ) {
				if ( 'interval' === $is_interval ) {
					$intervals = ( is_array( $total_orders ) && count( $total_orders ) > 0 ) ? $total_orders : array();
				} else {
					$total_orders = isset( $total_orders[0]['total_orders'] ) ? absint( $total_orders[0]['total_orders'] ) : 0;
				}
			}

			return ( 'interval' === $is_interval ) ? $intervals : $total_orders;
		}


		public function get_total_revenue( $funnel_id, $start_date, $end_date, $is_interval = '', $int_request = '' ) {

			/**
			 * get revenue
			 */ global $wpdb;
			$total_revenue_aero    = array();
			$total_revenue_bump    = array();
			$total_revenue_upsells = array();

			/**
			 * get revenue
			 */
			$table          = $wpdb->prefix . 'bwf_conversion_tracking';
			$date_col       = 'conv.timestamp';
			$interval_query = '';
			$group_by       = '';
			$funnel_id      = absint( $funnel_id );
			$funnel_query   = ( 0 === $funnel_id ) ? $wpdb->prepare( ' AND conv.funnel_id != %d ', $funnel_id ) : $wpdb->prepare( ' AND conv.funnel_id = %d ', $funnel_id );

			if ( 'interval' === $is_interval ) {
				$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
				$interval_query = $get_interval['interval_query'];
				$interval_group = $get_interval['interval_group'];
				$group_by       = ' GROUP BY ' . $interval_group;

			}

			$date = ( '' !== $start_date && '' !== $end_date ) ? $wpdb->prepare( ' AND conv.timestamp >= %s AND conv.timestamp < %s ', $start_date, $end_date ) : '';

			if ( class_exists( 'WFACP_Core' ) ) {
				$query              = 'SELECT SUM(conv.value) as total, SUM(conv.checkout_total) as sum_aero ' . $interval_query . ' FROM `' . $table . '` as conv WHERE 1=1 ' . $date . $funnel_query . $group_by . ' ORDER BY conv.id ASC';
				$total_revenue_aero = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $date/$funnel_query are prepare() fragments; $interval_query/$group_by are whitelisted (DATE_FORMAT specifiers, not preparable); $table is prefix+literal.

				$count_query     = 'SELECT COUNT(conv.id) AS checkout_orders ' . $interval_query . ' FROM `' . $table . '` as conv WHERE 1=1 ' . $date . $funnel_query . ' AND conv.checkout_total != 0 ' . $group_by . ' ORDER BY conv.id ASC';
				$checkout_orders = $wpdb->get_results( $count_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $date/$funnel_query are prepare() fragments; $interval_query/$group_by are whitelisted (DATE_FORMAT specifiers, not preparable); $table is prefix+literal.

				if ( ! empty( $total_revenue_aero ) && ! empty( $checkout_orders ) ) {
					if ( 'interval' === $is_interval ) {
						foreach ( $total_revenue_aero as $key => $value ) {
							if ( isset( $checkout_orders[ $key ] ) ) {
								$total_revenue_aero[ $key ]['checkout_orders'] = $checkout_orders[ $key ]['checkout_orders'];
							}
						}
					} else {
						$total_revenue_aero[0]['checkout_orders'] = $checkout_orders[0]['checkout_orders'];
					}
				}

				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						$total_revenue_aero = array();
						WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore
					}
				}
			}

			if ( class_exists( 'WFOB_Core' ) ) {
				$query              = 'SELECT SUM(conv.bump_total) as sum_bump ' . $interval_query . ' FROM `' . $table . '` as conv WHERE 1=1 ' . $date . $funnel_query . $group_by . ' ORDER BY conv.id ASC';
				$total_revenue_bump = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $date/$funnel_query are prepare() fragments; $interval_query/$group_by are whitelisted (DATE_FORMAT specifiers, not preparable); $table is prefix+literal.

				$count_query = 'SELECT COUNT(conv.id) AS bump_orders ' . $interval_query . ' FROM `' . $table . '` as conv WHERE 1=1 ' . $date . $funnel_query . " AND conv.bump_accepted != '' " . $group_by . ' ORDER BY conv.id ASC';
				$bump_orders = $wpdb->get_results( $count_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $date/$funnel_query are prepare() fragments; $interval_query/$group_by are whitelisted (DATE_FORMAT specifiers, not preparable); $table is prefix+literal.

				if ( ! empty( $total_revenue_bump ) && ! empty( $bump_orders ) ) {
					if ( 'interval' === $is_interval ) {
						foreach ( $total_revenue_bump as $key => $value ) {
							if ( isset( $bump_orders[ $key ] ) ) {
								$total_revenue_bump[ $key ]['bump_orders'] = $bump_orders[ $key ]['bump_orders'];
							}
						}
					} else {
						$total_revenue_bump[0]['bump_orders'] = $bump_orders[0]['bump_orders'];
					}
				}

				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						$total_revenue_bump = array();
						WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore
					}
				}
			}

			if ( class_exists( 'WFOCU_Core' ) ) {
				$query                 = 'SELECT SUM(conv.offer_total) as sum_upsells ' . $interval_query . ' FROM `' . $table . '` as conv WHERE 1=1 ' . $date . $funnel_query . $group_by . ' ORDER BY conv.id ASC';
				$total_revenue_upsells = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $date/$funnel_query are prepare() fragments; $interval_query/$group_by are whitelisted (DATE_FORMAT specifiers, not preparable); $table is prefix+literal.

				$count_query   = 'SELECT COUNT(conv.id) AS upsell_orders ' . $interval_query . ' FROM `' . $table . '` as conv WHERE 1=1 ' . $date . $funnel_query . " AND conv.offer_accepted != '' " . $group_by . ' ORDER BY conv.id ASC';
				$upsell_orders = $wpdb->get_results( $count_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $date/$funnel_query are prepare() fragments; $interval_query/$group_by are whitelisted (DATE_FORMAT specifiers, not preparable); $table is prefix+literal.

				if ( ! empty( $total_revenue_upsells ) && ! empty( $upsell_orders ) ) {
					if ( 'interval' === $is_interval ) {
						foreach ( $total_revenue_upsells as $key => $value ) {
							if ( isset( $upsell_orders[ $key ] ) ) {
								$total_revenue_upsells[ $key ]['upsell_orders'] = $upsell_orders[ $key ]['upsell_orders'];
							}
						}
					} else {
						$total_revenue_upsells[0]['upsell_orders'] = $upsell_orders[0]['upsell_orders'];
					}
				}

				if ( method_exists( 'WFFN_Common', 'maybe_wpdb_error' ) ) {
					$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						$total_revenue_upsells = array();
						WFFN_Core()->logger->log( 'failed fetch data #' . print_r( $db_error, true ), 'wffn-failed-actions', true ); // phpcs:ignore
					}
				}
			}

			return array(
				'aero'   => $total_revenue_aero,
				'bump'   => $total_revenue_bump,
				'upsell' => $total_revenue_upsells,
			);
		}


		/**
		 * @param $funnel_id
		 * @param $start_date
		 * @param $end_date
		 * @param $is_interval
		 * @param $int_request
		 *
		 * @return array|object|stdClass[]
		 */
		public function get_total_contacts( $funnel_id, $start_date, $end_date, $is_interval = '', $int_request = '' ) {
			global $wpdb;
			$table          = $wpdb->prefix . 'bwf_conversion_tracking';
			$date_col       = 'timestamp';
			$interval_query = '';
			$group_by       = '';
			$funnel_id      = absint( $funnel_id );
			$funnel_query   = ( 0 === $funnel_id ) ? ' AND funnel_id != ' . $funnel_id . ' ' : ' AND funnel_id = ' . $funnel_id . ' ';

			if ( 'interval' === $is_interval ) {
				$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
				$interval_query = $get_interval['interval_query'];
				$interval_group = $get_interval['interval_group'];
				$group_by       = ' GROUP BY ' . $interval_group;
			}

			$date = ( '' !== $start_date && '' !== $end_date ) ? " AND `timestamp` >= '" . esc_sql( $start_date ) . "' AND `timestamp` < '" . esc_sql( $end_date ) . "' " : '';

			$query        = 'SELECT COUNT( DISTINCT contact_id ) as contacts ' . $interval_query . ' FROM `' . $table . '` WHERE 1=1 ' . $date . ' ' . $funnel_query . ' ' . $group_by;
			$get_contacts = $wpdb->get_results( $query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( is_array( $get_contacts ) && count( $get_contacts ) > 0 ) {
				return $get_contacts;
			}

			return array();
		}

		public function get_all_source_data( $request ) {
			$resp = array(
				'data' => array(
					'sales' => array(),
					'lead'  => array(),
				),
			);

			if ( isset( $request['overall'] ) ) {
				$start_date = '';
				$end_date   = '';
			} else {
				$start_date = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
				$end_date   = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : self::default_date()->format( self::$sql_datetime_format );

			}

			$args = array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);

			$conv_data = apply_filters( 'wffn_source_data_by_conversion_query', array(), $args );

			if ( is_array( $conv_data ) && count( $conv_data ) > 0 ) {
				$resp['data']['sales'] = $conv_data['sales'];
				$resp['data']['lead']  = $conv_data['lead'];
			}

			$resp['status'] = true;
			$resp['msg']    = __( 'success', 'funnel-builder' );

			return rest_ensure_response( $resp );
		}

		public function get_stats_collection() {
			$params = array();

			$params['after']  = array(
				'type'              => 'string',
				'format'            => 'date-time',
				'validate_callback' => 'rest_validate_request_arg',
				'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'funnel-builder' ),
			);
			$params['before'] = array(
				'type'              => 'string',
				'format'            => 'date-time',
				'validate_callback' => 'rest_validate_request_arg',
				'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'funnel-builder' ),
			);
			$params['limit']  = array(
				'type'              => 'integer',
				'default'           => 5,
				'validate_callback' => 'rest_validate_request_arg',
				'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'funnel-builder' ),
			);

			return $params;
		}
	}

	WFFN_REST_API_Dashboard_EndPoint::get_instance();
}
