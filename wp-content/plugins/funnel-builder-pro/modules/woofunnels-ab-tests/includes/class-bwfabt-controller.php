<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Controller' ) ) {
	/**
	 * This class will be extended by all all single controller(like upstroke, aero etc) to register different controllers
	 * Class BWFABT_Controller
	 */
	#[AllowDynamicProperties]
	abstract class BWFABT_Controller {

		public $type = '';

		public $bucketing = true;
		public $view_support = false;

		public function __construct() {
			add_filter( 'wffn_maybe_get_ab_control', [ $this, 'maybe_return_ab_control' ] );
		}

		/**
		 * Get Controller's title, overridden by individual controller to provide title like (UpStroke, Aero etc)
		 * @return string
		 */
		public function get_title() {
			return '';
		}

		/**
		 * Provides all active controls for a called controller type
		 *
		 * @param $term
		 *
		 * @return array
		 */
		public function get_controls( $term ) {
			return array();
		}

		/**
		 * Creating/updating experiments
		 *
		 * @param $data
		 *
		 * @return mixed
		 */
		public function save_experiment( $data ) {
			$experiment_id = ( isset( $data['id'] ) && $data['id'] > 0 ) ? $data['id'] : 0; //Id will be non-zero in case of update
			$experiment    = new BWFABT_Experiment( $experiment_id );

			if ( isset( $data['title'] ) && ! empty( $data['title'] ) && ( $data['title'] !== $experiment->get_title() ) ) {
				$experiment->set_title( $data['title'] );
			}

			if ( isset( $data['status'] ) && ! empty( $data['status'] ) && ( $data['status'] !== $experiment->get_status() ) ) {
				$experiment->set_status( $data['status'] );
			}

			if ( isset( $data['desc'] ) && ! empty( $data['desc'] ) && ( $data['desc'] !== $experiment->get_desc() ) ) {
				$experiment->set_desc( $data['desc'] );
			}

			if ( isset( $data['type'] ) && ! empty( $data['type'] ) && ( $data['type'] !== $experiment->get_type() ) ) {
				$experiment->set_type( $data['type'] );
			}

			if ( isset( $data['date_added'] ) && ! empty( $data['date_added'] ) && ( $data['date_added'] !== $experiment->get_date_added() ) ) {
				$experiment->set_date_added( $data['date_added'] );
			}

			if ( isset( $data['date_started'] ) && ! empty( $data['date_started'] ) && ( $data['date_started'] !== $experiment->get_date_started() ) ) {
				$experiment->set_date_started( $data['date_started'] );
			}

			if ( isset( $data['last_reset_date'] ) && ! empty( $data['last_reset_date'] ) && ( $data['last_reset_date'] !== $experiment->get_last_reset_date() ) ) {
				$experiment->set_last_reset_date( $data['last_reset_date'] );
			}

			if ( isset( $data['date_completed'] ) && ! empty( $data['date_completed'] ) && ( $data['date_completed'] !== $experiment->get_date_completed() ) ) {
				$experiment->set_date_completed( $data['date_completed'] );
			}

			if ( isset( $data['goal'] ) && ! empty( $data['goal'] ) && ( $data['goal'] !== $experiment->get_goal() ) ) {
				$experiment->set_goal( $data['goal'] );
			}

			if ( isset( $data['control'] ) && ! empty( $data['control'] ) && ( $data['control'] !== $experiment->get_control() ) ) {
				$experiment->set_control( $data['control'] );
			}

			if ( isset( $data['variants'] ) && is_array( $data['variants'] ) ) {
				$variants   = $experiment->get_variants();
				$variants[] = $data['variants'];
				$experiment->set_variants( $variants );
			}

			return $experiment->save( array() );

		}

		/**
		 * @param $experiment
		 *
		 * @return bool|mixed
		 */
		public function delete_experiment( $experiment ) {
			$variants = $experiment->get_variants();
			$deleted  = false;
			$ids      = [];
			foreach ( array_keys( $variants ) as $variant_id ) {
				$variant = new BWFABT_Variant( $variant_id, $experiment );
				if ( false === $variant->get_control() ) {
					$ids[]   = $variant_id;
					$deleted = $this->delete_variant( $variant, $experiment, true );
					if ( false === $deleted ) {
						break;
					}
				} else {
					$deleted = true;
				}
			}
			if ( $deleted ) {
				$this->merge_variant_data( $experiment->get_control(), $ids );

				return $experiment->delete();
			}

			return $deleted;
		}

		/**
		 * @param $variant_data
		 *
		 * @return mixed
		 */
		public function add_variant( $variant_data ) {
			return $this->maybe_copy_bwf_in_funnel_meta( $variant_data );
		}

		/**
		 * @param $variant_data
		 *
		 * @return mixed
		 */
		public function duplicate_variant( $variant_data ) {
			$variant_data['control'] = false;

			return $this->maybe_copy_bwf_in_funnel_meta( $variant_data );
		}

		/**
		 * @param $variant_data
		 *
		 * @return mixed
		 */
		public function maybe_copy_bwf_in_funnel_meta( $variant_data ) {
			$variant_id = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;
			$control_id = isset( $variant_data['control_id'] ) ? $variant_data['control_id'] : 0;
			BWFABT_Core()->admin->log( "maybe_copy_bwf_in_funnel_meta: variant id $variant_id, control id: $control_id, variant data: " . print_r( $variant_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			if ( $control_id > 0 ) {
				$is_control_in_funnel = get_post_meta( $control_id, '_bwf_in_funnel', true );
				if ( $is_control_in_funnel > 0 ) {
					update_post_meta( $variant_id, '_bwf_in_funnel', $is_control_in_funnel );
				}
				if ( true !== $variant_data['control'] && $variant_id !== $control_id ) {
					update_post_meta( $variant_id, '_bwf_ab_variation_of', $control_id );
				}
				delete_post_meta( $variant_id, '_bwf_ab_control' );
			}

			return $variant_data;
		}

		/**
		 * @param $variant
		 * @param $experiment
		 * @param false $force
		 *
		 * @return mixed
		 */
		public function delete_variant( $variant, $experiment, $force = false ) {
			return $experiment->delete_variant( $variant, $force );
		}

		/**
		 * @param $traffic_data
		 * @param $experiment_id
		 *
		 * @return bool
		 */
		public function update_traffics( $traffic_data, $experiment_id ) {
			$experiment = BWFABT_Core()->admin->get_experiment( $experiment_id );

			return $experiment->update_traffic( $traffic_data );
		}


		/**
		 * @param BWFABT_Experiment $experiment
		 *
		 * @return mixed
		 */
		public function start_experiment( $experiment ) {
			return $experiment->start();
		}

		/**
		 * @param $experiment
		 *
		 * @return mixed
		 */
		public function stop_experiment( $experiment ) {
			return $experiment->stop();
		}

		/**
		 * @param $control_id
		 *
		 * @return int
		 */
		public function get_running_test_id( $control_id ) {
			$active_test_id = 0;

			if ( ! is_array( $control_id ) && $control_id > 0 ) {

				$where_pairs = array( 'status' => '2', 'control' => $control_id );

				$active_test_id = BWFABT_Core()->get_dataStore()->get_specific_column( 'id', $where_pairs );

				BWFABT_Core()->admin->log( "Active Experiment for control: $control_id is: $active_test_id" );

			}

			return intval( $active_test_id );
		}

		/**
		 * @param $step_id
		 *
		 * @return int
		 */
		public function get_running_test_id_on_step( $step_id ) {
			$control_id = get_post_meta( $step_id, '_bwf_ab_variation_of', true );
			$control_id = ( $control_id > 0 ) ? $control_id : $step_id;

			$running_test_id = 0;
			if ( $control_id > 0 ) {
				$running_test_id = $this->get_running_test_id( $control_id );
			}

			return $running_test_id;
		}

		/**
		 * Found is there we have any running test for the control
		 *
		 * @param $get_active_tests , A set of active experiments for the current type to search upon
		 * @param $control
		 *
		 * @return false|mixed
		 */
		public function get_experiment_to_run( $get_active_tests, $control ) {

			if ( ! is_array( $get_active_tests ) ) {
				return false;
			}

			if ( count( $get_active_tests ) === 0 ) {
				return false;
			}

			foreach ( $get_active_tests as $test ) {
				if ( $test['type'] === $this->type ) {
					return $test;
				}
			}

			return false;
		}

		/**
		 * @param BWFABT_Experiment $experiment
		 *
		 * @return int|string
		 */
		public function get_variation_to_run( $experiment ) {
			$active_variants = $experiment->get_active_variants( true );

			if ( true === $this->bucketing ) {
				$saved_variation = $this->get_bucket_variation( $experiment );
				BWFABT_Core()->admin->log( "Experiment id is in get bucket: {$experiment->get_id()}, control: {$experiment->get_control()}, variant: $saved_variation" );
				if ( ! empty( $saved_variation ) ) {
					return intval( $saved_variation );
				}
			}

			BWFABT_Core()->admin->log( "Active variants are: " . print_r( $active_variants, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			$random = function_exists( 'wp_rand' ) ? wp_rand( 1, 100 ) : rand( 1, 100 );  //phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand

			$decided_variation = $this->decide_final_variation( $random, $active_variants );


			if ( true === $this->bucketing ) {
				$this->set_bucket_variation( $experiment, $decided_variation );
			}
			BWFABT_Core()->admin->log( "Random number generated by wp_rand: $random, Bucketing: $this->bucketing, Decided variation: $decided_variation" );

			return $decided_variation;
		}

		/**
		 * @param $experiment
		 *
		 * @return int
		 */
		public function get_bucket_variation( $experiment ) {
			$cookie_key = 'bwfabt_bucket_variation_' . $experiment->get_id() . '_' . $experiment->get_control();

			return isset( $_COOKIE[ $cookie_key ] ) ? sanitize_text_field( $_COOKIE[ $cookie_key ] ) : 0; //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		}

		/**
		 * @param $experiment
		 * @param $variation
		 */
		public function set_bucket_variation( $experiment, $variation ) {
			$cookie_key = 'bwfabt_bucket_variation_' . $experiment->get_id() . '_' . $experiment->get_control();
			setcookie( $cookie_key, $variation, time() + ( 30 * 24 * 3600 ), '/' ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
			$_COOKIE[ $cookie_key ] = $variation; //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		}

		/**
		 * @param $random_percentage
		 * @param $act_variants
		 *
		 * @return int|string
		 */
		public function decide_final_variation( $random_percentage, $act_variants ) {

			$measurement = $decided = 0;
			foreach ( $act_variants as $key => $variant ) {

				if ( ( $random_percentage >= $measurement ) && ( $random_percentage <= ( $measurement + $variant['traffic'] ) ) ) {

					$decided = $key;
					break;
				}

				$measurement += $variant['traffic'];
			}
			BWFABT_Core()->admin->log( "Decided variation by A/B algo: $decided: " );

			return $decided;
		}

		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return string|void
		 */
		public function get_performance_overview( $experiment, $type ) {
			$get_report = BWFABT_Core()->reports->get_integration( $type );

			if ( ! is_null( $get_report ) && $get_report instanceof BWFABT_Report ) {
				return $get_report->get_performance_overview( $experiment, $type );
			}

			return esc_attr__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return string|void
		 */
		public function localize_chart_data( $experiment, $type ) {
			$get_report = BWFABT_Core()->reports->get_integration( $type );

			if ( ! is_null( $get_report ) && $get_report instanceof BWFABT_Report ) {
				return $get_report->localize_chart_data( $experiment, $type );
			}

			return esc_attr__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}


		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return array|string
		 */
		public function get_analytics( $experiment, $type ) {
			$get_report = BWFABT_Core()->reports->get_integration( $type );

			if ( ! is_null( $get_report ) && $get_report instanceof BWFABT_Report ) {
				return $get_report->get_analytics( $experiment );
			}

			return esc_attr__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		/**
		 * @param BWFABT_Experiment $experiment
		 * @param $variant_id
		 *
		 * @return mixed
		 */
		public function draft_variant( $experiment, $variant_id ) {
			return $experiment->draft_variant( $variant_id );
		}


		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return string|void
		 */
		public function get_choose_winner_table( $experiment, $type ) {

			$get_report = BWFABT_Core()->reports->get_integration( $type );

			if ( ! is_null( $get_report ) && $get_report instanceof BWFABT_Report ) {
				return $get_report->get_choose_winner_table( $experiment );
			}

			return esc_html__( 'Respective controller is not registered', 'woofunnels-ab-tests' );

		}

		/**
		 * @param BWFABT_Experiment $experiment
		 *
		 * @return false
		 */
		public function reset_stats( $experiment ) {
			if ( ! $this->view_support ) {
				return false;
			}
		}


		/**
		 * Copying winner data to control going to live after deleting existing control data (when variant wins)
		 *
		 * @param $control_id
		 * @param $winner_id
		 *
		 * @return false
		 */
		public function copy_winner_data_to_control( $control_id, $winner_id ) {
			return false;
		}

		/**
		 * Copying existing control data to new control data when a variant wins
		 *
		 * @param $control_data
		 * @param $new_variant_id
		 */
		public function copy_control_data_to_new_control( $control_data, $new_variant_id ) {
			update_post_meta( $new_variant_id, '_bwf_ab_control', $control_data );
		}

		/**
		 * @param $variant
		 * @param $experiment
		 *
		 * @return string
		 */
		public function get_variant_heading_url( $variant, $experiment ) {
			return '';
		}

		/**
		 * @param BWFABT_Variant $variant
		 * @param BWFABT_Experiment $experiment
		 *
		 * @return array[]
		 */
		public function get_variant_row_actions( $variant, $experiment ) {
			$row_actions = [];
			if ( $experiment->get_status() === BWFABT_Experiment::STATUS_DRAFT ) {
				$row_actions = array(
					'duplicate' => array(
						'text'      => __( 'Duplicate', 'woofunnels-ab-tests' ),
						'link'      => 'javascript:void(0)',
						'invisible' => $experiment->is_started() ? "yes" : "no",
					),
				);
				if ( false === $variant->get_control() ) {

					$row_actions['delete'] = array(
						'text'      => __( 'Delete', 'woofunnels-ab-tests' ),
						'link'      => 'javascript:void(0);',
						'invisible' => $experiment->is_started() ? "yes" : "no",
					);
				}
			}
			if ( $experiment->get_status() !== BWFABT_Experiment::STATUS_COMPLETE ) {
				if ( false === $variant->get_control() ) {
					if ( $this->is_variant_active( $variant->get_id() ) ) {
						$row_actions['draft'] = array(
							'text'      => __( 'Draft', 'woofunnels-ab-tests' ),
							'link'      => 'javascript:void(0);',
							'invisible' => $experiment->is_started() ? "no" : "yes",
						);
					} else {
						$row_actions['publish'] = array(
							'text'      => __( 'Publish', 'woofunnels-ab-tests' ),
							'link'      => 'javascript:void(0);',
							'invisible' => $experiment->is_started() ? "no" : "yes",
						);
					}
				}

			}


			return $row_actions;
		}


		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return mixed|string|void
		 */
		public function get_chart_frequencies( $experiment, $type ) {

			$get_report = BWFABT_Core()->reports->get_integration( $type );

			if ( ! is_null( $get_report ) && $get_report instanceof BWFABT_Report ) {
				return $get_report->get_chart_frequencies( $experiment, $type );
			}

			return esc_html__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		/**
		 * @param $experiment
		 * @param $type
		 *
		 * @return mixed|string|void
		 */
		public function get_stats_head( $experiment, $type ) {

			$get_report = BWFABT_Core()->reports->get_integration( $type );

			if ( ! is_null( $get_report ) && $get_report instanceof BWFABT_Report ) {
				return $get_report->get_stats_head( $experiment, $type );
			}

			return esc_html__( 'Respective controller is not registered', 'woofunnels-ab-tests' );
		}

		/**
		 * @param $control_variant
		 * @param $experiment
		 * @param $new_control
		 *
		 * @return mixed
		 */
		public function transfer_control( $control_variant, $experiment, $new_control ) {
			return $experiment->transfer_control( $control_variant, $new_control );
		}

		/**
		 * @param $variant_id
		 * @param $experiment
		 *
		 * @return bool
		 */
		public function is_variant_active( $variant_id ) {
			return false;
		}

		/**
		 * @param $variant_id
		 *
		 * @return string
		 */
		public function get_variant_desc( $variant_id ) {
			return '';
		}

		/**
		 * @param $variant_id
		 *
		 * @return mixed
		 */
		public function get_variant_title( $variant_id ) {
			return $variant_id;
		}

		/**
		 * @param int $step_id
		 *
		 * @return int
		 */
		public function maybe_return_ab_control( $step_id ) {
			if ( $step_id > 0 ) {
				$ab_control = get_post_meta( $step_id, '_bwf_ab_variation_of', true );
				if ( $ab_control > 0 ) {
					$step_id = $ab_control;
				}
			}

			return $step_id;
		}

		/**
		 * @param $experiment
		 * @param $types
		 */
		public function delete_ab_report_views( $experiment, $types ) {
			$active_variants   = $experiment->get_active_variants();
			$query_variant_ids = array_keys( $active_variants );

			$start_date = $experiment->get_report_start_date();
			$end_date   = $experiment->get_report_end_date();

			$query = "DELETE FROM {table_name} WHERE `object_id` IN (" . esc_sql( implode( ',', $query_variant_ids ) ) . ") AND `date` >= '" . gmdate( 'Y-m-d', $start_date ) . "' AND `date` <= '" . gmdate( 'Y-m-d', $end_date ) . "'";
			if ( is_array( $types ) && count( $types ) > 0 ) {
				$query .= " AND `type` IN (" . esc_sql( implode( ',', $types ) ) . ") ";
			} else {
				$query .= " AND `type` = " . esc_sql( $types );
			}

			WFCO_Model_Report_views::delete_multiple( $query );
		}

		/**
		 * Checks and validate environment to run ab tests
		 *
		 * @param int|string $object_id
		 *
		 * @return bool whether allowed or not
		 */
		public function validate_run( $object_id ) {

			if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * @param $step_id
		 *
		 * @return mixed
		 */
		public function get_entity_view_link( $step_id ) {
			$link = 'javascript:void(0);';
			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$link = esc_url( get_the_permalink( $step_id ) );
			}

			return $link;
		}

		public function publish_post_status( $step_id ) {
			wp_update_post( array(
				'ID'          => $step_id,
				'post_status' => 'publish'
			) );
		}

		/**
		 * @param $experiment
		 * @param $variant_id
		 *
		 * @return bool
		 */
		public function publish_variant( $experiment, $variant_id ) {
			$published = 0;
			if ( $variant_id > 0 ) {
				$funnel_post = get_post( $variant_id );
				if ( ! is_null( $funnel_post ) ) {
					$published = wp_update_post( array(
						'ID'          => $variant_id,
						'post_status' => 'publish',
					) );
				}
			}

			if ( ! is_wp_error( $published ) && absint( $published ) === absint( $variant_id ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Get percentage of a given number against a total
		 *
		 * @param float|int $total total number of occurrences
		 * @param float|int $number the number to get percentage against
		 *
		 * @return float|int
		 */
		public function get_percentage( $total, $number ) {
			if ( $total > 0 ) {
				return round( $number / ( $total / 100 ), 2 );
			} else {
				return 0;
			}
		}


		public function date_format( $interval ) {
			switch ( $interval ) {
				case 'hour':
					$format = '%Y-%m-%d %H';
					break;
				case 'day':
					$format = '%Y-%m-%d';
					break;
				case 'month':
					$format = '%Y-%m';
					break;
				case 'quarter':
					$format = 'QUARTER';
					break;
				case 'year':
					$format = 'YEAR';
					break;
				default:
					$format = '%x-%v';
					break;
			}

			return apply_filters( 'BWFABT_date_format_' . $interval, $format, $interval );
		}

		public function get_interval_format_query( $interval, $table_col ) {

			$interval_type = $this->date_format( $interval );
			$avg           = ( $interval === 'day' ) ? 1 : 0;
			if ( 'YEAR' === $interval_type ) {
				$interval = ", YEAR(" . $table_col . ") ";
				$avg      = 365;
			} elseif ( 'QUARTER' === $interval_type ) {
				$interval = ", CONCAT(YEAR(" . $table_col . "), '-', QUARTER(" . $table_col . ")) ";
				$avg      = 90;
			} elseif ( '%x-%v' === $interval_type ) {
				$first_day_of_week = absint( get_option( 'start_of_week' ) );

				if ( 1 === $first_day_of_week ) {
					$interval = ", DATE_FORMAT(" . $table_col . ", '" . $interval_type . "')";
				} else {
					$interval = ", CONCAT(YEAR(" . $table_col . "), '-', LPAD( FLOOR( ( DAYOFYEAR(" . $table_col . ") + ( ( DATE_FORMAT(MAKEDATE(YEAR(" . $table_col . "),1), '%w') - $first_day_of_week + 7 ) % 7 ) - 1 ) / 7  ) + 1 , 2, '0'))";
				}
				$avg = 7;
			} else {
				$interval = ", DATE_FORMAT( " . $table_col . ", '" . $interval_type . "')";
			}

			$interval       .= " as time_interval ";
			$interval_group = " `time_interval` ";

			return array(
				'interval_query' => $interval,
				'interval_group' => $interval_group,
				'interval_avg'   => $avg,

			);

		}

		/**
		 * @param $control_id
		 * @param $ids
		 *
		 * @return void
		 */
		public function merge_variant_data( $control_id, $ids ) {
			if ( absint( $control_id ) > 0 ) {
				global $wpdb;
				$step_ids   = is_array( $ids ) ? $ids : [];
				$control_id = esc_sql( $control_id );

				if ( ! empty( $step_ids ) ) {

					$type   = array_fill( 0, count( $step_ids ), '%d' );
					$format = implode( ', ', $type );
					$params = array( $control_id );
					$params = array_merge( $params, $step_ids );


					$wpdb->bwf_optin_entries = $wpdb->prefix . 'bwf_optin_entries';
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->bwf_optin_entries} SET `step_id` = %d WHERE `step_id` IN ({$format})", $params ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					$wpdb->wfco_report_views = $wpdb->prefix . 'wfco_report_views';
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->wfco_report_views} SET `object_id` = %d WHERE `object_id` IN ({$format})", $params ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					$wpdb->wfacp_stats = $wpdb->prefix . 'wfacp_stats';
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->wfacp_stats} SET `wfacp_id` = %d WHERE `wfacp_id` IN ({$format})", $params ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					$wpdb->wfocu_event = $wpdb->prefix . 'wfocu_event';
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->wfocu_event} SET `object_id` = %d WHERE `object_id` IN ({$format}) AND `object_type` = 'offer' ", $params ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

					$wpdb->wfocu_event_meta = $wpdb->prefix . 'wfocu_event_meta';
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->wfocu_event_meta} SET `meta_value` = %d WHERE `meta_value` IN ({$format}) AND `meta_key` = '_offer_id' ", $params ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				}
				$delete_view = "DELETE FROM " . $wpdb->prefix . "wfco_report_views WHERE `object_id` = '" . $control_id . "' AND `type` IN ( 12,13,15,16,17 )";


				$wpdb->query( $delete_view );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		/**
		 * @param $control_id
		 * @param $winner_id
		 *
		 * replace oxygen control page css with winner
		 *
		 * @return void
		 */
		public function replace_oxygen_page_css_with_winner( $winner_id, $control_id ) {
			if ( function_exists( 'wp_upload_dir' ) ) {
				$upload_dir  = wp_upload_dir();
				$oxy_dirname = $upload_dir['basedir'] . '/oxygen/css';
				$winner_file = $upload_dir['basedir'] . '/oxygen/css/' . $winner_id . '.css';

				if ( file_exists( $oxy_dirname ) && file_exists( $winner_file ) ) {

					$content = @file_get_contents( $winner_file );
					@file_put_contents( $oxy_dirname . '/' . $control_id . '.css', $content );
				}
			}
		}

		/**
		 * @param $variation_id
		 *
		 * Overrides variation content to control url
		 *
		 * @return void
		 */
		public function override_control_content_by_variant( $variation_id ) {
			$variant = get_post( $variation_id );

			if ( is_null( $variant ) || ! $variant instanceof WP_Post ) {
				return;
			}

			if ( $variant && 'publish' === $variant->post_status ) {

				$get_global = $GLOBALS;

				/** override global posts and set variant in array */
				if ( isset( $get_global['posts'] ) && isset( $get_global['posts'][0] ) ) {
					$GLOBALS['posts'][0] = $variant;
				}

				/** override global wp query control query by variant*/
				if ( isset( $get_global['wp_the_query'] ) && isset( $get_global['wp_the_query']->post ) ) {
					$GLOBALS['wp_the_query']->post = $variant;
				}
				$this->declared_control = $variation_id;
				/** override global control post and set variant */
				$GLOBALS['post'] = $variant;
			}
		}

		/*
		 * check maybe override permalink enabled
		 */
		public function maybe_enable_override_permalink() {

			if ( class_exists( 'BWF_Admin_General_Settings' ) ) {
				$bwb_admin_setting = BWF_Admin_General_Settings::get_instance();

				return ( ! empty( $bwb_admin_setting->get_option( 'ab_test_override_permalink' ) ) ) ? bwfabt_string_to_bool( $bwb_admin_setting->get_option( 'ab_test_override_permalink' ) ) : false;

			}

			return false;

		}

		public function render_js() {


			/**
			 * Add delay js exclusions for the script to rotate the variants
			 */
			if ( defined( 'WP_ROCKET_VERSION' ) ) {
				add_filter( 'rocket_delay_js_exclusions', function ( $excluded ) {
					$excluded[] = 'new BWFABT';

					return $excluded;
				} );
			}
			$experiment      = $this->declared_experiment;
			$post_id         = $this->declared_control;
			$active_variants = $experiment->get_active_variants( true );

			foreach ( $active_variants as $key => &$variant ) {
				$variant['url'] = get_permalink( $key );
			}
			$cookie_key = 'bwfabt_bucket_variation_' . $experiment->get_id() . '_' . $experiment->get_control();

			?>
			<script type="text/javascript" data-cfasync="false">
                class BWFABTesting {
                    constructor(activeVariants, postIDexp, cookieName) {
                        this.activeVariants = activeVariants || {};
                        this.postIDexp = postIDexp;
                        this.cookieName = cookieName;
                    }

                    static getRandomPercentage() {
                        return Math.floor(Math.random() * 100) + 1;
                    }

                    static setCookie(cname, cvalue, exdays) {
                        try {
                            const d = new Date();
                            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
                            let expires = "expires=" + d.toUTCString();
                            const secure = window.location.protocol === 'https:' ? ';secure' : '';
                            document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/" + secure;
                        } catch (e) {
                            console.warn('Failed to set cookie:', e);
                        }
                    }

                    static getCookie(cname) {
                        try {
                            let name = cname + "=";
                            let decodedCookie = decodeURIComponent(document.cookie);
                            let ca = decodedCookie.split(';');
                            for (let i = 0; i < ca.length; i++) {
                                let c = ca[i];
                                while (c.charAt(0) == ' ') {
                                    c = c.substring(1);
                                }
                                if (c.indexOf(name) == 0) {
                                    return c.substring(name.length, c.length);
                                }
                            }
                        } catch (e) {
                            console.warn('Failed to get cookie:', e);
                        }
                        return "";
                    }

                    static decideFinalVariation(randomPercentage, activeVariants) {
                        let measurement = 0;
                        let decided = null;

                        // Validate input
                        if (!activeVariants || typeof activeVariants !== 'object') {
                            return null;
                        }

                        // Get sorted keys to ensure consistent iteration order
                        const sortedKeys = Object.keys(activeVariants).sort((a, b) => {
                            // Sort by control first, then by ID
                            const variantA = activeVariants[a];
                            const variantB = activeVariants[b];
                            
                            if (variantA.control && !variantB.control) return -1;
                            if (!variantA.control && variantB.control) return 1;
                            
                            return parseInt(a) - parseInt(b);
                        });

                        for (const key of sortedKeys) {
                            const variant = activeVariants[key];
                            
                            if (!variant) {
                                continue;
                            }

                            // Convert traffic to number (handle both string and number types)
                            const trafficValue = parseFloat(variant.traffic) || 0;
                            
                            if (trafficValue <= 0) {
                                continue;
                            }

                            if (randomPercentage >= measurement && randomPercentage <= (measurement + trafficValue)) {
                                decided = key;
                                break;
                            }

                            // Ensure measurement is always a number
                            measurement = parseFloat(measurement) + trafficValue;
                        }

                        // If no variant was selected, pick the first one as fallback
                        if (decided === null && sortedKeys.length > 0) {
                            decided = sortedKeys[0];
                        }

                        return decided;
                    }

                    static redirectUrl(baseUrl) {
                        if (!baseUrl || typeof baseUrl !== 'string') {
                            console.warn('Invalid URL provided:', baseUrl);
                            return null;
                        }

                        try {
                            // Handle relative URLs
                            if (!baseUrl.startsWith('http') && !baseUrl.startsWith('/')) {
                                baseUrl = '/' + baseUrl;
                            }

                            // Add query parameters and hash
                            const separator = baseUrl.includes('?') ? '&' : '?';
                            const queryString = window.location.search ? separator + window.location.search.substring(1) : '';
                            const hash = window.location.hash || '';
                            
                            return baseUrl + queryString + hash;
                        } catch (e) {
                            console.warn('Error constructing redirect URL:', e);
                            return null;
                        }
                    }

                    runTest() {
                        try {
                            // Validate required data
                            if (!this.activeVariants || Object.keys(this.activeVariants).length === 0) {
                                return;
                            }

                            let decidedVariation = null;

                            if (BWFABTesting.getCookie(this.cookieName) !== '') {
                                decidedVariation = BWFABTesting.getCookie(this.cookieName);
                                
                                // Validate that the stored variation still exists
                                if (!this.activeVariants[decidedVariation]) {
                                    decidedVariation = null;
                                }
                            }

                            if (decidedVariation === null) {
                                const randomPercentage = BWFABTesting.getRandomPercentage();
                                decidedVariation = BWFABTesting.decideFinalVariation(randomPercentage, this.activeVariants);
                                
                                if (decidedVariation) {
                                    BWFABTesting.setCookie(this.cookieName, decidedVariation, 30);
                                }
                            }

                            // Validate we have a valid variation
                            if (!decidedVariation || !this.activeVariants[decidedVariation]) {
                                return;
                            }

                            // Only redirect if we're not already on the correct variation
                            if (parseInt(this.postIDexp) !== parseInt(decidedVariation)) {
                                const targetUrl = BWFABTesting.redirectUrl(this.activeVariants[decidedVariation].url);
                                
                                if (targetUrl && window.location.href !== targetUrl) {
                                    // Redirect to the target URL
                                    window.location.replace(targetUrl);
                                }
                            }
                        } catch (e) {
                            console.error('Error in A/B test execution:', e);
                        }
                    }
                }

                new BWFABTesting(<?php echo wp_json_encode( $active_variants ); ?>, <?php echo esc_js( $post_id ); ?>, '<?php echo esc_js( $cookie_key ); ?>').runTest();

			</script>
			<?php

		}

		public function maybe_render_js_for_ab_experiment( $experiment, $post_id ) {
			$this->declared_experiment = $experiment;
			$this->declared_control    = $post_id;

			add_action( 'wp_head', array( $this, 'render_js' ) );
		}

		/**
		 * Checks if the given control has an active experiment based on its meta value.
		 *
		 * This method looks for the `_experiment_status` meta value associated with the control (a WP_Post object).
		 * It returns `true` if the experiment is active (i.e., the `_experiment_status` is anything other than `'not_active'`).
		 * It returns `false` if the experiment is marked as `'not_active'`.
		 *
		 * @param WP_Post $control The control post object to check for an active experiment.
		 *
		 * @return bool `true` if the control has an active experiment (anything other than 'not_active'),
		 *              `false` if the control has a `'not_active'` experiment status.
		 */
		public function maybe_control_has_active_experiment( $control ) {
			if ( ! $control instanceof WP_Post ) {
				$control = get_post( $control );
			}
			if ( ! $control instanceof WP_Post || empty( $control->ID ) ) {
				return false;
			}

			return get_post_meta( $control->ID, '_experiment_status', true ) !== 'not_active';
		}


	}
}