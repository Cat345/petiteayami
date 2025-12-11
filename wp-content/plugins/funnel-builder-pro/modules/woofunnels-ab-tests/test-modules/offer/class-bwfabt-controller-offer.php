<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Controller_Offer' ) ) {
	/**
	 * Class contains all the offer related ab testing functionality
	 * Class BWFABT_Controller_Offer
	 */
	#[AllowDynamicProperties]
	class BWFABT_Controller_Offer extends BWFABT_Controller {
		private static $ins = null;
		private $control_query;

		/**
		 * BWFABT_AB_Controller_Offer constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'bwfabt_get_supported_controllers', array( $this, 'bwfabt_add_offer_controller' ) );
			add_action( 'wfocu_get_current_offer_id', array( $this, 'maybe_current_offer_is_variant' ), 10, 1 );
			add_action( 'wfocu_get_offer_id_filter', array( $this, 'maybe_redirect_to_ab_funnel' ), 10, 1 );
			add_filter( 'wfocu_fetch_upsell_offer', array( $this, 'maybe_variant_exists' ), 10, 2 );

			$this->control_query = false;
		}

		/**
		 * @return BWFABT_Controller_Offer|null
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
		public function bwfabt_add_offer_controller( $controllers ) {
			$controllers['offer'] = 'BWFABT_Controller_Offer';

			return $controllers;
		}

		/**
		 * Return title of offer controller
		 */
		public function get_title() {
			return __( 'Offer', 'woofunnels-ab-tests' );
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
			$post_type     = WFOCU_Common::get_funnel_post_type_slug();
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
					'post_type'   => WFOCU_Common::get_offer_post_type_slug(),
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
				if ( method_exists( 'WFOCU_Offers', 'duplicate_offer' ) ) {
					$new_post_id = WFOCU_Core()->offers->duplicate_offer( $variant_id, '', 0, false );

					/** update post meta for gutenburg */
					if ( $new_post_id > 0 && get_post( $new_post_id ) instanceof WP_Post ) {
						$post               = get_post( $new_post_id );
						$post->post_content = get_post_field( 'post_content', $control_id );
						wp_update_post( $post );
					}
					$upsell_id = get_post_meta( $variant_data['control_id'], '_funnel_id', true );
					if ( abs( $upsell_id ) > 0 ) {
						update_post_meta( $new_post_id, '_funnel_id', $upsell_id );
					}

					$this->publish_post_status( $new_post_id );
				}
			}
			$variant_data['variant_id'] = ( $new_post_id > 0 ) ? $new_post_id : $variant_id;

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

			if ( ! is_wp_error( $draft ) && absint( $draft ) === absint( $variant_id ) ) {
				parent::draft_variant( $experiment, $variant_id );

				return true;
			}

			return false;
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

			$exclude_metas = array(
				'_bwf_in_funnel',
				'_bwf_ab_variation_of',
				'_wp_old_slug',
				'_funnel_id',
				'_wfocu_edit_last'
			);

			if ( absint( $control_id ) > 0 ) {

				$offer_custom = get_option( 'wfocu_c_' . $winner_variant_id, '' );

				update_post_meta( $control_id, '_wfocu_edit_last', time() );

				if ( ! empty( $offer_custom ) ) {
					update_option( 'wfocu_c_' . $control_id, $offer_custom );
				}

				global $wpdb;

				$post_meta_all = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d", $winner_variant_id ) );
				do_action( 'wfocu_template_removed', $control_id );
				$post_content = get_post_field( 'post_content', $winner_variant_id );
				wp_update_post( wp_slash( [ 'ID' => $control_id, 'post_content' => $post_content ] ) );
				$control_metas = get_post_meta( $control_id );

				if ( ! empty( $post_meta_all ) ) {
					$content = '';
					foreach ( $post_meta_all as $meta_info ) {
						$meta_key = $meta_info->meta_key;

						if ( in_array( $meta_key, $exclude_metas, true ) ) {
							continue;
						}

						if ( $meta_key === '_elementor_data' ) {
							$content = $meta_info->meta_value;
						}

						$meta_key   = esc_sql( $meta_key );
						$meta_value = esc_sql( $meta_info->meta_value );

						if ( ! isset( $control_metas[ $meta_key ] ) ) {
							$sql_query_meta_val = "($control_id, '$meta_key', '$meta_value')";
							$sql_query_meta     = $wpdb->prepare( 'INSERT INTO %1$s (post_id, meta_key, meta_value) VALUES ' . $sql_query_meta_val, $wpdb->postmeta );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPress.DB.PreparedSQL.NotPrepared
						} else {
							$sql_query_meta = $wpdb->prepare( 'UPDATE %1$s SET `meta_value` = "' . $meta_value . '" WHERE `post_id` = ' . $control_id . ' AND `meta_key` = "' . $meta_key . '"', $wpdb->postmeta );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
						}

						$wpdb->query( $sql_query_meta ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					}

					if ( $content !== '' ) {
						WFOCU_Common::maybe_elementor_template( $winner_variant_id, $control_id );
					}
					do_action( 'wfocu_offer_duplicated', $control_id, $winner_variant_id );
					update_post_meta( $winner_variant_id, '_bwf_ab_variation_of', $control_id );

				}
			}
		}

		/**
		 * @param BWFABT_Variant $variant
		 * @param $experiment
		 *
		 * @return string|URL
		 */
		public function get_variant_heading_url( $variant, $experiment ) {
			return BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
				'page'    => 'upstroke',
				'section' => 'offers',
				'edit'    => $variant->get_id(),
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
					'link' => BWF_Admin_Breadcrumbs::maybe_add_refs( add_query_arg( array(
						'page'    => 'upstroke',
						'section' => 'offers',
						'edit'    => $variant->get_id(),
					), admin_url( 'admin.php' ) ) ),
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
				$funnel_post = get_post( $new_control_id );
				if ( ! is_null( $funnel_post ) ) {
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

			return $title;
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
			$date_col       = "events.timestamp";
			$interval_query = '';
			$group_by       = ' GROUP BY events.object_id ';

			if ( class_exists( 'WFOCU_Core' ) && version_compare( WFOCU_VERSION, '2.2.0', '<' ) ) {
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

			if ( 'interval' === $is_interval ) {
				$get_interval   = $this->get_interval_format_query( $int_request, $date_col );
				$interval_query = $get_interval['interval_query'];
				$interval_group = $get_interval['interval_group'];
				$group_by       = "GROUP BY " . $interval_group;
			}

			if ( is_array( $data ) && count( $data ) > 0 ) {
				foreach ( array_keys( $data ) as $id ) {

					$get_all_dates = BWFABT_Core()->get_dataStore()->get_experiment_time_chunk( $experiment_id );
					$date_query    = "";

					if ( is_array( $get_all_dates ) && count( $get_all_dates ) ) {
						foreach ( $get_all_dates as $date ) {
							$date_query .= " ( events.timestamp >= '" . esc_sql( $date['start_date'] ) . "' AND events.timestamp <= '" . esc_sql( $date['end_date'] ) . "' ) OR ";
						}
						$date_query = ' AND ( ' . rtrim( $date_query, " OR " ) . ') ';
					}

					$get_the_offer_query = "SELECT COUNT(CASE WHEN action_type_id = 4 THEN 1 END) AS `converted`, COUNT(CASE WHEN action_type_id = 2 THEN 1 END) AS `viewed`, object_id  as 'offer', action_type_id,SUM(value) as revenue " . $interval_query . " FROM " . $wpdb->prefix . 'wfocu_event' . "  as events WHERE object_id IN (" . esc_sql( implode( ',', $ids ) ) . ") AND (events.action_type_id = '2' OR events.action_type_id = '4' ) " . $date_query . " " . $group_by . " ORDER BY events.object_id ASC";

					$query_res = $wpdb->get_results( $get_the_offer_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					if ( method_exists( 'BWFABT_Core', 'maybe_wpdb_error' ) ) {
						$db_error = BWFABT_Core()->admin->maybe_wpdb_error( $wpdb );
						if ( true === $db_error['db_error'] ) {
							return $db_error;
						}
					}


					if ( 'interval' === $is_interval ) {
						if ( is_array( $query_res ) && count( $query_res ) > 0 ) {
							return $query_res;
						}
					}


					if ( is_array( $query_res ) && count( $query_res ) > 0 ) {
						foreach ( $query_res as $offer_data ) {
							if ( absint( $offer_data['offer'] ) > 0 && isset( $data[ $offer_data['offer'] ] ) ) {
								$data[ $offer_data['offer'] ]['revenue']           = isset( $offer_data['revenue'] ) ? $offer_data['revenue'] : 0;
								$data[ $offer_data['offer'] ]['revenue_per_visit'] = ( absint( $offer_data['viewed'] ) !== 0 ) ? round( $offer_data['revenue'] / $offer_data['viewed'], 2 ) : 0;
								$data[ $offer_data['offer'] ]['conversions']       = is_null( $offer_data['converted'] ) ? 0 : intval( $offer_data['converted'] );
								$data[ $offer_data['offer'] ]['views']             = is_null( $offer_data['viewed'] ) ? 0 : intval( $offer_data['viewed'] );
								$data[ $offer_data['offer'] ]['conversion_rate']   = $this->get_percentage( $offer_data['viewed'], $offer_data['converted'] );
							}
						}
					}

				}
			}

			if ( is_array( $data ) && count( $data ) > 0 ) {
				foreach ( $data as &$item ) {
					if ( isset ( $item['offers'] ) ) {
						$item['offers'] = array_values( $item['offers'] );
					}
				}
			}

			return $data;
		}

		/**
		 * Check maybe current open offer is variant then reset controller for create next offer url
		 */
		public function maybe_current_offer_is_variant( $offer_id ) {

			if ( 0 === $offer_id ) {
				return $offer_id;
			}

			$control_id = get_post_meta( $offer_id, '_bwf_ab_variation_of', true );
			if ( absint( $control_id ) ) {
				$offer_id = $control_id;
			}

			return $offer_id;

		}


		/**
		 *
		 * Redirecting to ab test decided offer page.
		 */
		public function maybe_redirect_to_ab_funnel( $offer_id ) {

			if ( 0 === $offer_id ) {
				return $offer_id;
			}
			if ( true !== $this->validate_run( $offer_id ) ) {
				return $offer_id;
			}

			if ( ! $this->maybe_control_has_active_experiment( $offer_id ) ) {
				return $offer_id;
			}

			$active_test_id = $this->get_running_test_id( $offer_id );

			if ( empty( $active_test_id ) || 0 === $active_test_id ) {
				return $offer_id;
			}

			$experiment   = BWFABT_Core()->admin->get_experiment( $active_test_id );
			$new_offer_id = $this->get_variation_to_run( $experiment );

			if ( 0 === intval( $new_offer_id ) || intval( $new_offer_id ) === intval( $offer_id ) ) {
				return $offer_id;
			}

			return $new_offer_id;

		}

		/**
		 * @param $steps
		 * @param $offer_id
		 * Check maybe offer is variant and show product in funnel admin product tab
		 *
		 * @return mixed
		 */
		public function maybe_variant_exists( $steps, $offer_id ) {

			$control_id = get_post_meta( $offer_id, '_bwf_ab_variation_of', true );

			if ( empty( $control_id ) ) {
				return $steps;
			}
			$offer_post = get_post( $offer_id );
			if ( ! $offer_post instanceof WP_Post || WFOCU_Common::get_offer_post_type_slug() !== $offer_post->post_type ) {
				return $steps;
			}
			$steps[] = array(
				'id'    => $offer_id,
				'name'  => $offer_post->post_name,
				'url'   => get_permalink( $offer_id ),
				'state' => 'publish' === $offer_post->post_status ? '1' : '0',
				'slug'  => $offer_post->post_name,
				'type'  => 'upsell',
			);

			return $steps;

		}
	}

	BWFABT_Controller_Offer::get_instance();
}