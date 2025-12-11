<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Controller_Upstroke' ) ) {
	/**
	 * Class contains all the upstroke related ab testing functionality
	 * Class BWFABT_Controller_Upstroke
	 */
	#[AllowDynamicProperties]
	class BWFABT_Controller_Upstroke extends BWFABT_Controller {
		private static $ins = null;
		private $control_query;

		/**
		 * BWFABT_AB_Controller_Upstroke constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'bwfabt_get_supported_controllers', array( $this, 'bwfabt_add_upstroke_controller' ) );

			add_filter( 'wfocu_front_funnel_filter', array( $this, 'wfocu_alter_decided_funnel' ), 10, 1 );

			$this->control_query = false;
		}

		/**
		 * @return BWFABT_Controller_Upstroke|null
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
		public function bwfabt_add_upstroke_controller( $controllers ) {
			$controllers['upstroke'] = 'BWFABT_Controller_Upstroke';

			return $controllers;
		}

		/**
		 * Return title of upstroke controller
		 */
		public function get_title() {
			return __( 'One Click Upsells', 'woofunnels-ab-tests' );
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
				$funnels_to_create = array(
					'title'           => $variant_data['variant_title'],
					'description'     => $variant_data['variant_desc'],
					'status'          => 'publish',
					'offers_override' => array(
						0 => array( 'meta_override' => array( '_wfocu_setting_override' => array( 'template' => 'sp-classic' ) ) ),
					),
				);

				BWFABT_Core()->admin->log( "Going to create an upstroke funnel in add_variant method in upstroke controller for contorl id: $control_id and variant id: $variant_id, Funnel to create: " . print_r( $funnels_to_create, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				$variant_id = WFOCU_Core()->funnels->generate_preset_funnel_data( $funnels_to_create );

				$variant_data['variant_id'] = $variant_id;

				if ( true !== $variant_data['control'] && $control_id > 0 && $variant_id !== $control_id ) {
					update_post_meta( $variant_id, '_bwf_ab_variation_of', $control_id );
					delete_post_meta( $variant_id, '_bwf_ab_control' );
				}

				BWFABT_Core()->admin->log( "Updated variant Data after creating variant funnel for control id: $control_id, variant id: $variant_id: " . print_r( $variant_data, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			}

			return parent::add_variant( $variant_data );
		}

		/**
		 * @param $variant_data
		 *
		 * @return mixed
		 */
		public function duplicate_variant( $variant_data ) {
			$variant_id    = $variant_data['variant_id'];
			$experiment_id = $variant_data['experiment_id'];
			global $wpdb;
			$new_funnel_id = 0;
			if ( $variant_id > 0 && $experiment_id > 0 ) {
				$funnel_id = $variant_id;
				if ( method_exists( 'WFOCU_AJAX_Controller', 'duplicating_funnel' ) ) {
					$resp          = WFOCU_AJAX_Controller::duplicating_funnel( $funnel_id, array(
						'msg'    => '',
						'status' => true,
					) );
					$new_funnel_id = $resp['duplicate_id'];
				} else {
					$data_funnels = WFOCU_Core()->funnels->get_funnel_offers_admin( $funnel_id );
					$offers       = array();
					foreach ( is_array( $data_funnels['steps'] ) ? $data_funnels['steps'] : array() as $offer_step ) {
						$post_meta_all = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d", $offer_step['id'] ) );
						$offers[]      = array(
							'id'               => '{{offer_id}}',
							'name'             => $offer_step['name'] . __( ' Copy', 'woofunnels-ab-tests' ),
							'type'             => $offer_step['type'],
							'state'            => $offer_step['state'],
							'slug'             => $offer_step['slug'],
							'_customizer_data' => get_option( 'wfocu_c_' . $offer_step['id'], '' ),
							'parent_meta'      => $post_meta_all,
							'post_content'     => get_post_field( 'post_content', $offer_step['id'] ),
						);
					}

					$funnels_to_create = array(
						'title'       => get_the_title( $funnel_id ) . ' Copy',
						'description' => get_post_field( 'post_content', $funnel_id ),
						'status'      => 'publish',
						'priority'    => WFOCU_Common::get_next_funnel_priority(),
						'offers'      => $offers,
						'meta'        => array(
							'_wfocu_is_rules_saved'   => get_post_meta( $funnel_id, '_wfocu_is_rules_saved', true ),
							'_wfocu_rules'            => get_post_meta( $funnel_id, '_wfocu_rules', true ),
							'_funnel_steps'           => array(),
							'_funnel_upsell_downsell' => array(),
						),
					);

					$new_funnel_id = WFOCU_Core()->funnels->generate_preset_funnel_data( $funnels_to_create );
				}
			}
			if ( $new_funnel_id > 0 ) {
				$variant_data['variant_id'] = $new_funnel_id;
				$funnel_settings            = get_post_meta( $funnel_id, '_wfocu_settings', true );
				$funnel_settings            = is_array( $funnel_settings ) ? $funnel_settings : array();

				$funnel_priority_new                = WFOCU_Common::get_next_funnel_priority();
				$funnel_settings['funnel_priority'] = $funnel_priority_new;

				update_post_meta( $new_funnel_id, '_wfocu_settings', $funnel_settings );
			}

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
		 * @param $args
		 *
		 * @return mixed
		 */
		public function search_all_posts_that_are_not_variants( $args ) {

			global $wpdb;
			$pages         = [];
			$like_term     = '%' . $wpdb->esc_like( $term ) . '%';
			$post_type     = WFOCU_Common::get_funnel_post_type_slug();
			$post_statuses = array( 'publish', 'draft' );
			$query         = $wpdb->prepare( "SELECT DISTINCT posts.ID FROM {$wpdb->posts} posts WHERE ( posts.post_title LIKE %s or posts.ID = %s )	AND posts.post_type = %s AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "') ORDER BY posts.post_parent ASC, posts.post_title ASC", $like_term, $like_term, $post_type ); //phpcs:ignore

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
		 * @param $decided_funnel
		 *
		 * @return mixed
		 */
		public function wfocu_alter_decided_funnel( $decided_funnel ) {
			BWFABT_Core()->admin->log( "UpStroke decided funnel: $decided_funnel" );
			if ( $decided_funnel > 0 ) {
				if ( ! $this->maybe_control_has_active_experiment( $decided_funnel ) ) {
					return $decided_funnel;
				}

				$active_test_id = $this->get_running_test_id( $decided_funnel );
				if ( ! empty( $active_test_id ) && $active_test_id > 0 ) {
					$experiment = BWFABT_Core()->admin->get_experiment( $active_test_id );
					$ab_upsell  = $this->get_variation_to_run( $experiment );
					if ( 0 !== $ab_upsell ) {
						$decided_funnel = $ab_upsell;
					}
				}
			}

			return $decided_funnel;
		}

		/**
		 * Save existing funnel offers ids and titles data to new control funnel mata when a variant wins (to display ideal result later on)
		 *
		 * @param $control_data
		 * @param $new_variant_id
		 */
		public function copy_control_data_to_new_control( $control_data, $new_variant_id ) {
			$control_id = isset( $control_data['control_id'] ) ? $control_data['control_id'] : 0;
			if ( $control_id > 0 ) {
				$control_steps          = get_post_meta( $control_id, '_funnel_steps', true );
				$control_data['offers'] = array_combine( wp_list_pluck( $control_steps, 'name' ), wp_list_pluck( $control_steps, 'id' ) );
			}
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
			$winner_funnel_data = WFOCU_Core()->funnels->get_funnel_offers_admin( $winner_variant_id );

			delete_post_meta( $control_id, '_wfocu_rules' );
			delete_post_meta( $control_id, '_wfocu_is_rules_saved' );
			delete_post_meta( $control_id, '_wfocu_settings' );
			delete_post_meta( $control_id, '_funnel_upsell_downsell' );

			/**
			 * Deleting existing control funnel offers before copying form winner variant and keep offers and title to copy in N1 meta
			 */
			$control_funnel_steps = get_post_meta( $control_id, '_funnel_steps', true );
			$control_funnel_steps = is_array( $control_funnel_steps ) ? $control_funnel_steps : array();
			foreach ( $control_funnel_steps as $control_offers ) {
				$offer_id_old = $control_offers['id'];
				if ( ! is_null( get_post( $offer_id_old ) ) ) {
					wp_delete_post( $offer_id_old );
				}
			}
			delete_post_meta( $control_id, '_funnel_steps' );

			$offers = array();
			foreach ( $winner_funnel_data['steps'] as $offer_step ) {
				$post_meta_all = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d", $offer_step['id'] ) );
				$offers[]      = array(
					'id'               => '{{offer_id}}',
					'name'             => $offer_step['name'],
					'type'             => $offer_step['type'],
					'state'            => $offer_step['state'],
					'slug'             => $offer_step['slug'],
					'meta'             => array(),
					'post_content'     => get_post( $offer_step['id'] )->post_content,
					'_customizer_data' => get_option( 'wfocu_c_' . $offer_step['id'], '' ),
					'parent_meta'      => $post_meta_all,
				);
			}

			$funnel_to_copy = array(
				'offers' => $offers,
				'meta'   => array(
					'_wfocu_is_rules_saved'   => get_post_meta( $winner_variant_id, '_wfocu_is_rules_saved', true ),
					'_wfocu_rules'            => get_post_meta( $winner_variant_id, '_wfocu_rules', true ),
					'_wfocu_settings'         => get_post_meta( $winner_variant_id, '_wfocu_settings', true ),
					'_funnel_steps'           => array(),
					'_funnel_upsell_downsell' => array(),
				),
			);

			$get_default_schema = WFOCU_Core()->funnels->get_default_funnel_schema();

			$funnel_data = wp_parse_args( $funnel_to_copy, $get_default_schema );

			if ( count( $offers ) > 0 ) {
				$funnel_data['meta']['_funnel_steps'] = [];
				foreach ( $funnel_data['offers'] as $key => $offer_raw ) {
					if ( isset( $funnel_to_copy['offers_override'][ $key ] ) ) {
						$offer_raw = wp_parse_args( $funnel_to_copy['offers_override'][ $key ], $offer_raw );
					}

					$offer_post_type    = WFOCU_Common::get_offer_post_type_slug();
					$offer_post_content = ( isset( $offer_raw['post_content'] ) ? $offer_raw['post_content'] : '' );
					$offer_post_new     = array(
						'post_title'   => $offer_raw['name'],
						'post_type'    => $offer_post_type,
						'post_name'    => sanitize_title( $offer_raw['name'] ) . '-' . time(),
						'post_status'  => 'publish',
						'post_content' => $offer_post_content
					);

					$offer_id_new = wp_insert_post( $offer_post_new );

					if ( ! is_wp_error( $offer_id_new ) && $offer_id_new ) {

						if ( isset( $offer_raw['meta_override'] ) ) {
							$offer_raw['meta'] = wp_parse_args( $offer_raw['meta_override'], $offer_raw['meta'] );
						}

						if ( isset( $offer_raw['meta']['_wfocu_setting_override'] ) ) {
							$offer_raw['meta']['_wfocu_setting'] = (object) $this->wp_parse_args( $offer_raw['meta']['_wfocu_setting_override'], $offer_raw['meta']['_wfocu_setting'] );
						}

						if ( ! empty( $offer_raw['_customizer_data'] ) ) {
							WFOCU_Core()->import->import_customizer_data( $offer_id_new, $offer_raw['_customizer_data'] );
						}

						if ( isset( $offer_raw['parent_meta'] ) && ! empty( $offer_raw['parent_meta'] ) ) {
							$parent_meta_all   = $offer_raw['parent_meta'];
							$sql_query_selects = [];

							foreach ( $parent_meta_all as $meta_info ) {

								$meta_key   = esc_sql( $meta_info->meta_key );
								$meta_value = esc_sql( $meta_info->meta_value );

								$sql_query_selects[] = "( $offer_id_new, '$meta_key', '$meta_value')"; //db call ok; no-cache ok; WPCS: unprepared SQL ok.
							}

							$sql_query_meta_val = implode( ',', $sql_query_selects );
							$wpdb->query( $wpdb->prepare( 'INSERT INTO %1$s (post_id, meta_key, meta_value) VALUES ' . $sql_query_meta_val, $wpdb->postmeta ) );//phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder,WordPress.DB.PreparedSQL.NotPrepared

						} else {
							foreach ( $offer_raw['meta'] as $key_meta => $meta_val ) {
								update_post_meta( $offer_id_new, $key_meta, $meta_val );
							}
						}

						update_post_meta( $offer_id_new, '_funnel_id', $control_id );
						$funnel_data['meta']['_funnel_steps'][] = array(
							'id'    => (string) $offer_id_new,
							'name'  => $offer_raw['name'],
							'type'  => $offer_raw['type'],
							'state' => $offer_raw['state'],
							'slug'  => sanitize_title( $offer_raw['name'] ) . '-' . time(),
						);
					}
				}
			}

			foreach ( $funnel_data['meta'] as $key => $meta_val ) {
				switch ( $key ) {
					case '_funnel_steps':
						update_post_meta( $control_id, $key, $funnel_data['meta']['_funnel_steps'] );
						break;
					case '_funnel_upsell_downsell':
						update_post_meta( $control_id, $key, WFOCU_Core()->funnels->prepare_upsell_downsells( $funnel_data['meta']['_funnel_steps'] ) );
						break;
					default:
						update_post_meta( $control_id, $key, $meta_val );
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
		 * @param $step_id
		 *
		 * @return mixed
		 */
		public function get_entity_view_link( $step_id ) {
			$link = parent::get_entity_view_link( $step_id );
			if ( $step_id > 0 && get_post( $step_id ) instanceof WP_Post ) {
				$steps = WFOCU_Core()->funnels->get_funnel_steps( $step_id );
				$link  = ( is_array( $steps ) && count( $steps ) > 0 ) ? get_permalink( $steps[0]['id'] ) : "";
			}

			return $link;
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

					$data[ $id ]['offers'] = [];

					$steps = get_post_meta( $id, '_funnel_steps', true );

					if ( is_array( $steps ) && count( $steps ) > 0 ) {
						foreach ( $steps as $step ) {
							$data[ $id ]['offers'][ $step['id'] ] = array(
								'object_id'         => $step_id,
								'object_name'       => html_entity_decode( get_the_title( $step['id'] ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
								'revenue'           => 0,
								'revenue_per_visit' => 0,
								'conversions'       => 0,
								'views'             => 0,
								'conversion_rate'   => 0,
							);
						}
					}

					$get_all_dates = BWFABT_Core()->get_dataStore()->get_experiment_time_chunk( $experiment_id );
					$date_query    = "";

					if ( is_array( $get_all_dates ) && count( $get_all_dates ) ) {
						foreach ( $get_all_dates as $date ) {
							$date_query .= " ( events.timestamp >= '" . esc_sql( $date['start_date'] ) . "' AND events.timestamp <= '" . esc_sql( $date['end_date'] ) . "' ) OR ";
						}
						$date_query = ' AND ( ' . rtrim( $date_query, " OR " ) . ') ';
					}

					$get_the_upsell_query = "SELECT COUNT(CASE WHEN action_type_id = 4 THEN 1 END) AS `converted`, COUNT(CASE WHEN action_type_id = 2 THEN 1 END) AS `viewed`, object_id  as 'offer', action_type_id,SUM(value) as revenue " . $interval_query . " FROM " . $wpdb->prefix . 'wfocu_event' . "  as events INNER JOIN " . $wpdb->prefix . 'wfocu_event_meta' . " AS events_meta__funnel_id ON ( events.ID = events_meta__funnel_id.event_id ) AND ( ( events_meta__funnel_id.meta_key   = '_funnel_id' AND events_meta__funnel_id.meta_value = $id )) AND (events.action_type_id = '2' OR events.action_type_id = '4' ) " . $date_query . " " . $group_by . " ORDER BY events.object_id ASC";


					$query_res = $wpdb->get_results( $get_the_upsell_query, ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

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
							if ( absint( $offer_data['offer'] ) > 0 ) {
								$data[ $id ]['offers'][ $offer_data['offer'] ] = array(
									'object_name'       => html_entity_decode( get_the_title( $offer_data['offer'] ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
									'revenue'           => isset( $offer_data['revenue'] ) ? $offer_data['revenue'] : 0,
									'revenue_per_visit' => ( absint( $offer_data['viewed'] ) !== 0 ) ? round( $offer_data['revenue'] / $offer_data['viewed'], 2 ) : 0,
									'conversions'       => is_null( $offer_data['converted'] ) ? 0 : intval( $offer_data['converted'] ),
									'views'             => is_null( $offer_data['viewed'] ) ? 0 : intval( $offer_data['viewed'] ),
									'conversion_rate'   => $this->get_percentage( $offer_data['viewed'], $offer_data['converted'] ),
								);
								$data[ $id ]['revenue']                        += $data[ $id ]['offers'][ $offer_data['offer'] ]['revenue'];
								$data[ $id ]['conversions']                    += $data[ $id ]['offers'][ $offer_data['offer'] ]['conversions'];
								$data[ $id ]['views']                          += $data[ $id ]['offers'][ $offer_data['offer'] ]['views'];
								$data[ $id ]['revenue_per_visit']              = ( absint( $data[ $id ]['views'] ) !== 0 ) ? round( $data[ $id ]['revenue'] / $data[ $id ]['views'], 2 ) : 0;
								$data[ $id ]['conversion_rate']                = $this->get_percentage( $data[ $id ]['views'], $data[ $id ]['conversions'] );

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
	}

	BWFABT_Controller_Upstroke::get_instance();
}