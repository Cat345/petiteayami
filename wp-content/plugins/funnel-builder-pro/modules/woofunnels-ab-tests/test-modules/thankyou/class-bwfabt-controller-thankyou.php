<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Controller_Thank_You' ) ) {
	/**
	 * Class contains all the thank_you related ab testing functionality
	 * Class BWFABT_Controller_Thank_You
	 */
	#[AllowDynamicProperties]
	class BWFABT_Controller_Thank_You extends BWFABT_Controller {
		private static $ins = null;
		private $control_query;

		/**
		 * BWFABT_Controller_Thank_You constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'bwfabt_get_supported_controllers', array( $this, 'bwfabt_add_thank_you_controller' ) );
			add_filter( 'wffn_thank_you_post_type_args', array( $this, 'wffn_add_control_meta_query' ), 10, 1 );
			add_action( 'wp', array( $this, 'maybe_redirect_to_ab_funnel' ), 1, 1 );
			add_action( 'wffn_event_step_viewed_wc_thankyou', array( $this, 'update_ab_thankyou_visited' ) );

			$this->control_query = false;
		}

		/**
		 * @return BWFABT_Controller_Thank_You|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @param $controllers
		 *
		 * @return mixed
		 */
		public function bwfabt_add_thank_you_controller( $controllers ) {
			$controllers['thank_you'] = 'BWFABT_Controller_Thank_You';

			return $controllers;
		}

		/**
		 * Return title of thank_you controller
		 */
		public function get_title() {
			return __( 'Thank You Page', 'woofunnels-ab-tests' );
		}

		/**
		 * Get all active funnels(exclude variants) for deciding control of the experiment
		 *
		 * @param $term
		 *
		 * @return array
		 */
		public function get_controls( $term ) {
			global $wpdb;
			$pages = [];
			if ( '' === $term ) {
				return $pages;
			}

			$like_term     = '%' . $wpdb->esc_like( $term ) . '%';
			$post_type     = WFFN_Core()->thank_you_pages->get_post_type_slug();
			$post_statuses = array( 'publish', 'draft' );
			if ( 'get_all' === $term ) {
				$query = $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} as posts WHERE posts.post_type = %s AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') ORDER BY posts.post_parent ASC, posts.post_title ASC", $post_type ); //phpcs:ignore
			} else {
				$query = $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} as posts WHERE ( posts.post_title LIKE %s or posts.ID = %s ) AND posts.post_type = %s AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') ORDER BY posts.post_parent ASC, posts.post_title ASC", $like_term, $like_term, $post_type ); //phpcs:ignore
			}

			$post_ids = $wpdb->get_col( $query ); //phpcs:ignore

			if ( ! is_array( $post_ids ) || count( $post_ids ) === 0 ) {
				return $pages;
			}
			foreach ( $post_ids as $id ) {
				if ( get_post_meta( $id, '_bwf_ab_variation_of', true ) ) {
					continue;
				}
				$pages[] = array(
					'id'   => $id,
					'name' => html_entity_decode( get_the_title( $id ) ),
				);
			}

			return $pages;
		}


		/**
		 * @param $variant_data
		 *
		 * @return int
		 */
		public function add_variant( $variant_data ) {
			$variant_id = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;
			if ( $variant_id < 1 ) {
				$args       = [
					'post_title'  => $variant_data['variant_title'],
					'post_name'   => sanitize_title( $variant_data['variant_title'] ),
					'post_type'   => WFFN_Core()->thank_you_pages->get_post_type_slug(),
					'post_status' => 'publish',
				];
				$variant_id = wp_insert_post( $args );
				if ( ! is_wp_error( $variant_id ) ) {
					delete_post_meta( $variant_id, '_bwf_ab_control' );
					$variant_data['variant_id'] = $variant_id;
				}
			}

			return parent::add_variant( $variant_data );
		}

		/**
		 * @param $variant_data
		 *
		 * @return mixed
		 */
		public function duplicate_variant( $variant_data ) {
			$variant_id                 = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;
			$control_id                 = isset( $variant_data['control_id'] ) ? $variant_data['control_id'] : 0;
			$experiment_id              = isset( $variant_data['experiment_id'] ) ? $variant_data['experiment_id'] : 0;
			$variant_data['control_id'] = ( $control_id < 0 && true === $variant_data['control'] ) ? $variant_id : $control_id;
			$new_post_id                = 0;

			if ( $variant_id > 0 && $experiment_id > 0 ) {
				$new_post_id = WFFN_Core()->thank_you_pages->duplicate_thank_you_page( $variant_id );

				/** update post meta for gutenburg */
				if ( $new_post_id > 0 && get_post( $new_post_id ) instanceof WP_Post ) {
					$post               = get_post( $new_post_id );
					$post->post_content = get_post_field( 'post_content', $variant_id );
					wp_update_post( $post );
				}

				$this->publish_post_status( $new_post_id );
			}
			$variant_data['variant_id'] = ( $new_post_id > 0 ) ? $new_post_id : $variant_id;

			return parent::duplicate_variant( $variant_data );
		}

		/**
		 * @param $experiment
		 * @param $variant_id
		 *
		 * @return bool|false
		 */
		public function draft_variant( $experiment, $variant_id ) {
			$draft = false;
			if ( $variant_id > 0 ) {
				$funnel_post = get_post( $variant_id );
				if ( ! is_null( $funnel_post ) ) {
					$draft = wp_update_post( array(
						'ID'          => $variant_id,
						'post_status' => 'draft',
					) );
				}
			}

			if ( ! is_wp_error( $draft ) && absint( $draft ) === absint( $variant_id ) ) {
				parent::draft_variant( $experiment, $variant_id );

				return true;
			}

			return false;
		}

		/**
		 * @param $args
		 *
		 * @return mixed
		 */
		public function wffn_add_control_meta_query( $args ) {
			$args['meta_query'] = array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_bwf_ab_variation_of',
					'compare' => 'NOT EXISTS',
					'value'   => ''
				),
			);

			if ( true === $this->control_query ) {
				$args['post_status']  = array( 'publish', 'draft' );
				$args['get_existing'] = true;
			}


			return $args;
		}

		/**
		 *
		 * Redirecting to ab test decided thank-you page.
		 */
		public function maybe_redirect_to_ab_funnel() {
			global $post;
			$wffn_post_id = isset( $post->ID ) ? $post->ID : 0;
			if ( ! $this->maybe_control_has_active_experiment( $post ) ) {
				return;
			}
			if ( $wffn_post_id > 0 && true === $this->validate_run( $wffn_post_id ) && WFFN_Core()->thank_you_pages->get_post_type_slug() === $post->post_type ) {
				$active_test_id = $this->get_running_test_id( $wffn_post_id );
				if ( $active_test_id > 0 ) {
					$experiment   = BWFABT_Core()->admin->get_experiment( $active_test_id );
					$variation_id = $this->get_variation_to_run( $experiment );
					if ( 0 !== intval( $variation_id ) && intval( $variation_id ) !== intval( $wffn_post_id ) ) {
						if ( $this->maybe_enable_override_permalink() ) {
							$this->override_control_content_by_variant( $variation_id );
						} else {
							$query_var = $_GET;//phpcs:ignore
							$slug      = WFFN_Core()->thank_you_pages->get_post_type_slug();

							$url = get_permalink( $variation_id );
							if ( ! empty( $query_var ) ) {
								/**
								 * set wfty_source variation id for record view
								 */
								$query_var[ 'wfty_source' ] = $variation_id;
								unset( $query_var[ $slug ] );
								$url = add_query_arg( $query_var, $url );
							}
							wp_safe_redirect( $url );
							exit();
						}
					}
				}
			}
		}


		/**
		 * Save existing funnel offers ids and titles data to new control funnel mata when a variant wins (to display ideal result later on)
		 *
		 * @param $control_data
		 * @param $new_variant_id
		 */
		public function copy_control_data_to_new_control( $control_data, $new_variant_id ) {
			parent::copy_control_data_to_new_control( $control_data, $new_variant_id );
		}

		/**
		 * Copying winner data to control going to live after deleting existing control data
		 *
		 * @param $control_id
		 * @param $winner_variant_id
		 */
		public function copy_winner_data_to_control( $control_id, $winner_variant_id ) {

			global $wpdb;
			$exclude_metas = array(
				'_bwf_in_funnel',
				'_bwf_ab_variation_of',
				'_wp_old_slug'
			);

			$post_meta_all = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d",$winner_variant_id ) );
			do_action( 'woofunnels_module_template_removed', $control_id );
			$post_content = get_post_field( 'post_content', $winner_variant_id );
			wp_update_post( wp_slash( [ 'ID' => $control_id, 'post_content' => $post_content ] ) );
			$control_metas = get_post_meta( $control_id );

			if ( ! empty( $post_meta_all ) ) {
				$content = '';
				$is_oxy  = false;

				foreach ( $post_meta_all as $meta_info ) {

					$meta_key = $meta_info->meta_key;

					if ( in_array( $meta_key, $exclude_metas, true ) ) {
						continue;
					}

					$meta_key   = esc_sql( $meta_key );
					$meta_value = esc_sql( $meta_info->meta_value );

					if ( $meta_key === '_elementor_data' ) {
						$content = $meta_info->meta_value;
					}

					if ( $meta_key === 'ct_builder_shortcodes' ) {
						$is_oxy = true;
					}

					if ( ! isset( $control_metas[ $meta_key ] ) ) {
						$sql_query_meta_val = "($control_id, '$meta_key', '$meta_value')";
						$sql_query_meta     = $wpdb->prepare( 'INSERT INTO %1$s (post_id, meta_key, meta_value) VALUES ' . $sql_query_meta_val, $wpdb->postmeta );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPress.DB.PreparedSQL.NotPrepared
					} else {
						$sql_query_meta = $wpdb->prepare( "UPDATE %1s SET `meta_value` = '" . $meta_value . "' WHERE `post_id` = " . $control_id . " AND `meta_key` = '" . $meta_key . "'", $wpdb->postmeta );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
					}

					$wpdb->query( $sql_query_meta ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}

				if ( $content !== '' ) {
					WFFN_Common::maybe_elementor_template( $winner_variant_id, $control_id );
				}

				if ( true === $is_oxy ) {
					$this->replace_oxygen_page_css_with_winner( $winner_variant_id, $control_id );
				}

				update_post_meta( $winner_variant_id, '_bwf_ab_variation_of', $control_id );

			}
		}

		/**
		 * @param BWFABT_Variant $variant
		 * @param $experiment
		 *
		 * @return string
		 */
		public function get_variant_heading_url( $variant, $experiment ) {
			return BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
				'page'    => 'wf-ty',
				'section' => 'design',
				'edit'    => $variant->get_id(),
			), admin_url( 'admin.php' ) ) );

		}


		/**
		 * @param BWFABT_Variant $variant
		 * @param BWFABT_Experiment $experiment
		 *
		 * @return array
		 */
		public function get_variant_row_actions( $variant, $experiment ) {

			$row_actions = array(
				'edit' => array(
					'text' => __( 'Edit', 'woofunnels-ab-tests' ),
					'link' => BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
						'page'    => 'wf-ty',
						'section' => 'design',
						'edit'    => $variant->get_id(),
					), admin_url( 'admin.php' ) ) ),
				)
			);

			return array_merge( $row_actions, parent::get_variant_row_actions( $variant, $experiment ) );
		}


		/**
		 * @param $variant
		 * @param $experiment
		 * @param bool $force
		 *
		 * @return mixed
		 */
		public function delete_variant( $variant, $experiment, $force = false ) {
			$variant_id = $variant->get_id();
			if ( true === $force ) {
				if ( ! is_null( get_post( $variant_id ) ) ) {
					wp_delete_post( $variant_id, $force );
				}
			}

			return parent::delete_variant( $variant, $experiment, $force );
		}

		/**
		 * @param $control_variant
		 * @param $experiment
		 * @param $new_control
		 *
		 * @return false|mixed
		 */
		public function transfer_control( $control_variant, $experiment, $new_control ) {
			$transferred    = false;
			$original_title = get_the_title( $control_variant->get_id() );
			$new_control_id = $new_control->get_id();
			if ( $new_control_id > 0 ) {
				$funnel_post = get_post( $new_control_id );
				if ( ! is_null( $funnel_post ) ) {
					$transferred = wp_update_post( array(
						'ID'         => $new_control_id,
						'post_title' => $original_title,
					) );
				}
			}
			if ( $new_control_id === $transferred ) {
				return parent::transfer_control( $control_variant, $experiment, $new_control );
			}

			return false;
		}

		/**
		 * @param $variant_id
		 * @param $experiment
		 *
		 * @return bool
		 */
		public function is_variant_active( $variant_id ) {
			$active = false;
			if ( $variant_id > 0 ) {
				$active = ( 'publish' === get_post_status( $variant_id ) );
			}

			return $active;
		}

		/**
		 * @param $variant_id
		 *
		 * @return string
		 */
		public function get_variant_desc( $variant_id ) {
			$desc = '';
			if ( $variant_id > 0 ) {
				$desc = get_post_field( 'post_content', $variant_id );
			}

			return $desc;
		}

		/**
		 * @param $variant_id
		 *
		 * @return mixed
		 */
		public function get_variant_title( $variant_id ) {
			$title = $variant_id;
			if ( $variant_id > 0 ) {
				$title = get_the_title( $variant_id );
			}

			return $title;
		}

		/**
		 * @param $thankyou_id
		 */
		public function update_ab_thankyou_visited( $thankyou_id ) {
			$running_ab_test_id = $this->get_running_test_id_on_step( $thankyou_id );
			WFFN_Core()->logger->log( "Updating AB thankyou id: $thankyou_id visited, running AB test id: $running_ab_test_id" );
			if ( $thankyou_id > 0 && $running_ab_test_id > 0 && class_exists( 'WFCO_Model_Report_views' ) ) {
				WFCO_Model_Report_views::update_data( date( 'Y-m-d', current_time( 'timestamp' ) ), $thankyou_id, 15 );
				WFFN_Core()->logger->log( 'Updated AB thankyou id: ' . $thankyou_id . ' visited (15) @: ' . current_time( 'mysql' ) );
			}
		}

		/**
		 * @param BWFABT_Experiment $experiment
		 */
		public function reset_stats( $experiment ) {
			$type = 15;
			$this->delete_ab_report_views( $experiment, $type );
			$experiment->set_last_reset_date( BWFABT_Core()->get_dataStore()->now() );
			$experiment->save( array() );
		}

		/**
		 * @param $step_ids
		 * @param $experiment_id
		 *
		 * @return array|false[]
		 */
		public function get_analytics_data( $step_ids, $experiment_id, $is_interval = '', $int_request = '' ) {
			global $wpdb;
			$data           = [];
			$ids            = [];
			$date_col       = "date";
			$interval_query = '';
			$group_by       = ' GROUP BY object_id ';
			$params         = '';

			if ( ! is_array( $step_ids ) || count( $step_ids ) === 0 ) {
				return $data;
			}

			foreach ( $step_ids as $step_id ) {
				$ids[]            = $step_id;
				$data[ $step_id ] = array(
					'object_id'         => $step_id,
					'object_name'       => html_entity_decode( get_the_title( $step_id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
					'revenue'           => 0,
					'revenue_per_visit' => 0,
					'conversions'       => 0,
					'views'             => 0,
					'conversion_rate'   => 0,
				);
			}

			if ( count( $ids ) < 1 ) {
				return $data;
			}

			if ( 'interval' === $is_interval ) {
				$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
				$interval_query = $get_interval['interval_query'];
				$interval_group = $get_interval['interval_group'];
				$group_by       = "GROUP BY " . $interval_group;
				$params         = " ,0 as converted, 0 as 'revenue' ";
			}

			$step_ids = esc_sql( implode( ',', $ids ) );

			$get_all_dates = BWFABT_Core()->get_dataStore()->get_experiment_time_chunk( $experiment_id );
			$date_query    = "";

			if ( is_array( $get_all_dates ) && count( $get_all_dates ) ) {
				foreach ( $get_all_dates as $date ) {
					$start_date = explode( " ", $date['start_date'] );
					$end_date   = explode( " ", $date['end_date'] );
					$date_query .= " ( `date` >= '" . esc_sql( $start_date[0] ) . "' AND `date` <= '" . esc_sql( $end_date[0] ) . "' ) OR ";
				}

				$date_query = ' AND ( ' . rtrim( $date_query, " OR " ) . ') ';
			}

			$get_query = "SELECT object_id, SUM(CASE WHEN type = 15 THEN `no_of_sessions` END) AS `viewed` " . $params . " " . $interval_query . " FROM  `" . $wpdb->prefix . "wfco_report_views`  WHERE object_id IN (" . $step_ids . ") " . $date_query . " " . $group_by . " ORDER BY object_id ASC";
			$get_data  = $wpdb->get_results( $get_query, ARRAY_A ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
				$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					return $db_error;
				}
			}

			if ( 'interval' === $is_interval ) {
				if ( is_array( $get_data ) && count( $get_data ) > 0 ) {
					return $get_data;
				}
			}

			if ( is_array( $get_data ) && count( $get_data ) > 0 ) {
				foreach ( $get_data as $item ) {
					if ( isset( $data[ $item['object_id'] ] ) ) {
						$data[ $item['object_id'] ]['views'] = is_null( $item['viewed'] ) ? 0 : (int) $item['viewed'];
					}
				}
			}

			return $data;
		}
	}

	BWFABT_Controller_Thank_You::get_instance();
}