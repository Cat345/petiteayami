<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BWFABT_REST_Analytics
 *
 * * @extends BWFABT_REST_Controller
 */
if ( ! class_exists( 'BWFABT_REST_Analytics' ) ) {
	#[AllowDynamicProperties]
	class BWFABT_REST_Analytics extends BWFABT_REST_Controller {

		public static $_instance = null;

		/**
		 * Route base.
		 *
		 * @var string
		 */

		protected $namespace = 'funnelkit-app';
		protected $rest_base = 'experiment';
		protected $rest_base_id = 'experiment/(?P<experiment_id>[\d]+)';

		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new self;
			}

			return self::$_instance;
		}

		/**
		 * Register the routes for taxes.
		 */
		public function register_routes() {
			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/stats/', array(
				array(
					'args'                => $this->get_stats_collection(),
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
			) );
			register_rest_route( $this->namespace, '/' . $this->rest_base_id . '/variants/', array(
				array(
					'args'                => [],
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all_variants' ),
					'permission_callback' => array( $this, 'get_read_api_permission_check' ),
				),
			) );
		}

		public function get_read_api_permission_check() {
			if ( BWFABT_Core()->role->user_access( 'analytics', 'read' ) ) {
				return true;
			}
			return false;
		}

		public function get_stats( $request ) {

			$response = array();
			$totals   = $this->prepare_item_for_response( $request );
			if ( is_array( $totals ) ) {
				$response['totals'] = $totals;

				$response['intervals'] = $this->prepare_item_for_response( $request, 'interval' );
			}

			return rest_ensure_response( $response );
		}

		public function prepare_item_for_response( $request, $is_interval = '' ) {

			$start_date    = ( isset( $request['after'] ) && '' !== $request['after'] ) ? $request['after'] : self::default_date( WEEK_IN_SECONDS )->format( self::$sql_datetime_format );
			$end_date      = ( isset( $request['before'] ) && '' !== $request['before'] ) ? $request['before'] : self::default_date()->format( self::$sql_datetime_format );
			$int_request   = ( isset( $request['interval'] ) && '' !== $request['interval'] ) ? $request['interval'] : 'week';
			$experiment_id = ( isset( $request['experiment_id'] ) && '' !== $request['experiment_id'] ) ? (int) $request['experiment_id'] : 0;
			$result        = [];
			$experiment    = BWFABT_Core()->admin->get_experiment( $experiment_id );

			if ( ! $experiment instanceof BWFABT_Experiment || ( 0 >= $experiment->get_id() && $experiment->get_variants() === 0 ) ) {
				return $result;
			}

			$get_result = $this->get_unique_visits( $experiment, $start_date, $end_date, $is_interval, $int_request );

			if ( ! is_array( $get_result ) || isset( $get_result['db_error'] ) ) {
				return $get_result;
			}

			$intervals = array();
			if ( ! empty( $is_interval ) ) {
				$intervals_all = $this->intervals_between( $start_date, $end_date, $int_request );
				foreach ( $intervals_all as $all_interval ) {
					$interval   = $all_interval['time_interval'];
					$start_date = $all_interval['start_date'];
					$end_date   = $all_interval['end_date'];

					$interval_data = $this->maybe_interval_exists( $get_result, 'time_interval', $interval );

					$get_total_visit = is_array( $interval_data ) ? $interval_data[0]['unique_views'] : 0;
					$sub_conversion  = is_array( $interval_data ) ? $interval_data[0]['converted'] : 0;
					$sub_revenue     = is_array( $interval_data ) ? $interval_data[0]['revenue'] : 0;

					$intervals['interval']       = $interval;
					$intervals['start_date']     = $start_date;
					$intervals['date_start_gmt'] = $this->convert_local_datetime_to_gmt( $start_date )->format( self::$sql_datetime_format );
					$intervals['end_date']       = $end_date;
					$intervals['date_end_gmt']   = $this->convert_local_datetime_to_gmt( $end_date )->format( self::$sql_datetime_format );

					$intervals['subtotals'] = [
						'total_views'           => is_null( $get_total_visit ) ? 0 : $get_total_visit,
						'total_conversion'      => is_null( $sub_conversion ) ? 0 : $sub_conversion,
						'conversion_rate'       => ( absint( $get_total_visit ) !== 0 ) ? $this->get_percentage( $get_total_visit, $sub_conversion ) : 0,
						'total_revenue'         => is_null( $sub_revenue ) ? 0 : $sub_revenue,
						'avg_revenue_per_visit' => ( absint( $get_total_visit ) !== 0 ) ? round( $sub_revenue / $get_total_visit, 2 ) : 0,
					];


					$result[] = $intervals;

				}

			} else {

				$total_visits  = $get_result[0]['unique_views'];
				$conversion    = $get_result[0]['converted'];
				$total_revenue = $get_result[0]['revenue'];
				$result        = [
					'total_views'           => is_null( $total_visits ) ? 0 : $total_visits,
					'total_conversion'      => is_null( $conversion ) ? 0 : $conversion,
					'conversion_rate'       => ( absint( $total_visits ) !== 0 ) ? $this->get_percentage( $total_visits, $conversion ) : 0,
					'total_revenue'         => is_null( $total_revenue ) ? 0 : $total_revenue,
					'avg_revenue_per_visit' => ( absint( $total_visits ) !== 0 ) ? round( $total_revenue / $total_visits, 2 ) : 0,
				];
			}

			return $result;

		}

		public function get_unique_visits( $experiment, $start_date, $end_date, $is_interval, $int_request ) {

			global $wpdb;
			$table          = $wpdb->prefix . 'wfco_report_views';
			$date_col       = "date";
			$interval_query = '';
			$group_by       = '';
			$result         = '';


			$variants = $this->maybe_variants( $experiment->get_variants() );
			if ( ! is_array( $variants ) || count( $variants ) === 0 ) {
				return $result;
			}

			$step_ids   = esc_sql( implode( ',', $variants ) );
			$start_date = esc_sql( $start_date );
			$end_date   = esc_sql( $end_date );

			if ( 'interval' === $is_interval ) {
				$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
				$interval_query = $get_interval['interval_query'];
				$interval_group = $get_interval['interval_group'];
				$group_by       = "GROUP BY " . $interval_group;

			}

			if ( 'landing' === $experiment->get_type() ) {
				$get_query = "SELECT object_id, SUM(CASE WHEN type = 13 THEN `no_of_sessions` END) AS `unique_views`, SUM(CASE WHEN type = 14 THEN `no_of_sessions` END) AS `converted`, 0 AS revenue " . $interval_query . " FROM  " . $table . "  WHERE object_id IN (" . $step_ids . ") AND " . $date_col . " BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY object_id ASC";
				$result    = $wpdb->get_results( $get_query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( method_exists( 'BWFABT_Admin', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}
			}

			if ( 'optin' === $experiment->get_type() ) {
				$optin_sql = "SELECT object_id, SUM(CASE WHEN type = 16 THEN `no_of_sessions` END) AS `unique_views`, 0 AS `converted`, 0 AS revenue " . $interval_query . " FROM  " . $table . "  WHERE object_id IN (" . $step_ids . ") AND " . $date_col . " BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY object_id ASC";
				$result = $wpdb->get_results( $optin_sql, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}

				if ( ! is_array( $result ) || count( $result ) === 0 ) {
					return $result;
				}

				$sql = "SELECT step_id as 'object_id', COUNT(id) as cn " . $interval_query . " FROM `" . $wpdb->prefix . "bwf_optin_entries` WHERE step_id IN (" . $step_ids . ") AND date BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY step_id ASC";
				$get_data = $wpdb->get_results( $sql, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}

				if ( is_array( $get_data ) && count( $get_data ) > 0 ) {
					foreach ( $get_data as $item ) {
						foreach ( $result as &$i ){
							if( ! isset( $item['time_interval'] ) && $item['object_id'] === $i['object_id'] ){
								$i['converted'] = (int) $item['cn'];
							}
							if( isset( $item['time_interval'] ) && $i['time_interval'] && $item['object_id'] === $i['object_id'] ){
								if ( $item['time_interval'] === $i['time_interval'] ) {
									$i['converted'] = (int) $item['cn'];
								}
							}
						}
					}
				}

			}

			if ( 'optin_ty' === $experiment->get_type() ) {
				$get_query = "SELECT object_id, SUM(CASE WHEN type = 17 THEN `no_of_sessions` END) AS `unique_views` ,SUM(CASE WHEN type = 18 THEN `no_of_sessions` END) AS `converted`, 0 AS revenue " . $interval_query . " FROM  " . $table . "  WHERE object_id IN (" . $step_ids . ") AND " . $date_col . " BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY object_id ASC";
				$result    = $wpdb->get_results( $get_query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( method_exists( 'BWFABT_Admin', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}
			}

			if ( 'thank_you' === $experiment->get_type() ) {
				$get_query = "SELECT object_id, SUM(CASE WHEN type = 15 THEN `no_of_sessions` END) AS `unique_views`, 0 AS `converted`, 0 AS revenue " . $interval_query . " FROM " . $table . " WHERE object_id IN (" . $step_ids . ") AND " . $date_col . " BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY object_id ASC";
				$result    = $wpdb->get_results( $get_query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( method_exists( 'BWFABT_Admin', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}
			}

			if ( 'aero' === $experiment->get_type() ) {
				$aero_sql = "SELECT object_id, SUM( CASE WHEN type = 12 THEN `no_of_sessions` END ) AS `unique_views`, 0 AS `converted`, 0 AS revenue " . $interval_query . " FROM " . $table . "  WHERE object_id IN (" . $step_ids . ") AND " . $date_col . " BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY object_id ASC";
				$result = $wpdb->get_results( $aero_sql, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}

				if ( ! is_array( $result ) || count( $result ) === 0 ) {
					return $result;
				}

				$sql = "SELECT wfacp_id as 'object_id', SUM(total_revenue) as 'total_revenue', COUNT(ID) as cn " . $interval_query . " FROM " . $wpdb->prefix . 'wfacp_stats' . " WHERE wfacp_id IN (" . $step_ids . ") AND " . $date_col . " BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY wfacp_id ASC";
				$get_data = $wpdb->get_results( $sql, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}

				if ( is_array( $get_data ) && count( $get_data ) > 0 ) {
					foreach ( $get_data as $item ) {
						foreach ( $result as &$i ){
							if( ! isset( $item['time_interval'] ) && $item['object_id'] === $i['object_id'] ){
								$i['revenue']     = $item['total_revenue'];
								$i['converted'] = (int) $item['cn'];
							}
							if( isset( $item['time_interval'] ) && $i['time_interval'] && $item['object_id'] === $i['object_id'] ){
								if ( $item['time_interval'] === $i['time_interval'] ) {
									$i['revenue']   = $item['total_revenue'];
									$i['converted'] = (int) $item['cn'];
								}
							}
						}
					}
				}

			}

			if ( 'order_bump' === $experiment->get_type() ) {
				$bump_sql = "SELECT bid as 'object_id', COUNT(ID) AS `unique_views`, COUNT(CASE WHEN converted = 1 THEN 1 END) AS `converted`, SUM(total) AS revenue " . $interval_query . " FROM " . $wpdb->prefix . 'wfob_stats' . " WHERE bid IN (" . $step_ids . ") AND " . $date_col . " BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY bid ASC";
				$result = $wpdb->get_results( $bump_sql, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}

			}

			if ( 'upstroke' === $experiment->get_type() ) {
				$interval_query = str_replace('date', 'events.timestamp',$interval_query);
				$upsell_sql = "SELECT object_id, COUNT(CASE WHEN action_type_id = 2 THEN 1 END) AS `unique_views`, COUNT(CASE WHEN action_type_id = 4 THEN 1 END) AS `converted`, SUM(value) as revenue " . $interval_query . " FROM " . $wpdb->prefix . 'wfocu_event' . "  as events INNER JOIN " . $wpdb->prefix . 'wfocu_event_meta' . " AS events_meta__funnel_id ON ( events.ID = events_meta__funnel_id.event_id ) AND ( ( events_meta__funnel_id.meta_key   = '_funnel_id' AND events_meta__funnel_id.meta_value IN (" . $step_ids . ") )) AND (events.action_type_id = '2' OR events.action_type_id = '4' ) AND events.timestamp BETWEEN '" . $start_date . "' AND '" . $end_date . "' " . $group_by . " ORDER BY events.object_id ASC"; //GROUP BY events.object_id";

				$result = $wpdb->get_results( $upsell_sql, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
					$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
					if ( true === $db_error['db_error'] ) {
						return $db_error;
					}
				}

			}

			return $result;
		}


		public function get_all_variants( $request ) {
			$experiment_id = (int) $request['experiment_id'];//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$result     = [
				'status'  => false,
				'message' => __( 'No variant found', 'woofunnels-ab-tests' )
			];
			$experiment    = BWFABT_Core()->admin->get_experiment( $experiment_id );

			if ( $experiment instanceof BWFABT_Experiment && 0 < $experiment->get_id() && count( $experiment->get_variants() ) > 0 && absint($experiment->status) !== 1 ) {

				if ( 'landing' === $experiment->get_type() ) {
					$result = $this->get_all_landing( $experiment );
				}

				if ( 'optin' === $experiment->get_type() ) {
					$result = $this->get_all_optins( $experiment );
				}

				if ( 'optin_ty' === $experiment->get_type() ) {
					$result = $this->get_all_op_thankyou( $experiment );
				}

				if ( 'thank_you' === $experiment->get_type() ) {
					$result = $this->get_all_thankyou( $experiment );
				}

				if ( 'aero' === $experiment->get_type() ) {
					$result = $this->get_all_checkout( $experiment );
				}

				if ( 'order_bump' === $experiment->get_type() ) {
					$result = $this->get_all_bump( $experiment );
				}

				if ( 'upstroke' === $experiment->get_type() ) {
					$result = $this->get_all_upsell( $experiment );
				}

				if ( 'offer' === $experiment->get_type() ) {
					$result = $this->get_all_offer( $experiment );
				}

			}

			return rest_ensure_response( $result );
		}

		public function get_all_landing( $experiment ) {
			$data      = [];
			$get_steps = $this->maybe_variants( $experiment->get_variants(), true );

			if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
				return $data;
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			if ( ! $get_controller instanceof BWFABT_Controller ) {
				return $data;
			}

			$data = $get_controller->get_analytics_data( $get_steps, $experiment->get_id() );

			return $this->maybe_complete_experiment( $data, $experiment );
		}

		public function get_all_optins( $experiment ) {
			$data = [];
			$get_steps = $this->maybe_variants( $experiment->get_variants(), true );

			if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
				return $data;
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			if ( ! $get_controller instanceof BWFABT_Controller ) {
				return $data;
			}

			$data = $get_controller->get_analytics_data( $get_steps,  $experiment->get_id() );

			return $this->maybe_complete_experiment( $data, $experiment );

		}

		public function get_all_op_thankyou( $experiment ) {
			$data = [];
			$get_steps = $this->maybe_variants( $experiment->get_variants(), true );

			if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
				return $data;
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			if ( ! $get_controller instanceof BWFABT_Controller ) {
				return $data;
			}

			$data = $get_controller->get_analytics_data( $get_steps,  $experiment->get_id() );

			return $this->maybe_complete_experiment($data, $experiment );
		}

		public function get_all_thankyou( $experiment ) {
			$data = [];

			$get_steps = $this->maybe_variants( $experiment->get_variants(), true );

			if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
				return $data;
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			if ( ! $get_controller instanceof BWFABT_Controller ) {
				return $data;
			}

			$data = $get_controller->get_analytics_data( $get_steps,  $experiment->get_id() );

			return $this->maybe_complete_experiment($data, $experiment );

		}

		public function get_all_checkout( $experiment ) {
			$data = [];
			if ( version_compare( WFACP_VERSION, '2.0.7', '<' ) ) {
				return $data;
			}

			$get_steps = $this->maybe_variants( $experiment->get_variants(), true );

			if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
				return $data;
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			if ( ! $get_controller instanceof BWFABT_Controller ) {
				return $data;
			}

			$data = $get_controller->get_analytics_data( $get_steps,  $experiment->get_id() );

			return $this->maybe_complete_experiment($data, $experiment );
		}

		public function get_all_bump( $experiment ) {
			$data = [];
			if ( class_exists( 'WFOB_Core' ) && version_compare( WFOB_VERSION, '1.8,1', '<=' ) ) {
				return $data;
			}

			$get_steps = $this->maybe_variants( $experiment->get_variants(), true );

			if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
				return $data;
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			if ( ! $get_controller instanceof BWFABT_Controller ) {
				return $data;
			}

			$data = $get_controller->get_analytics_data( $get_steps, $experiment->get_id() );

			return $this->maybe_complete_experiment($data, $experiment );

		}

		public function get_all_upsell( $experiment ) {
			$data = [];
			if ( class_exists( 'WFOCU_Core' ) && version_compare( WFOCU_VERSION, '2.2.0', '<' ) ) {
				return $data;
			}

			$get_steps = $this->maybe_variants( $experiment->get_variants(), true );

			if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
				return $data;
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			if ( ! $get_controller instanceof BWFABT_Controller ) {
				return $data;
			}

			$data = $get_controller->get_analytics_data( $get_steps, $experiment->get_id() );

			return $this->maybe_complete_experiment($data, $experiment );
		}

		public function get_all_offer( $experiment ) {
			$data = [];
			if ( class_exists( 'WFOCU_Core' ) && version_compare( WFOCU_VERSION, '2.2.0', '<' ) ) {
				return $data;
			}

			$get_steps = $this->maybe_variants( $experiment->get_variants(), true );

			if ( ! is_array( $get_steps ) || count( $get_steps ) === 0 ) {
				return $data;
			}

			$get_controller = BWFABT_Core()->controllers->get_integration( $experiment->get_type() );
			if ( ! $get_controller instanceof BWFABT_Controller ) {
				return $data;
			}

			$data = $get_controller->get_analytics_data( $get_steps, $experiment->get_id() );

			return $this->maybe_complete_experiment($data, $experiment );
		}

		public function maybe_variants( $variants, $control = false ) {

			$query_variant_ids = array();
			if ( count( $variants ) < 1 ) {
				return $variants;
			}

			$variants = BWFABT_Core()->admin->move_controller_on_top( $variants );

			foreach ( array_keys( $variants ) as $v_key => $variant_id ) {
				$query_variant_ids[ $v_key ] = $variant_id;

				if( true === $control ) {
					$control_data = get_post_meta( $variant_id, '_bwf_ab_control', true );
					$control_id   = ( is_array( $control_data ) && isset( $control_data['control_id'] ) ) ? intval( $control_data['control_id'] ) : 0;
					if ( $control_id > 0 ) {
						$query_variant_ids[ $v_key ] = $control_id;
					}
				}
			}

			return $query_variant_ids;
		}

		/**
		 * @param $data
		 * @param $experiment
		 * After declare winner set analytics on newly created control
		 * @return mixed
		 */
		public function maybe_complete_experiment( $data, $experiment ){
			$current_variants = $this->maybe_variants( $experiment->get_variants(), false );

			if ( is_array( $current_variants ) && count( $current_variants ) > 0 ) {
				foreach ( $current_variants as $current_variant ) {
					$is_control = get_post_meta( $current_variant, '_bwf_ab_variation_of', true );

					if ( $is_control > 0 && ! in_array( $is_control, $current_variants ) && isset( $data[ $is_control ] ) && !isset( $data[ $current_variant ] ) ) { //phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
						$data[ $current_variant ] = $data[ $is_control ];
						unset( $data[ $is_control ] );
					}

				}
			}

			return $data;
		}

		public function get_stats_collection() {
			$params = array();

			$params['after']  = array(
				'type'              => 'string',
				'format'            => 'date-time',
				'validate_callback' => 'rest_validate_request_arg',
				'description'       => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
			);
			$params['before'] = array(
				'type'              => 'string',
				'format'            => 'date-time',
				'validate_callback' => 'rest_validate_request_arg',
				'description'       => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woofunnels-upstroke-one-click-upsell' ),
			);

			$params['interval'] = array(
				'type'              => 'string',
				'default'           => 'week',
				'validate_callback' => 'rest_validate_request_arg',
				'description'       => __( 'Time interval to use for buckets in the returned data.', 'woofunnels-upstroke-one-click-upsell' ),
				'enum'              => array(
					'hour',
					'day',
					'week',
					'month',
					'quarter',
					'year',
				),
			);

			return apply_filters( 'bwfabt_rest_analytics_stats_collection', $params );
		}

		public function sanitize_custom( $data ) {

			return json_decode( $data, true );
		}


		/**
		 * Get percentage of a given number against a total
		 *
		 * @param float|int $total total number of occurrences
		 * @param float|int $number the number to get percentage against
		 *
		 * @return float|int
		 */
		function get_percentage( $total, $number ) {
			if ( $total > 0 ) {
				return round( $number / ( $total / 100 ), 2 );
			} else {
				return 0;
			}
		}


	}

	if ( ! function_exists( 'bwfabt_rest_analytics' ) ) {

		function bwfabt_rest_analytics() {  //@codingStandardsIgnoreLine
			return BWFABT_REST_Analytics::get_instance();
		}
	}

	bwfabt_rest_analytics();
}
