<?php

if ( ! class_exists( 'WFFN_Conversion_Data' ) ) {
	#[\AllowDynamicProperties]
	class WFFN_Conversion_Data {

		/**
		 * @var null
		 */
		private static $ins = null;
		/**
		 * @var string
		 */
		protected $table;
		/**
		 * @var string
		 */
		protected $namespace = 'funnelkit-app';


		protected $cached_Ids_title_mapping = [];

		/**
		 * WFFN_Conversion_Data constructor.
		 */
		public function __construct() {
			$this->table = 'bwf_conversion_tracking';
			add_action( 'rest_api_init', array( $this, 'utm_data_end_points' ), 11 );
			add_action( 'wffn_delete_optin_entries', array( $this, 'delete_optin_entries' ) );
			add_action( 'wffn_delete_funnel_contacts', array( $this, 'wffn_delete_funnel_contacts' ), 10, 2 );
			add_filter( 'wffn_conversion_tracking_data_activity', array( $this, 'get_conversion_tracking_activity_data' ), 10, 3 );
			add_filter( 'wffn_filter_data_conversion_query', array( $this, 'filter_data_conversion_query' ), 10, 4 );
			add_filter( 'wffn_conversion_order_row', array( $this, 'add_journey_to_row' ), 10, 4 );
			 add_filter( 'wffn_conversion_optin_row', array( $this, 'add_journey_to_row' ), 10, 4 );
			add_filter( 'rest_request_after_callbacks', array( $this, 'backfill_journey_for_legacy_lite' ), 10, 3 );
			add_filter( 'wffn_conversion_filters_list', array( $this, 'maybe_drop_journey_filter_rule' ), 20, 1 );
			add_filter( 'wffn_dashboard_top_campaigns', array( $this, 'get_top_campaigns' ), 10, 2 );
			add_filter( 'wffn_source_data_by_conversion_query', array( $this, 'get_source_data' ), 10, 2 );
			add_filter( 'wffn_utm_campaign_campaigns', array( $this, 'get_global_utm_campaigns' ), 10, 2 );
		}

		/**
		 * @return WFFN_Conversion_Data|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Retrieves UTM campaign metrics, including conversions and revenue, based on filters.
		 *
		 * @param WP_REST_Request $request The request object containing pagination, search, and date filters.
		 *
		 * @return array UTM campaign data and pagination information.
		 */
		public function get_global_utm_campaigns( $request ) {
			global $wpdb;
			$page_no     = max( 1, (int) ( $request['page_no'] ?? 1 ) );
			$limit       = max( 1, (int) ( $request['limit'] ?? 25 ) );
			$offset      = ( $page_no - 1 ) * $limit;
			$search_term = $request['s'] ?? '';

			$filters = is_string( $request['filters'] ?? null ) ? json_decode( $request['filters'], true ) : ( $request['filters'] ?? array() );
			if ( isset( $request['funnel_id'] ) && $request['funnel_id'] > 0 ) {
				array_push(
					$filters,
					array(
						'filter' => 'funnels',
						'rule'   => '',
						'data'   => array(
							array(
								'id'    => $request['funnel_id'],
								'label' => '',
							),
						),
					)
				);
			}
			[ $filter_conditions, $funnel_ids, $conversions_filter, $date_filter ] = $this->parse_filters( $filters );

			$sales_result = $wpdb->get_results( $this->generate_sales_query( $filter_conditions, $funnel_ids, $date_filter ), ARRAY_A );
			$lead_result  = $wpdb->get_results( $this->generate_lead_query( $filter_conditions, $funnel_ids, $date_filter ), ARRAY_A );
			$data         = $this->merge_results( $sales_result, $lead_result );

			$filtered_data = $this->apply_filters( $data, $search_term, $conversions_filter );

			$total_count = count( $filtered_data );
			$offset      = $offset >= $total_count ? 0 : $offset;

			$paginated_data = array_slice( array_values( $filtered_data ), $offset, $limit );

			return array(
				'records'     => $paginated_data,
				'total_count' => $total_count,
			);
		}

		/**
		 * Parses filters to extract conditions, funnel IDs, and conversion filter.
		 *
		 * @param array $filters Filters from the request.
		 *
		 * @return array Parsed filter conditions, funnel IDs, and conversion type.
		 */
		protected function parse_filters( array $filters ) {
			$filter_conditions  = $funnel_ids = array();
			$conversions_filter = null;
			$date_filter        = array();
			foreach ( $filters as $filter ) {
				if ( $filter['filter'] === 'conversions' ) {
					$conversions_filter = $filter['data'];
				} elseif ( $filter['filter'] === 'funnels' && is_array( $filter['data'] ) ) {
					$funnel_ids = array_column( $filter['data'], 'id' );
				} elseif ( $filter['filter'] === 'period' && isset( $filter['data']['after'], $filter['data']['before'] ) ) {
					$date_filter = array(
						'after'  => $filter['data']['after'],
						'before' => $filter['data']['before'],
					);
				} elseif ( ! empty( $filter['data'] ) && is_array( $filter['data'] ) ) {
					$filter_conditions[ $filter['filter'] ] = array_column( $filter['data'], 'id' );
				}
			}

			return array( $filter_conditions, $funnel_ids, $conversions_filter, $date_filter );
		}

		/**
		 * Filters data based on search term and conversion type.
		 *
		 * @param array       $data Data to filter.
		 * @param string      $search_term Term to search in the data.
		 * @param string|null $conversions_filter Conversion type filter ('orders' or 'optins').
		 *
		 * @return array Filtered data.
		 */
		protected function apply_filters( array $data, $search_term, $conversions_filter ) {
			return array_filter(
				$data,
				function ( $item ) use ( $search_term, $conversions_filter ) {
					$matches_search = empty( $search_term ) || stripos( $item['utm_campaign'], $search_term ) !== false || stripos( $item['utm_source'], $search_term ) !== false || stripos( $item['utm_medium'], $search_term ) !== false || stripos( $item['utm_content'], $search_term ) !== false || stripos( $item['utm_term'], $search_term ) !== false || stripos( $item['orders'], $search_term ) !== false || stripos( $item['optins'], $search_term ) !== false || stripos( $item['revenue'], $search_term ) !== false;

					if ( $conversions_filter === 'orders' ) {
						return $matches_search && $item['orders'] !== '-';
					} elseif ( $conversions_filter === 'optins' ) {
						return $matches_search && $item['optins'] !== '-';
					}

					return $matches_search;
				}
			);
		}

		/**
		 * Constructs the SQL query for fetching sales data related to UTM campaigns.
		 *
		 * @param array $filter_conditions Associative array of UTM filters to apply to the query.
		 * @param array $funnel_ids Array of funnel IDs to filter sales results.
		 *
		 * @return string The complete sales_query SQL query for retrieving sales data.
		 */
		protected function generate_sales_query( $filter_conditions, $funnel_ids, $date_filter ) {
			global $wpdb;
			$conv_table = $wpdb->prefix . $this->table;

			$sales_query = WFFN_Common::is_wc_hpos_enabled() ? "SELECT conv.utm_campaign AS campaign, conv.utm_source AS source, conv.utm_medium AS medium, conv.utm_content AS content, conv.utm_term AS term,
				   COUNT(DISTINCT conv.source) AS conversion, ROUND(SUM(order_t.total_amount), 2) AS revenue, conv.timestamp
				   FROM {$conv_table} AS conv
				   LEFT JOIN {$wpdb->prefix}wc_orders AS order_t ON conv.source = order_t.id
				   WHERE conv.type = %d AND conv.utm_campaign != '' AND conv.funnel_id != 0" : "SELECT conv.utm_campaign AS campaign, conv.utm_source AS source, conv.utm_medium AS medium, conv.utm_content AS content, conv.utm_term AS term,
				   COUNT(DISTINCT conv.source) AS conversion, ROUND(SUM(order_t.total_sales), 2) AS revenue, conv.timestamp
				   FROM {$conv_table} AS conv
				   LEFT JOIN {$wpdb->prefix}wc_order_stats AS order_t ON conv.source = order_t.order_id
				   WHERE conv.type = %d AND conv.utm_campaign != '' AND conv.funnel_id != 0";

			$sales_query = $wpdb->prepare( $sales_query, 2 );

			if ( ! empty( $funnel_ids ) ) {
				$funnel_placeholders = implode( ',', array_fill( 0, count( $funnel_ids ), '%d' ) );
				$funnel_ids_sql      = $wpdb->prepare( " AND conv.funnel_id IN ($funnel_placeholders)", ...$funnel_ids );
				$sales_query        .= $funnel_ids_sql;
			}
			if ( ! empty( $date_filter ) ) {
				$sales_query .= $wpdb->prepare( ' AND conv.timestamp BETWEEN %s AND %s', $date_filter['after'], $date_filter['before'] );
			}
			$filter_query = $this->build_filter_query( $filter_conditions, 'conv' );
			$sales_query .= $filter_query;

			$sales_query .= ' GROUP BY conv.utm_campaign, conv.utm_source, conv.utm_medium, conv.utm_content, conv.utm_term';

			return $sales_query;
		}

		/**
		 * Constructs the SQL query for fetching lead data related to UTM campaigns.
		 *
		 * @param array $filter_conditions Associative array of UTM filters to apply to the query.
		 * @param array $funnel_ids Array of funnel IDs to filter lead results.
		 *
		 * @return string The complete SQL lead_query query for retrieving lead data.
		 */
		protected function generate_lead_query( $filter_conditions, $funnel_ids, $date_filter ) {
			global $wpdb;
			$conv_table = $wpdb->prefix . $this->table;

			$lead_query = "SELECT conv.utm_campaign AS campaign, conv.utm_source AS source, conv.utm_medium AS medium, conv.utm_content AS content, conv.utm_term AS term,
						   COUNT(DISTINCT conv.source) AS conversion, conv.timestamp
						   FROM {$conv_table} AS conv
						   WHERE conv.type = %d AND conv.utm_campaign != '' AND conv.funnel_id != 0";

			$lead_query = $wpdb->prepare( $lead_query, 1 );

			if ( ! empty( $funnel_ids ) ) {
				$funnel_placeholders = implode( ',', array_fill( 0, count( $funnel_ids ), '%d' ) );
				$funnel_ids_sql      = $wpdb->prepare( " AND conv.funnel_id IN ($funnel_placeholders)", ...$funnel_ids );
				$lead_query         .= $funnel_ids_sql;
			}
			if ( ! empty( $date_filter ) ) {
				$lead_query .= $wpdb->prepare( ' AND conv.timestamp BETWEEN %s AND %s', $date_filter['after'], $date_filter['before'] );
			}
			$filter_query = $this->build_filter_query( $filter_conditions, 'conv' );
			$lead_query  .= $filter_query;

			$lead_query .= ' GROUP BY conv.utm_campaign, conv.utm_source, conv.utm_medium, conv.utm_content, conv.utm_term';

			return $lead_query;
		}

		/**
		 * Builds a SQL condition string based on UTM filters for use in queries.
		 *
		 * @param array $filter_conditions Associative array of UTM filters (e.g., 'utm_source') with arrays of filter values.
		 *
		 * @return string A SQL string containing UTM filter conditions.
		 */
		protected function build_filter_query( $filter_conditions, $table_alias ) {
			global $wpdb;

			// $filter_type is interpolated as a column identifier (cannot be bound), so restrict it to a fixed allow-list of UTM columns.
			$allowed_columns = array( 'utm_campaign', 'utm_source', 'utm_medium', 'utm_content', 'utm_term' );

			$filter_query = '';
			foreach ( $filter_conditions as $filter_type => $values ) {
				if ( ! in_array( $filter_type, $allowed_columns, true ) || empty( $values ) ) {
					continue;
				}
				$placeholders       = implode( ',', array_fill( 0, count( $values ), '%s' ) );
				$filter_condition   = "$table_alias.$filter_type IN ($placeholders)";
				$prepared_condition = $wpdb->prepare( " AND $filter_condition", ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $filter_type allow-listed above, $table_alias is the internal literal 'conv'; IN-list values bound via %s.
				$filter_query      .= $prepared_condition;
			}

			return $filter_query;
		}

		/**
		 * Merges sales and lead data results to provide combined UTM campaign metrics.
		 *
		 * @param array $sales_result Array of sales data retrieved from the database.
		 * @param array $lead_result Array of lead data retrieved from the database.
		 *
		 * @return array Merged array of sales and lead data with UTM campaign metrics.
		 */
		protected function merge_results( $sales_result, $lead_result ) {
			$data = array();

			foreach ( $sales_result as $sale ) {
				$key          = $sale['campaign'] . '-' . $sale['source'] . '-' . $sale['medium'] . '-' . $sale['content'] . '-' . $sale['term'];
				$data[ $key ] = array(
					'utm_campaign'   => $sale['campaign'],
					'utm_source'     => $sale['source'],
					'utm_medium'     => $sale['medium'],
					'utm_content'    => $sale['content'],
					'utm_term'       => $sale['term'],
					'orders'         => (int) $sale['conversion'],
					'optins'         => '-',
					'revenue'        => $sale['revenue'],
					'timestamp_unix' => strtotime( $sale['timestamp'] ),
				);
			}

			foreach ( $lead_result as $lead ) {
				$key = $lead['campaign'] . '-' . $lead['source'] . '-' . $lead['medium'] . '-' . $lead['content'] . '-' . $lead['term'];
				if ( isset( $data[ $key ] ) ) {
					$data[ $key ]['optins'] = (int) $lead['conversion'];
				} else {
					$data[ $key ] = array(
						'utm_campaign'   => $lead['campaign'],
						'utm_source'     => $lead['source'],
						'utm_medium'     => $lead['medium'],
						'utm_content'    => $lead['content'],
						'utm_term'       => $lead['term'],
						'orders'         => '-',
						'optins'         => (int) $lead['conversion'],
						'revenue'        => '0.00',
						'timestamp_unix' => strtotime( $lead['timestamp'] ),
					);
				}
			}
			usort(
				$data,
				function ( $a, $b ) {
					return $b['timestamp_unix'] - $a['timestamp_unix'];
				}
			);

			return $data;
		}

		/**
		 * @return void
		 */
		public function utm_data_end_points() {
			register_rest_route(
				$this->namespace,
				'/funnel-utms/',
				array(
					'args'                => array(),
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_utm_data' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				)
			);
			register_rest_route(
				$this->namespace,
				'/funnel-referrers/',
				array(
					'args'                => array(),
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_referrer' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				)
			);
			register_rest_route(
				$this->namespace,
				'/funnel-journey-pages/',
				array(
					'args'                => array(),
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_journey_pages' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				)
			);
		}

		/**
		 * @return bool
		 */
		public function get_read_api_permission_check() {
			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				return false;
			}

			return wffn_rest_api_helpers()->get_api_permission_check( 'funnel', 'read' );
		}

		public function get_utm_data( $request = array(), $is_return = false ) {
			global $wpdb;
			$args = array(
				'funnel_id'   => $request['funnel_id'] ?? 0,
				's'           => $request['s'] ?? '',
				'limit'       => $request['limit'] ?? get_option( 'posts_per_page' ),
				'page_no'     => $request['page_no'] ?? 1,
				'total_count' => $request['total_count'] ?? false,
				'utm_key'     => $request['utm_key'],
			);

			// Whitelist the UTM column: it is used as a raw SQL identifier and cannot be parameterized.
			$allowed_utm_keys = array( 'utm_campaign', 'utm_source', 'utm_medium', 'utm_content', 'utm_term' );
			if ( ! in_array( $args['utm_key'], $allowed_utm_keys, true ) ) {
				return $is_return ? array() : rest_ensure_response( array() );
			}
			$utm_key = $args['utm_key'];

			$limit       = absint( $args['limit'] );
			$page_no     = $args['page_no'];
			$offset      = isset( $args['offset'] ) ? absint( $args['offset'] ) : ( intval( $limit ) * intval( $page_no - 1 ) );
			$where_query = array( '(1=1)' );
			if ( ! empty( $args['s'] ) ) {
				$search_text   = trim( $args['s'] );
				$where_query[] = $wpdb->prepare( '(`' . $utm_key . '` like %s)', '%' . $wpdb->esc_like( $search_text ) . '%' );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			if ( isset( $request['s'] ) && empty( $args['s'] ) ) {
				$limit = 5;
			}

			if ( $args['funnel_id'] > 0 ) {
				$where_query[] = $wpdb->prepare( '(funnel_id = %d)', absint( $args['funnel_id'] ) );
			}
			$where_query[] = '(`' . $utm_key . "` != '')";
			$where_query   = implode( ' AND ', $where_query );
			$limit_str     = ' LIMIT ' . absint( $offset ) . ', ' . absint( $limit );
			$select_type   = 'SELECT `' . $utm_key . '` as utm_label, COUNT(*) as total_count';
			if ( isset( $args['total_count'] ) && 'yes' === $args['total_count'] ) {
				$limit_str = '';
			}
			$conv_table = $wpdb->prefix . $this->table;
			$sql_query  = "{$select_type} from {$conv_table} where {$where_query} GROUP BY " . $utm_key . " {$limit_str}";

			$results = $wpdb->get_results( $sql_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! empty( $results ) ) {
				$results = array_map(
					function ( $item ) {
						return array(
							'id'    => $item['utm_label'],
							'label' => $item['utm_label'],
							'value' => absint( $item['total_count'] ),
						);
					},
					$results
				);
			}

			return $is_return ? $results : rest_ensure_response( $results );
		}

		/**
		 * Typeahead for the "Pages Visited" filter.
		 *
		 * Searches published/private/draft posts by title (or exact id) across the
		 * viewable public post types plus the funnel step types. When funnel_id is
		 * given, that funnel's own steps are listed first.
		 *
		 * @since 3.16.1
		 *
		 * @param array|WP_REST_Request $request   Request args (s, funnel_id, limit).
		 * @param bool                  $is_return When true, return the raw array instead of a REST response.
		 *
		 * @return array|WP_REST_Response List of { id, label, name, value } options.
		 */
		public function get_journey_pages( $request = array(), $is_return = false ) {
			if ( ! class_exists( 'WFFN_Journey' ) || ! WFFN_Journey::is_enabled() ) {
				return $is_return ? array() : rest_ensure_response( array() );
			}

			global $wpdb;

			$search    = isset( $request['s'] ) ? trim( (string) $request['s'] ) : '';
			$funnel_id = isset( $request['funnel_id'] ) ? absint( $request['funnel_id'] ) : 0;
			$limit     = isset( $request['limit'] ) ? absint( $request['limit'] ) : 10;
			if ( $limit <= 0 ) {
				$limit = 10;
			}
			$limit = min( $limit, 50 );

			$post_types = class_exists( 'WFFN_Journey' ) ? WFFN_Journey::page_post_types() : array( 'page', 'post', 'product' );
			$post_types = array_values( array_filter( array_map( 'sanitize_key', (array) $post_types ) ) );
			if ( empty( $post_types ) ) {
				return $is_return ? array() : rest_ensure_response( array() );
			}

			$where   = array();
			$type_ph = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$where[] = $wpdb->prepare( "post_type IN ($type_ph)", $post_types ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built with array_fill, values bound by prepare.
			$where[] = "post_status IN ('publish','private','draft')";

			if ( '' !== $search ) {
				// Draft steps can be tested before publishing, so drafts are included.
				if ( is_numeric( $search ) ) {
					$where[] = $wpdb->prepare( '( post_title LIKE %s OR ID = %d )', '%' . $wpdb->esc_like( $search ) . '%', absint( $search ) );
				} else {
					$where[] = $wpdb->prepare( 'post_title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
				}
			}

			// Funnel-scoped steps first when a funnel is in context.
			$step_ids = array();
			if ( $funnel_id > 0 && class_exists( 'WFFN_Funnel' ) ) {
				$funnel = new WFFN_Funnel( $funnel_id );
				if ( $funnel instanceof WFFN_Funnel && $funnel->get_id() > 0 ) {
					foreach ( (array) $funnel->get_steps() as $step ) {
						$sid = ( is_array( $step ) && isset( $step['id'] ) ) ? absint( $step['id'] ) : 0;
						if ( $sid > 0 ) {
							$step_ids[] = $sid;
						}
					}
				}
			}

			$order_by = 'ORDER BY post_date DESC';
			if ( ! empty( $step_ids ) ) {
				$in_ids   = implode( ',', array_map( 'absint', array_unique( $step_ids ) ) );
				$order_by = "ORDER BY ( ID IN ($in_ids) ) DESC, post_date DESC";
			}

			$where_sql = implode( ' AND ', $where );
			// $where_sql is assembled from $wpdb->prepare() fragments; $order_by only
			// interpolates absint() step ids; LIMIT is absint(). Trusted table identifier.
			$sql     = "SELECT ID, post_title FROM {$wpdb->posts} WHERE {$where_sql} {$order_by} LIMIT " . absint( $limit );
			$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$options = array();
			if ( ! empty( $results ) && is_array( $results ) ) {
				$ids = array_map( 'absint', wp_list_pluck( $results, 'ID' ) );
				foreach ( $ids as $post_id ) {
					$option = class_exists( 'WFFN_Journey' ) ? WFFN_Journey::page_option( $post_id ) : array();
					if ( ! empty( $option ) ) {
						$options[] = $option;
					}
				}
			}

			return $is_return ? $options : rest_ensure_response( $options );
		}

		/**
		 * @param $request
		 *
		 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public function get_referrer( $request ) {
			global $wpdb;
			$case_string = $this->get_conversion_cases_string();

			$response = array(
				'status'  => true,
				'message' => __( 'No Referrer Founds', 'funnel-builder-powerpack' ),
				'records' => array(),
			);
			$args     = array(
				'funnel_id'   => $request['funnel_id'] ?? 0,
				's'           => $request['s'] ?? '',
				'limit'       => $request['limit'] ?? get_option( 'posts_per_page' ),
				'page_no'     => $request['page_no'] ?? 1,
				'total_count' => $request['total_count'] ?? false,
			);

			$limit   = absint( $args['limit'] );
			$page_no = $args['page_no'];
			$offset  = isset( $args['offset'] ) ? absint( $args['offset'] ) : ( intval( $limit ) * intval( $page_no - 1 ) );

			$where_query = array( '(1=1)' );
			if ( ! empty( $args['s'] ) ) {
				$search_text   = trim( $args['s'] );
				$where_query[] = $wpdb->prepare( '(referrer like %s)', '%' . $wpdb->esc_like( $search_text ) . '%' );
			}

			if ( $args['funnel_id'] > 0 ) {
				$where_query[] = $wpdb->prepare( '(funnel_id = %d)', absint( $args['funnel_id'] ) );
			}

			$where_query = implode( ' AND ', $where_query );
			$limit_str   = ' LIMIT ' . absint( $offset ) . ', ' . absint( $limit );
			$select_type = "SELECT $case_string , COUNT(*) as total_count";
			if ( isset( $args['total_count'] ) && 'yes' === $args['total_count'] ) {
				$limit_str = '';
			}
			$conv_table = $wpdb->prefix . $this->table;
			$sql_query  = "{$select_type} from {$conv_table} as tracking where {$where_query} GROUP BY referrer {$limit_str}";
			$results    = $wpdb->get_results( $sql_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( ! empty( $results ) ) {
				$results                 = array_map(
					function ( $item ) {
						return array(
							'id'    => $item['referrers'],
							'label' => $item['referrers'],
							'value' => absint( $item['total_count'] ),
						);
					},
					$results
				);
				$response['status']      = true;
				$response['records']     = $results;
				$response['total_count'] = array_sum( array_column( $results, 'value' ) );

			}

			return rest_ensure_response( $response );
		}

		public function delete_optin_entries( $entry_ids ) {
			$entry_ids = is_array( $entry_ids ) ? implode( ',', $entry_ids ) : $entry_ids;
			if ( empty( $entry_ids ) ) {
				return;
			}
			global $wpdb;
			$conv_table = $wpdb->prefix . $this->table;
			$query      = 'DELETE FROM ' . $conv_table . ' WHERE type = 1 AND source IN (' . esc_sql( $entry_ids ) . ')';
			$wpdb->query( $query );//phpcs:ignore
		}

		public function wffn_delete_funnel_contacts( $c_ids, $funnel_id ) {
			$c_ids        = is_array( $c_ids ) ? implode( ',', $c_ids ) : $c_ids;
			$funnel_query = ( absint( $funnel_id ) > 0 ) ? esc_sql( ' AND fid = ' . $funnel_id . ' ' ) : '';
			if ( empty( $c_ids ) ) {
				return;
			}

			global $wpdb;
			$conv_table = $wpdb->prefix . $this->table;
			$query      = 'DELETE FROM ' . $conv_table . ' WHERE contact_id IN (' . esc_sql( $c_ids ) . ') ' . $funnel_query;
			$wpdb->query( $query );//phpcs:ignore
		}

		/**
		 * @param $tracking_data
		 * @param $contact_id
		 * @param $source_id
		 *
		 * get conversion activity data by contact and source id for single contact screen
		 *
		 * @return array|false[]
		 */
		public function get_conversion_tracking_activity_data( $tracking_data, $contact_id, $source_id = 0 ) {
			global $wpdb;
			$tracking_data               = array();
			$tracking_data['campaign']   = array(
				'source'  => '',
				'medium'  => '',
				'name'    => '',
				'term'    => '',
				'content' => '',
			);
			$tracking_data['conversion'] = array(
				'click_id' => '',
				'referrer' => '',
				'order_id' => '',
				'device'   => '',
				'browser'  => '',
				'country'  => '',
				'time'     => '',
			);

			$tracking_data['overview'] = array(
				'aov'      => 0,
				'bump'     => 0,
				'upsell'   => 0,
				'checkout' => 0,
				'total'    => 0,
			);

			if ( ! empty( $contact_id ) ) {
				$oid_query  = ( $source_id > 0 ) ? esc_sql( ' AND source = ' . $source_id . ' ' ) : '';
				$conv_table = $wpdb->prefix . $this->table;
				$query      = "SELECT *, (CASE WHEN TIMESTAMPDIFF( SECOND, first_click, timestamp ) != 0 THEN TIMESTAMPDIFF( SECOND, first_click, timestamp )/COUNT(id) ELSE 0 END ) as 'seconds' FROM " . $conv_table . ' AS tracking WHERE tracking.contact_id = ' . esc_sql( $contact_id ) . ' ' . $oid_query . ' ORDER BY tracking.timestamp asc';

				$data = $wpdb->get_row( $query );//phpcs:ignore

				$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					return $db_error;
				}

				if ( ! empty( $data ) ) {
					unset( $data->id, $data->contact_id );

					$tracking_data['campaign']   = array(
						'source'  => ! empty( $data->utm_source ) ? $data->utm_source : '',
						'medium'  => ! empty( $data->utm_medium ) ? $data->utm_medium : '',
						'name'    => ! empty( $data->utm_campaign ) ? $data->utm_campaign : '',
						'term'    => ! empty( $data->utm_term ) ? $data->utm_term : '',
						'content' => ! empty( $data->utm_content ) ? $data->utm_content : '',
					);
					$tracking_data['conversion'] = array(
						'funnel_id'     => ! empty( $data->funnel_id ) ? $data->funnel_id : 0,
						'click_id'      => ! empty( $data->click_id ) ? $data->click_id : '',
						'referrer'      => ! empty( $data->referrer ) ? $data->referrer : '',
						'source_id'     => ! empty( $data->source ) ? $data->source : '',
						'device'        => ! empty( $data->device ) ? ucfirst( $data->device ) : '',
						'browser'       => ! empty( $data->browser ) ? $data->browser : '',
						'country'       => ! empty( $data->country ) ? $data->country : '',
						'first_click'   => ( ! empty( $data->first_click ) && '0000-00-00 00:00:00' !== $data->first_click ) ? $data->first_click : '',
						'aov_in_second' => ! empty( $data->seconds ) ? human_time_diff( current_time( 'timestamp' ), current_time( 'timestamp' ) + absint( $data->seconds ) ) : '',

					);
				}
			}

			return $tracking_data;
		}

		/**
		 * @param $data
		 * @param $type
		 * @param $where_query
		 * @param $filters
		 *
		 * update query for get conversion data for showing in optin and order list
		 *
		 * @return array
		 */
		public function filter_data_conversion_query( $data, $type, $where_query, $filters ) {
			global $wpdb;
			$conv_table = $wpdb->prefix . $this->table;

			if ( 'order' === $type ) {
				if ( ! in_array( absint( wffn_conversion_tracking_migrator()->get_upgrade_state() ), array( 3, 4 ), true ) ) {
					$data['join']        = " LEFT JOIN {$conv_table} as tracking ON tracking.source = aero_stats.order_id ";
					$data['case_string'] = ', tracking.*, ' . $this->get_conversion_cases_string();
				} else {
					// Only select the journey column once the migration confirms it exists;
					// the updater option advances to 1.1 only after the ALTER succeeds, so a
					// missing column degrades to no journey rather than a fatal SQL error.
					$journey_col         = version_compare( get_option( 'wffn_conversion_tracking_db_updater', '1.0' ), '1.1', '>=' ) ? 'tracking.journey, ' : '';
					$data['case_string'] = ', tracking.utm_source,tracking.utm_medium,tracking.utm_campaign,tracking.utm_term,tracking.utm_content,tracking.source, tracking.device, ' . $journey_col . $this->get_conversion_cases_string();

				}
			}

			if ( 'optin' === $type ) {
				// Same migration guard as the order branch: only select the journey
				// column once the updater confirms the ALTER succeeded.
				$journey_col         = version_compare( get_option( 'wffn_conversion_tracking_db_updater', '1.0' ), '1.1', '>=' ) ? 'tracking.journey, ' : '';
				$data['case_string'] = ", (CASE WHEN TIMESTAMPDIFF( SECOND, tracking.first_click, tracking.timestamp ) != 0 THEN TIMESTAMPDIFF( SECOND, tracking.first_click, tracking.timestamp ) ELSE 0 END ) as 'convert_time', tracking.utm_source,tracking.utm_medium,tracking.utm_campaign,tracking.utm_term,tracking.utm_content,tracking.source, tracking.device, " . $journey_col . $this->get_conversion_cases_string();
			}

			$data['where_query'] = $this->get_utm_data_query( $where_query, $filters );

			return $data;
		}

		/**
		 * Attach the resolved customer journey to a Conversions row.
		 *
		 * Rides the neutral Lite seam wffn_conversion_order_row. The row always
		 * gets a `journey` list (empty when there is none); export rows also get
		 * the flattened `journey_text`.
		 *
		 * @since 3.16.1
		 *
		 * @param array $data        Assembled row.
		 * @param array $result      Raw DB row (carries `journey` once selected).
		 * @param bool  $return_data Whether the row is being built for export.
		 *
		 * @return array
		 */
		public function add_journey_to_row( $data, $result = array(), $return_data = false, $results = [] ) {
			global $wpdb;
	        if ( ! is_array( $data ) || ! class_exists( 'WFFN_Journey' ) || ! WFFN_Journey::is_enabled() ) {
				return $data;
			}

			$raw = (is_array($result) && isset($result['journey'])) ? $result['journey'] : '';

			$all_found_ids = [];

			if (!empty($results) && is_array($results)) {
				foreach ($results as $res) {
					// Ensure $res is an array and the key 'journey' exists and isn't empty
					if (!is_array($res) || empty($res['journey'])) {
						continue;
					}

					$single_journey = WFFN_Journey::decode($res['journey']);

					// Check if decoded journey exists and contains valid 'j' data
					if (!empty($single_journey['j']) && is_array($single_journey['j'])) {
						$extracted_ids = wp_list_pluck($single_journey['j'], 'i');

						// Filter out any null, empty, or non-numeric IDs
						$clean_ids = array_filter($extracted_ids, fn($id) => !empty($id) && is_numeric($id));

						if (!empty($clean_ids)) {
							$all_found_ids = array_merge($all_found_ids, array_values($clean_ids));
						}
					}
				}
			}

			// Remove duplicate IDs and sanitize to integer values
			$all_found_ids = array_values(array_unique(array_map('intval', $all_found_ids)));
			$titles_by_id = [];

			// Query database ONLY if we have valid IDs to prevent SQL errors
			if (!empty($all_found_ids)) {
				global $wpdb;

				$placeholders = implode(',', array_fill(0, count($all_found_ids), '%d'));


				$uniqye_md5 = md5(wp_json_encode($all_found_ids));
				if(isset($this->cached_Ids_title_mapping[$uniqye_md5])) {
					$titles_by_id = $this->cached_Ids_title_mapping[$uniqye_md5];
				}else{
					$db_results = $wpdb->get_results($wpdb->prepare(
						"SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_status = 'publish'",
						$all_found_ids
					), OBJECT_K);

					if (!empty($db_results) && is_array($db_results)) {
						$titles_by_id = array_map(fn($item) => $item->post_title ?? '', $db_results);
						$this->cached_Ids_title_mapping[$uniqye_md5] = $titles_by_id;
					}
				}

			}

			// Prime the post cache for this row's page ids in a single query so the
			// per-entry get_post() in title resolution stays cheap. Object cache
			// dedups ids repeated across rows (checkout/thank-you pages recur).
			$store = WFFN_Journey::decode( $raw );
			$ids   = array();
			if ( isset( $store['j'] ) && is_array( $store['j'] ) ) {
				foreach ( $store['j'] as $entry ) {
					if ( is_array( $entry ) && isset( $entry['i'] ) && is_numeric( $entry['i'] ) && absint( $entry['i'] ) > 0 ) {
						$ids[] = absint( $entry['i'] );
					}
				}
			}


			$steps           = WFFN_Journey::resolve( $raw, $titles_by_id );
			$data['journey'] = $steps;

			if ( true === $return_data ) {
				$data['journey_text'] = WFFN_Journey::to_text( $steps, apply_filters( 'wffn_export_journey_separator', ' > ' ) );
			}

			return $data;
		}

		/**
		 * Withdraw the Pages Visited rule while journey recording is off.
		 *
		 * Lite ships the rule so it can be offered in a locked state, and it has
		 * no way to read this setting, so the removal belongs here.
		 *
		 * @since 3.16.1
		 *
		 * @param array $filters Filter-rule groups.
		 *
		 * @return array
		 */
		public function maybe_drop_journey_filter_rule( $filters ) {
			if ( ! is_array( $filters ) || ! class_exists( 'WFFN_Journey' ) || WFFN_Journey::is_enabled() ) {
				return $filters;
			}

			foreach ( $filters as $index => $group ) {
				if ( ! is_array( $group ) || empty( $group['rules'] ) || ! is_array( $group['rules'] ) ) {
					continue;
				}
				foreach ( $group['rules'] as $rule_index => $rule ) {
					if ( is_array( $rule ) && isset( $rule['slug'] ) && 'journey_pages' === $rule['slug'] ) {
						unset( $filters[ $index ]['rules'][ $rule_index ] );
						$filters[ $index ]['rules'] = array_values( $filters[ $index ]['rules'] );
					}
				}
			}

			return $filters;
		}

		/**
		 * Whether the installed Lite build predates the journey seams.
		 *
		 * Lite gained the row and filter-list seams in WFFN_PRO_MIN_LITE_VERSION.
		 * Updating Pro alone leaves those absent, so the Conversions and Optins
		 * lists would come back without a journey key and drop the column.
		 *
		 * @since 3.16.1
		 *
		 * @return bool
		 */
		private function is_legacy_lite() {
			if ( ! defined( 'WFFN_VERSION' ) || ! defined( 'WFFN_PRO_MIN_LITE_VERSION' ) ) {
				return false;
			}

			return version_compare( WFFN_VERSION, WFFN_PRO_MIN_LITE_VERSION, '<' );
		}

		/**
		 * Add the journey to list responses a legacy Lite cannot decorate itself.
		 *
		 * Rides the REST response rather than the row seam: on those builds the
		 * seam does not exist, so this is the only place the rows can be reached.
		 * Records that already carry a journey are left alone, so a build that
		 * does fire the seam never has it applied twice.
		 *
		 * @since 3.16.1
		 *
		 * @param WP_REST_Response|WP_HTTP_Response|WP_Error $response Result to send.
		 * @param array                                      $handler  Route handler.
		 * @param WP_REST_Request                            $request  Current request.
		 *
		 * @return WP_REST_Response|WP_HTTP_Response|WP_Error
		 */
		public function backfill_journey_for_legacy_lite( $response, $handler, $request ) {
			if ( ! $this->is_legacy_lite() || ! class_exists( 'WFFN_Journey' ) || ! WFFN_Journey::is_enabled() ) {
				return $response;
			}

			if ( ! $request instanceof WP_REST_Request || ! $response instanceof WP_REST_Response ) {
				return $response;
			}

			$route = $request->get_route();
			if ( preg_match( '#/funnel-orders(/\d+)?/?$#', $route ) ) {
				$id_key = 'order_id';
				$type   = 2;
			} elseif ( preg_match( '#/funnel-leads(/\d+)?/?$#', $route ) ) {
				$id_key = 'entry_id';
				$type   = 1;
			} else {
				return $response;
			}

			$data = $response->get_data();
			if ( ! is_array( $data ) ) {
				return $response;
			}

			if ( ! empty( $data['records'] ) && is_array( $data['records'] ) ) {
				$data['records'] = $this->attach_journey_to_records( $data['records'], $id_key, $type );
			}

			// The rule ships with Lite from WFFN_PRO_MIN_LITE_VERSION; add it here
			// for the builds that predate it so the list can still be filtered.
			if ( ! empty( $data['filters_list'] ) && is_array( $data['filters_list'] ) ) {
				$data['filters_list'] = $this->add_journey_filter_rule( $data['filters_list'], $request );
			}

			$response->set_data( $data );

			return $response;
		}

		/**
		 * Resolve each record's journey in one query over the tracking table.
		 *
		 * @since 3.16.1
		 *
		 * @param array  $records List rows.
		 * @param string $id_key  Row key holding the tracking source id.
		 * @param int    $type    Tracking row type: 2 for orders, 1 for optins.
		 *
		 * @return array
		 */
		private function attach_journey_to_records( $records, $id_key, $type ) {
			// The column only exists once the migration has run.
			if ( version_compare( get_option( 'wffn_conversion_tracking_db_updater', '1.0' ), '1.1', '<' ) ) {
				return $records;
			}

			$ids = array();
			foreach ( $records as $record ) {
				if ( is_array( $record ) && ! isset( $record['journey'] ) && ! empty( $record[ $id_key ] ) ) {
					$ids[] = absint( $record[ $id_key ] );
				}
			}

			$ids = array_values( array_filter( array_unique( $ids ) ) );
			if ( empty( $ids ) ) {
				return $records;
			}

			global $wpdb;
			$conv_table   = $wpdb->prefix . $this->table;
			$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
			$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT source, journey FROM {$conv_table} WHERE type = %d AND source IN ({$placeholders})", array_merge( array( $type ), $ids ) ), ARRAY_A ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$journeys = array();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$journeys[ absint( $row['source'] ) ] = isset( $row['journey'] ) ? $row['journey'] : '';
				}
			}

			foreach ( $records as $index => $record ) {
				if ( ! is_array( $record ) || isset( $record['journey'] ) || empty( $record[ $id_key ] ) ) {
					continue;
				}
				$source = absint( $record[ $id_key ] );
				// Reuses the seam callback so both paths resolve identically.
				$records[ $index ] = $this->add_journey_to_row( $record, array( 'journey' => isset( $journeys[ $source ] ) ? $journeys[ $source ] : '' ) );
			}

			return $records;
		}

		/**
		 * Append the Pages Visited rule to a legacy Lite filter list.
		 *
		 * @since 3.16.1
		 *
		 * @param array           $filters Filter-rule groups.
		 * @param WP_REST_Request $request Current request.
		 *
		 * @return array
		 */
		private function add_journey_filter_rule( $filters, $request ) {
			foreach ( $filters as $group ) {
				if ( ! is_array( $group ) || empty( $group['rules'] ) || ! is_array( $group['rules'] ) ) {
					continue;
				}
				foreach ( $group['rules'] as $existing ) {
					if ( is_array( $existing ) && isset( $existing['slug'] ) && 'journey_pages' === $existing['slug'] ) {
						return $filters;
					}
				}
			}

			$funnel_id = absint( $request->get_param( 'funnel_id' ) );

			$rule = array(
				'slug'            => 'journey_pages',
				'title'           => __( 'Pages Visited', 'funnel-builder-pro' ),
				'type'            => 'search',
				'multiple'        => true,
				'operators'       => array(
					'any' => __( 'Visited any of', 'funnel-builder-pro' ),
					'all' => __( 'Visited all of', 'funnel-builder-pro' ),
				),
				'api'             => '/funnel-journey-pages/?s={{search}}',
				'op_label'        => __( 'Pages', 'funnel-builder-pro' ),
				'required'        => array( 'rule', 'data' ),
				'readable_text'   => '{{rule /}} - {{value /}}',
				'default_options' => WFFN_Journey::default_page_options( $funnel_id ),
				'is_pro'          => true,
			);

			if ( isset( $filters[0]['rules'] ) && is_array( $filters[0]['rules'] ) ) {
				$filters[0]['rules'][] = $rule;
			}

			return $filters;
		}

		public function get_top_campaigns( $top_campaigns, $request ) {
			global $wpdb;
			$conv_table = $wpdb->prefix . $this->table;
			$limit      = isset( $request['top_campaigns_limit'] ) ? $request['top_campaigns_limit'] : ( isset( $request['limit'] ) ? $request['limit'] : 5 );

			if ( isset( $request['overall'] ) ) {
				$date_query = ' AND 1=1 ';
			} else {
				$start_date = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : WFFN_REST_Controller::default_date( WEEK_IN_SECONDS )->format( WFFN_REST_Controller::$sql_datetime_format );
				$end_date   = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : WFFN_REST_Controller::default_date()->format( WFFN_REST_Controller::$sql_datetime_format );
				$date_query = " AND conv.timestamp >= '" . esc_sql( $start_date ) . "' AND conv.timestamp < '" . esc_sql( $end_date ) . "'";

			}
			/**
			 * Get all top sales campaigns
			 */
			$limit = absint( $limit );
			if ( WFFN_Common::is_wc_hpos_enabled() ) {
				$s_query = 'SELECT conv.utm_campaign AS campaign, COUNT(DISTINCT conv.source) as `conversion`, ROUND(SUM(order_t.total_amount), 2) as revenue FROM ' . $conv_table . ' AS conv
                LEFT JOIN ' . $wpdb->prefix . "wc_orders AS order_t ON conv.source = order_t.id WHERE conv.type = 2 AND conv.utm_campaign != '' AND conv.funnel_id != 0 " . $date_query . ' GROUP BY conv.utm_campaign ORDER BY revenue DESC LIMIT ' . $limit;

			} else {
				$s_query = 'SELECT conv.utm_campaign AS campaign, COUNT(DISTINCT conv.source) as `conversion`, ROUND(SUM( order_t.total_sales ), 2) as revenue FROM ' . $conv_table . ' AS conv
                     LEFT JOIN ' . $wpdb->prefix . "wc_order_stats AS order_t ON conv.source = order_t.order_id WHERE conv.type = 2 AND conv.utm_campaign != '' AND conv.funnel_id != 0 " . $date_query . ' GROUP BY conv.utm_campaign ORDER BY revenue DESC LIMIT ' . $limit;
			}

			$s_result = $wpdb->get_results( $s_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( is_array( $s_result ) && count( $s_result ) > 0 ) {

				foreach ( $s_result as &$s_item ) {

					/**
					 * Get all source data based by campaign
					 */
					if ( WFFN_Common::is_wc_hpos_enabled() ) {
						$source_query = 'SELECT DISTINCT conv.utm_source as u_source, COUNT(DISTINCT conv.source) as `conversion`, ROUND(SUM(order_t.total_amount), 2 ) as revenue FROM ' . $conv_table . ' AS conv
                              LEFT JOIN ' . $wpdb->prefix . "wc_orders AS order_t ON conv.source = order_t.id
                              WHERE conv.utm_campaign = '" . esc_sql( $s_item['campaign'] ) . "' AND conv.type = 2 AND conv.funnel_id != 0 AND conv.utm_source !='' " . $date_query . ' GROUP BY u_source ORDER BY revenue DESC';
					} else {
						$source_query = 'SELECT DISTINCT conv.utm_source as u_source, COUNT(DISTINCT conv.source) as `conversion`, ROUND(SUM( order_t.total_sales ), 2 ) as revenue FROM ' . $conv_table . ' AS conv
                              LEFT JOIN ' . $wpdb->prefix . "wc_order_stats AS order_t ON conv.source = order_t.order_id
                              WHERE conv.utm_campaign = '" . esc_sql( $s_item['campaign'] ) . "' AND conv.type = 2 AND conv.funnel_id != 0 AND conv.utm_source !='' " . $date_query . ' GROUP BY u_source ORDER BY revenue DESC';
					}

					$source_result = $wpdb->get_results( $source_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					if ( is_array( $source_result ) && count( $source_result ) > 0 ) {
						$s_item['source_count'] = count( $source_result );
						$s_item['source']       = $source_result;
					} else {
						$s_item['source_count'] = 0;
						$s_item['source']       = array();
					}

					/**
					 * Get all medium data based by campaign
					 */
					if ( WFFN_Common::is_wc_hpos_enabled() ) {
						$medium_query = 'SELECT DISTINCT conv.utm_medium as u_medium, COUNT(DISTINCT conv.source) as `conversion`, ROUND(SUM(order_t.total_amount), 2 ) as revenue FROM ' . $conv_table . ' AS conv
                              LEFT JOIN ' . $wpdb->prefix . "wc_orders AS order_t ON conv.source = order_t.id
                              WHERE conv.utm_campaign = '" . esc_sql( $s_item['campaign'] ) . "' AND conv.type = 2 AND conv.funnel_id != 0 AND conv.utm_medium !='' " . $date_query . ' GROUP BY u_medium ORDER BY revenue DESC';
					} else {
						$medium_query = 'SELECT DISTINCT conv.utm_medium as u_medium, COUNT(DISTINCT conv.source) as `conversion`, ROUND(SUM( order_t.total_sales ), 2 ) as revenue FROM ' . $conv_table . ' AS conv
                              LEFT JOIN ' . $wpdb->prefix . "wc_order_stats AS order_t ON conv.source = order_t.order_id
                              WHERE conv.utm_campaign = '" . esc_sql( $s_item['campaign'] ) . "' AND conv.type = 2 AND conv.funnel_id != 0 AND conv.utm_medium !='' " . $date_query . ' GROUP BY u_medium ORDER BY revenue DESC';
					}

					$medium_result = $wpdb->get_results( $medium_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					if ( is_array( $medium_result ) && count( $medium_result ) > 0 ) {
						$s_item['medium_count'] = count( $medium_result );
						$s_item['medium']       = $medium_result;
					} else {
						$s_item['medium_count'] = 0;
						$s_item['medium']       = array();
					}
				}

				$top_campaigns['sales'] = $s_result;
			}

			/**
			 * Get all top sales campaigns
			 */
			$op_query = 'SELECT conv.utm_campaign AS campaign, COUNT(DISTINCT conv.source) as `conversion`, 0 as revenue FROM ' . $conv_table . " AS conv
                     WHERE conv.type = 1 AND conv.utm_campaign != '' AND conv.funnel_id != 0 GROUP BY conv.utm_campaign ORDER BY `conversion` DESC LIMIT " . $limit;

			$op_result = $wpdb->get_results( $op_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( is_array( $op_result ) && count( $op_result ) > 0 ) {

				foreach ( $op_result as &$o_item ) {

					/**
					 * Get all source and medium data based by campaign
					 */
					$os_query  = 'SELECT DISTINCT conv.utm_source as u_source, COUNT(DISTINCT conv.source) as `conversion`, 0 as revenue FROM ' . $conv_table . " AS conv
                          WHERE conv.utm_campaign = '" . esc_sql( $o_item['campaign'] ) . "' AND conv.type = 1 AND conv.funnel_id != 0 GROUP BY u_source ORDER BY `conversion` DESC";
					$os_result = $wpdb->get_results( $os_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					if ( is_array( $os_result ) && count( $os_result ) > 0 ) {
						$o_item['source_count'] = count( $os_result );
						$o_item['source']       = $os_result;
					} else {
						$o_item['source_count'] = 0;
						$o_item['source']       = array();
					}

					/**
					 * Get all medium data based by campaign
					 */
					$os_query  = 'SELECT DISTINCT conv.utm_medium as u_medium, COUNT(DISTINCT conv.source) as `conversion`, 0 as revenue FROM ' . $conv_table . " AS conv
                              WHERE conv.utm_campaign = '" . esc_sql( $o_item['campaign'] ) . "' AND conv.type = 1 AND conv.funnel_id != 0 GROUP BY u_medium ORDER BY `conversion` DESC";
					$os_result = $wpdb->get_results( $os_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					if ( is_array( $os_result ) && count( $os_result ) > 0 ) {
						$o_item['medium_count'] = count( $os_result );
						$o_item['medium']       = $os_result;
					} else {
						$o_item['medium_count'] = 0;
						$o_item['medium']       = array();
					}
				}

				$top_campaigns['lead'] = $op_result;
			}

			return $top_campaigns;
		}

		public function get_source_data( $data, $args ) {
			$data = array(
				'sales' => array(),
				'lead'  => array(),
			);

			if ( ! is_array( $args ) || count( $args ) === 0 ) {
				return $data;

			}
			global $wpdb;
			$conv_table = $wpdb->prefix . $this->table;

			$start_date = isset( $args['start_date'] ) ? $args['start_date'] : '';
			$end_date   = isset( $args['end_date'] ) ? $args['end_date'] : '';

			$refs        = WFFN_Common::get_refs( true );
			$case_string = $this->get_conversion_cases_string( true );
			$date        = ( '' !== $start_date && '' !== $end_date ) ? " AND `timestamp` >= '" . esc_sql( $start_date ) . "' AND `timestamp` < '" . esc_sql( $end_date ) . "' " : '';
			$s_query     = "SELECT {$case_string} , COUNT(id) as 'orders',ROUND( SUM(value), 2 ) as total_revenue FROM " . $conv_table . ' WHERE 1=1 AND type = 2 ' . $date . ' GROUP BY referrers';

			$s_result   = $wpdb->get_results( $s_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$db_error   = WFFN_Common::maybe_wpdb_error( $wpdb );
			$sale_data  = array();
			$optin_data = array();
			foreach ( $refs as $ref_Key => $ref ) {

				$ref_index                = strtolower( str_replace( ' ', '-', $ref_Key ) );
				$sale_data[ $ref_index ]  = array(
					'ref_key'       => strtolower( str_replace( ' ', '-', $ref_Key ) ),
					'referrers'     => ucwords( $ref_Key ),
					'orders'        => 0,
					'percentage'    => 0,
					'total_revenue' => 0,
				);
				$optin_data[ $ref_index ] = array(
					'ref_key'       => strtolower( str_replace( ' ', '-', $ref_Key ) ),
					'referrers'     => ucwords( $ref_Key ),
					'conversion'    => 0,
					'percentage'    => 0,
					'total_revenue' => 0,
				);
			}
			if ( true !== $db_error['db_error'] ) {
				if ( is_array( $s_result ) && count( $s_result ) > 0 ) {
					$s_count = array_sum( wp_list_pluck( $s_result, 'orders' ) );
					foreach ( $s_result as &$s_item ) {
						$s_item['percentage'] = $this->get_percentage( $s_count, $s_item['orders'] );
					}
				}

				foreach ( $s_result as $k => $source ) {
					$referrer = empty( $source['referrers'] ) ? 'direct' : $source['referrers'];
					$referrer = strtolower( str_replace( ' ', '-', $referrer ) );
					if ( isset( $sale_data[ $referrer ] ) ) {
						$s_result[ $k ]['ref_key']   = strtolower( str_replace( ' ', '-', $s_result[ $k ]['referrers'] ) );
						$s_result[ $k ]['referrers'] = ucwords( $s_result[ $k ]['referrers'] );
						$sale_data[ $referrer ]      = $s_result[ $k ];
					}
				}
				$data['sales'] = array_values( $sale_data );
			}
			$case_string = $this->get_conversion_cases_string( true );
			$op_query    = "SELECT {$case_string}, COUNT(id) as 'conversion' FROM " . $conv_table . ' WHERE 1=1 AND type = 1 ' . $date . ' GROUP BY referrers';

			$op_result = $wpdb->get_results( $op_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$db_error  = WFFN_Common::maybe_wpdb_error( $wpdb );

			if ( true !== $db_error['db_error'] ) {
				if ( is_array( $op_result ) && count( $op_result ) > 0 ) {
					$o_count = array_sum( wp_list_pluck( $op_result, 'conversion' ) );
					foreach ( $op_result as &$o_item ) {
						$o_item['percentage'] = $this->get_percentage( $o_count, $o_item['conversion'] );
					}
				}

				foreach ( $op_result as $k => $source ) {
					$referrer = empty( $source['referrers'] ) ? 'direct' : $source['referrers'];
					$referrer = strtolower( str_replace( ' ', '-', $referrer ) );
					if ( isset( $optin_data[ $referrer ] ) ) {
						$op_result[ $k ]['ref_key']   = strtolower( str_replace( ' ', '-', $op_result[ $k ]['referrers'] ) );
						$op_result[ $k ]['referrers'] = ucwords( $op_result[ $k ]['referrers'] );
						$optin_data[ $referrer ]      = $op_result[ $k ];
					}
				}

				$data['lead'] = array_values( $optin_data );
			}

			return $data;
		}

		public function get_utm_data_query( $query_array, $filters ) {
			// utm_campaign Type Filter
			if ( ! empty( $filters['utm_campaign']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_campaign']['data'] );
				$query_array[] = '(tracking.utm_campaign IN (' . $utm_campaign . '))';
			}

			// utm_source Type Filter
			if ( ! empty( $filters['utm_source']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_source']['data'] );
				$query_array[] = '(tracking.utm_source IN (' . $utm_campaign . '))';
			}

			// utm_medium Type Filter
			if ( ! empty( $filters['utm_medium']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_medium']['data'] );
				$query_array[] = '(tracking.utm_medium IN (' . $utm_campaign . '))';
			}
			// utm_term Type Filter
			if ( isset( $filters['utm_term'] ) && isset( $filters['utm_term']['data'] ) && ! empty( $filters['utm_term']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_term']['data'] );
				$query_array[] = '(tracking.utm_term IN (' . $utm_campaign . '))';
			}
			// utm_content Type Filter
			if ( isset( $filters['utm_content'] ) && isset( $filters['utm_content']['data'] ) && ! empty( $filters['utm_content']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_content']['data'] );
				$query_array[] = '(tracking.utm_content IN (' . $utm_campaign . '))';
			}

			// referrer Filters
			if ( ! empty( $filters['utm_referrer']['data'] ) ) {
				$referrer = $filters['utm_referrer']['data'];

				$refs_all  = WFFN_Common::get_refs();
				$other_set = $refs_all;
				$is_other  = false;

				if ( ! in_array( 'all', $referrer, true ) ) {
					$referrer_where = array();
					$domain_string  = array();

					foreach ( $referrer as $ref ) {

						if ( $ref === 'others' ) {
							$is_other = true;
							continue;
						}

						if ( $ref === 'direct' ) {
							$referrer_where[] = "(tracking.referrer = '') ";
							continue;
						}
						if ( array_key_exists( $ref, $refs_all ) ) {
							$domain_string[] = $refs_all[ $ref ];
							unset( $other_set[ $ref ] );
						}
					}

					if ( $is_other && ! empty( $other_set ) ) {
						$domain_string_other = implode( '|', WFFN_Common::array_flatten( $other_set ) );
						if ( empty( $referrer_where ) ) {
							$referrer_where[] = "(tracking.referrer NOT REGEXP '{$domain_string_other}') AND tracking.referrer != '' ";

						} else {
							$referrer_where[] = "(tracking.referrer NOT REGEXP '{$domain_string_other}') ";

						}
					}

					if ( ! empty( $domain_string ) ) {
						$domain_string    = implode( '|', WFFN_Common::array_flatten( $domain_string ) );
						$referrer_where[] = "(tracking.referrer REGEXP '{$domain_string}')";
					}
					$query_array[] = '(' . implode( ' OR ', $referrer_where ) . ')';
				}
			}

			// device filters
			if ( ! empty( $filters['device']['data'] ) ) {
				$device = $filters['device']['data'];

				$device_types = array(
					'Desktop' => 'desktop',
					'Mobile'  => 'mobile',
				);

				if ( ! in_array( 'all', $device, true ) ) {
					$device_where = array();

					$selected_types = array_map(
						function ( $dev ) use ( $device_types ) {
							return $device_types[ $dev ] ?? null;
						},
						$device
					);

					$selected_types = array_filter( $selected_types );

					if ( ! empty( $selected_types ) ) {
						$domain_string  = implode( '|', $selected_types );
						$device_where[] = "(tracking.device REGEXP '{$domain_string}')";

						$query_array[] = '(' . implode( ' OR ', $device_where ) . ')';
					}
				}
			}

			// Pages Visited filter — match on the journey entry page id inside the
			// JSON. Only absint() ids reach the REGEXP; the trailing [,}] stops
			// 9483 matching 94831 and "?...? tolerates both string and int ids.
			if ( ! empty( $filters['journey_pages']['data'] ) && class_exists( 'WFFN_Journey' ) ) {
				$rule = ( isset( $filters['journey_pages']['rule'] ) && 'all' === $filters['journey_pages']['rule'] ) ? 'all' : 'any';
				$ids  = array_filter( array_map( 'absint', explode( ',', $filters['journey_pages']['data'] ) ) );

				if ( ! empty( $ids ) ) {
					if ( 'all' === $rule ) {
						// Visited all of: one predicate per id, ANDed with the rest.
						foreach ( $ids as $id ) {
							$pattern = WFFN_Journey::page_id_pattern( array( $id ) );
							if ( '' !== $pattern ) {
								$query_array[] = "(tracking.journey REGEXP '" . $pattern . "')";
							}
						}
					} else {
						// Visited any of: a single alternation.
						$pattern = WFFN_Journey::page_id_pattern( $ids );
						if ( '' !== $pattern ) {
							$query_array[] = "(tracking.journey REGEXP '" . $pattern . "')";
						}
					}
				}
			}

			return $query_array;
		}

		public function get_conversion_cases_string( $is_other = false ) {
			if ( ! method_exists( 'WFFN_Common', 'get_refs' ) ) {
				return ' referrer as referrers ';
			}
			$refs = WFFN_Common::get_refs();

			$cases = array();
			foreach ( $refs as $name => $lists ) {
				$cases[] = implode(
					' ',
					array_map(
						function ( $domain ) use ( $name ) {

							if ( false !== strpos( $domain, '://' ) ) {
									$domain = str_replace( '://', '', $domain );

									return "WHEN referrer LIKE '{$domain}' THEN '{$name}'";
							} else {
								return "WHEN referrer LIKE '%{$domain}%' THEN '{$name}'";
							}
						},
						$lists
					)
				);
			}
			$cases = implode( ' ', $cases );

			if ( false === $is_other ) {
				return "( CASE WHEN referrer = '' THEN 'Direct' {$cases} ELSE referrer END ) as 'referrers'";

			} else {
				return "( CASE WHEN referrer = '' THEN 'Direct' {$cases} WHEN referrer != '' THEN 'others' ELSE referrer END) as 'referrers'";

			}
		}

		public function add_string_quote( $string ) {
			// Escape each value individually before joining. Doing the join first
			// would put literal single-quote delimiters into the string that
			// esc_sql() then escapes, collapsing the IN list into one big string
			$values  = explode( ',', $string );
			$escaped = array_map( 'esc_sql', $values );

			return "'" . implode( "', '", $escaped ) . "'";
		}

		public function get_percentage( $total, $number ) {
			if ( $total > 0 ) {
				return round( $number / ( $total / 100 ), 2 );
			} else {
				return 0;
			}
		}
	}

	WFFN_Conversion_Data::get_instance();
}
