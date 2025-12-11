<?php

if ( ! class_exists( 'WFFN_Conversion_Data' ) ) {
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

		/**
		 * WFFN_Conversion_Data constructor.
		 */
		public function __construct() {
			$this->table = 'bwf_conversion_tracking';
			add_action( 'rest_api_init', [ $this, 'utm_data_end_points' ], 11 );
			add_action( 'wffn_delete_optin_entries', [ $this, 'delete_optin_entries' ] );
			add_action( 'wffn_delete_funnel_contacts', [ $this, 'wffn_delete_funnel_contacts' ], 10, 2 );
			add_filter( 'wffn_conversion_tracking_data_activity', [ $this, 'get_conversion_tracking_activity_data' ], 10, 3 );
			add_filter( 'wffn_filter_data_conversion_query', [ $this, 'filter_data_conversion_query' ], 10, 4 );
			add_filter( 'wffn_dashboard_top_campaigns', [ $this, 'get_top_campaigns' ], 10, 2 );
			add_filter( 'wffn_source_data_by_conversion_query', [ $this, 'get_source_data' ], 10, 2 );
			add_filter( 'wffn_conversion_tracking_localize_data', [ $this, 'update_data_localize_data' ] );
			add_filter( 'wffn_utm_campaign_campaigns', [ $this, 'get_global_utm_campaigns' ], 10, 2 );
		}

		/**
		 * @return WFFN_Conversion_Data|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
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


			$filters = is_string( $request['filters'] ?? null ) ? json_decode( $request['filters'], true ) : ( $request['filters'] ?? [] );
			if ( isset( $request['funnel_id'] ) && $request['funnel_id'] > 0 ) {
				array_push( $filters, [ 'filter' => 'funnels', 'rule' => '', 'data' => [ [ 'id' => $request['funnel_id'], 'label' => '' ] ] ] );
			}
			[ $filter_conditions, $funnel_ids, $conversions_filter, $date_filter ] = $this->parse_filters( $filters );

			$sales_result = $wpdb->get_results( $this->generate_sales_query( $filter_conditions, $funnel_ids, $date_filter ), ARRAY_A );
			$lead_result  = $wpdb->get_results( $this->generate_lead_query( $filter_conditions, $funnel_ids, $date_filter ), ARRAY_A );
			$data         = $this->merge_results( $sales_result, $lead_result );

			$filtered_data = $this->apply_filters( $data, $search_term, $conversions_filter );

			$total_count = count( $filtered_data );
			$offset      = $offset >= $total_count ? 0 : $offset;

			$paginated_data = array_slice( array_values( $filtered_data ), $offset, $limit );

			return [
				'records'     => $paginated_data,
				'total_count' => $total_count,
			];
		}

		/**
		 * Parses filters to extract conditions, funnel IDs, and conversion filter.
		 *
		 * @param array $filters Filters from the request.
		 *
		 * @return array Parsed filter conditions, funnel IDs, and conversion type.
		 */
		protected function parse_filters( array $filters ) {
			$filter_conditions  = $funnel_ids = [];
			$conversions_filter = null;
			$date_filter = [];
			foreach ( $filters as $filter ) {
				if ( $filter['filter'] === 'conversions' ) {
					$conversions_filter = $filter['data'];
				} elseif ( $filter['filter'] === 'funnels' && is_array( $filter['data'] ) ) {
					$funnel_ids = array_column( $filter['data'], 'id' );
				} elseif ( $filter['filter'] === 'period' && isset( $filter['data']['after'], $filter['data']['before'] ) ) {
					$date_filter = [
						'after'  => $filter['data']['after'],
						'before' => $filter['data']['before'],
					];
				} elseif ( ! empty( $filter['data'] ) && is_array( $filter['data'] ) ) {
					$filter_conditions[ $filter['filter'] ] = array_column( $filter['data'], 'id' );
				}
			}

			return [ $filter_conditions, $funnel_ids, $conversions_filter, $date_filter ];
		}

		/**
		 * Filters data based on search term and conversion type.
		 *
		 * @param array $data Data to filter.
		 * @param string $search_term Term to search in the data.
		 * @param string|null $conversions_filter Conversion type filter ('orders' or 'optins').
		 *
		 * @return array Filtered data.
		 */
		protected function apply_filters( array $data, $search_term, $conversions_filter ) {
			return array_filter( $data, function ( $item ) use ( $search_term, $conversions_filter ) {
				$matches_search = empty( $search_term ) || stripos( $item['utm_campaign'], $search_term ) !== false || stripos( $item['utm_source'], $search_term ) !== false || stripos( $item['utm_medium'], $search_term ) !== false || stripos( $item['utm_content'], $search_term ) !== false || stripos( $item['utm_term'], $search_term ) !== false || stripos( $item['orders'], $search_term ) !== false || stripos( $item['optins'], $search_term ) !== false || stripos( $item['revenue'], $search_term ) !== false;

				if ( $conversions_filter === 'orders' ) {
					return $matches_search && $item['orders'] !== '-';
				} elseif ( $conversions_filter === 'optins' ) {
					return $matches_search && $item['optins'] !== '-';
				}

				return $matches_search;
			} );
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
				   COUNT(DISTINCT conv.id) AS conversion, ROUND(SUM(order_t.total_amount), 2) AS revenue, conv.timestamp
				   FROM {$conv_table} AS conv 
				   LEFT JOIN {$wpdb->prefix}wc_orders AS order_t ON conv.source = order_t.id 
				   WHERE conv.type = %d AND conv.utm_campaign != ''" : "SELECT conv.utm_campaign AS campaign, conv.utm_source AS source, conv.utm_medium AS medium, conv.utm_content AS content, conv.utm_term AS term, 
				   COUNT(DISTINCT conv.id) AS conversion, ROUND(SUM(order_t.total_sales), 2) AS revenue, conv.timestamp
				   FROM {$conv_table} AS conv 
				   LEFT JOIN {$wpdb->prefix}wc_order_stats AS order_t ON conv.source = order_t.order_id 
				   WHERE conv.type = %d AND conv.utm_campaign != ''";

			$sales_query = $wpdb->prepare( $sales_query, 2 );

			if ( ! empty( $funnel_ids ) ) {
				$funnel_placeholders = implode( ',', array_fill( 0, count( $funnel_ids ), '%d' ) );
				$funnel_ids_sql      = $wpdb->prepare( " AND conv.funnel_id IN ($funnel_placeholders)", ...$funnel_ids );
				$sales_query         .= $funnel_ids_sql;
			}
			if ( ! empty( $date_filter ) ) {
				$sales_query .= $wpdb->prepare( " AND conv.timestamp BETWEEN %s AND %s", $date_filter['after'], $date_filter['before'] );
			}
			$filter_query = $this->build_filter_query( $filter_conditions, 'conv' );
			$sales_query  .= $filter_query;

			$sales_query .= " GROUP BY conv.utm_campaign, conv.utm_source, conv.utm_medium, conv.utm_content, conv.utm_term";

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
						   COUNT(DISTINCT conv.id) AS conversion, conv.timestamp
						   FROM {$conv_table} AS conv 
						   WHERE conv.type = %d AND conv.utm_campaign != ''";

			$lead_query = $wpdb->prepare( $lead_query, 1 );

			if ( ! empty( $funnel_ids ) ) {
				$funnel_placeholders = implode( ',', array_fill( 0, count( $funnel_ids ), '%d' ) );
				$funnel_ids_sql      = $wpdb->prepare( " AND conv.funnel_id IN ($funnel_placeholders)", ...$funnel_ids );
				$lead_query          .= $funnel_ids_sql;
			}
			if ( ! empty( $date_filter ) ) {
				$lead_query .= $wpdb->prepare( " AND conv.timestamp BETWEEN %s AND %s", $date_filter['after'], $date_filter['before'] );
			}
			$filter_query = $this->build_filter_query( $filter_conditions, 'conv' );
			$lead_query   .= $filter_query;

			$lead_query .= " GROUP BY conv.utm_campaign, conv.utm_source, conv.utm_medium, conv.utm_content, conv.utm_term";

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

			$filter_query = '';
			foreach ( $filter_conditions as $filter_type => $values ) {
				$placeholders       = implode( ',', array_fill( 0, count( $values ), '%s' ) );
				$filter_condition   = "$table_alias.$filter_type IN ($placeholders)";
				$prepared_condition = $wpdb->prepare( " AND $filter_condition", ...$values );
				$filter_query       .= $prepared_condition;
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
			$data = [];

			foreach ( $sales_result as $sale ) {
				$key          = $sale['campaign'] . '-' . $sale['source'] . '-' . $sale['medium'] . '-' . $sale['content'];
				$data[ $key ] = [
					'utm_campaign'   => $sale['campaign'],
					'utm_source'     => $sale['source'],
					'utm_medium'     => $sale['medium'],
					'utm_content'    => $sale['content'],
					'utm_term'       => $sale['term'],
					'orders'         => (int) $sale['conversion'],
					'optins'         => '-',
					'revenue'        => $sale['revenue'],
					'timestamp_unix' => strtotime( $sale['timestamp'] )
				];
			}

			foreach ( $lead_result as $lead ) {
				$key = $lead['campaign'] . '-' . $lead['source'] . '-' . $lead['medium'] . '-' . $lead['content'];
				if ( isset( $data[ $key ] ) ) {
					$data[ $key ]['optins'] = (int) $lead['conversion'];
				} else {
					$data[ $key ] = [
						'utm_campaign'   => $lead['campaign'],
						'utm_source'     => $lead['source'],
						'utm_medium'     => $lead['medium'],
						'utm_content'    => $lead['content'],
						'utm_term'       => $lead['term'],
						'orders'         => '-',
						'optins'         => (int) $lead['conversion'],
						'revenue'        => "0.00",
						'timestamp_unix' => strtotime( $lead['timestamp'] )
					];
				}
			}
			usort( $data, function ( $a, $b ) {
				return $b['timestamp_unix'] - $a['timestamp_unix'];
			} );

			return $data;
		}

		/**
		 * @return void
		 */
		public function utm_data_end_points() {
			register_rest_route( $this->namespace, '/funnel-utms/', array(
				'args'                => [],
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_utm_data' ),
				'permission_callback' => array( $this, 'get_read_api_permission_check' ),
			) );
			register_rest_route( $this->namespace, '/funnel-referrers/', array(
				'args'                => [],
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_referrer' ),
				'permission_callback' => array( $this, 'get_read_api_permission_check' ),
			) );
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

		public function get_utm_data( $request = [], $is_return = false ) {
			global $wpdb;
			$args = array(
				'funnel_id'   => $request['funnel_id'] ?? 0,
				's'           => $request['s'] ?? '',
				'limit'       => $request['limit'] ?? get_option( 'posts_per_page' ),
				'page_no'     => $request['page_no'] ?? 1,
				'total_count' => $request['total_count'] ?? false,
				'utm_key'     => $request['utm_key'],
			);

			$limit       = $args['limit'];
			$page_no     = $args['page_no'];
			$offset      = $args['offset'] ?? ( intval( $limit ) * intval( $page_no - 1 ) );
			$where_query = [ '(1=1)' ];
			if ( ! empty( $args['s'] ) ) {
				$search_text   = trim( $args['s'] );
				$where_query[] = $wpdb->prepare( "(%1s like %s)", $args['utm_key'], "%" . $search_text . "%" );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
			}
			if ( isset( $request['s'] ) && empty( $args['s'] ) ) {
				$limit = 5;
			}

			if ( $args['funnel_id'] > 0 ) {
				$where_query[] = "(funnel_id= '" . esc_sql( $args['funnel_id'] ) . "')";
			}
			$utm_key =  esc_sql( $args['utm_key'] );
			$where_query[] = "(`" . $utm_key . "` != '')";
			$where_query   = implode( ' AND ', $where_query );
			$limit_str     = esc_sql( " LIMIT $offset, $limit" );
			$select_type   = "SELECT " . $utm_key . " as utm_label, COUNT(*) as total_count";
			if ( isset( $args['total_count'] ) && 'yes' === $args['total_count'] ) {
				$limit_str = '';
			}
			$conv_table = $wpdb->prefix . $this->table;
			$sql_query  = "{$select_type} from {$conv_table} where {$where_query} GROUP BY " . $utm_key . " {$limit_str}";

			$results = $wpdb->get_results( $sql_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! empty( $results ) ) {
				$results = array_map( function ( $item ) {
					return [ 'id' => $item['utm_label'], 'label' => $item['utm_label'], 'value' => absint( $item['total_count'] ) ];
				}, $results );
			}

			return $is_return ? $results : rest_ensure_response( $results );
		}

		/**
		 * @param $request
		 *
		 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
		 */
		public function get_referrer( $request ) {
			global $wpdb;
			$case_string = $this->get_conversion_cases_string();

			$response = [ 'status' => true, 'message' => __( 'No Referrer Founds', 'funnel-builder-powerpack' ), 'records' => [] ];
			$args     = array(
				'funnel_id'   => $request['funnel_id'] ?? 0,
				's'           => $request['s'] ?? '',
				'limit'       => $request['limit'] ?? get_option( 'posts_per_page' ),
				'page_no'     => $request['page_no'] ?? 1,
				'total_count' => $request['total_count'] ?? false,
			);

			$limit   = $args['limit'];
			$page_no = $args['page_no'];
			$offset  = $args['offset'] ?? ( intval( $limit ) * intval( $page_no - 1 ) );

			$where_query = [ '(1=1)' ];
			if ( ! empty( $args['s'] ) ) {
				$search_text   = esc_sql( trim( $args['s'] ) );
				$where_query[] = "(referrer like '%$search_text%')";
			}

			if ( $args['funnel_id'] > 0 ) {
				$where_query[] = "(funnel_id= '". esc_sql( $args['funnel_id'] ) ."')";
			}


			$where_query = implode( ' AND ', $where_query );
			$limit_str   = esc_sql( " LIMIT $offset, $limit" );
			$select_type = "SELECT $case_string , COUNT(*) as total_count";
			if ( isset( $args['total_count'] ) && 'yes' === $args['total_count'] ) {
				$limit_str = '';
			}
			$conv_table = $wpdb->prefix . $this->table;
			$sql_query  = "{$select_type} from {$conv_table} as tracking where {$where_query} GROUP BY referrer {$limit_str}";
			$results    = $wpdb->get_results( $sql_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( ! empty( $results ) ) {
				$results                 = array_map( function ( $item ) {
					return [ 'id' => $item['referrers'], 'label' => $item['referrers'], 'value' => absint( $item['total_count'] ) ];
				}, $results );
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
			$query      = "DELETE FROM " . $conv_table . " WHERE type = 1 AND source IN (" . esc_sql( $entry_ids ) . ")";
			$wpdb->query( $query );//phpcs:ignore
		}

		public function wffn_delete_funnel_contacts( $c_ids, $funnel_id ) {
			$c_ids        = is_array( $c_ids ) ? implode( ',', $c_ids ) : $c_ids;
			$funnel_query = ( absint( $funnel_id ) > 0 ) ? esc_sql( " AND fid = " . $funnel_id . " " ) : '';
			if ( empty( $c_ids ) ) {
				return;
			}

			global $wpdb;
			$conv_table = $wpdb->prefix . $this->table;
			$query      = "DELETE FROM " . $conv_table . " WHERE contact_id IN (" . esc_sql( $c_ids ) . ") " . $funnel_query;
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
			$tracking_data               = [];
			$tracking_data['campaign']   = [
				'source'  => '',
				'medium'  => '',
				'name'    => '',
				'term'    => '',
				'content' => '',
			];
			$tracking_data['conversion'] = [
				'click_id' => '',
				'referrer' => '',
				'order_id' => '',
				'device'   => '',
				'browser'  => '',
				'country'  => '',
				'time'     => ''
			];

			$tracking_data['overview'] = [
				'aov'      => 0,
				'bump'     => 0,
				'upsell'   => 0,
				'checkout' => 0,
				'total'    => 0
			];

			if ( ! empty( $contact_id ) ) {
				$oid_query  = ( $source_id > 0 ) ? esc_sql( ' AND source = ' . $source_id . ' ' ) : '';
				$conv_table = $wpdb->prefix . $this->table;
				$query      = "SELECT *, (CASE WHEN TIMESTAMPDIFF( SECOND, first_click, timestamp ) != 0 THEN TIMESTAMPDIFF( SECOND, first_click, timestamp )/COUNT(id) ELSE 0 END ) as 'seconds' FROM " . $conv_table . " AS tracking WHERE tracking.contact_id = " . esc_sql( $contact_id ) . " " . $oid_query . " ORDER BY tracking.timestamp asc";

				$data = $wpdb->get_row( $query );//phpcs:ignore

				$db_error = WFFN_Common::maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					return $db_error;
				}

				if ( ! empty( $data ) ) {
					unset( $data->id, $data->contact_id );

					$tracking_data['campaign']   = [
						'source'  => ! empty( $data->utm_source ) ? $data->utm_source : '',
						'medium'  => ! empty( $data->utm_medium ) ? $data->utm_medium : '',
						'name'    => ! empty( $data->utm_campaign ) ? $data->utm_campaign : '',
						'term'    => ! empty( $data->utm_term ) ? $data->utm_term : '',
						'content' => ! empty( $data->utm_content ) ? $data->utm_content : '',
					];
					$tracking_data['conversion'] = [
						'funnel_id'     => ! empty( $data->funnel_id ) ? $data->funnel_id : 0,
						'click_id'      => ! empty( $data->click_id ) ? $data->click_id : '',
						'referrer'      => ! empty( $data->referrer ) ? $data->referrer : '',
						'source_id'     => ! empty( $data->source ) ? $data->source : '',
						'device'        => ! empty( $data->device ) ? ucfirst( $data->device ) : '',
						'browser'       => ! empty( $data->browser ) ? $data->browser : '',
						'country'       => ! empty( $data->country ) ? $data->country : '',
						'first_click'   => ( ! empty( $data->first_click ) && '0000-00-00 00:00:00' !== $data->first_click ) ? $data->first_click : '',
						'aov_in_second' => ! empty( $data->seconds ) ? human_time_diff( current_time( 'timestamp' ), current_time( 'timestamp' ) + absint( $data->seconds ) ) : '',

					];
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
				if ( ! in_array( absint( wffn_conversion_tracking_migrator()->get_upgrade_state() ), [ 3, 4 ], true ) ) {
					$data['join']        = " LEFT JOIN {$conv_table} as tracking ON tracking.source = aero_stats.order_id ";
					$data['case_string'] = ", tracking.*, " . $this->get_conversion_cases_string();
				} else {
					$data['case_string'] = ", tracking.utm_source,tracking.utm_medium,tracking.utm_campaign,tracking.utm_term,tracking.utm_content,tracking.source, tracking.device, " . $this->get_conversion_cases_string();

				}
			}

			if ( 'optin' === $type ) {
				$data['case_string'] = ", (CASE WHEN TIMESTAMPDIFF( SECOND, tracking.first_click, tracking.timestamp ) != 0 THEN TIMESTAMPDIFF( SECOND, tracking.first_click, tracking.timestamp ) ELSE 0 END ) as 'convert_time', tracking.utm_source,tracking.utm_medium,tracking.utm_campaign,tracking.utm_term,tracking.utm_content,tracking.source, tracking.device, " . $this->get_conversion_cases_string();
			}

			$data['where_query'] = $this->get_utm_data_query( $where_query, $filters );

			return $data;
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
			$limit = esc_sql( $limit );
			if ( WFFN_Common::is_wc_hpos_enabled() ) {
				$s_query = "SELECT conv.utm_campaign AS campaign, COUNT(DISTINCT conv.id) as conversion, ROUND(SUM(order_t.total_amount), 2) as revenue FROM " . $conv_table . " AS conv 
                LEFT JOIN " . $wpdb->prefix . "wc_orders AS order_t ON conv.source = order_t.id WHERE conv.type = 2 AND conv.utm_campaign != '' " . $date_query . " GROUP BY conv.utm_campaign ORDER BY revenue DESC LIMIT " . $limit;

			} else {
				$s_query = "SELECT conv.utm_campaign AS campaign, COUNT(DISTINCT conv.id) as conversion, ROUND(SUM( order_t.total_sales ), 2) as revenue FROM " . $conv_table . " AS conv 
                     LEFT JOIN " . $wpdb->prefix . "wc_order_stats AS order_t ON conv.source = order_t.order_id WHERE conv.type = 2 AND conv.utm_campaign != '' " . $date_query . " GROUP BY conv.utm_campaign ORDER BY revenue DESC LIMIT " . $limit;
			}

			$s_result = $wpdb->get_results( $s_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( is_array( $s_result ) && count( $s_result ) > 0 ) {

				foreach ( $s_result as &$s_item ) {

					/**
					 * Get all source data based by campaign
					 */
					if ( WFFN_Common::is_wc_hpos_enabled() ) {
						$source_query = "SELECT DISTINCT conv.utm_source as u_source, COUNT(DISTINCT conv.id) as conversion, ROUND(SUM(order_t.total_amount), 2 ) as revenue FROM " . $conv_table . " AS conv 
                              LEFT JOIN " . $wpdb->prefix . "wc_orders AS order_t ON conv.source = order_t.id 
                              WHERE conv.utm_campaign = '" . $s_item['campaign'] . "' AND conv.type = 2 AND conv.utm_source !='' " . $date_query . " GROUP BY u_source ORDER BY revenue DESC";
					} else {
						$source_query = "SELECT DISTINCT conv.utm_source as u_source, COUNT(DISTINCT conv.id) as conversion, ROUND(SUM( order_t.total_sales ), 2 ) as revenue FROM " . $conv_table . " AS conv 
                              LEFT JOIN " . $wpdb->prefix . "wc_order_stats AS order_t ON conv.source = order_t.order_id 
                              WHERE conv.utm_campaign = '" . $s_item['campaign'] . "' AND conv.type = 2 AND conv.utm_source !='' " . $date_query . " GROUP BY u_source ORDER BY revenue DESC";
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
						$medium_query = "SELECT DISTINCT conv.utm_medium as u_medium, COUNT(DISTINCT conv.id) as conversion, ROUND(SUM(order_t.total_amount), 2 ) as revenue FROM " . $conv_table . " AS conv 
                              LEFT JOIN " . $wpdb->prefix . "wc_orders AS order_t ON conv.source = order_t.id 
                              WHERE conv.utm_campaign = '" . $s_item['campaign'] . "' AND conv.type = 2 AND conv.utm_medium !='' " . $date_query . " GROUP BY u_medium ORDER BY revenue DESC";
					} else {
						$medium_query = "SELECT DISTINCT conv.utm_medium as u_medium, COUNT(DISTINCT conv.id) as conversion, ROUND(SUM( order_t.total_sales ), 2 ) as revenue FROM " . $conv_table . " AS conv 
                              LEFT JOIN " . $wpdb->prefix . "wc_order_stats AS order_t ON conv.source = order_t.order_id 
                              WHERE conv.utm_campaign = '" . $s_item['campaign'] . "' AND conv.type = 2 AND conv.utm_medium !='' " . $date_query . " GROUP BY u_medium ORDER BY revenue DESC";
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
			$op_query = "SELECT conv.utm_campaign AS campaign, COUNT(DISTINCT conv.id) as conversion, 0 as revenue FROM " . $conv_table . " AS conv 
                     WHERE conv.type = 1 AND conv.utm_campaign != '' GROUP BY conv.utm_campaign ORDER BY conversion DESC LIMIT " . $limit;

			$op_result = $wpdb->get_results( $op_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( is_array( $op_result ) && count( $op_result ) > 0 ) {

				foreach ( $op_result as &$o_item ) {

					/**
					 * Get all source and medium data based by campaign
					 */
					$os_query  = "SELECT DISTINCT conv.utm_source as u_source, COUNT(DISTINCT conv.id) as conversion, 0 as revenue FROM " . $conv_table . " AS conv 
                          WHERE conv.utm_campaign = '" . $o_item['campaign'] . "' AND conv.type = 1 GROUP BY u_source ORDER BY conversion DESC";
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
					$os_query  = "SELECT DISTINCT conv.utm_medium as u_medium, COUNT(DISTINCT conv.id) as conversion, 0 as revenue FROM " . $conv_table . " AS conv 
                              WHERE conv.utm_campaign = '" . $o_item['campaign'] . "' AND conv.type = 1 GROUP BY u_medium ORDER BY conversion DESC";
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
				'sales' => [],
				'lead'  => []
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
			$s_query     = "SELECT {$case_string} , COUNT(id) as 'orders',ROUND( SUM(value), 2 ) as total_revenue FROM " . $conv_table . " WHERE 1=1 AND type = 2 " . $date . " GROUP BY referrers";


			$s_result   = $wpdb->get_results( $s_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$db_error   = WFFN_Common::maybe_wpdb_error( $wpdb );
			$sale_data  = [];
			$optin_data = [];
			foreach ( $refs as $ref_Key => $ref ) {


				$ref_index                = strtolower( str_replace( " ", "-", $ref_Key ) );
				$sale_data[ $ref_index ]  = array(
					'ref_key'       => strtolower( str_replace( " ", "-", $ref_Key ) ),
					'referrers'     => ucwords( $ref_Key ),
					'orders'        => 0,
					'percentage'    => 0,
					'total_revenue' => 0,
				);
				$optin_data[ $ref_index ] = array(
					'ref_key'       => strtolower( str_replace( " ", "-", $ref_Key ) ),
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
					$referrer = strtolower( str_replace( " ", "-", $referrer ) );
					if ( isset( $sale_data[ $referrer ] ) ) {
						$s_result[ $k ]['ref_key']   = strtolower( str_replace( " ", "-", $s_result[ $k ]['referrers'] ) );
						$s_result[ $k ]['referrers'] = ucwords( $s_result[ $k ]['referrers'] );
						$sale_data[ $referrer ]      = $s_result[ $k ];
					}
				}
				$data['sales'] = array_values( $sale_data );
			}
			$case_string = $this->get_conversion_cases_string( true );
			$op_query    = "SELECT {$case_string}, COUNT(id) as 'conversion' FROM " . $conv_table . " WHERE 1=1 AND type = 1 " . $date . " GROUP BY referrers";

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
					$referrer = strtolower( str_replace( " ", "-", $referrer ) );
					if ( isset( $optin_data[ $referrer ] ) ) {
						$op_result[ $k ]['ref_key']   = strtolower( str_replace( " ", "-", $op_result[ $k ]['referrers'] ) );
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
				$query_array[] = "(tracking.utm_campaign IN (" . $utm_campaign . "))";
			}

			// utm_source Type Filter
			if ( ! empty( $filters['utm_source']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_source']['data'] );
				$query_array[] = "(tracking.utm_source IN (" . $utm_campaign . "))";
			}

			// utm_medium Type Filter
			if ( ! empty( $filters['utm_medium']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_medium']['data'] );
				$query_array[] = "(tracking.utm_medium IN (" . $utm_campaign . "))";
			}
			// utm_term Type Filter
			if ( isset( $filters['utm_term'] ) && isset( $filters['utm_term']['data'] ) && ! empty( $filters['utm_term']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_term']['data'] );
				$query_array[] = "(tracking.utm_term IN (" . $utm_campaign . "))";
			}
			// utm_content Type Filter
			if ( isset( $filters['utm_content'] ) && isset( $filters['utm_content']['data'] ) && ! empty( $filters['utm_content']['data'] ) ) {
				$utm_campaign  = $this->add_string_quote( $filters['utm_content']['data'] );
				$query_array[] = "(tracking.utm_content IN (" .  $utm_campaign  . "))";
			}

			// referrer Filters
			if ( ! empty( $filters['utm_referrer']['data'] ) ) {
				$referrer = $filters['utm_referrer']['data'];

				$refs_all  = WFFN_Common::get_refs();
				$other_set = $refs_all;
				$is_other  = false;

				if ( ! in_array( 'all', $referrer, true ) ) {
					$referrer_where = [];
					$domain_string  = [];

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

			//device filters
			if ( ! empty( $filters['device']['data'] ) ) {
				$device = $filters['device']['data'];

				$device_types = [
					'Desktop' => 'desktop',
					'Mobile'  => 'mobile',
				];

				if ( ! in_array( 'all', $device, true ) ) {
					$device_where = [];

					$selected_types = array_map(function($dev) use ($device_types) {
						return $device_types[$dev] ?? null;
					}, $device);

					$selected_types = array_filter($selected_types);

					if ( !empty($selected_types) ) {
						$domain_string = implode('|', $selected_types);
						$device_where[] = "(tracking.device REGEXP '{$domain_string}')";

						$query_array[] = '(' . implode( ' OR ', $device_where ) . ')';
					}
				}
			}

			return $query_array;
		}

		public function update_data_localize_data( $args ) {

			if ( ! is_array( $args ) ) {
				return $args;
			}

			if ( true === wffn_string_to_bool( BWF_Admin_General_Settings::get_instance()->get_option( 'track_utms' ) ) ) {
				$args['cookieKeys'] = array_merge( $args['cookieKeys'], [ "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content" ] );
			}

			return $args;

		}

		public function get_conversion_cases_string( $is_other = false ) {
			if ( ! method_exists( 'WFFN_Common', 'get_refs' ) ) {
				return ' referrer as referrers ';
			}
			$refs = WFFN_Common::get_refs();

			$cases = [];
			foreach ( $refs as $name => $lists ) {
				$cases[] = implode( ' ', array_map( function ( $domain ) use ( $name ) {

					if ( false !== strpos( $domain, '://' ) ) {
						$domain = str_replace( '://', '', $domain );

						return "WHEN referrer LIKE '{$domain}' THEN '{$name}'";
					} else {
						return "WHEN referrer LIKE '%{$domain}%' THEN '{$name}'";
					}

				}, $lists ) );
			}
			$cases = implode( " ", $cases );

			if ( false === $is_other ) {
				return "( CASE WHEN referrer = '' THEN 'Direct' {$cases} ELSE referrer END ) as 'referrers'";

			} else {
				return "( CASE WHEN referrer = '' THEN 'Direct' {$cases} WHEN referrer != '' THEN 'others' ELSE referrer END) as 'referrers'";

			}
		}

		public function add_string_quote( $string ) {
			$string = explode( ',', $string );

			return "'" . esc_sql( implode( "', '", $string ) ) . "'";
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