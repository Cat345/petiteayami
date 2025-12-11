<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Controller_Order_Bump' ) ) {
	/**
	 * Class contains all the order bump related ab testing functionality
	 * Class BWFABT_Controller_Order_Bump
	 */
	#[AllowDynamicProperties]
	class BWFABT_Controller_Order_Bump extends BWFABT_Controller {

		private static $ins = null;

		/**
		 * BWFABT_Controller_Order_Bump constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'bwfabt_get_supported_controllers', array( $this, 'bwfabt_add_order_bump_controller' ) );
			add_filter( 'wfob_add_control_meta_query', array( $this, 'wfob_add_control_meta_query_function' ), 10, 1 );
			add_filter( 'wfob_filter_final_bumps', array( $this, 'wfob_filter_final_bumps_final' ), 10, 2 );
		}

		/**
		 * @return BWFABT_Controller_Order_Bump|null
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
		public function bwfabt_add_order_bump_controller( $controllers ) {
			$controllers['order_bump'] = 'BWFABT_Controller_Order_Bump';

			return $controllers;
		}

		/**
		 * Return title of order bump controller
		 */
		public function get_title() {
			return __( 'Order Bump', 'woofunnels-ab-tests' );
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
			$post_type     = WFOB_Common::get_bump_post_type_slug();
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
		 * @param $args
		 *
		 * @return mixed
		 */
		public function wfob_add_control_meta_query_function( $args ) {
			$meta_query = array(
				'key'     => '_bwf_ab_variation_of',
				'compare' => 'NOT EXISTS',
				'value'   => ''
			);

			if ( isset( $args['meta_query'] ) ) {
				array_push( $args['meta_query'], $meta_query );
			} else {
				$args['meta_query'] = $meta_query;
			}

			return $args;
		}


		/**
		 * @param $variant_data
		 *
		 * @return int
		 */
		public function add_variant( $variant_data ) {
			$variant_id = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;
			if ( $variant_id < 1 ) {
				$post                 = array();
				$post['post_title']   = $variant_data['variant_title'];
				$post['post_type']    = WFOB_Common::get_bump_post_type_slug();
				$post['post_status']  = 'publish';
				$post['post_content'] = $variant_data['variant_desc'];

				$menu_order = WFOB_Common::get_highest_menu_order();

				$post['menu_order'] = $menu_order + 1;
				$variant_id         = wp_insert_post( $post );
				if ( ! is_wp_error( $variant_id ) && $variant_id > 0 ) {
					update_post_meta( $variant_id, '_wfob_version', WFOB_VERSION );
					delete_post_meta( $variant_id, '_bwf_ab_control' );
					$variant_data['variant_id'] = $variant_id;
				}

				BWFABT_Core()->admin->log( "Updated variant Data after creating variant bump: " . print_r( $variant_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			}

			return parent::add_variant( $variant_data );
		}

		/**
		 * @param $variant_data
		 *
		 * @return int
		 */
		public function duplicate_variant( $variant_data ) {
			$variant_id                 = isset( $variant_data['variant_id'] ) ? $variant_data['variant_id'] : 0;
			$control_id                 = isset( $variant_data['control_id'] ) ? $variant_data['control_id'] : 0;
			$experiment_id              = isset( $variant_data['experiment_id'] ) ? $variant_data['experiment_id'] : 0;
			$variant_data['control_id'] = ( $control_id < 0 && true === $variant_data['control'] ) ? $variant_id : $control_id;
			$new_bump_id                = 0;

			if ( $variant_id > 0 && $experiment_id > 0 ) {
				$new_bump_id = WFOB_Common::make_duplicate( $variant_id );
				$this->publish_post_status( $new_bump_id );
			}
			$variant_data['variant_id'] = ( $new_bump_id > 0 ) ? $new_bump_id : $variant_id;

			return parent::duplicate_variant( $variant_data );
		}

		/**
		 * @param $experiment
		 * @param $variant_id
		 *
		 * @return bool
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

			if ( absint( $draft ) === absint( $variant_id ) && ! is_wp_error( $draft ) ) {
				parent::draft_variant( $experiment, $variant_id );

				return true;
			}

			return false;
		}

		/**
		 * @param $decided_bumps
		 *
		 * @return mixed
		 */
		public function wfob_filter_final_bumps_final( $decided_bumps, $posted_data ) {
			if ( is_array( $decided_bumps ) && count( $decided_bumps ) > 0 ) {
				foreach ( $decided_bumps as $bump_id ) {
					$active_test_id = $this->get_running_test_id( $bump_id );

					if ( ! empty( $active_test_id ) && $active_test_id > 0 ) {
						$experiment   = BWFABT_Core()->admin->get_experiment( $active_test_id );
						$altered_bump = $this->get_variation_to_run( $experiment );
						if ( $altered_bump > 0 && $altered_bump !== $bump_id ) {
							unset( $decided_bumps[ $bump_id ] );
							$decided_bumps[ $altered_bump ] = $altered_bump;
						}
					}
				}
			}

			return $decided_bumps;
		}

		/**
		 * Copying existing control data to new control data when a variant wins
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

			$wfob_rules        = get_post_meta( $winner_variant_id, '_wfob_rules', true );
			$is_rules_saved    = get_post_meta( $winner_variant_id, '_wfob_is_rules_saved', true );
			$selected_products = get_post_meta( $winner_variant_id, '_wfob_selected_products', true );
			$design_data       = get_post_meta( $winner_variant_id, '_wfob_design_data', true );
			$wfob_settings     = get_post_meta( $winner_variant_id, '_wfob_settings', true );

			update_post_meta( $control_id, '_wfob_rules', $wfob_rules );
			update_post_meta( $control_id, '_wfob_is_rules_saved', $is_rules_saved );
			update_post_meta( $control_id, '_wfob_selected_products', $selected_products );
			update_post_meta( $control_id, '_wfob_design_data', $design_data );
			update_post_meta( $control_id, '_wfob_settings', $wfob_settings );

		}

		/**
		 * @param BWFABT_Variant $variant
		 * @param $experiment
		 *
		 * @return string
		 */
		public function get_variant_heading_url( $variant, $experiment ) {
			return BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
				'page'    => 'wfob',
				'section' => 'products',
				'wfob_id' => $variant->get_id(),
			), admin_url( 'admin.php' ) ) );

		}

		/**
		 * @param BWFABT_Variant $variant
		 * @param BWFABT_Experiment $experiment
		 *
		 * @return array|array[]
		 */
		public function get_variant_row_actions( $variant, $experiment ) {

			$row_actions = array(
				'edit' => array(
					'text' => __( 'Edit', 'woofunnels-ab-tests' ),
					'link' => add_query_arg( array(
						'page'    => 'wfob',
						'section' => 'products',
						'wfob_id' => $variant->get_id(),
						'ref'     => 'bwfabt_' . $experiment->get_id()
					), admin_url( 'admin.php' ) ),
				)
			);

			return array_merge( $row_actions, parent::get_variant_row_actions( $variant, $experiment ) );
		}

		/**
		 * @param $variant
		 * @param $experiment
		 * @param false $force
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
			$transfered     = false;
			$original_title = get_the_title( $control_variant->get_id() );
			$new_control_id = $new_control->get_id();
			if ( $new_control_id > 0 ) {
				$new_control_post = get_post( $new_control_id );
				if ( ! is_null( $new_control_post ) ) {
					$transfered = wp_update_post( array(
						'ID'         => $new_control_id,
						'post_title' => $original_title,
					) );
				}
			}
			if ( $new_control_id === $transfered ) {
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
		 * @return array|int|string
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

			return esc_html( $title );
		}

		/**
		 * @param $step_ids
		 * @param $experiment_id
		 *
		 * @return array|false[]
		 */
		public function get_analytics_data( $step_ids, $experiment_id ) {
			global $wpdb;
			$data = [];
			$ids  = [];

			if ( class_exists( 'WFOB_Core' ) && version_compare( WFOB_VERSION, '1.8,1', '<=' ) ) {
				return $data;
			}

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

			$step_ids = esc_sql( implode( ',', $ids ) );

			$get_all_dates = BWFABT_Core()->get_dataStore()->get_experiment_time_chunk( $experiment_id );
			$date_query    = "";

			if ( is_array( $get_all_dates ) && count( $get_all_dates ) ) {
				foreach ( $get_all_dates as $date ) {
					$date_query .= " ( `date` >= '" . esc_sql( $date['start_date'] ) . "' AND `date` <= '" . esc_sql( $date['end_date'] ) . "' ) OR ";
				}

				$date_query = ' AND ( ' . rtrim( $date_query, " OR " ) . ') ';
			}

			$bump_sql = "SELECT bump.bid as 'object_id',COUNT(CASE WHEN converted = 1 THEN 1 END) AS `converted`, p.post_title as 'object_name',SUM(bump.total) as 'total_revenue',COUNT(bump.ID) as viewed, 'bump' as 'type' FROM " . $wpdb->prefix . 'wfob_stats' . " AS bump LEFT JOIN " . $wpdb->prefix . 'posts' . " as p ON bump.bid  = p.id WHERE bump.bid IN (" . $step_ids . ") " . $date_query . " GROUP by bump.bid ORDER BY bump.bid ASC";

			$get_all_bump_records = $wpdb->get_results( $bump_sql, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
				$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
				if ( true === $db_error['db_error'] ) {
					return $db_error;
				}
			}

			if ( is_array( $get_all_bump_records ) && count( $get_all_bump_records ) > 0 ) {
				foreach ( $get_all_bump_records as $item ) {
					if ( isset( $data[ $item['object_id'] ] ) ) {
						$data[ $item['object_id'] ]['object_name']       = $item['object_name'];
						$data[ $item['object_id'] ]['revenue']           = $item['total_revenue'];
						$data[ $item['object_id'] ]['revenue_per_visit'] = ( absint( $item['viewed'] ) !== 0 ) ? round( $item['total_revenue'] / $item['viewed'], 2 ) : 0;
						$data[ $item['object_id'] ]['conversions']       = intval( $item['converted'] );
						$data[ $item['object_id'] ]['views']             = intval( $item['viewed'] );
						$data[ $item['object_id'] ]['conversion_rate']   = $this->get_percentage( $item['viewed'], $item['converted'] );
					}
				}
			}

			return $data;
		}
	}

	BWFABT_Controller_Order_Bump::get_instance();
}